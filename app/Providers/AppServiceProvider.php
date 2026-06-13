<?php

namespace App\Providers;

use App\Services\Admin\SettingsService;
use App\Services\Ai\ActionExecutorService;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ChatOrchestrator;
use App\Services\Ai\ChatService;
use App\Services\Ai\ClaudeProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\IntentParserService;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\ResilientAiProvider;
use App\Services\Plugin\PluginConfigurationService;
use App\Services\Plugin\PluginManager;
use App\Services\Plugin\PluginSchedulerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The "base" provider is the concrete AI provider resolved from the
        // active configuration (database settings, falling back to env config
        // during migrations or before any settings have been saved).
        $this->app->singleton('ai.provider.base', function ($app) {
            // Get AI config from database via SettingsService
            // Only if database is available (not during migrations)
            if (Schema::hasTable('system_settings')) {
                $settingsService = $app->make(SettingsService::class);
                $config = $settingsService->getActiveAiConfig();

                return match ($config['provider']) {
                    'gemini' => new GeminiProvider(
                        $config['api_key'],
                        $config['model'],
                        $config['base_url']
                    ),
                    'anthropic' => new ClaudeProvider(
                        $config['api_key'],
                        $config['model'],
                        $config['base_url']
                    ),
                    'openai' => new OpenAiProvider(
                        $config['api_key'],
                        $config['model'],
                        $config['base_url']
                    ),
                    default => new OpenAiProvider(
                        $config['api_key'],
                        $config['model'],
                        $config['base_url'] ?? null
                    ),
                };
            }

            // Fallback to env config during migrations or if table doesn't exist
            return new OpenAiProvider(
                config('services.openai.api_key'),
                config('services.openai.model'),
                config('services.openai.base_url')
            );
        });

        // General-purpose provider used by ChatService, ChatOrchestrator, etc.
        // Wraps the base provider with a fallback model for resilience.
        $this->app->singleton(AiProviderInterface::class, function ($app) {
            $config = $this->resolveAiConfig($app);

            return new ResilientAiProvider(
                $app->make('ai.provider.base'),
                null,
                $config['fallback_model'] ?? null,
            );
        });

        // Provider used by background jobs (conversation memory extraction,
        // thread title generation) that prefer a faster/cheaper model.
        $this->app->singleton('ai.provider.fast', function ($app) {
            $config = $this->resolveAiConfig($app);

            return new ResilientAiProvider(
                $app->make('ai.provider.base'),
                $config['fast_model'] ?? null,
                $config['fast_fallback_model'] ?? null,
            );
        });

        // Plugin system - must be registered before services that depend on it
        $this->app->singleton(PluginManager::class, function ($app) {
            $manager = new PluginManager;
            $manager->discoverPlugins();

            return $manager;
        });

        $this->app->singleton(IntentParserService::class, function ($app) {
            return new IntentParserService(
                $app->make(AiProviderInterface::class),
                $app->make(PluginManager::class)
            );
        });

        $this->app->singleton(ActionExecutorService::class, function ($app) {
            return new ActionExecutorService;
        });

        $this->app->singleton(ChatOrchestrator::class, function ($app) {
            return new ChatOrchestrator(
                $app->make(ChatService::class),
                $app->make(IntentParserService::class),
                $app->make(ActionExecutorService::class),
                $app->make(AiProviderInterface::class),
                $app->make(PluginManager::class),
                $app->make(\App\Services\Ai\ConversationMemoryService::class),
                $app->make(\App\Services\Admin\SettingsService::class),
            );
        });

        $this->app->singleton(PluginConfigurationService::class, function ($app) {
            return new PluginConfigurationService($app->make(PluginManager::class));
        });

        $this->app->singleton(PluginSchedulerService::class, function ($app) {
            return new PluginSchedulerService($app->make(PluginManager::class));
        });

        // Background jobs (memory extraction, thread title generation) use the
        // "fast" provider instead of the default AiProviderInterface binding.
        $this->app->singleton(\App\Services\Ai\ConversationMemoryService::class, function ($app) {
            return new \App\Services\Ai\ConversationMemoryService(
                $app->make('ai.provider.fast'),
                $app->make(SettingsService::class),
            );
        });
    }

    /**
     * Resolve the active AI configuration from settings, or an empty array
     * when the settings table is not yet available (e.g. during migrations).
     *
     * @return array<string, mixed>
     */
    protected function resolveAiConfig(\Illuminate\Contracts\Foundation\Application $app): array
    {
        if (Schema::hasTable('system_settings')) {
            return $app->make(SettingsService::class)->getActiveAiConfig();
        }

        return [];
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->forceHttpsInProduction();
        $this->configureDefaults();
        $this->configureDynamicMail();
    }

    /**
     * Force HTTPS in production environment.
     */
    protected function forceHttpsInProduction(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    /**
     * Configure dynamic mail settings from database.
     */
    protected function configureDynamicMail(): void
    {
        // Only configure if database is available
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        try {
            $settingsService = app(SettingsService::class);
            $smtpConfig = $settingsService->getSmtpConfig();

            // Only override if we have settings in database
            if ($smtpConfig['host'] && $smtpConfig['username']) {
                Config::set('mail.default', 'smtp');
                Config::set('mail.mailers.smtp.host', $smtpConfig['host']);
                Config::set('mail.mailers.smtp.port', $smtpConfig['port']);
                Config::set('mail.mailers.smtp.encryption', $smtpConfig['encryption']);
                Config::set('mail.mailers.smtp.username', $smtpConfig['username']);
                Config::set('mail.mailers.smtp.password', $smtpConfig['password']);

                if ($smtpConfig['from']['address']) {
                    Config::set('mail.from.address', $smtpConfig['from']['address']);
                    Config::set('mail.from.name', $smtpConfig['from']['name']);
                }
            }
        } catch (\Exception $e) {
            // Silently fail if database is not available
        }
    }
}
