<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use App\Models\PluginLog;
use App\Models\UserPlugin;
use App\Services\Plugin\Contracts\PluginInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PluginManager
{
    /**
     * @var array<string, class-string<PluginInterface>>
     */
    protected array $registeredPlugins = [];

    /**
     * Cache key for registered plugins.
     */
    protected const CACHE_KEY = 'plugins:registry';

    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Register a plugin class.
     *
     * @param  class-string<PluginInterface>  $pluginClass
     */
    public function register(string $pluginClass): void
    {
        if (! class_exists($pluginClass)) {
            throw new \InvalidArgumentException("Plugin class {$pluginClass} does not exist.");
        }

        if (! in_array(PluginInterface::class, class_implements($pluginClass) ?: [])) {
            throw new \InvalidArgumentException("Plugin class {$pluginClass} must implement PluginInterface.");
        }

        /** @var PluginInterface $instance */
        $instance = app($pluginClass);
        $this->registeredPlugins[$instance->getSlug()] = $pluginClass;
    }

    /**
     * Get all registered plugin classes.
     *
     * @return array<string, class-string<PluginInterface>>
     */
    public function getRegisteredPlugins(): array
    {
        return $this->registeredPlugins;
    }

    /**
     * Get a plugin instance by slug.
     */
    public function getPlugin(string $slug): ?PluginInterface
    {
        if (! isset($this->registeredPlugins[$slug])) {
            return null;
        }

        return app($this->registeredPlugins[$slug]);
    }

    /**
     * Install a plugin to the system.
     */
    public function install(string $slug): Plugin
    {
        $pluginClass = $this->registeredPlugins[$slug] ?? null;

        if (! $pluginClass) {
            throw new \InvalidArgumentException("Plugin {$slug} is not registered.");
        }

        /** @var PluginInterface $instance */
        $instance = app($pluginClass);

        // Check if already installed
        $existing = Plugin::where('slug', $slug)->first();
        if ($existing) {
            return $existing;
        }

        // Create plugin record
        $plugin = Plugin::create([
            'slug' => $instance->getSlug(),
            'name' => $instance->getName(),
            'description' => $instance->getDescription(),
            'version' => $instance->getVersion(),
            'author' => $instance->getAuthor(),
            'icon' => $instance->getIcon(),
            'class_name' => $pluginClass,
            'is_system' => true,
            'config_schema' => $instance->getConfigSchema(),
            'default_config' => $instance->getDefaultConfig(),
            'installed_at' => now(),
        ]);

        // Call plugin's install hook
        $instance->install();

        PluginLog::info($plugin->id, 'Plugin installed');

        $this->clearCache();

        return $plugin;
    }

    /**
     * Uninstall a plugin from the system.
     */
    public function uninstall(string $slug): bool
    {
        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin) {
            return false;
        }

        // Get plugin instance and call uninstall hook
        $instance = $this->getPlugin($slug);
        if ($instance) {
            $instance->uninstall();
        }

        PluginLog::info($plugin->id, 'Plugin uninstalled');

        // Delete plugin and all related data (cascade)
        $plugin->delete();

        $this->clearCache();

        return true;
    }

    /**
     * Activate a plugin for a user.
     */
    public function activateForUser(string $slug, int $userId): UserPlugin
    {
        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin) {
            throw new \InvalidArgumentException("Plugin {$slug} is not installed.");
        }

        // Create or get user plugin
        $userPlugin = UserPlugin::firstOrCreate([
            'user_id' => $userId,
            'plugin_id' => $plugin->id,
        ], [
            'is_active' => false,
        ]);

        // Activate if not already active
        if (! $userPlugin->is_active) {
            $userPlugin->activate();

            // Call plugin's activate hook
            $instance = $this->getPlugin($slug);
            if ($instance) {
                $instance->activate($userId);
            }

            PluginLog::info($plugin->id, 'Plugin activated for user', $userId);
        }

        return $userPlugin;
    }

    /**
     * Deactivate a plugin for a user.
     */
    public function deactivateForUser(string $slug, int $userId): ?UserPlugin
    {
        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin) {
            return null;
        }

        $userPlugin = UserPlugin::where('user_id', $userId)
            ->where('plugin_id', $plugin->id)
            ->first();

        if (! $userPlugin) {
            return null;
        }

        // Call plugin's deactivate hook
        $instance = $this->getPlugin($slug);
        if ($instance) {
            $instance->deactivate($userId);
        }

        $userPlugin->deactivate();

        PluginLog::info($plugin->id, 'Plugin deactivated for user', $userId);

        return $userPlugin;
    }

    /**
     * Get all installed plugins.
     *
     * @return Collection<int, Plugin>
     */
    public function getInstalledPlugins(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Plugin::whereNotNull('installed_at')->get();
        });
    }

    /**
     * Get all plugins available for a user.
     *
     * @return Collection<int, Plugin>
     */
    public function getPluginsForUser(int $userId): Collection
    {
        return Plugin::whereNotNull('installed_at')
            ->with(['userPlugins' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->get()
            ->map(function (Plugin $plugin) {
                $userPlugin = $plugin->userPlugins->first();
                $plugin->user_is_active = $userPlugin?->is_active ?? false;
                $plugin->user_plugin_id = $userPlugin?->id;

                return $plugin;
            });
    }

    /**
     * Get active plugins for a user.
     *
     * @return Collection<int, UserPlugin>
     */
    public function getActivePluginsForUser(int $userId): Collection
    {
        return UserPlugin::where('user_id', $userId)
            ->where('is_active', true)
            ->with('plugin')
            ->get();
    }

    /**
     * Execute a plugin for a user.
     *
     * @param  array<string, mixed>  $context
     */
    public function executePlugin(string $slug, int $userId, array $context = []): void
    {
        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin) {
            throw new \InvalidArgumentException("Plugin {$slug} is not installed.");
        }

        $userPlugin = UserPlugin::where('user_id', $userId)
            ->where('plugin_id', $plugin->id)
            ->where('is_active', true)
            ->first();

        if (! $userPlugin) {
            throw new \InvalidArgumentException("Plugin {$slug} is not active for this user.");
        }

        $instance = $this->getPlugin($slug);
        if (! $instance) {
            throw new \RuntimeException("Plugin {$slug} instance could not be created.");
        }

        $config = $userPlugin->getAllConfig();
        $config = array_merge($plugin->default_config ?? [], $config);

        $instance->execute($userId, $config, $context);
    }

    /**
     * Clear plugin cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Discover and register all plugins from the Plugins directory.
     */
    public function discoverPlugins(): void
    {
        $pluginsPath = app_path('Plugins');

        if (! is_dir($pluginsPath)) {
            return;
        }

        $directories = glob($pluginsPath.'/*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $pluginName = basename($directory);
            $pluginClass = "App\\Plugins\\{$pluginName}\\{$pluginName}Plugin";

            if (class_exists($pluginClass)) {
                $this->register($pluginClass);
            }
        }
    }

    /**
     * Sync registered plugins with database.
     */
    public function syncPlugins(): void
    {
        foreach ($this->registeredPlugins as $slug => $pluginClass) {
            $existing = Plugin::where('slug', $slug)->first();

            if (! $existing) {
                $this->install($slug);
            } else {
                // Update plugin info
                /** @var PluginInterface $instance */
                $instance = app($pluginClass);

                $existing->update([
                    'name' => $instance->getName(),
                    'description' => $instance->getDescription(),
                    'version' => $instance->getVersion(),
                    'author' => $instance->getAuthor(),
                    'icon' => $instance->getIcon(),
                    'config_schema' => $instance->getConfigSchema(),
                    'default_config' => $instance->getDefaultConfig(),
                ]);
            }
        }

        $this->clearCache();
    }
}
