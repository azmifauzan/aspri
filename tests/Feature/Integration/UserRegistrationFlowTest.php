<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_user_registration_flow(): void
    {
        // 1. User mengakses halaman register
        $response = $this->get(route('register'));
        $response->assertOk();

        // 2. User submit form registrasi dengan profile lengkap
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'call_preference' => 'Pak',
            'aspri_name' => 'Jarvis',
            'aspri_persona' => 'pria profesional',
        ];

        $response = $this->post(route('register.store'), $userData);

        // 3. User diredirect ke dashboard setelah registrasi
        $response->assertRedirect(route('dashboard'));

        // 4. User berhasil authenticated
        $this->assertAuthenticated();

        // 5. User data tersimpan di database
        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));

        // 6. User profile otomatis dibuat dengan data yang benar
        $this->assertNotNull($user->profile);
        $this->assertEquals('Pak', $user->profile->call_preference);
        $this->assertEquals('Jarvis', $user->profile->aspri_name);
        $this->assertEquals('pria profesional', $user->profile->aspri_persona);
        $this->assertEquals('Asia/Jakarta', $user->profile->timezone); // Default
        $this->assertEquals('id', $user->profile->locale); // Default
        $this->assertEquals('light', $user->profile->theme); // Default

        // 7. User role default adalah 'user'
        $this->assertEquals('user', $user->role);

        // 8. User active by default
        $this->assertTrue($user->is_active);
    }

    public function test_registration_validation_requires_profile_fields(): void
    {
        // Test registrasi tanpa profile fields harus gagal
        $response = $this->post(route('register.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // Missing: call_preference, aspri_name, aspri_persona
        ]);

        $response->assertSessionHasErrors([
            'call_preference',
            'aspri_name',
            'aspri_persona',
        ]);

        // User tidak terbuat di database
        $this->assertDatabaseMissing('users', [
            'email' => 'jane@example.com',
        ]);
    }

    public function test_registration_validates_email_uniqueness(): void
    {
        // Buat user pertama
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        // Coba registrasi dengan email yang sama
        $response = $this->post(route('register.store'), [
            'name' => 'Duplicate User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_registration_validates_password_confirmation(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_user_can_access_dashboard_after_registration(): void
    {
        // Register user
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $user->markEmailAsVerified();
        $this->actingAs($user);

        // User bisa akses dashboard
        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_registered_user_has_default_subscription_status(): void
    {
        // Register user
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'pria',
        ]);

        $user = User::where('email', 'test@example.com')->first();

        // Check subscription status
        $this->assertNull($user->subscription);
        // User bisa menggunakan fitur basic
        $this->assertTrue($user->is_active);
    }
}
