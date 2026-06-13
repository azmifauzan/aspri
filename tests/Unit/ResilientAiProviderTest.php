<?php

namespace Tests\Unit;

use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ResilientAiProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Fake AI provider that records the resolved `$options['model']` for every
 * `chat`/`chatStream` call and can be configured to throw for specific models.
 */
class FakeAiProvider implements AiProviderInterface
{
    /** @var array<int, string|null> */
    public array $chatModelsSeen = [];

    /** @var array<int, string|null> */
    public array $chatStreamModelsSeen = [];

    /** @var array<int, string> */
    public array $failOnModels = [];

    /** @var array<int, string> */
    public array $chatStreamChunks = [];

    public function chat(array $messages, array $options = []): string|array
    {
        $model = $options['model'] ?? null;
        $this->chatModelsSeen[] = $model;

        if ($model !== null && in_array($model, $this->failOnModels, true)) {
            throw new RuntimeException("chat failed for model: {$model}");
        }

        return "response from {$model}";
    }

    public function chatStream(array $messages, callable $callback, array $options = []): string
    {
        $model = $options['model'] ?? null;
        $this->chatStreamModelsSeen[] = $model;

        if ($model !== null && in_array($model, $this->failOnModels, true)) {
            foreach ($this->chatStreamChunks as $chunk) {
                $callback($chunk);
            }

            throw new RuntimeException("chatStream failed for model: {$model}");
        }

        $callback("stream from {$model}");

        return "stream from {$model}";
    }
}

class ResilientAiProviderTest extends TestCase
{
    public function test_primary_model_override_is_applied(): void
    {
        $fake = new FakeAiProvider;
        $provider = new ResilientAiProvider($fake, model: 'primary-model');

        $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['primary-model'], $fake->chatModelsSeen);
    }

    public function test_without_override_uses_inner_default(): void
    {
        $fake = new FakeAiProvider;
        $provider = new ResilientAiProvider($fake);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);

        // No model override set, and the caller didn't pass one either,
        // so $options['model'] should not be set at all.
        $this->assertSame([null], $fake->chatModelsSeen);
    }

    public function test_retries_with_fallback_model_when_primary_fails(): void
    {
        $fake = new FakeAiProvider;
        $fake->failOnModels = ['primary-model'];
        $provider = new ResilientAiProvider($fake, model: 'primary-model', fallbackModel: 'fallback-model');

        $result = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('response from fallback-model', $result);
        $this->assertSame(['primary-model', 'fallback-model'], $fake->chatModelsSeen);
    }

    public function test_rethrows_when_no_fallback_configured(): void
    {
        $fake = new FakeAiProvider;
        $fake->failOnModels = ['primary-model'];
        $provider = new ResilientAiProvider($fake, model: 'primary-model');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('chat failed for model: primary-model');

        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_does_not_retry_when_fallback_equals_failed_model(): void
    {
        $fake = new FakeAiProvider;
        $fake->failOnModels = ['primary-model'];
        $provider = new ResilientAiProvider($fake, model: 'primary-model', fallbackModel: 'primary-model');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('chat failed for model: primary-model');

        $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['primary-model'], $fake->chatModelsSeen);
    }

    public function test_chat_stream_retries_when_no_chunk_emitted_yet(): void
    {
        $fake = new FakeAiProvider;
        $fake->failOnModels = ['primary-model'];
        $fake->chatStreamChunks = []; // no chunks emitted before failure
        $provider = new ResilientAiProvider($fake, model: 'primary-model', fallbackModel: 'fallback-model');

        $received = [];
        $result = $provider->chatStream([['role' => 'user', 'content' => 'hi']], function ($chunk) use (&$received) {
            $received[] = $chunk;
        });

        $this->assertSame('stream from fallback-model', $result);
        $this->assertSame(['primary-model', 'fallback-model'], $fake->chatStreamModelsSeen);
        $this->assertSame(['stream from fallback-model'], $received);
    }

    public function test_chat_stream_does_not_retry_after_chunk_emitted(): void
    {
        $fake = new FakeAiProvider;
        $fake->failOnModels = ['primary-model'];
        $fake->chatStreamChunks = ['partial chunk']; // chunk emitted before failure
        $provider = new ResilientAiProvider($fake, model: 'primary-model', fallbackModel: 'fallback-model');

        $received = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('chatStream failed for model: primary-model');

        try {
            $provider->chatStream([['role' => 'user', 'content' => 'hi']], function ($chunk) use (&$received) {
                $received[] = $chunk;
            });
        } finally {
            $this->assertSame(['primary-model'], $fake->chatStreamModelsSeen);
            $this->assertSame(['partial chunk'], $received);
        }
    }

    public function test_empty_string_model_and_fallback_are_treated_as_null(): void
    {
        $fake = new FakeAiProvider;
        $fake->failOnModels = ['some-model'];
        $provider = new ResilientAiProvider($fake, model: '', fallbackModel: '');

        // No override applied (model treated as null), caller passes its own model.
        $provider->chat([['role' => 'user', 'content' => 'hi']], ['model' => 'caller-model']);

        $this->assertSame(['caller-model'], $fake->chatModelsSeen);

        // Now verify empty fallback means no retry on failure.
        $fake->chatModelsSeen = [];
        $this->expectException(RuntimeException::class);
        $provider->chat([['role' => 'user', 'content' => 'hi']], ['model' => 'some-model']);
    }
}
