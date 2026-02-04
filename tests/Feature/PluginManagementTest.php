<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\User;
use App\Models\UserPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plugin = Plugin::factory()->create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'description' => 'A test plugin for testing',
            'config_schema' => [
                'enabled' => [
                    'type' => 'boolean',
                    'label' => 'Aktifkan',
                    'default' => true,
                    'required' => true,
                ],
                'message' => [
                    'type' => 'text',
                    'label' => 'Pesan',
                    'default' => 'Hello',
                    'required' => false,
                ],
            ],
            'default_config' => [
                'enabled' => true,
                'message' => 'Hello',
            ],
        ]);
    }

    public function test_user_can_view_plugins_list(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('plugins.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('plugins/Index')
            ->has('plugins')
        );
    }

    public function test_user_can_activate_plugin(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('plugins.activate', $this->plugin));

        $response->assertRedirect();

        $this->assertDatabaseHas('user_plugins', [
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);
    }

    public function test_user_can_deactivate_plugin(): void
    {
        // First activate the plugin
        UserPlugin::factory()->create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('plugins.deactivate', $this->plugin));

        $response->assertRedirect();

        $this->assertDatabaseHas('user_plugins', [
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => false,
        ]);
    }

    public function test_user_can_view_plugin_details(): void
    {
        // Activate plugin first
        UserPlugin::factory()->create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('plugins.show', $this->plugin));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('plugins/Show')
            ->has('plugin')
            ->has('userPlugin')
            ->has('config')
            ->has('formFields')
        );
    }

    public function test_unauthenticated_user_cannot_access_plugins(): void
    {
        $response = $this->get(route('plugins.index'));

        $response->assertRedirect(route('login'));
    }
}
