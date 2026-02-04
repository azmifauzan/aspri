<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use App\Models\PluginLog;
use App\Models\PluginSchedule;
use App\Models\UserPlugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PluginSchedulerService
{
    public function __construct(
        protected PluginManager $pluginManager
    ) {}

    /**
     * Create or update a schedule for a user plugin.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function createSchedule(
        int $userId,
        string $pluginSlug,
        string $scheduleType,
        string $scheduleValue,
        ?array $metadata = null
    ): PluginSchedule {
        $userPlugin = $this->getUserPlugin($userId, $pluginSlug);

        if (! $userPlugin) {
            throw new \InvalidArgumentException("User does not have plugin {$pluginSlug} activated.");
        }

        // Deactivate existing schedules
        $userPlugin->schedules()->update(['is_active' => false]);

        // Create new schedule
        $schedule = $userPlugin->schedules()->create([
            'schedule_type' => $scheduleType,
            'schedule_value' => $scheduleValue,
            'is_active' => true,
            'metadata' => $metadata,
        ]);

        // Calculate next run time
        $schedule->calculateNextRun();

        return $schedule;
    }

    /**
     * Update an existing schedule.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function updateSchedule(
        int $scheduleId,
        string $scheduleType,
        string $scheduleValue,
        ?array $metadata = null
    ): PluginSchedule {
        $schedule = PluginSchedule::findOrFail($scheduleId);

        $schedule->update([
            'schedule_type' => $scheduleType,
            'schedule_value' => $scheduleValue,
            'metadata' => $metadata,
        ]);

        // Recalculate next run time
        $schedule->calculateNextRun();

        return $schedule;
    }

    /**
     * Activate a schedule.
     */
    public function activateSchedule(int $scheduleId): PluginSchedule
    {
        $schedule = PluginSchedule::findOrFail($scheduleId);

        $schedule->update(['is_active' => true]);
        $schedule->calculateNextRun();

        return $schedule;
    }

    /**
     * Deactivate a schedule.
     */
    public function deactivateSchedule(int $scheduleId): PluginSchedule
    {
        $schedule = PluginSchedule::findOrFail($scheduleId);

        $schedule->update(['is_active' => false]);

        return $schedule;
    }

    /**
     * Delete a schedule.
     */
    public function deleteSchedule(int $scheduleId): bool
    {
        $schedule = PluginSchedule::find($scheduleId);

        return $schedule?->delete() ?? false;
    }

    /**
     * Get all due schedules.
     *
     * @return Collection<int, PluginSchedule>
     */
    public function getDueSchedules(): Collection
    {
        return PluginSchedule::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->with(['userPlugin.plugin', 'userPlugin.user'])
            ->get();
    }

    /**
     * Process all due schedules.
     */
    public function processDueSchedules(): int
    {
        $dueSchedules = $this->getDueSchedules();
        $processed = 0;

        foreach ($dueSchedules as $schedule) {
            try {
                $this->executeSchedule($schedule);
                $processed++;
            } catch (\Exception $e) {
                $this->logScheduleError($schedule, $e);
            }
        }

        return $processed;
    }

    /**
     * Execute a single schedule.
     */
    public function executeSchedule(PluginSchedule $schedule): void
    {
        $userPlugin = $schedule->userPlugin;

        if (! $userPlugin || ! $userPlugin->is_active) {
            // Plugin is not active, skip
            $schedule->update(['is_active' => false]);

            return;
        }

        $plugin = $userPlugin->plugin;
        $instance = $this->pluginManager->getPlugin($plugin->slug);

        if (! $instance) {
            PluginLog::error(
                $plugin->id,
                'Plugin instance could not be created',
                $userPlugin->user_id
            );

            return;
        }

        // Get user's configuration
        $config = $userPlugin->getAllConfig();
        $config = array_merge($plugin->default_config ?? [], $config);

        // Build context with schedule metadata
        $context = [
            'schedule_id' => $schedule->id,
            'schedule_type' => $schedule->schedule_type,
            'scheduled_at' => $schedule->next_run_at?->toIso8601String(),
            'metadata' => $schedule->metadata,
        ];

        // Execute the plugin
        DB::beginTransaction();
        try {
            $instance->execute($userPlugin->user_id, $config, $context);

            // Mark as run and calculate next run
            $schedule->markAsRun();

            PluginLog::info(
                $plugin->id,
                'Scheduled execution completed',
                $userPlugin->user_id,
                ['schedule_id' => $schedule->id]
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get schedules for a user plugin.
     *
     * @return Collection<int, PluginSchedule>
     */
    public function getSchedulesForUserPlugin(int $userId, string $pluginSlug): Collection
    {
        $userPlugin = $this->getUserPlugin($userId, $pluginSlug);

        if (! $userPlugin) {
            return collect();
        }

        return $userPlugin->schedules;
    }

    /**
     * Get active schedule for a user plugin.
     */
    public function getActiveSchedule(int $userId, string $pluginSlug): ?PluginSchedule
    {
        $userPlugin = $this->getUserPlugin($userId, $pluginSlug);

        return $userPlugin?->activeSchedule;
    }

    /**
     * Create default schedule from plugin definition.
     */
    public function createDefaultSchedule(int $userId, string $pluginSlug): ?PluginSchedule
    {
        $instance = $this->pluginManager->getPlugin($pluginSlug);

        if (! $instance || ! $instance->supportsScheduling()) {
            return null;
        }

        $defaultSchedule = $instance->getDefaultSchedule();

        if (! $defaultSchedule) {
            return null;
        }

        return $this->createSchedule(
            $userId,
            $pluginSlug,
            $defaultSchedule['type'],
            $defaultSchedule['value']
        );
    }

    /**
     * Get user plugin model.
     */
    protected function getUserPlugin(int $userId, string $pluginSlug): ?UserPlugin
    {
        return UserPlugin::whereHas('plugin', function ($query) use ($pluginSlug) {
            $query->where('slug', $pluginSlug);
        })
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Log schedule execution error.
     */
    protected function logScheduleError(PluginSchedule $schedule, \Exception $e): void
    {
        $userPlugin = $schedule->userPlugin;
        $plugin = $userPlugin?->plugin;

        if ($plugin) {
            PluginLog::error(
                $plugin->id,
                'Scheduled execution failed: '.$e->getMessage(),
                $userPlugin->user_id,
                [
                    'schedule_id' => $schedule->id,
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }

        // Still mark as run to prevent infinite retries
        $schedule->markAsRun();
    }

    /**
     * Get execution history for a user plugin.
     *
     * @return Collection<int, PluginLog>
     */
    public function getExecutionHistory(int $userId, string $pluginSlug, int $limit = 20): Collection
    {
        $plugin = Plugin::where('slug', $pluginSlug)->first();

        if (! $plugin) {
            return collect();
        }

        return PluginLog::where('plugin_id', $plugin->id)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old log entries.
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        return PluginLog::where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }
}
