<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Ai\ChatService;
use App\Services\Ai\ClaudeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeProviderCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Capture the outgoing request payload for a single Anthropic call.
     *
     * @return array<string, mixed>
     */
    private function capturePayload(callable $invoke): array
    {
        Http::fake([
            '*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
            ], 200),
        ]);

        $invoke();

        $captured = [];
        Http::assertSent(function ($request) use (&$captured) {
            $captured = $request->data();

            return true;
        });

        return $captured;
    }

    public function test_system_block_has_ephemeral_cache_control(): void
    {
        $systemText = 'You are a helpful assistant.';

        $messages = [
            ['role' => 'system', 'content' => $systemText],
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $payload = $this->capturePayload(function () use ($messages) {
            (new ClaudeProvider('test-key', 'claude-3-5-sonnet-20241022'))->chat($messages);
        });

        $this->assertIsArray($payload['system']);
        $last = $payload['system'][count($payload['system']) - 1];
        $this->assertSame('text', $last['type']);
        $this->assertSame($systemText, $last['text']);
        $this->assertSame(['type' => 'ephemeral'], $last['cache_control']);
    }

    public function test_last_tool_definition_has_ephemeral_cache_control(): void
    {
        $functions = [
            [
                'name' => 'view_balance',
                'description' => 'View balance summary.',
                'parameters' => [
                    'properties' => [
                        'period' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_transaction',
                'description' => 'Record a new financial transaction.',
                'parameters' => [
                    'properties' => [
                        'amount' => ['type' => 'number'],
                    ],
                    'required' => ['amount'],
                ],
            ],
        ];

        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $payload = $this->capturePayload(function () use ($messages, $functions) {
            (new ClaudeProvider('test-key', 'claude-3-5-sonnet-20241022'))->chat($messages, ['functions' => $functions]);
        });

        $tools = $payload['tools'];
        $this->assertCount(2, $tools);

        // First tool keeps original shape and has no cache_control.
        $this->assertSame('view_balance', $tools[0]['name']);
        $this->assertSame('View balance summary.', $tools[0]['description']);
        $this->assertArrayHasKey('input_schema', $tools[0]);
        $this->assertArrayNotHasKey('cache_control', $tools[0]);

        // Last tool keeps original shape and is marked ephemeral.
        $this->assertSame('create_transaction', $tools[1]['name']);
        $this->assertSame('Record a new financial transaction.', $tools[1]['description']);
        $this->assertArrayHasKey('input_schema', $tools[1]);
        $this->assertSame(['type' => 'ephemeral'], $tools[1]['cache_control']);
    }

    public function test_system_prompt_is_byte_stable(): void
    {
        $user = User::factory()->create();

        $chatService = app(ChatService::class);

        $first = $chatService->buildSystemPrompt($user, 'some memory');
        $second = $chatService->buildSystemPrompt($user, 'some memory');

        // Two consecutive calls must be byte-identical (no now()-based diff).
        $this->assertSame($first, $second);

        // No time-of-day pattern (HH:MM) should leak into the cached prefix.
        $this->assertDoesNotMatchRegularExpression('/\d{2}:\d{2}/', $first);
    }
}
