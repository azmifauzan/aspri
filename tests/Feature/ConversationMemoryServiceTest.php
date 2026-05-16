<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\ConversationMemory;
use App\Models\User;
use App\Services\Admin\SettingsService;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ConversationMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConversationMemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeService(AiProviderInterface $provider, ?SettingsService $settings = null): ConversationMemoryService
    {
        return new ConversationMemoryService(
            $provider,
            $settings ?? app(SettingsService::class),
        );
    }

    public function test_extract_memories_creates_records_for_each_returned_item(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'content' => 'Aku suka kopi tanpa gula.',
        ]);
        ChatMessage::factory()->fromAssistant()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'content' => 'Baik, kak.',
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn(json_encode([
            [
                'type' => 'preference',
                'content' => 'User suka kopi tanpa gula',
                'importance' => 4,
                'metadata' => ['module' => 'general', 'tags' => ['food']],
            ],
            [
                'type' => 'fact',
                'content' => 'User minum kopi setiap pagi',
                'importance' => 3,
                'metadata' => [],
            ],
        ]));

        $service = $this->makeService($provider);
        $service->extractMemoriesFromThread($thread, $user);

        $this->assertDatabaseCount('conversation_memories', 2);
        $this->assertDatabaseHas('conversation_memories', [
            'user_id' => $user->id,
            'memory_type' => 'preference',
            'content' => 'User suka kopi tanpa gula',
            'importance' => 4,
        ]);
    }

    public function test_extract_skips_when_thread_has_no_messages(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chat');

        $this->makeService($provider)->extractMemoriesFromThread($thread, $user);

        $this->assertDatabaseCount('conversation_memories', 0);
    }

    public function test_extract_deduplicates_existing_content(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        ConversationMemory::factory()->create([
            'user_id' => $user->id,
            'content' => 'User suka kopi tanpa gula',
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn(json_encode([
            ['type' => 'preference', 'content' => 'User suka kopi tanpa gula', 'importance' => 4],
            ['type' => 'fact', 'content' => 'Brand new fact', 'importance' => 3],
        ]));

        $this->makeService($provider)->extractMemoriesFromThread($thread, $user);

        $this->assertDatabaseCount('conversation_memories', 2);
        $this->assertEquals(
            1,
            ConversationMemory::where('user_id', $user->id)
                ->where('content', 'User suka kopi tanpa gula')
                ->count()
        );
    }

    public function test_extract_handles_markdown_wrapped_json(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $jsonInMarkdown = "```json\n".json_encode([
            ['type' => 'fact', 'content' => 'Fakta dari markdown', 'importance' => 3],
        ])."\n```";

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn($jsonInMarkdown);

        $this->makeService($provider)->extractMemoriesFromThread($thread, $user);

        $this->assertDatabaseHas('conversation_memories', [
            'content' => 'Fakta dari markdown',
        ]);
    }

    public function test_extract_skips_items_without_content(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn(json_encode([
            ['type' => 'fact', 'importance' => 3],
            ['type' => 'fact', 'content' => 'Real content', 'importance' => 3],
        ]));

        $this->makeService($provider)->extractMemoriesFromThread($thread, $user);

        $this->assertDatabaseCount('conversation_memories', 1);
        $this->assertDatabaseHas('conversation_memories', ['content' => 'Real content']);
    }

    public function test_extract_logs_warning_on_invalid_json(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn('not json at all');

        $this->makeService($provider)->extractMemoriesFromThread($thread, $user);

        $this->assertDatabaseCount('conversation_memories', 0);
    }

    public function test_extract_swallows_provider_exception(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andThrow(new \RuntimeException('AI down'));

        $this->makeService($provider)->extractMemoriesFromThread($thread, $user);

        $this->assertDatabaseCount('conversation_memories', 0);
    }

    public function test_build_memory_context_returns_active_sorted_by_importance(): void
    {
        $user = User::factory()->create();

        ConversationMemory::factory()->create([
            'user_id' => $user->id,
            'content' => 'Low importance',
            'importance' => 1,
            'is_active' => true,
        ]);
        ConversationMemory::factory()->create([
            'user_id' => $user->id,
            'content' => 'High importance',
            'importance' => 5,
            'is_active' => true,
        ]);
        ConversationMemory::factory()->create([
            'user_id' => $user->id,
            'content' => 'Inactive should not appear',
            'importance' => 5,
            'is_active' => false,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $context = $this->makeService($provider)->buildMemoryContext($user, 1000);

        $this->assertStringContainsString('High importance', $context);
        $this->assertStringContainsString('Low importance', $context);
        $this->assertStringNotContainsString('Inactive', $context);
        $this->assertLessThan(strpos($context, 'Low importance'), strpos($context, 'High importance'));
    }

    public function test_build_memory_context_respects_token_budget(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            ConversationMemory::factory()->create([
                'user_id' => $user->id,
                'content' => str_repeat('x', 150),
                'importance' => 5 - $i,
            ]);
        }

        $provider = Mockery::mock(AiProviderInterface::class);
        $service = $this->makeService($provider);

        $context = $service->buildMemoryContext($user, 60); // ~60 tokens budget

        $this->assertNotEmpty($context);
        $this->assertLessThanOrEqual(60, $service->estimateTokenCount($context.''));
    }

    public function test_build_memory_context_records_access(): void
    {
        $user = User::factory()->create();
        $memory = ConversationMemory::factory()->create([
            'user_id' => $user->id,
            'content' => 'Test memory',
            'access_count' => 0,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $this->makeService($provider)->buildMemoryContext($user, 1000);

        $memory->refresh();
        $this->assertSame(1, $memory->access_count);
        $this->assertNotNull($memory->last_accessed_at);
    }

    public function test_should_compact_returns_true_when_item_count_exceeds_limit(): void
    {
        $user = User::factory()->create();
        ConversationMemory::factory()->count(51)->create([
            'user_id' => $user->id,
            'is_active' => true,
            'content' => 'short',
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $this->assertTrue($this->makeService($provider)->shouldCompact($user, 32000));
    }

    public function test_should_compact_returns_true_when_tokens_exceed_threshold(): void
    {
        $user = User::factory()->create();
        // contextLength 1000, threshold = 150 tokens. Each ~50 tokens, 5 items = 250.
        for ($i = 0; $i < 5; $i++) {
            ConversationMemory::factory()->create([
                'user_id' => $user->id,
                'is_active' => true,
                'content' => str_repeat('x', 150),
            ]);
        }

        $provider = Mockery::mock(AiProviderInterface::class);
        $this->assertTrue($this->makeService($provider)->shouldCompact($user, 1000));
    }

    public function test_should_compact_returns_false_when_under_threshold(): void
    {
        $user = User::factory()->create();
        ConversationMemory::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'content' => 'tiny',
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $this->assertFalse($this->makeService($provider)->shouldCompact($user, 32000));
    }

    public function test_compact_skips_when_low_importance_memories_under_ten(): void
    {
        $user = User::factory()->create();
        ConversationMemory::factory()->count(5)->create([
            'user_id' => $user->id,
            'importance' => 2,
            'is_active' => true,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chat');

        $this->makeService($provider)->compact($user);

        $this->assertSame(5, ConversationMemory::where('user_id', $user->id)->active()->count());
    }

    public function test_compact_deactivates_old_and_inserts_summary(): void
    {
        $user = User::factory()->create();
        $old = ConversationMemory::factory()->count(12)->create([
            'user_id' => $user->id,
            'importance' => 2,
            'is_active' => true,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn(json_encode([
            ['type' => 'summary', 'content' => 'Compacted summary 1', 'importance' => 3],
            ['type' => 'summary', 'content' => 'Compacted summary 2', 'importance' => 3],
        ]));

        $this->makeService($provider)->compact($user);

        foreach ($old as $m) {
            $this->assertDatabaseHas('conversation_memories', [
                'id' => $m->id,
                'is_active' => false,
            ]);
        }
        $this->assertDatabaseHas('conversation_memories', [
            'user_id' => $user->id,
            'memory_type' => 'summary',
            'content' => 'Compacted summary 1',
            'is_active' => true,
        ]);
    }

    public function test_compact_does_not_touch_high_importance_memories(): void
    {
        $user = User::factory()->create();
        ConversationMemory::factory()->count(12)->create([
            'user_id' => $user->id,
            'importance' => 2,
            'is_active' => true,
        ]);
        $critical = ConversationMemory::factory()->create([
            'user_id' => $user->id,
            'importance' => 5,
            'is_active' => true,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn(json_encode([
            ['type' => 'summary', 'content' => 'Summary', 'importance' => 3],
        ]));

        $this->makeService($provider)->compact($user);

        $this->assertDatabaseHas('conversation_memories', [
            'id' => $critical->id,
            'is_active' => true,
        ]);
    }

    public function test_compact_handles_invalid_json_safely(): void
    {
        $user = User::factory()->create();
        ConversationMemory::factory()->count(12)->create([
            'user_id' => $user->id,
            'importance' => 2,
            'is_active' => true,
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')->once()->andReturn('garbage');

        $this->makeService($provider)->compact($user);

        // Originals untouched
        $this->assertSame(12, ConversationMemory::where('user_id', $user->id)->active()->count());
    }

    public function test_estimate_token_count_uses_three_chars_per_token(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        $service = $this->makeService($provider);

        $this->assertSame(1, $service->estimateTokenCount('abc'));
        $this->assertSame(2, $service->estimateTokenCount('abcabc'));
        $this->assertSame(4, $service->estimateTokenCount(str_repeat('a', 10)));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
