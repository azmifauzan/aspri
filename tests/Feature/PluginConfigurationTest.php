<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\User;
use App\Models\UserPlugin;
use App\Services\Plugin\PluginConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plugin $plugin;

    private UserPlugin $userPlugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plugin = Plugin::factory()->create([
            'slug' => 'config-test-plugin',
            'name' => 'Config Test Plugin',
            'config_schema' => [
                'enabled' => [
                    'type' => 'boolean',
                    'label' => 'Aktifkan',
                    'default' => true,
                    'required' => true,
                ],
                'frequency' => [
                    'type' => 'select',
                    'label' => 'Frekuensi',
                    'options' => ['daily', 'weekly', 'monthly'],
                    'default' => 'daily',
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
                'frequency' => 'daily',
                'message' => 'Hello',
            ],
        ]);
        $this->userPlugin = UserPlugin::factory()->create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    public function test_user_can_save_plugin_configuration(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('plugins.config.update', $this->plugin), [
                'config' => [
                    'enabled' => true,
                    'frequency' => 'weekly',
                    'message' => 'Custom message',
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('plugin_configurations', [
            'user_plugin_id' => $this->userPlugin->id,
            'config_key' => 'frequency',
        ]);
    }

    public function test_user_can_reset_plugin_configuration(): void
    {
        // First save some config
        $this->userPlugin->setConfig('frequency', 'weekly');
        $this->userPlugin->setConfig('message', 'Custom');

        $response = $this->actingAs($this->user)
            ->delete(route('plugins.config.reset', $this->plugin));

        $response->assertRedirect();

        $this->assertDatabaseMissing('plugin_configurations', [
            'user_plugin_id' => $this->userPlugin->id,
        ]);
    }

    public function test_configuration_service_returns_default_for_new_users(): void
    {
        $newUser = User::factory()->create();
        $configService = app(PluginConfigurationService::class);

        $config = $configService->getConfig($newUser->id, $this->plugin->slug);

        $this->assertEquals($this->plugin->default_config, $config);
    }

    public function test_configuration_service_merges_user_config_with_defaults(): void
    {
        $this->userPlugin->setConfig('frequency', 'monthly');

        $configService = app(PluginConfigurationService::class);
        $config = $configService->getConfig($this->user->id, $this->plugin->slug);

        $this->assertEquals('monthly', $config['frequency']);
        $this->assertEquals(true, $config['enabled']); // default
        $this->assertEquals('Hello', $config['message']); // default
    }

    public function test_configuration_builds_form_fields_correctly(): void
    {
        $configService = app(PluginConfigurationService::class);
        $fields = $configService->buildFormFields($this->plugin->slug);

        $this->assertCount(3, $fields);

        $frequencyField = collect($fields)->firstWhere('key', 'frequency');
        $this->assertNotNull($frequencyField);
        $this->assertEquals('select', $frequencyField['type']);
        $this->assertEquals(['daily', 'weekly', 'monthly'], $frequencyField['options']);
    }
}
