<?php

namespace Tests\Feature;

use App\Jobs\ExtractConversationMemories;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Ai\ConversationMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class ExtractConversationMemoriesJobTest extends TestCase
{
    use MockeryPHPUnitIntegration, RefreshDatabase;

    public function test_handle_invokes_service_with_thread_user(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldReceive('extractMemoriesFromThread')
            ->once()
            ->with(Mockery::on(fn ($t) => $t->id === $thread->id), Mockery::on(fn ($u) => $u->id === $user->id));

        (new ExtractConversationMemories($thread))->handle($service);
    }

    public function test_handle_skips_when_dispatched_at_is_stale(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        Cache::put("memory_extraction_last_dispatch_{$thread->id}", '2026-05-15 12:00:00');

        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldNotReceive('extractMemoriesFromThread');

        (new ExtractConversationMemories($thread, '2026-05-15 11:00:00'))->handle($service);
    }

    public function test_handle_proceeds_when_dispatched_at_matches_cache(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $dispatchedAt = '2026-05-15 12:00:00';
        Cache::put("memory_extraction_last_dispatch_{$thread->id}", $dispatchedAt);

        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldReceive('extractMemoriesFromThread')->once();

        (new ExtractConversationMemories($thread, $dispatchedAt))->handle($service);
    }

    public function test_handle_proceeds_when_no_dispatched_at_provided(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldReceive('extractMemoriesFromThread')->once();

        (new ExtractConversationMemories($thread))->handle($service);
    }

    public function test_handle_skips_when_thread_user_is_missing(): void
    {
        // Build an unsaved thread with no related user so $thread->user is null.
        $thread = new ChatThread(['id' => '00000000-0000-0000-0000-000000000000']);
        $this->assertNull($thread->user);

        $service = Mockery::mock(ConversationMemoryService::class);
        $service->shouldNotReceive('extractMemoriesFromThread');

        (new ExtractConversationMemories($thread))->handle($service);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
