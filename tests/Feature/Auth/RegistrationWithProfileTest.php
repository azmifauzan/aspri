<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationWithProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_register_with_default_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->profile);
        $this->assertEquals('Kak', $user->profile->call_preference);
        $this->assertEquals('ASPRI', $user->profile->aspri_name);
        $this->assertEquals('pria', $user->profile->aspri_persona);
        $this->assertEquals('Asia/Jakarta', $user->profile->timezone);
        $this->assertEquals('id', $user->profile->locale);
    }

    public function test_users_can_register_with_custom_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'call_preference' => 'Bapak',
            'aspri_name' => 'Jarvis',
            'aspri_persona' => 'profesional dan formal',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->profile);
        $this->assertEquals('Bapak', $user->profile->call_preference);
        $this->assertEquals('Jarvis', $user->profile->aspri_name);
        $this->assertEquals('profesional dan formal', $user->profile->aspri_persona);
    }

    public function test_profile_fields_are_required(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'call_preference',
            'aspri_name',
            'aspri_persona',
        ]);
    }

    public function test_profile_is_deleted_when_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $profileId = $user->profile->id;

        $user->delete();

        $this->assertDatabaseMissing('profiles', ['id' => $profileId]);
    }
}
