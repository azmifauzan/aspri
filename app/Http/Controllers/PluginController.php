<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plugin\PluginConfigUpdateRequest;
use App\Http\Requests\Plugin\PluginScheduleRequest;
use App\Models\Plugin;
use App\Services\Plugin\PluginConfigurationService;
use App\Services\Plugin\PluginManager;
use App\Services\Plugin\PluginSchedulerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PluginController extends Controller
{
    public function __construct(
        protected PluginManager $pluginManager,
        protected PluginConfigurationService $configService,
        protected PluginSchedulerService $schedulerService
    ) {}

    /**
     * Display all available plugins.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get base plugins query
        $query = Plugin::query()
            ->withCount('ratings')
            ->withAvg('ratings', 'rating');

        // Sort by rating (default) or other criteria
        $sortBy = $request->input('sort_by', 'rating');
        if ($sortBy === 'rating') {
            $query->orderByDesc('ratings_avg_rating')
                ->orderByDesc('ratings_count');
        } elseif ($sortBy === 'name') {
            $query->orderBy('name');
        } elseif ($sortBy === 'newest') {
            $query->orderByDesc('created_at');
        }

        $plugins = $query->get()
            ->when($request->input('min_rating'), function ($collection, $minRating) {
                return $collection->filter(fn ($plugin) => ($plugin->ratings_avg_rating ?? 0) >= $minRating);
            })
            ->map(function ($plugin) use ($user) {
                $userPlugin = $plugin->userPlugins()->where('user_id', $user->id)->first();

                return [
                    'id' => $plugin->id,
                    'slug' => $plugin->slug,
                    'name' => $plugin->name,
                    'description' => $plugin->description,
                    'version' => $plugin->version,
                    'author' => $plugin->author,
                    'icon' => $plugin->icon,
                    'is_system' => $plugin->is_system,
                    'installed_at' => $plugin->installed_at,
                    'user_is_active' => $userPlugin?->is_active ?? false,
                    'average_rating' => round($plugin->ratings_avg_rating ?? 0, 1),
                    'total_ratings' => $plugin->ratings_count ?? 0,
                    'user_rating' => $plugin->ratings()->where('user_id', $user->id)->first()?->rating,
                ];
            })
            ->values();

        return Inertia::render('plugins/Index', [
            'plugins' => $plugins,
            'filters' => [
                'min_rating' => $request->input('min_rating'),
                'sort_by' => $sortBy,
            ],
        ]);
    }

    /**
     * Display plugin details and configuration.
     */
    public function show(Request $request, Plugin $plugin): Response
    {
        $user = $request->user();

        // Get user's plugin status
        $userPlugin = $plugin->userPlugins()->where('user_id', $user->id)->first();

        // Get configuration
        $config = $this->configService->getConfig($user->id, $plugin->slug);
        $formFields = $this->configService->buildFormFields($plugin->slug);

        // Get schedule if supports scheduling
        $instance = $this->pluginManager->getPlugin($plugin->slug);
        $supportsScheduling = $instance?->supportsScheduling() ?? false;
        $schedule = $supportsScheduling
            ? $this->schedulerService->getActiveSchedule($user->id, $plugin->slug)
            : null;

        // Get execution history
        $executionHistory = $this->schedulerService->getExecutionHistory($user->id, $plugin->slug, 10);

        // Get plugin ratings
        $ratings = $plugin->ratings()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(10);

        // Get user's rating if exists
        $userRating = $plugin->ratings()->where('user_id', $user->id)->first();

        return Inertia::render('plugins/Show', [
            'plugin' => array_merge($plugin->toArray(), [
                'average_rating' => round($plugin->ratings()->avg('rating') ?? 0, 1),
                'total_ratings' => $plugin->ratings()->count(),
            ]),
            'userPlugin' => $userPlugin,
            'config' => $config,
            'formFields' => $formFields,
            'supportsScheduling' => $supportsScheduling,
            'schedule' => $schedule,
            'executionHistory' => $executionHistory,
            'ratings' => $ratings,
            'userRating' => $userRating,
        ]);
    }

    /**
     * Activate a plugin for the current user.
     */
    public function activate(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->pluginManager->activateForUser($plugin->slug, $user->id);

            return back()->with('success', "Plugin {$plugin->name} berhasil diaktifkan.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengaktifkan plugin: '.$e->getMessage());
        }
    }

    /**
     * Deactivate a plugin for the current user.
     */
    public function deactivate(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->pluginManager->deactivateForUser($plugin->slug, $user->id);

            return back()->with('success', "Plugin {$plugin->name} berhasil dinonaktifkan.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menonaktifkan plugin: '.$e->getMessage());
        }
    }

    /**
     * Update plugin configuration.
     */
    public function updateConfig(PluginConfigUpdateRequest $request, Plugin $plugin): RedirectResponse
    {
        $user = $request->user();

        // Check if plugin is activated for this user
        $userPlugin = $plugin->userPlugins()->where('user_id', $user->id)->first();
        if (! $userPlugin || ! $userPlugin->is_active) {
            abort(403, 'Plugin must be activated before configuration.');
        }

        try {
            $this->configService->saveConfig($user->id, $plugin->slug, $request->validated('config'));

            return back()->with('success', 'Konfigurasi berhasil disimpan.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyimpan konfigurasi: '.$e->getMessage());
        }
    }

    /**
     * Reset plugin configuration to defaults.
     */
    public function resetConfig(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->configService->resetConfig($user->id, $plugin->slug);

            return back()->with('success', 'Konfigurasi berhasil direset ke default.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mereset konfigurasi: '.$e->getMessage());
        }
    }

    /**
     * Update or create plugin schedule.
     */
    public function updateSchedule(PluginScheduleRequest $request, Plugin $plugin): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $this->schedulerService->createSchedule(
                $user->id,
                $plugin->slug,
                $validated['schedule_type'],
                $validated['schedule_value'],
                $validated['metadata'] ?? null
            );

            return back()->with('success', 'Jadwal berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui jadwal: '.$e->getMessage());
        }
    }

    /**
     * Delete plugin schedule.
     */
    public function deleteSchedule(Request $request, Plugin $plugin, int $scheduleId): RedirectResponse
    {
        try {
            $this->schedulerService->deleteSchedule($scheduleId);

            return back()->with('success', 'Jadwal berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus jadwal: '.$e->getMessage());
        }
    }

    /**
     * Test execute a plugin.
     */
    public function testExecute(Request $request, Plugin $plugin): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->pluginManager->executePlugin($plugin->slug, $user->id, ['test' => true]);

            return back()->with('success', 'Plugin berhasil dieksekusi.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengeksekusi plugin: '.$e->getMessage());
        }
    }
}
