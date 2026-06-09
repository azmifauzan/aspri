<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialiteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_google()
    {
        $response = $this->get('/auth/google');
        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->getTargetUrl());
    }

    public function test_callback_creates_new_user_and_redirects_to_profile_setup()
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('123456789')
            ->shouldReceive('getName')->andReturn('John Doe')
            ->shouldReceive('getEmail')->andReturn('john@example.com')
            ->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        $abstractUser->id = '123456789';
        $abstractUser->name = 'John Doe';
        $abstractUser->email = 'john@example.com';
        $abstractUser->avatar = 'https://example.com/avatar.jpg';

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'google_id' => '123456789',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('profile.edit'));
    }

    public function test_callback_logs_in_existing_google_user()
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'google_id' => '987654321',
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getId')->andReturn('987654321')
            ->shouldReceive('getName')->andReturn($user->name)
            ->shouldReceive('getEmail')->andReturn($user->email)
            ->shouldReceive('getAvatar')->andReturn('https://example.com/new-avatar.jpg');

        $abstractUser->id = '987654321';
        $abstractUser->name = $user->name;
        $abstractUser->email = $user->email;
        $abstractUser->avatar = 'https://example.com/new-avatar.jpg';

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');

        // Avatar should be updated
        $this->assertEquals('https://example.com/new-avatar.jpg', $user->fresh()->google_avatar);
    }

    public function test_callback_auto_links_user_with_same_email()
    {
        $user = User::factory()->create([
            'email' => 'linkme@example.com',
            'google_id' => null,
            'password' => Hash::make('password'),
        ]);

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->id = 'linked123';
        $abstractUser->name = $user->name;
        $abstractUser->email = $user->email;
        $abstractUser->avatar = 'https://example.com/avatar.jpg';

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/dashboard');

        $this->assertEquals('linked123', $user->fresh()->google_id);
    }
}
