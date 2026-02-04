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

        $plugins = $this->pluginManager->getPluginsForUser($user->id);

        return Inertia::render('plugins/Index', [
            'plugins' => $plugins,
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

        return Inertia::render('plugins/Show', [
            'plugin' => $plugin,
            'userPlugin' => $userPlugin,
            'config' => $config,
            'formFields' => $formFields,
            'supportsScheduling' => $supportsScheduling,
            'schedule' => $schedule,
            'executionHistory' => $executionHistory,
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
