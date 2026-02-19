<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('telegram.index'));

        $response->assertOk();
    }

    public function test_telegram_settings_page_shows_linked_status_when_connected(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 123456789,
            'telegram_username' => 'testuser',
        ]);

        $response = $this->actingAs($user)->get(route('telegram.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Telegram')
            ->where('isLinked', true)
            ->where('telegramUsername', 'testuser')
        );
    }

    public function test_telegram_settings_page_shows_unlinked_status_when_not_connected(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => null,
            'telegram_username' => null,
        ]);

        $response = $this->actingAs($user)->get(route('telegram.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Telegram')
            ->where('isLinked', false)
        );
    }

    public function test_telegram_can_be_disconnected(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 123456789,
            'telegram_username' => 'testuser',
            'telegram_link_code' => 'SOMECODE',
            'telegram_link_expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($user)->delete(route('telegram.disconnect'));

        $response->assertRedirect(route('telegram.index'));

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_username);
        $this->assertNull($user->telegram_link_code);
        $this->assertNull($user->telegram_link_expires_at);
    }

    public function test_disconnect_redirects_with_error_when_not_connected(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => null,
            'telegram_username' => null,
        ]);

        $response = $this->actingAs($user)->delete(route('telegram.disconnect'));

        $response->assertRedirect(route('telegram.index'));
        $response->assertSessionHas('error');
    }

    public function test_unauthenticated_user_cannot_access_telegram_settings(): void
    {
        $response = $this->get(route('telegram.index'));

        $response->assertRedirect('/login');
    }

    public function test_unauthenticated_user_cannot_disconnect_telegram(): void
    {
        $response = $this->delete(route('telegram.disconnect'));

        $response->assertRedirect('/login');
    }

    public function test_link_code_is_generated_when_not_connected(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => null,
            'telegram_link_code' => null,
        ]);

        $this->actingAs($user)->get(route('telegram.index'));

        $user->refresh();
        $this->assertNotNull($user->telegram_link_code);
        $this->assertNotNull($user->telegram_link_expires_at);
        $this->assertTrue($user->telegram_link_expires_at->isFuture());
    }
}
