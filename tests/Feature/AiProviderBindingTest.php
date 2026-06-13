<?php

namespace Tests\Feature;

use App\Services\Admin\SettingsService;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ConversationMemoryService;
use App\Services\Ai\ResilientAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
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

        $provider = app('ai.provider.fast');

        $this->assertInstanceOf(ResilientAiProvider::class, $provider);

        $modelProperty = new ReflectionProperty($provider, 'model');
        $modelProperty->setAccessible(true);

        $this->assertSame('gpt-4o-mini', $modelProperty->getValue($provider));
    }

    public function test_conversation_memory_service_uses_fast_provider(): void
    {
        $service = app(ConversationMemoryService::class);

        $reflection = new ReflectionProperty($service, 'provider');
        $reflection->setAccessible(true);

        $this->assertInstanceOf(ResilientAiProvider::class, $reflection->getValue($service));
        $this->assertSame(app('ai.provider.fast'), $reflection->getValue($service));
    }
}
