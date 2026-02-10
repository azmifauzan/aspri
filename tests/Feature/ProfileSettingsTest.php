<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_settings_page_requires_authentication(): void
    {
        $response = $this->get('/settings/profile');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_profile_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('settings/Profile'));
    }

    public function test_user_can_update_profile_with_persona_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'Test User',
            'email' => $user->email,
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah dan membantu',
        ]);

        $response->assertRedirect('/settings/profile');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Test User',
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah dan membantu',
        ]);
    }

    public function test_profile_update_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => '',
            'email' => '',
            'call_preference' => '',
            'aspri_name' => '',
            'aspri_persona' => '',
        ]);

        $response->assertSessionHasErrors([
            'name',
            'email',
            'call_preference',
            'aspri_name',
            'aspri_persona',
        ]);
    }

    public function test_existing_profile_can_be_updated(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Mas',
            'aspri_name' => 'Jarvis',
            'aspri_persona' => 'profesional',
        ]);

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'call_preference' => 'Bos',
            'aspri_name' => 'Friday',
            'aspri_persona' => 'teman yang santai',
        ]);

        $response->assertRedirect('/settings/profile');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'call_preference' => 'Bos',
            'aspri_name' => 'Friday',
            'aspri_persona' => 'teman yang santai',
        ]);

        // Ensure only one profile exists
        $this->assertEquals(1, $user->fresh()->profile()->count());
    }

    public function test_profile_settings_shows_existing_profile_data(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah dan membantu',
        ]);

        $response = $this->actingAs($user)->get('/settings/profile');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('settings/Profile')
                ->has('profile')
                ->where('profile.call_preference', 'Kak')
                ->where('profile.aspri_name', 'ASPRI')
        );
    }
}
