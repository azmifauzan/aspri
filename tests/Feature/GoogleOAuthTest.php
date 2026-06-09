<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\Telegram\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

    protected function mockSocialiteUser(string $id, string $email, string $name = 'Test User', string $avatar = 'https://example.com/avatar.jpg'): void
    {
        $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialiteUser->id = $id;
        $socialiteUser->email = $email;
        $socialiteUser->name = $name;
        $socialiteUser->avatar = $avatar;

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_returns_redirect_to_google(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/oauth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google');

        $response->assertRedirect();
    }

    public function test_new_google_user_is_created_with_profile_and_subscription(): void
    {
        Notification::fake();

        $this->mockSocialiteUser('google-123', 'newuser@example.com', 'New User');

        $adminNotifier = Mockery::mock(AdminNotificationService::class);
        $adminNotifier->shouldReceive('notifyNewUserRegistration')->once();
        $this->app->instance(AdminNotificationService::class, $adminNotifier);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('profile.edit'));

        $user = User::where('email', 'newuser@example.com')->firstOrFail();

        // User created with correct fields
        $this->assertSame('google-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);

        // Profile record created
        $this->assertNotNull($user->profile);

        // Free trial subscription provisioned
        $this->assertNotNull($user->activeSubscription());

        // Welcome notification queued
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_existing_google_user_logs_in_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'google_id' => 'google-456',
            'google_avatar' => 'https://old-avatar.com/img.jpg',
        ]);

        $this->mockSocialiteUser('google-456', 'existing@example.com', 'Existing User', 'https://new-avatar.com/img.jpg');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Avatar updated
        $this->assertSame('https://new-avatar.com/img.jpg', $user->fresh()->google_avatar);
    }

    public function test_google_login_auto_links_existing_email_account(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@example.com',
            'google_id' => null,
        ]);

        $this->mockSocialiteUser('google-789', 'linked@example.com');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertSame('google-789', $user->google_id);
    }

    public function test_callback_redirects_to_login_on_socialite_exception(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \Exception('OAuth error'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_new_google_user_has_default_profile_with_aspri_name(): void
    {
        Notification::fake();

        $adminNotifier = Mockery::mock(AdminNotificationService::class);
        $adminNotifier->shouldReceive('notifyNewUserRegistration')->once();
        $this->app->instance(AdminNotificationService::class, $adminNotifier);

        $this->mockSocialiteUser('google-new-profile', 'profiletest@example.com', 'Profile Test');

        $this->get('/auth/google/callback');

        $user = User::where('email', 'profiletest@example.com')->firstOrFail();

        $this->assertSame('ASPRI', $user->profile->aspri_name);
        $this->assertNull($user->profile->call_preference);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
