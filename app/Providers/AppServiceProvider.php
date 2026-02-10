<?php

namespace App\Providers;

use App\Services\Admin\SettingsService;
use App\Services\Ai\ActionExecutorService;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ChatOrchestrator;
use App\Services\Ai\ChatService;
use App\Services\Ai\IntentParserService;
use App\Services\Ai\OpenAiProvider;
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
        $this->app->singleton(AiProviderInterface::class, function ($app) {
            // Get AI config from database via SettingsService
            // Only if database is available (not during migrations)
            if (Schema::hasTable('system_settings')) {
                $settingsService = $app->make(SettingsService::class);
                $config = $settingsService->getActiveAiConfig();

                return new OpenAiProvider(
                    $config['api_key'],
                    $config['model'],
                );
            }

            // Fallback to env config during migrations or if table doesn't exist
            return new OpenAiProvider(
                config('services.openai.api_key'),
                config('services.openai.model'),
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
                $app->make(PluginManager::class)
            );
        });

        $this->app->singleton(PluginConfigurationService::class, function ($app) {
            return new PluginConfigurationService($app->make(PluginManager::class));
        });

        $this->app->singleton(PluginSchedulerService::class, function ($app) {
            return new PluginSchedulerService($app->make(PluginManager::class));
        });
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
