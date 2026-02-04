<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Admin\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    /**
     * Display the settings page.
     */
    public function index(): Response
    {
        return Inertia::render('admin/settings/Index', [
            'aiSettings' => $this->settingsService->getAiSettings(),
            'telegramSettings' => $this->settingsService->getTelegramSettings(),
            'appSettings' => $this->settingsService->getAppSettings(),
            'subscriptionSettings' => $this->settingsService->getSubscriptionSettings(),
            'emailSettings' => $this->settingsService->getEmailSettings(),
        ]);
    }

    /**
     * Update AI provider settings.
     */
    public function updateAi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_provider' => ['required', 'in:gemini,openai,anthropic'],
            'gemini_api_key' => ['nullable', 'string'],
            'gemini_model' => ['nullable', 'string'],
            'openai_api_key' => ['nullable', 'string'],
            'openai_model' => ['nullable', 'string'],
            'anthropic_api_key' => ['nullable', 'string'],
            'anthropic_model' => ['nullable', 'string'],
        ]);

        $this->settingsService->updateAiSettings($validated);

        ActivityLog::log('update', 'Updated AI provider settings');

        return back()->with('success', 'AI settings updated successfully.');
    }

    /**
     * Update Telegram settings.
     */
    public function updateTelegram(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bot_token' => ['nullable', 'string'],
            'webhook_url' => ['nullable', 'url'],
            'bot_username' => ['nullable', 'string'],
            'admin_chat_ids' => ['nullable', 'string'],
        ]);

        $this->settingsService->updateTelegramSettings($validated);

        ActivityLog::log('update', 'Updated Telegram settings');

        return back()->with('success', 'Telegram settings updated successfully.');
    }

    /**
     * Update general app settings.
     */
    public function updateApp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_description' => ['nullable', 'string', 'max:500'],
            'app_locale' => ['required', 'string', 'in:id,en'],
            'app_timezone' => ['required', 'string', 'timezone'],
            'maintenance_mode' => ['boolean'],
        ]);

        $this->settingsService->updateAppSettings($validated);

        ActivityLog::log('update', 'Updated application settings');

        return back()->with('success', 'Application settings updated successfully.');
    }

    /**
     * Test AI connection.
     */
    public function testAi(Request $request): RedirectResponse
    {
        $config = $this->settingsService->getActiveAiConfig();
        $provider = $config['provider'];
        $apiKey = $config['api_key'];
        $model = $config['model'];

        if (! $apiKey) {
            return back()->with('error', "No API key configured for {$provider}. Please save your API key first.");
        }

        try {
            $result = match ($provider) {
                'openai' => $this->testOpenAi($apiKey, $model),
                'gemini' => $this->testGemini($apiKey, $model),
                'anthropic' => $this->testAnthropic($apiKey, $model),
                default => ['success' => false, 'message' => 'Unknown provider'],
            };

            if ($result['success']) {
                ActivityLog::log('test', "AI connection test successful for provider: {$provider}");

                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            ActivityLog::log('test', "AI connection test failed for provider: {$provider} - {$e->getMessage()}");

            return back()->with('error', "Connection failed: {$e->getMessage()}");
        }
    }

    /**
     * Test OpenAI connection.
     *
     * @return array<string, mixed>
     */
    private function testOpenAi(string $apiKey, ?string $model): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->withOptions([
            'verify' => app()->environment('production'),
        ])->timeout(30)->get('https://api.openai.com/v1/models');

        if ($response->successful()) {
            $models = $response->json('data', []);
            $modelCount = count($models);

            return [
                'success' => true,
                'message' => "OpenAI connection successful! Found {$modelCount} available models. Using model: {$model}",
            ];
        }

        $error = $response->json('error.message', 'Unknown error');

        return ['success' => false, 'message' => "OpenAI API error: {$error}"];
    }

    /**
     * Test Google Gemini connection.
     *
     * @return array<string, mixed>
     */
    private function testGemini(string $apiKey, ?string $model): array
    {
        $response = Http::withOptions([
            'verify' => app()->environment('production'),
        ])->timeout(30)
            ->get("https://generativelanguage.googleapis.com/v1/models?key={$apiKey}");

        if ($response->successful()) {
            $models = $response->json('models', []);
            $modelCount = count($models);

            return [
                'success' => true,
                'message' => "Gemini connection successful! Found {$modelCount} available models. Using model: {$model}",
            ];
        }

        $error = $response->json('error.message', 'Unknown error');

        return ['success' => false, 'message' => "Gemini API error: {$error}"];
    }

    /**
     * Test Anthropic connection.
     *
     * @return array<string, mixed>
     */
    private function testAnthropic(string $apiKey, ?string $model): array
    {
        // Anthropic doesn't have a simple "list models" endpoint, so we test with a minimal message
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->withOptions([
            'verify' => app()->environment('production'),
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model ?? 'claude-3-haiku-20240307',
            'max_tokens' => 10,
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => "Anthropic connection successful! Using model: {$model}",
            ];
        }

        $error = $response->json('error.message', 'Unknown error');

        return ['success' => false, 'message' => "Anthropic API error: {$error}"];
    }

    /**
     * Test Telegram bot connection.
     */
    public function testTelegram(): RedirectResponse
    {
        // Try database first, then fallback to .env
        $botToken = $this->settingsService->get('telegram_bot_token');

        if (! $botToken) {
            $botToken = config('telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        }

        if (! $botToken) {
            return back()->with('error', 'No Telegram bot token configured. Please save your bot token first.');
        }

        try {
            $response = Http::withOptions([
                'verify' => app()->environment('production'),
            ])->timeout(30)
                ->get("https://api.telegram.org/bot{$botToken}/getMe");

            if ($response->successful()) {
                $bot = $response->json('result', []);
                $username = $bot['username'] ?? 'Unknown';
                $firstName = $bot['first_name'] ?? 'Unknown';

                ActivityLog::log('test', "Telegram bot connection test successful: @{$username}");

                return back()->with('success', "Telegram bot connected! Bot: @{$username} ({$firstName})");
            }

            $error = $response->json('description', 'Unknown error');

            return back()->with('error', "Telegram API error: {$error}");
        } catch (\Exception $e) {
            $shortMessage = str($e->getMessage())->limit(200)->toString();
            ActivityLog::log('test', "Telegram bot connection test failed: {$shortMessage}");

            return back()->with('error', "Connection failed: {$shortMessage}");
        }
    }

    /**
     * Update subscription settings.
     */
    public function updateSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'free_trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'monthly_price' => ['required', 'integer', 'min:0'],
            'yearly_price' => ['required', 'integer', 'min:0'],
            'free_trial_daily_chat_limit' => ['required', 'integer', 'min:1'],
            'full_member_daily_chat_limit' => ['required', 'integer', 'min:1'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
        ]);

        $this->settingsService->updateSubscriptionSettings($validated);

        ActivityLog::log('update', 'Updated subscription settings');

        return back()->with('success', 'Subscription settings updated successfully.');
    }

    /**
     * Update email/SMTP settings.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);

        $this->settingsService->updateEmailSettings($validated);

        ActivityLog::log('update', 'Updated email settings');

        return back()->with('success', 'Email settings updated successfully.');
    }

    /**
     * Test email connection.
     */
    public function testEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'Ini adalah email percobaan dari ASPRI untuk memverifikasi konfigurasi SMTP Anda berhasil.',
                function ($message) use ($validated) {
                    $message->to($validated['test_email'])
                        ->subject('Test Email dari ASPRI');
                }
            );

            ActivityLog::log('test', "Email test sent successfully to {$validated['test_email']}");

            return back()->with('success', "Email test berhasil dikirim ke {$validated['test_email']}");
        } catch (\Exception $e) {
            $shortMessage = str($e->getMessage())->limit(200)->toString();
            ActivityLog::log('test', "Email test failed: {$shortMessage}");

            return back()->with('error', "Gagal mengirim email: {$shortMessage}");
        }
    }
}
