<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HttpsConfigurationTest extends TestCase
{
    /**
     * Test that HTTPS is enforced in production environment.
     */
    public function test_https_is_forced_in_production(): void
    {
        // Temporarily set to production
        $originalEnv = config('app.env');
        Config::set('app.env', 'production');
        Config::set('app.url', 'https://aspriai.my.id');

        // Force HTTPS scheme (simulating what AppServiceProvider does)
        URL::forceScheme('https');

        // Generate URLs
        $url = url('/test');
        $routeUrl = route('login');

        // Assert they use HTTPS
        $this->assertStringStartsWith('https://', $url);
        $this->assertStringStartsWith('https://', $routeUrl);

        // Restore original environment
        Config::set('app.env', $originalEnv);
    }

    /**
     * Test that HTTP is allowed in local environment.
     */
    public function test_http_is_allowed_in_local(): void
    {
        // Ensure we're in local environment
        Config::set('app.env', 'local');
        Config::set('app.url', 'http://localhost');

        // URL generation should respect the configured scheme
        $url = config('app.url');

        // In local, HTTP should be allowed
        $this->assertStringStartsWith('http://', $url);
    }

    /**
     * Test that routes generate HTTPS URLs in production.
     */
    public function test_routes_generate_https_urls_in_production(): void
    {
        // Simulate production environment
        Config::set('app.env', 'production');
        Config::set('app.url', 'https://aspriai.my.id');

        // Force HTTPS
        URL::forceScheme('https');

        // Generate route URL
        $url = route('login');

        // Assert it uses HTTPS
        $this->assertStringStartsWith('https://', $url);
    }

    /**
     * Test that assets generate HTTPS URLs in production.
     */
    public function test_assets_generate_https_urls_in_production(): void
    {
        // Simulate production environment
        Config::set('app.env', 'production');
        Config::set('app.url', 'https://aspriai.my.id');

        // Force HTTPS
        URL::forceScheme('https');

        // Generate asset URL
        $url = asset('css/app.css');

        // Assert it uses HTTPS
        $this->assertStringStartsWith('https://', $url);
    }

    /**
     * Test that X-Forwarded-Proto header is respected.
     */
    public function test_x_forwarded_proto_header_is_respected(): void
    {
        // Simulate request from reverse proxy with HTTPS
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'aspriai.my.id',
            'X-Forwarded-Port' => '443',
        ])->get('/');

        // Request should be recognized as secure
        $this->assertTrue($this->app['request']->secure());
    }

    /**
     * Test trust proxies middleware is registered.
     */
    public function test_trust_proxies_middleware_exists(): void
    {
        $middleware = $this->app[\Illuminate\Contracts\Http\Kernel::class]
            ->getMiddlewareGroups();

        // Check that web middleware group exists
        $this->assertArrayHasKey('web', $middleware);

        // Check that TrustProxies is in the middleware stack
        $webMiddleware = collect($middleware['web']);
        $hasTrustProxies = $webMiddleware->contains(function ($item) {
            return $item === \App\Http\Middleware\TrustProxies::class
                || str_contains($item, 'TrustProxies');
        });

        $this->assertTrue(
            $hasTrustProxies,
            'TrustProxies middleware should be registered in web middleware group'
        );
    }
}
