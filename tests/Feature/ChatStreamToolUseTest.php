<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatStreamToolUseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ChatThread $thread;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create(['name' => 'Budi']);
        $this->user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        $this->thread = ChatThread::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindProvider(AiProviderInterface $provider): void
    {
        $this->app->instance(AiProviderInterface::class, $provider);
    }

    public function test_confirmation_keyword_streams_without_llm_call(): void
    {
        PendingAction::create([
            'user_id' => $this->user->id,
            'thread_id' => $this->thread->id,
            'action_type' => 'create_transaction',
            'module' => 'finance',
            'payload' => [
                'tx_type' => 'expense',
                'amount' => 50000,
                'category' => 'Makanan',
            ],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chat');
        $provider->shouldNotReceive('chatStream');
        $this->bindProvider($provider);

        $response = $this->actingAs($this->user)->post('/chat/message/stream', [
            'message' => 'ya',
            'thread_id' => $this->thread->id,
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('message_chunk', $content);

        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $this->user->id,
            'amount' => 50000,
        ]);
    }

    public function test_action_message_streams_confirmation_with_single_llm_call(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')
            ->once()
            ->andReturn([
                'function_name' => 'create_transaction',
                'arguments' => [
                    'tx_type' => 'expense',
                    'amount' => 50000,
                    'category' => 'Makanan',
                ],
            ]);
        $provider->shouldNotReceive('chatStream');
        $this->bindProvider($provider);

        $response = $this->actingAs($this->user)->post('/chat/message/stream', [
            'message' => 'catat pengeluaran 50 ribu untuk makan',
            'thread_id' => $this->thread->id,
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('50.000', $content);

        $this->assertDatabaseHas('pending_actions', [
            'user_id' => $this->user->id,
            'thread_id' => $this->thread->id,
            'action_type' => 'create_transaction',
            'module' => 'finance',
            'status' => 'pending',
        ]);
    }

    public function test_greeting_streams_plain_text_response(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')
            ->once()
            ->andReturn('Halo Kak Budi! Ada yang bisa aku bantu hari ini?');
        $provider->shouldNotReceive('chatStream');
        $this->bindProvider($provider);

        $response = $this->actingAs($this->user)->post('/chat/message/stream', [
            'message' => 'halo',
            'thread_id' => $this->thread->id,
        ]);

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Halo Kak Budi', $content);
    }
}
