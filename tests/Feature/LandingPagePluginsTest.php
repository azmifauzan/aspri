<?php

namespace Tests\Feature;

use App\Services\Plugin\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPagePluginsTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_displays_featured_plugins(): void
    {
        // Seed plugins first
        $pluginManager = app(PluginManager::class);
        $pluginManager->syncPlugins();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Welcome')
                ->has('featuredPlugins')
                ->has('featuredPlugins', 3) // kata-motivasi, pengingat-minum-air, expense-alert
                ->where('featuredPlugins.0.slug', 'expense-alert') // Ordered alphabetically
                ->where('featuredPlugins.1.slug', 'kata-motivasi')
                ->where('featuredPlugins.2.slug', 'pengingat-minum-air');
        });
    }

    public function test_landing_page_displays_without_plugins(): void
    {
        // Don't seed any plugins
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Welcome')
                ->has('featuredPlugins', 0);
        });
    }

    public function test_featured_plugins_contain_required_fields(): void
    {
        $pluginManager = app(PluginManager::class);
        $pluginManager->syncPlugins();

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Welcome')
                ->has('featuredPlugins.0', function ($plugin) {
                    $plugin->has('slug')
                        ->has('name')
                        ->has('description')
                        ->has('icon');
                });
        });
    }
}
