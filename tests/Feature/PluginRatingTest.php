<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\PluginRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rate_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $response = $this->actingAs($user)->post(route('plugins.ratings.store', $plugin), [
            'rating' => 5,
            'review' => 'Excellent plugin!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_ratings', [
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'rating' => 5,
            'review' => 'Excellent plugin!',
        ]);
    }

    public function test_user_can_rate_plugin_without_review(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $response = $this->actingAs($user)->post(route('plugins.ratings.store', $plugin), [
            'rating' => 4,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_ratings', [
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'rating' => 4,
            'review' => null,
        ]);
    }

    public function test_user_cannot_rate_same_plugin_twice(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        // Create first rating
        PluginRating::factory()->create([
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'rating' => 4,
        ]);

        // Try to rate again
        $response = $this->actingAs($user)->post(route('plugins.ratings.store', $plugin), [
            'rating' => 5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertEquals(1, PluginRating::where('user_id', $user->id)
            ->where('plugin_id', $plugin->id)
            ->count());
    }

    public function test_rating_must_be_between1_and5(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        // Test rating below 1
        $response = $this->actingAs($user)->post(route('plugins.ratings.store', $plugin), [
            'rating' => 0,
        ]);
        $response->assertSessionHasErrors('rating');

        // Test rating above 5
        $response = $this->actingAs($user)->post(route('plugins.ratings.store', $plugin), [
            'rating' => 6,
        ]);
        $response->assertSessionHasErrors('rating');

        // Test valid rating
        $response = $this->actingAs($user)->post(route('plugins.ratings.store', $plugin), [
            'rating' => 3,
        ]);
        $response->assertSessionHasNoErrors();
    }

    public function test_review_cannot_exceed500_characters(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $longReview = str_repeat('a', 501);

        $response = $this->actingAs($user)->post(route('plugins.ratings.store', $plugin), [
            'rating' => 4,
            'review' => $longReview,
        ]);

        $response->assertSessionHasErrors('review');
    }

    public function test_user_can_update_their_rating(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $rating = PluginRating::factory()->create([
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'rating' => 3,
            'review' => 'Good',
        ]);

        $response = $this->actingAs($user)->put(
            route('plugins.ratings.update', ['plugin' => $plugin, 'rating' => $rating]),
            [
                'rating' => 5,
                'review' => 'Excellent after update!',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('plugin_ratings', [
            'id' => $rating->id,
            'rating' => 5,
            'review' => 'Excellent after update!',
        ]);
    }

    public function test_user_cannot_update_others_rating(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $rating = PluginRating::factory()->create([
            'user_id' => $user1->id,
            'plugin_id' => $plugin->id,
        ]);

        $response = $this->actingAs($user2)->put(
            route('plugins.ratings.update', ['plugin' => $plugin, 'rating' => $rating]),
            [
                'rating' => 5,
            ]
        );

        $response->assertForbidden();
    }

    public function test_user_can_delete_their_rating(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $rating = PluginRating::factory()->create([
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
        ]);

        $response = $this->actingAs($user)->delete(
            route('plugins.ratings.destroy', ['plugin' => $plugin, 'rating' => $rating])
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('plugin_ratings', [
            'id' => $rating->id,
        ]);
    }

    public function test_user_cannot_delete_others_rating(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $plugin = Plugin::factory()->create();
        $rating = PluginRating::factory()->create([
            'user_id' => $user1->id,
            'plugin_id' => $plugin->id,
        ]);

        $response = $this->actingAs($user2)->delete(
            route('plugins.ratings.destroy', ['plugin' => $plugin, 'rating' => $rating])
        );

        $response->assertForbidden();
    }

    public function test_plugin_list_is_sorted_by_rating_by_default(): void
    {
        $user = User::factory()->create();

        // Create plugins with different ratings
        $plugin1 = Plugin::factory()->create(['name' => 'Plugin 1']);
        $plugin2 = Plugin::factory()->create(['name' => 'Plugin 2']);
        $plugin3 = Plugin::factory()->create(['name' => 'Plugin 3']);

        // Plugin 2 has highest rating (5.0)
        PluginRating::factory()->count(2)->create(['plugin_id' => $plugin2->id, 'rating' => 5]);

        // Plugin 1 has medium rating (3.5)
        PluginRating::factory()->create(['plugin_id' => $plugin1->id, 'rating' => 3]);
        PluginRating::factory()->create(['plugin_id' => $plugin1->id, 'rating' => 4]);

        // Plugin 3 has lowest rating (2.0)
        PluginRating::factory()->create(['plugin_id' => $plugin3->id, 'rating' => 2]);

        $response = $this->actingAs($user)->get(route('plugins.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('plugins/Index')
            ->has('plugins', 3)
            ->where('plugins.0.name', 'Plugin 2')
            ->where('plugins.1.name', 'Plugin 1')
            ->where('plugins.2.name', 'Plugin 3')
        );
    }

    public function test_plugin_list_can_be_filtered_by_minimum_rating(): void
    {
        $user = User::factory()->create();

        // Create plugins with different ratings
        $plugin1 = Plugin::factory()->create(['name' => 'High Rated']);
        $plugin2 = Plugin::factory()->create(['name' => 'Low Rated']);

        PluginRating::factory()->count(2)->create(['plugin_id' => $plugin1->id, 'rating' => 5]);
        PluginRating::factory()->count(2)->create(['plugin_id' => $plugin2->id, 'rating' => 2]);

        $response = $this->actingAs($user)->get(route('plugins.index', ['min_rating' => 4]));

        $response->assertInertia(fn ($page) => $page
            ->component('plugins/Index')
            ->has('plugins', 1)
            ->where('plugins.0.name', 'High Rated')
        );
    }

    public function test_plugin_show_page_includes_rating_information(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        // Create ratings
        PluginRating::factory()->create(['plugin_id' => $plugin->id, 'rating' => 5]);
        PluginRating::factory()->create(['plugin_id' => $plugin->id, 'rating' => 3]);

        $response = $this->actingAs($user)->get(route('plugins.show', $plugin));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('plugins/Show')
            ->where('plugin.average_rating', 4)
            ->where('plugin.total_ratings', 2)
        );
    }

    public function test_guest_cannot_rate_plugin(): void
    {
        $plugin = Plugin::factory()->create();

        $response = $this->post(route('plugins.ratings.store', $plugin), [
            'rating' => 5,
        ]);

        $response->assertRedirect(route('login'));
    }
}
