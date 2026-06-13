<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ChatOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatOrchestratorToolUseTest extends TestCase
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

    /**
     * Register a mock AiProviderInterface in the container and resolve a fresh
     * ChatOrchestrator that uses it.
     */
    private function orchestratorWithProvider(AiProviderInterface $provider): ChatOrchestrator
    {
        $this->app->instance(AiProviderInterface::class, $provider);

        return $this->app->make(ChatOrchestrator::class);
    }

    public function test_view_balance_uses_response_template_with_single_llm_call(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        // Exactly one chat call: returns the view_balance function call.
        $provider->shouldReceive('chat')
            ->once()
            ->andReturn([
                'function_name' => 'view_balance',
                'arguments' => ['period' => 'this_month'],
            ]);
        $provider->shouldNotReceive('chatStream');

        $orchestrator = $this->orchestratorWithProvider($provider);

        $result = $orchestrator->processMessage($this->user, 'berapa saldo saya bulan ini?', $this->thread, []);

        $this->assertStringContainsString('Rp', $result['response']);
        $this->assertFalse($result['action_taken']);
        $this->assertNull($result['pending_action']);
    }

    public function test_create_transaction_creates_pending_action_with_no_second_llm_call(): void
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

        $orchestrator = $this->orchestratorWithProvider($provider);

        $result = $orchestrator->processMessage($this->user, 'catat pengeluaran 50 ribu untuk makan', $this->thread, []);

        // Confirmation message contains the nominal and the "ya" keyword.
        $this->assertStringContainsString('50.000', $result['response']);
        $this->assertStringContainsString('ya', strtolower($result['response']));
        $this->assertNotNull($result['pending_action']);
        $this->assertFalse($result['action_taken']);

        $this->assertDatabaseHas('pending_actions', [
            'user_id' => $this->user->id,
            'thread_id' => $this->thread->id,
            'action_type' => 'create_transaction',
            'module' => 'finance',
            'status' => 'pending',
        ]);
    }

    public function test_confirmation_keyword_executes_pending_action_with_zero_llm_calls(): void
    {
        // Existing pending action awaiting confirmation.
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

        $orchestrator = $this->orchestratorWithProvider($provider);

        $result = $orchestrator->processMessage($this->user, 'ya', $this->thread, []);

        $this->assertTrue($result['action_taken']);
        $this->assertNull($result['pending_action']);
        $this->assertNotEmpty($result['response']);

        // Transaction persisted.
        $this->assertDatabaseHas('finance_transactions', [
            'user_id' => $this->user->id,
            'amount' => 50000,
        ]);

        // Pending action consumed.
        $this->assertDatabaseMissing('pending_actions', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    public function test_cancellation_keyword_cancels_pending_action_with_zero_llm_calls(): void
    {
        PendingAction::create([
            'user_id' => $this->user->id,
            'thread_id' => $this->thread->id,
            'action_type' => 'create_transaction',
            'module' => 'finance',
            'payload' => ['tx_type' => 'expense', 'amount' => 50000],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldNotReceive('chat');
        $provider->shouldNotReceive('chatStream');

        $orchestrator = $this->orchestratorWithProvider($provider);

        $result = $orchestrator->processMessage($this->user, 'batal', $this->thread, []);

        $this->assertFalse($result['action_taken']);
        $this->assertNull($result['pending_action']);
        $this->assertNotEmpty($result['response']);

        $this->assertDatabaseMissing('finance_transactions', [
            'user_id' => $this->user->id,
        ]);
    }

    public function test_greeting_returns_plain_text_response_from_llm(): void
    {
        $provider = Mockery::mock(AiProviderInterface::class);
        $provider->shouldReceive('chat')
            ->once()
            ->andReturn('Halo Kak Budi! Ada yang bisa aku bantu hari ini?');

        $orchestrator = $this->orchestratorWithProvider($provider);

        $result = $orchestrator->processMessage($this->user, 'halo', $this->thread, []);

        $this->assertSame('Halo Kak Budi! Ada yang bisa aku bantu hari ini?', $result['response']);
        $this->assertFalse($result['action_taken']);
        $this->assertNull($result['pending_action']);
    }
}
