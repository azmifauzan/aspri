<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use App\Models\PluginConfiguration;
use App\Models\UserPlugin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PluginConfigurationService
{
    public function __construct(
        protected PluginManager $pluginManager
    ) {}

    /**
     * Get configuration for a user plugin.
     *
     * @return array<string, mixed>
     */
    public function getConfig(int $userId, string $pluginSlug): array
    {
        $userPlugin = $this->getUserPlugin($userId, $pluginSlug);

        if (! $userPlugin) {
            return $this->getDefaultConfig($pluginSlug);
        }

        $defaultConfig = $userPlugin->plugin->default_config ?? [];
        $userConfig = $userPlugin->getAllConfig();

        return array_merge($defaultConfig, $userConfig);
    }

    /**
     * Get default configuration for a plugin.
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(string $pluginSlug): array
    {
        $plugin = Plugin::where('slug', $pluginSlug)->first();

        return $plugin?->default_config ?? [];
    }

    /**
     * Get configuration schema for a plugin.
     *
     * @return array<string, mixed>
     */
    public function getConfigSchema(string $pluginSlug): array
    {
        $plugin = Plugin::where('slug', $pluginSlug)->first();

        return $plugin?->config_schema ?? [];
    }

    /**
     * Save configuration for a user plugin.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed> The saved configuration
     *
     * @throws ValidationException
     */
    public function saveConfig(int $userId, string $pluginSlug, array $config): array
    {
        $userPlugin = $this->getUserPlugin($userId, $pluginSlug);

        if (! $userPlugin) {
            throw new \InvalidArgumentException("User does not have plugin {$pluginSlug} activated.");
        }

        // Validate configuration
        $errors = $this->validateConfig($pluginSlug, $config);
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // Save each configuration key
        foreach ($config as $key => $value) {
            $userPlugin->setConfig($key, $value);
        }

        return $userPlugin->getAllConfig();
    }

    /**
     * Update a single configuration value.
     *
     * @throws ValidationException
     */
    public function updateConfigValue(int $userId, string $pluginSlug, string $key, mixed $value): PluginConfiguration
    {
        $userPlugin = $this->getUserPlugin($userId, $pluginSlug);

        if (! $userPlugin) {
            throw new \InvalidArgumentException("User does not have plugin {$pluginSlug} activated.");
        }

        // Validate this single field
        $schema = $this->getConfigSchema($pluginSlug);
        if (isset($schema[$key])) {
            $instance = $this->pluginManager->getPlugin($pluginSlug);
            if ($instance) {
                $errors = $instance->validateConfig([$key => $value]);
                if (! empty($errors)) {
                    throw ValidationException::withMessages($errors);
                }
            }
        }

        return $userPlugin->setConfig($key, $value);
    }

    /**
     * Reset configuration to default values.
     *
     * @return array<string, mixed>
     */
    public function resetConfig(int $userId, string $pluginSlug): array
    {
        $userPlugin = $this->getUserPlugin($userId, $pluginSlug);

        if (! $userPlugin) {
            throw new \InvalidArgumentException("User does not have plugin {$pluginSlug} activated.");
        }

        // Delete all user configurations
        $userPlugin->configurations()->delete();

        // Return default config
        return $this->getDefaultConfig($pluginSlug);
    }

    /**
     * Validate configuration against plugin schema.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string> Validation errors
     */
    public function validateConfig(string $pluginSlug, array $config): array
    {
        $instance = $this->pluginManager->getPlugin($pluginSlug);

        if (! $instance) {
            // Fallback to schema-based validation
            return $this->validateBySchema($pluginSlug, $config);
        }

        return $instance->validateConfig($config);
    }

    /**
     * Validate configuration by schema from database.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    protected function validateBySchema(string $pluginSlug, array $config): array
    {
        $schema = $this->getConfigSchema($pluginSlug);
        $errors = [];
        $rules = [];

        foreach ($schema as $key => $field) {
            $fieldRules = [];

            if (! empty($field['required'])) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['type'] ?? 'string') {
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'number':
                case 'integer':
                    $fieldRules[] = 'numeric';
                    if (isset($field['min'])) {
                        $fieldRules[] = "min:{$field['min']}";
                    }
                    if (isset($field['max'])) {
                        $fieldRules[] = "max:{$field['max']}";
                    }
                    break;
                case 'select':
                    if (! empty($field['options'])) {
                        $fieldRules[] = 'in:'.implode(',', $field['options']);
                    }
                    break;
                case 'multiselect':
                    $fieldRules[] = 'array';
                    if (! empty($field['options'])) {
                        $rules["{$key}.*"] = 'in:'.implode(',', $field['options']);
                    }
                    break;
                case 'time':
                    $fieldRules[] = 'regex:/^\d{2}:\d{2}(,\d{2}:\d{2})*$/';
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                default:
                    $fieldRules[] = 'string';
            }

            $rules[$key] = implode('|', $fieldRules);
        }

        $validator = Validator::make($config, $rules);

        if ($validator->fails()) {
            return $validator->errors()->toArray();
        }

        return [];
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
     * Build form fields from schema for frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildFormFields(string $pluginSlug): array
    {
        $schema = $this->getConfigSchema($pluginSlug);
        $fields = [];

        foreach ($schema as $key => $field) {
            $fields[] = [
                'key' => $key,
                'type' => $field['type'] ?? 'text',
                'label' => $field['label'] ?? $key,
                'description' => $field['description'] ?? null,
                'required' => $field['required'] ?? false,
                'default' => $field['default'] ?? null,
                'options' => $field['options'] ?? null,
                'multiple' => $field['multiple'] ?? false,
                'condition' => $field['condition'] ?? null,
                'min' => $field['min'] ?? null,
                'max' => $field['max'] ?? null,
            ];
        }

        return $fields;
    }
}
