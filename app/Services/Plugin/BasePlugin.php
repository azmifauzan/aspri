<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use App\Models\PluginLog;
use App\Models\User;
use App\Models\UserPlugin;
use App\Services\Plugin\Contracts\PluginInterface;
use App\Services\Telegram\TelegramBotService;

abstract class BasePlugin implements PluginInterface
{
    protected ?Plugin $model = null;

    /**
     * Get the plugin model from database.
     */
    public function getModel(): ?Plugin
    {
        if (! $this->model) {
            $this->model = Plugin::where('slug', $this->getSlug())->first();
        }

        return $this->model;
    }

    /**
     * Get the author of the plugin.
     */
    public function getAuthor(): string
    {
        return 'ASPRI Team';
    }

    /**
     * Get the icon identifier for the plugin.
     */
    public function getIcon(): string
    {
        return 'puzzle-piece';
    }

    /**
     * Called when the plugin is installed system-wide.
     */
    public function install(): void
    {
        // Override in subclass if needed
    }

    /**
     * Called when the plugin is uninstalled from the system.
     */
    public function uninstall(): void
    {
        // Override in subclass if needed
    }

    /**
     * Called when a user activates the plugin.
     */
    public function activate(int $userId): void
    {
        // Override in subclass if needed
    }

    /**
     * Called when a user deactivates the plugin.
     */
    public function deactivate(int $userId): void
    {
        // Override in subclass if needed
    }

    /**
     * Get the default configuration values.
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        $schema = $this->getConfigSchema();
        $defaults = [];

        foreach ($schema as $key => $field) {
            if (isset($field['default'])) {
                $defaults[$key] = $field['default'];
            }
        }

        return $defaults;
    }

    /**
     * Validate configuration values.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string> Array of errors (empty if valid)
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        $schema = $this->getConfigSchema();

        foreach ($schema as $key => $field) {
            // Check required fields
            if (! empty($field['required']) && ! isset($config[$key])) {
                $errors[$key] = "{$field['label']} is required.";

                continue;
            }

            // Skip validation if not provided and not required
            if (! isset($config[$key])) {
                continue;
            }

            $value = $config[$key];

            // Validate based on type
            $errors = array_merge($errors, $this->validateField($key, $value, $field));
        }

        return $errors;
    }

    /**
     * Validate a single field.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    protected function validateField(string $key, mixed $value, array $field): array
    {
        $errors = [];

        switch ($field['type']) {
            case 'select':
                if (! empty($field['options']) && ! in_array($value, $field['options'])) {
                    $errors[$key] = "{$field['label']} must be one of: ".implode(', ', $field['options']);
                }
                break;

            case 'multiselect':
                if (! is_array($value)) {
                    $errors[$key] = "{$field['label']} must be an array.";
                } elseif (! empty($field['options'])) {
                    $invalid = array_diff($value, $field['options']);
                    if (! empty($invalid)) {
                        $errors[$key] = "{$field['label']} contains invalid values: ".implode(', ', $invalid);
                    }
                }
                break;

            case 'number':
            case 'integer':
                if (! is_numeric($value)) {
                    $errors[$key] = "{$field['label']} must be a number.";
                } else {
                    if (isset($field['min']) && $value < $field['min']) {
                        $errors[$key] = "{$field['label']} must be at least {$field['min']}.";
                    }
                    if (isset($field['max']) && $value > $field['max']) {
                        $errors[$key] = "{$field['label']} must be at most {$field['max']}.";
                    }
                }
                break;

            case 'time':
                if (! preg_match('/^\d{2}:\d{2}(,\d{2}:\d{2})*$/', $value)) {
                    $errors[$key] = "{$field['label']} must be in HH:MM format.";
                }
                break;

            case 'boolean':
                if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $errors[$key] = "{$field['label']} must be a boolean.";
                }
                break;
        }

        return $errors;
    }

    /**
     * Check if the plugin supports scheduled execution.
     */
    public function supportsScheduling(): bool
    {
        return false;
    }

    /**
     * Get the default schedule configuration.
     *
     * @return array{type: string, value: string}|null
     */
    public function getDefaultSchedule(): ?array
    {
        return null;
    }

    /**
     * Check if the plugin supports chat integration.
     */
    public function supportsChatIntegration(): bool
    {
        return false;
    }

    /**
     * Get chat intents supported by this plugin.
     *
     * @return array<int, array{action: string, description: string, entities: array<string, string>, examples: array<string>}>
     */
    public function getChatIntents(): array
    {
        return [];
    }

    /**
     * Handle chat intent execution.
     *
     * @param  array<string, mixed>  $entities
     * @return array{success: bool, message: string, data?: mixed}
     */
    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        return [
            'success' => false,
            'message' => 'Chat integration not implemented for this plugin.',
        ];
    }

    /**
     * Get user plugin model.
     */
    protected function getUserPlugin(int $userId): ?UserPlugin
    {
        $model = $this->getModel();
        if (! $model) {
            return null;
        }

        return UserPlugin::where('user_id', $userId)
            ->where('plugin_id', $model->id)
            ->first();
    }

    /**
     * Get user's configuration.
     *
     * @return array<string, mixed>
     */
    protected function getUserConfig(int $userId): array
    {
        $userPlugin = $this->getUserPlugin($userId);
        if (! $userPlugin) {
            return $this->getDefaultConfig();
        }

        return array_merge($this->getDefaultConfig(), $userPlugin->getAllConfig());
    }

    /**
     * Get the user model.
     */
    protected function getUser(int $userId): ?User
    {
        return User::find($userId);
    }

    /**
     * Send a Telegram message to the user.
     *
     * @param  array<string, mixed>  $options
     */
    protected function sendTelegramMessage(int $userId, string $message, array $options = []): bool
    {
        $user = $this->getUser($userId);

        if (! $user) {
            $this->logWarning('User not found', $userId);

            return false;
        }

        if (! $user->telegram_chat_id) {
            $this->logWarning('User has no Telegram connected', $userId);

            return false;
        }

        if (! array_key_exists('parse_mode', $options)) {
            $options['parse_mode'] = 'Markdown';
        }

        app(TelegramBotService::class)->sendMessage($user->telegram_chat_id, $message, $options);

        return true;
    }

    /**
     * Log an info message.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logInfo(string $message, ?int $userId = null, array $context = []): void
    {
        $model = $this->getModel();
        if ($model) {
            PluginLog::info($model->id, $message, $userId, $context);
        }
    }

    /**
     * Log a warning message.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logWarning(string $message, ?int $userId = null, array $context = []): void
    {
        $model = $this->getModel();
        if ($model) {
            PluginLog::warning($model->id, $message, $userId, $context);
        }
    }

    /**
     * Log an error message.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, ?int $userId = null, array $context = []): void
    {
        $model = $this->getModel();
        if ($model) {
            PluginLog::error($model->id, $message, $userId, $context);
        }
    }

    /**
     * Log a debug message.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logDebug(string $message, ?int $userId = null, array $context = []): void
    {
        $model = $this->getModel();
        if ($model) {
            PluginLog::debug($model->id, $message, $userId, $context);
        }
    }

    /**
     * Unified logging method.
     *
     * @param  array<string, mixed>  $context
     */
    protected function log(int $userId, string $level, string $message, array $context = []): void
    {
        match ($level) {
            'info' => $this->logInfo($message, $userId, $context),
            'warning' => $this->logWarning($message, $userId, $context),
            'error' => $this->logError($message, $userId, $context),
            'debug' => $this->logDebug($message, $userId, $context),
            default => $this->logInfo($message, $userId, $context),
        };
    }

    /**
     * Create a schedule for this plugin.
     *
     * @param  array<string, mixed>  $scheduleData
     */
    protected function createSchedule(int $userId, array $scheduleData): void
    {
        $userPlugin = $this->getUserPlugin($userId);
        if (! $userPlugin) {
            return;
        }

        // Deactivate existing schedules of the same type if specified
        if (isset($scheduleData['metadata']['type'])) {
            $userPlugin->schedules()
                ->whereJsonContains('metadata->type', $scheduleData['metadata']['type'])
                ->update(['is_active' => false]);
        }

        // Create new schedule
        $schedule = $userPlugin->schedules()->create([
            'schedule_type' => $scheduleData['schedule_type'],
            'schedule_value' => $scheduleData['schedule_value'],
            'is_active' => true,
            'metadata' => $scheduleData['metadata'] ?? null,
        ]);

        // Calculate next run time
        $schedule->calculateNextRun();
    }

    /**
     * Delete all schedules for this plugin and user.
     */
    protected function deleteSchedules(int $userId): void
    {
        $userPlugin = $this->getUserPlugin($userId);
        if (! $userPlugin) {
            return;
        }

        $userPlugin->schedules()->delete();
    }
}
