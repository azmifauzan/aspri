<?php

namespace Tests\Feature;

use App\Jobs\ExtractConversationMemories;
use App\Models\User;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ChatOrchestrator;
use App\Services\Ai\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatStreamMemoryDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_dispatches_extraction_once_for_streamed_general_chat(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chatStream')->once()->andReturnUsing(function ($messages, $callback) {
            $callback('Halo!');

            return 'Halo!';
        });

        $orchestrator = Mockery::mock(ChatOrchestrator::class);
        $orchestrator->shouldReceive('buildMemoryContext')->andReturn('');
        $orchestrator->shouldReceive('parseIntent')->andReturn([
            'action' => 'query',
            'module' => 'general',
            'entities' => [],
            'confidence' => 0.9,
            'requires_confirmation' => false,
        ]);
        $orchestrator->shouldReceive('getChatService')->andReturn(app(ChatService::class));
        $orchestrator->shouldReceive('getAiProvider')->andReturn($provider);

        $this->app->instance(ChatOrchestrator::class, $orchestrator);

        $response = $this->actingAs($user)->post('/chat/message/stream', [
            'message' => 'Halo apa kabar?',
        ]);

        $response->assertOk();
        $response->streamedContent();

        Queue::assertPushed(ExtractConversationMemories::class, 1);
    }

    public function test_stream_does_not_dispatch_extraction_when_processing_via_orchestrator(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $orchestrator = Mockery::mock(ChatOrchestrator::class);
        $orchestrator->shouldReceive('buildMemoryContext')->andReturn('');
        $orchestrator->shouldReceive('parseIntent')->andReturn([
            'action' => 'create',
            'module' => 'finance',
            'entities' => [],
            'confidence' => 0.9,
            'requires_confirmation' => true,
        ]);
        // processMessage dispatches extraction itself in production; the
        // controller must not dispatch a second job for this branch.
        $orchestrator->shouldReceive('processMessage')->once()->andReturn([
            'response' => 'Transaksi dicatat.',
        ]);

        $this->app->instance(ChatOrchestrator::class, $orchestrator);

        $response = $this->actingAs($user)->post('/chat/message/stream', [
            'message' => 'catat pengeluaran 50 ribu',
        ]);

        $response->assertOk();
        $response->streamedContent();

        Queue::assertNotPushed(ExtractConversationMemories::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
