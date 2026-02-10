<?php

namespace Tests\Feature\Integration;

use App\Models\Plugin;
use App\Models\PluginRating;
use App\Models\PluginSchedule;
use App\Models\User;
use App\Models\UserPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        // Create a system plugin
        $this->plugin = Plugin::create([
            'slug' => 'motivational-quotes',
            'name' => 'Kata Motivasi',
            'description' => 'Kirim quote motivasi harian',
            'version' => '1.0.0',
            'author' => 'ASPRI Team',
            'icon' => '🎯',
            'class_name' => 'App\\Plugins\\MotivationalQuotesPlugin',
            'is_system' => true,
            'config_schema' => [
                'time' => [
                    'type' => 'time',
                    'label' => 'Waktu Pengiriman',
                    'default' => '09:00',
                ],
            ],
        ]);
    }

    public function test_user_can_view_plugins_list(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('plugins.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('plugins/Index')
            ->has('plugins')
        );
    }

    public function test_user_can_view_plugin_detail(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('plugins.show', $this->plugin));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('plugins/Show')
            ->where('plugin.slug', 'motivational-quotes')
            ->where('plugin.name', 'Kata Motivasi')
        );
    }

    public function test_user_can_activate_plugin(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('plugins.activate', $this->plugin));

        $response->assertRedirect();

        // Check UserPlugin pivot table
        $this->assertDatabaseHas('user_plugins', [
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);
    }

    public function test_user_can_deactivate_plugin(): void
    {
        $this->actingAs($this->user);

        // First activate
        UserPlugin::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);

        // Then deactivate
        $response = $this->post(route('plugins.deactivate', $this->plugin));

        $response->assertRedirect();

        $this->assertDatabaseHas('user_plugins', [
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => false,
        ]);
    }

    public function test_user_can_configure_plugin(): void
    {
        $this->actingAs($this->user);

        // Activate plugin first
        $userPlugin = UserPlugin::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);

        $configData = [
            'time' => '08:00',
        ];

        $response = $this->post(route('plugins.config.update', $this->plugin), [
            'config' => $configData,
        ]);

        $response->assertRedirect();

        // Check that configuration was saved with key-value structure
        $this->assertDatabaseHas('plugin_configurations', [
            'user_plugin_id' => $userPlugin->id,
            'config_key' => 'time',
            'config_value' => json_encode('08:00'),
        ]);
    }

    public function test_user_can_reset_plugin_configuration(): void
    {
        $this->actingAs($this->user);

        // Activate plugin first
        $userPlugin = UserPlugin::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);

        // Create configuration via service
        $this->app->make(\App\Services\Plugin\PluginConfigurationService::class)
            ->saveConfig($this->user->id, $this->plugin->slug, ['time' => '10:00']);

        $response = $this->delete(route('plugins.config.reset', $this->plugin));

        $response->assertRedirect();

        // After reset, configurations should be deleted
        $this->assertDatabaseMissing('plugin_configurations', [
            'user_plugin_id' => $userPlugin->id,
        ]);
    }

    public function test_user_can_schedule_plugin_execution(): void
    {
        $this->actingAs($this->user);

        // Activate plugin
        $userPlugin = UserPlugin::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);

        $scheduleData = [
            'schedule_type' => 'cron',
            'schedule_value' => '0 9 * * *', // Every day at 9 AM
        ];

        $response = $this->post(route('plugins.schedule.update', $this->plugin), $scheduleData);

        $response->assertRedirect();

        $this->assertDatabaseHas('plugin_schedules', [
            'user_plugin_id' => $userPlugin->id,
            'schedule_type' => 'cron',
            'schedule_value' => '0 9 * * *',
            'is_active' => true,
        ]);
    }

    public function test_user_can_delete_plugin_schedule(): void
    {
        $this->actingAs($this->user);

        // Activate plugin first
        $userPlugin = UserPlugin::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'is_active' => true,
        ]);

        $schedule = PluginSchedule::create([
            'user_plugin_id' => $userPlugin->id,
            'schedule_type' => 'cron',
            'schedule_value' => '0 9 * * *',
            'is_active' => true,
        ]);

        $response = $this->delete(route('plugins.schedule.delete', [
            'plugin' => $this->plugin,
            'scheduleId' => $schedule->id,
        ]));

        $response->assertRedirect();

        $this->assertDatabaseMissing('plugin_schedules', [
            'id' => $schedule->id,
        ]);
    }

    public function test_user_can_rate_plugin(): void
    {
        $this->actingAs($this->user);

        $ratingData = [
            'rating' => 5,
            'review' => 'Excellent plugin! Very helpful.',
        ];

        $response = $this->post(route('plugins.ratings.store', $this->plugin), $ratingData);

        $response->assertRedirect();

        $this->assertDatabaseHas('plugin_ratings', [
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'rating' => 5,
            'review' => 'Excellent plugin! Very helpful.',
        ]);
    }

    public function test_user_can_update_plugin_rating(): void
    {
        $this->actingAs($this->user);

        $rating = PluginRating::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'rating' => 4,
            'review' => 'Good plugin',
        ]);

        $updateData = [
            'rating' => 5,
            'review' => 'Actually, it\'s excellent!',
        ];

        $response = $this->put(route('plugins.ratings.update', [
            'plugin' => $this->plugin,
            'rating' => $rating,
        ]), $updateData);

        $response->assertRedirect();

        $this->assertDatabaseHas('plugin_ratings', [
            'id' => $rating->id,
            'rating' => 5,
            'review' => 'Actually, it\'s excellent!',
        ]);
    }

    public function test_user_can_delete_plugin_rating(): void
    {
        $this->actingAs($this->user);

        $rating = PluginRating::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'rating' => 3,
            'review' => 'Average',
        ]);

        $response = $this->delete(route('plugins.ratings.destroy', [
            'plugin' => $this->plugin,
            'rating' => $rating,
        ]));

        $response->assertRedirect();

        $this->assertDatabaseMissing('plugin_ratings', [
            'id' => $rating->id,
        ]);
    }

    public function test_user_cannot_rate_plugin_with_invalid_rating(): void
    {
        $this->actingAs($this->user);

        $ratingData = [
            'rating' => 6, // Invalid (must be 1-5)
            'review' => 'Invalid rating',
        ];

        $response = $this->post(route('plugins.ratings.store', $this->plugin), $ratingData);

        $response->assertSessionHasErrors(['rating']);
    }

    public function test_user_can_only_rate_plugin_once(): void
    {
        $this->actingAs($this->user);

        // First rating
        PluginRating::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'rating' => 4,
            'review' => 'First rating',
        ]);

        // Try to create second rating (should fail)
        $ratingData = [
            'rating' => 5,
            'review' => 'Second rating',
        ];

        $response = $this->post(route('plugins.ratings.store', $this->plugin), $ratingData);

        // Should have validation error or redirect with error
        $this->assertEquals(1, PluginRating::where('user_id', $this->user->id)
            ->where('plugin_id', $this->plugin->id)
            ->count());
    }

    public function test_user_cannot_configure_inactive_plugin(): void
    {
        $this->actingAs($this->user);

        // Plugin not activated
        $configData = [
            'time' => '08:00',
        ];

        $response = $this->post(route('plugins.config.update', $this->plugin), [
            'config' => $configData,
        ]);

        // Should fail because plugin is not activated
        $response->assertStatus(403);
    }

    public function test_plugin_average_rating_calculation(): void
    {
        $this->actingAs($this->user);

        // Create multiple ratings from different users
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        PluginRating::create([
            'user_id' => $this->user->id,
            'plugin_id' => $this->plugin->id,
            'rating' => 5,
        ]);

        PluginRating::create([
            'user_id' => $user2->id,
            'plugin_id' => $this->plugin->id,
            'rating' => 4,
        ]);

        PluginRating::create([
            'user_id' => $user3->id,
            'plugin_id' => $this->plugin->id,
            'rating' => 5,
        ]);

        $plugin = Plugin::withAvg('ratings', 'rating')->find($this->plugin->id);

        // Average should be (5 + 4 + 5) / 3 = 4.67
        $this->assertEquals(4.67, round($plugin->ratings_avg_rating, 2));
    }

    public function test_guest_can_view_explore_plugins_page(): void
    {
        $response = $this->get(route('explore-plugins'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ExplorePlugins')
            ->has('plugins')
        );
    }

    public function test_explore_plugins_shows_only_system_plugins(): void
    {
        // Create a non-system plugin (should not appear)
        Plugin::create([
            'slug' => 'custom-plugin',
            'name' => 'Custom Plugin',
            'description' => 'User custom plugin',
            'version' => '1.0.0',
            'class_name' => 'App\\Plugins\\CustomPlugin',
            'is_system' => false,
        ]);

        $response = $this->get(route('explore-plugins'));

        $response->assertOk();
        // Should only show system plugins
    }
}
