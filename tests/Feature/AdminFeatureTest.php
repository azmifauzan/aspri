<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/Dashboard'));
    }

    public function test_super_admin_can_access_admin_dashboard(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->get('/admin');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/Dashboard'));
    }

    public function test_admin_can_view_users_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(5)->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 6) // 5 users + admin
        );
    }

    public function test_admin_can_toggle_user_active_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->post("/admin/users/{$user->id}/toggle-active");

        $response->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_cannot_access_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/settings');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_settings(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->get('/admin/settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/settings/Index'));
    }

    public function test_admin_can_view_activity_logs(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/activity');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/activity/Index'));
    }

    public function test_admin_can_view_queue_monitor(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/queues');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('admin/queues/Index'));
    }

    public function test_inactive_admin_cannot_access_admin(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_test_ai_connection_without_api_key(): void
    {
        // Clear any .env values during test
        config(['services.openai.api_key' => null]);

        $superAdmin = User::factory()->superAdmin()->create();
        SystemSetting::setValue('ai_provider', 'gemini'); // Use gemini which has no .env fallback in test
        SystemSetting::where('key', 'gemini_api_key')->delete();
        \Illuminate\Support\Facades\Cache::forget('system_setting_gemini_api_key');

        $response = $this->actingAs($superAdmin)->post('/admin/settings/test-ai');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_super_admin_can_test_ai_connection_with_api_key(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['id' => 'gpt-4'],
                    ['id' => 'gpt-3.5-turbo'],
                ],
            ], 200),
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        SystemSetting::setValue('ai_provider', 'openai');
        SystemSetting::setValue('openai_api_key', 'test-key', ['encrypted' => true]);
        SystemSetting::setValue('openai_model', 'gpt-4');

        $response = $this->actingAs($superAdmin)->post('/admin/settings/test-ai');

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_super_admin_can_test_telegram_connection_without_token(): void
    {
        // This test verifies the error case, but if TELEGRAM_BOT_TOKEN exists in .env,
        // the fallback will succeed. We use Http::fake to simulate API error instead.
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => false,
                'description' => 'Invalid token',
            ], 401),
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        // Don't store token in database - will fallback to .env
        SystemSetting::where('key', 'telegram_bot_token')->delete();
        \Illuminate\Support\Facades\Cache::forget('system_setting_telegram_bot_token');

        $response = $this->actingAs($superAdmin)->post('/admin/settings/test-telegram');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_super_admin_can_test_telegram_connection_with_token(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456,
                    'is_bot' => true,
                    'first_name' => 'Test Bot',
                    'username' => 'test_bot',
                ],
            ], 200),
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        SystemSetting::setValue('telegram_bot_token', 'test-token', ['encrypted' => true]);

        $response = $this->actingAs($superAdmin)->post('/admin/settings/test-telegram');

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
