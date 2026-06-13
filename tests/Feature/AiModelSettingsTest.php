<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_save_fast_and_fallback_model_settings(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->post('/admin/settings/ai', [
            'ai_provider' => 'gemini',
            'gemini_model' => 'gemini-pro',
            'gemini_fast_model' => 'gemini-flash',
            'gemini_fallback_model' => 'gemini-pro-fallback',
            'gemini_fast_fallback_model' => 'gemini-flash-fallback',
            'openai_model' => 'gpt-4-turbo',
            'openai_fast_model' => 'gpt-4o-mini',
            'openai_fallback_model' => 'gpt-4o',
            'openai_fast_fallback_model' => 'gpt-3.5-turbo',
            'anthropic_model' => 'claude-3-sonnet',
            'anthropic_fast_model' => 'claude-3-haiku',
            'anthropic_fallback_model' => 'claude-3-opus',
            'anthropic_fast_fallback_model' => 'claude-3-haiku-fallback',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $settingsService = app(SettingsService::class);
        $aiSettings = $settingsService->getAiSettings();

        $this->assertSame('gemini-flash', $aiSettings['gemini_fast_model']);
        $this->assertSame('gemini-pro-fallback', $aiSettings['gemini_fallback_model']);
        $this->assertSame('gemini-flash-fallback', $aiSettings['gemini_fast_fallback_model']);

        $this->assertSame('gpt-4o-mini', $aiSettings['openai_fast_model']);
        $this->assertSame('gpt-4o', $aiSettings['openai_fallback_model']);
        $this->assertSame('gpt-3.5-turbo', $aiSettings['openai_fast_fallback_model']);

        $this->assertSame('claude-3-haiku', $aiSettings['anthropic_fast_model']);
        $this->assertSame('claude-3-opus', $aiSettings['anthropic_fallback_model']);
        $this->assertSame('claude-3-haiku-fallback', $aiSettings['anthropic_fast_fallback_model']);
    }

    public function test_active_ai_config_exposes_fast_and_fallback_models(): void
    {
        $settingsService = app(SettingsService::class);

        $settingsService->updateAiSettings([
            'ai_provider' => 'gemini',
            'gemini_fast_model' => 'gemini-flash',
            'gemini_fallback_model' => 'gemini-pro-fallback',
            'gemini_fast_fallback_model' => 'gemini-flash-fallback',
        ]);

        $config = $settingsService->getActiveAiConfig();

        $this->assertSame('gemini-flash', $config['fast_model']);
        $this->assertSame('gemini-pro-fallback', $config['fallback_model']);
        $this->assertSame('gemini-flash-fallback', $config['fast_fallback_model']);
    }

    public function test_fast_fallback_model_defaults_to_fallback_model_when_empty(): void
    {
        $settingsService = app(SettingsService::class);

        $settingsService->updateAiSettings([
            'ai_provider' => 'gemini',
            'gemini_fast_model' => 'gemini-flash',
            'gemini_fallback_model' => 'gemini-pro-fallback',
            'gemini_fast_fallback_model' => '',
        ]);

        $config = $settingsService->getActiveAiConfig();

        $this->assertSame('gemini-pro-fallback', $config['fallback_model']);
        $this->assertSame('gemini-pro-fallback', $config['fast_fallback_model']);
    }

    public function test_unset_model_settings_return_null(): void
    {
        $settingsService = app(SettingsService::class);

        $aiSettings = $settingsService->getAiSettings();

        $this->assertNull($aiSettings['gemini_fast_model']);
        $this->assertNull($aiSettings['gemini_fallback_model']);
        $this->assertNull($aiSettings['gemini_fast_fallback_model']);
        $this->assertNull($aiSettings['openai_fast_model']);
        $this->assertNull($aiSettings['openai_fallback_model']);
        $this->assertNull($aiSettings['openai_fast_fallback_model']);
        $this->assertNull($aiSettings['anthropic_fast_model']);
        $this->assertNull($aiSettings['anthropic_fallback_model']);
        $this->assertNull($aiSettings['anthropic_fast_fallback_model']);

        $config = $settingsService->getActiveAiConfig();

        $this->assertNull($config['fast_model']);
        $this->assertNull($config['fallback_model']);
        $this->assertNull($config['fast_fallback_model']);
    }
}
