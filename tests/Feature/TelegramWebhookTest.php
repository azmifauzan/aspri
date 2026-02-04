<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_unauthorized_requests(): void
    {
        config(['services.telegram.webhook_secret' => 'test-secret']);
        config(['services.telegram.bot_token' => 'test-token']);

        // Mock TelegramBotService
        $this->mock(TelegramBotService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processUpdate')->andReturnNull();
        });

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 123,
            'message' => [
                'chat' => ['id' => 123456],
                'text' => 'Hello',
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_authorized_requests(): void
    {
        config(['services.telegram.webhook_secret' => 'test-secret']);
        config(['services.telegram.bot_token' => 'test-token']);

        // Mock TelegramBotService
        $this->mock(TelegramBotService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processUpdate')->once()->andReturnNull();
        });

        $response = $this->postJson(
            '/api/telegram/webhook',
            [
                'update_id' => 123,
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => 123456, 'type' => 'private'],
                    'from' => [
                        'id' => 123456,
                        'first_name' => 'Test',
                        'username' => 'testuser',
                    ],
                    'text' => '/start',
                    'date' => time(),
                ],
            ],
            ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret']
        );

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_link_command_with_valid_code(): void
    {
        config(['services.telegram.webhook_secret' => 'test-secret']);
        config(['services.telegram.bot_token' => 'test-token']);

        // Mock TelegramBotService
        $this->mock(TelegramBotService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processUpdate')->once()->andReturnNull();
        });

        $user = User::factory()->create([
            'telegram_link_code' => 'ABC123',
            'telegram_link_expires_at' => now()->addHours(1),
        ]);

        // We can't fully test this because it requires mocking the Telegram API
        // But we can test that the webhook processes correctly
        $response = $this->postJson(
            '/api/telegram/webhook',
            [
                'update_id' => 124,
                'message' => [
                    'message_id' => 2,
                    'chat' => ['id' => 789012, 'type' => 'private'],
                    'from' => [
                        'id' => 789012,
                        'first_name' => 'Test',
                        'username' => 'testuser',
                    ],
                    'text' => '/link ABC123',
                    'date' => time(),
                ],
            ],
            ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret']
        );

        $response->assertStatus(200);
    }

    public function test_user_can_generate_telegram_link_code(): void
    {
        $user = User::factory()->create();

        $code = strtoupper(Str::random(6));

        $user->update([
            'telegram_link_code' => $code,
            'telegram_link_expires_at' => now()->addHours(24),
        ]);

        $this->assertEquals($code, $user->fresh()->telegram_link_code);
        $this->assertTrue($user->fresh()->telegram_link_expires_at->isFuture());
    }

    public function test_linked_telegram_user_can_be_found(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 123456789,
            'telegram_username' => 'testuser',
        ]);

        $foundUser = User::where('telegram_chat_id', 123456789)->first();

        $this->assertNotNull($foundUser);
        $this->assertEquals($user->id, $foundUser->id);
    }
}
