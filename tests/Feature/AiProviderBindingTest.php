<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Admin\SettingsService;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ConversationMemoryService;
use App\Services\Ai\ResilientAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_provider_interface_resolves_to_resilient_provider(): void
    {
        $provider = app(AiProviderInterface::class);

        $this->assertInstanceOf(ResilientAiProvider::class, $provider);
    }

    public function test_ai_provider_fast_resolves_to_resilient_provider(): void
    {
        $provider = app('ai.provider.fast');

        $this->assertInstanceOf(ResilientAiProvider::class, $provider);
    }

    public function test_ai_provider_fast_uses_fast_model_from_settings(): void
    {
        $settingsService = app(SettingsService::class);
        $settingsService->set('ai_provider', 'openai', ['group' => 'ai']);
        $settingsService->set('openai_model', 'gpt-4-turbo', ['group' => 'ai']);
        $settingsService->set('openai_fast_model', 'gpt-4o-mini', ['group' => 'ai']);
        $settingsService->set('openai_fast_fallback_model', 'gpt-3.5-turbo', ['group' => 'ai']);

        $this->app->forgetInstance('ai.provider.fast');
        $this->app->forgetInstance('ai.provider.base');

        $recordingProvider = new RecordingAiProvider;
        $this->app->extend('ai.provider.base', fn () => $recordingProvider);

        $provider = app('ai.provider.fast');

        $this->assertInstanceOf(ResilientAiProvider::class, $provider);

        $provider->chat([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertSame('gpt-4o-mini', $recordingProvider->lastOptions['model'] ?? null);
    }

    public function test_conversation_memory_service_uses_fast_provider(): void
    {
        $settingsService = app(SettingsService::class);
        $settingsService->set('ai_provider', 'openai', ['group' => 'ai']);
        $settingsService->set('openai_model', 'gpt-4-turbo', ['group' => 'ai']);
        $settingsService->set('openai_fast_model', 'gpt-4o-mini', ['group' => 'ai']);

        $this->app->forgetInstance('ai.provider.fast');
        $this->app->forgetInstance('ai.provider.base');
        $this->app->forgetInstance(ConversationMemoryService::class);

        $recordingProvider = new RecordingAiProvider;
        $this->app->extend('ai.provider.base', fn () => $recordingProvider);

        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);
        ChatMessage::factory()->fromUser()->create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'content' => 'Saya suka kopi tanpa gula.',
        ]);

        $service = app(ConversationMemoryService::class);
        $service->extractMemoriesFromThread($thread, $user);

        $this->assertSame('gpt-4o-mini', $recordingProvider->lastOptions['model'] ?? null);
    }
}

/**
 * Minimal AiProviderInterface fake that records the options it was called
 * with and returns an empty JSON array so callers that parse the response
 * (e.g. ConversationMemoryService) don't choke on the result.
 */
class RecordingAiProvider implements AiProviderInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $lastOptions = [];

    public function chat(array $messages, array $options = []): string|array
    {
        $this->lastOptions = $options;

        return '[]';
    }

    public function chatStream(array $messages, callable $callback, array $options = []): string
    {
        $this->lastOptions = $options;

        return '[]';
    }
}
