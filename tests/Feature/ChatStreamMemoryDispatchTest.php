<?php

namespace Tests\Feature;

use App\Jobs\ExtractConversationMemories;
use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatStreamMemoryDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_stream_dispatches_extraction_once_for_agent_loop(): void
    {
        Queue::fake();

        $user = User::factory()->create(['name' => 'Budi']);
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        // The real orchestrator runs the agent loop, which dispatches memory
        // extraction. Only the AI provider is mocked.
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn('Halo!');
        $provider->shouldNotReceive('chatStream');
        $this->app->instance(AiProviderInterface::class, $provider);

        $response = $this->actingAs($user)->post('/chat/message/stream', [
            'message' => 'Halo apa kabar?',
        ]);

        $response->assertOk();
        $response->streamedContent();

        Queue::assertPushed(ExtractConversationMemories::class, 1);
    }

    public function test_stream_does_not_dispatch_extraction_for_confirmation(): void
    {
        Queue::fake();

        $user = User::factory()->create(['name' => 'Budi']);
        $user->profile()->create([
            'call_preference' => 'Kak',
            'aspri_name' => 'ASPRI',
            'aspri_persona' => 'asisten yang ramah',
        ]);

        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        PendingAction::create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
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

        // Confirmation is resolved by keyword with ZERO LLM calls and does NOT
        // dispatch memory extraction.
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chat');
        $provider->shouldNotReceive('chatStream');
        $this->app->instance(AiProviderInterface::class, $provider);

        $response = $this->actingAs($user)->post('/chat/message/stream', [
            'message' => 'ya',
            'thread_id' => $thread->id,
        ]);

        $response->assertOk();
        $response->streamedContent();

        Queue::assertNotPushed(ExtractConversationMemories::class);
    }
}
