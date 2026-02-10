<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckHttpsConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-https';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check HTTPS configuration and URL generation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔒 ASPRI HTTPS Configuration Check');
        $this->newLine();

        // Check environment
        $env = config('app.env');
        $isProduction = app()->isProduction();
        $this->checkItem(
            'Environment',
            $env,
            $isProduction ? '✅ Production' : '⚠️  Not production'
        );

        // Check APP_URL
        $appUrl = config('app.url');
        $isHttps = str_starts_with($appUrl, 'https://');
        $this->checkItem(
            'APP_URL',
            $appUrl,
            $isHttps ? '✅ Using HTTPS' : '❌ Using HTTP (should be HTTPS in production)'
        );

        // Check generated URLs
        $this->newLine();
        $this->info('Generated URLs:');
        $this->line('  Base URL: '.url('/'));
        $this->line('  Route URL: '.route('login'));
        $this->line('  Asset URL: '.asset('test.css'));

        // Check if all URLs use HTTPS
        $baseUrl = url('/');
        $routeUrl = route('login');
        $assetUrl = asset('test.css');

        $allHttps = str_starts_with($baseUrl, 'https://')
            && str_starts_with($routeUrl, 'https://')
            && str_starts_with($assetUrl, 'https://');

        $this->newLine();
        if ($allHttps) {
            $this->info('✅ All URLs are using HTTPS');
        } else {
            $this->error('❌ Some URLs are not using HTTPS');
        }

        // Check middleware
        $this->newLine();
        $this->info('Middleware Configuration:');

        $middlewareClasses = [
            'TrustProxies' => \App\Http\Middleware\TrustProxies::class,
        ];

        foreach ($middlewareClasses as $name => $class) {
            $exists = class_exists($class);
            $this->line('  '.$name.': '.($exists ? '✅ Registered' : '❌ Missing'));
        }

        // Recommendations
        $this->newLine();
        $this->info('💡 Recommendations:');

        $recommendations = [];

        if (! $isProduction && $isHttps) {
            $recommendations[] = 'You\'re using HTTPS in non-production. Make sure this is intentional.';
        }

        if ($isProduction && ! $isHttps) {
            $recommendations[] = 'Set APP_URL to use HTTPS in production: APP_URL=https://yourdomain.com';
        }

        if (! $allHttps && $isProduction) {
            $recommendations[] = 'Clear config cache: php artisan config:clear';
            $recommendations[] = 'Restart your server/workers after changing .env';
        }

        if ($isProduction) {
            $recommendations[] = 'Ensure your reverse proxy (Nginx/Apache) passes X-Forwarded-Proto header';
            $recommendations[] = 'Configure SSL certificate on your server or use Cloudflare';
            $recommendations[] = 'Enable HTTPS redirect at reverse proxy level';
        }

        if (empty($recommendations)) {
            $this->line('  ✅ Configuration looks good!');
        } else {
            foreach ($recommendations as $i => $recommendation) {
                $this->line('  '.($i + 1).'. '.$recommendation);
            }
        }

        $this->newLine();
        $this->info('📚 For more information, see: docs/HTTPS_SECURITY.md');

        return $isProduction && $allHttps ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Display a configuration check item.
     */
    protected function checkItem(string $label, string $value, string $status): void
    {
        $this->line(sprintf('  %s: %s', $label, $value));
        $this->line(sprintf('    %s', $status));
    }
}
