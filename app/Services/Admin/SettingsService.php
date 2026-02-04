<?php

namespace App\Services\Admin;

use App\Models\SystemSetting;

class SettingsService
{
    /**
     * Get a setting value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return SystemSetting::getValue($key, $default);
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value, array $options = []): void
    {
        SystemSetting::setValue($key, $value, $options);
    }

    /**
     * Get all AI provider settings.
     *
     * @return array<string, mixed>
     */
    public function getAiSettings(): array
    {
        return [
            'ai_provider' => $this->get('ai_provider', 'gemini'),
            'gemini_model' => $this->get('gemini_model', 'gemini-pro'),
            'openai_model' => $this->get('openai_model', 'gpt-4-turbo'),
            'anthropic_model' => $this->get('anthropic_model', 'claude-3-sonnet'),
            'has_gemini_key' => (bool) $this->get('gemini_api_key'),
            'has_openai_key' => (bool) $this->get('openai_api_key'),
            'has_anthropic_key' => (bool) $this->get('anthropic_api_key'),
        ];
    }

    /**
     * Update AI provider settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAiSettings(array $data): void
    {
        if (isset($data['ai_provider'])) {
            $this->set('ai_provider', $data['ai_provider'], ['group' => 'ai']);
        }

        if (isset($data['gemini_api_key']) && $data['gemini_api_key']) {
            $this->set('gemini_api_key', $data['gemini_api_key'], [
                'encrypted' => true,
                'group' => 'ai',
            ]);
        }

        if (isset($data['gemini_model'])) {
            $this->set('gemini_model', $data['gemini_model'], ['group' => 'ai']);
        }

        if (isset($data['openai_api_key']) && $data['openai_api_key']) {
            $this->set('openai_api_key', $data['openai_api_key'], [
                'encrypted' => true,
                'group' => 'ai',
            ]);
        }

        if (isset($data['openai_model'])) {
            $this->set('openai_model', $data['openai_model'], ['group' => 'ai']);
        }

        if (isset($data['anthropic_api_key']) && $data['anthropic_api_key']) {
            $this->set('anthropic_api_key', $data['anthropic_api_key'], [
                'encrypted' => true,
                'group' => 'ai',
            ]);
        }

        if (isset($data['anthropic_model'])) {
            $this->set('anthropic_model', $data['anthropic_model'], ['group' => 'ai']);
        }
    }

    /**
     * Get the active AI provider configuration.
     *
     * @return array<string, mixed>
     */
    public function getActiveAiConfig(): array
    {
        $provider = $this->get('ai_provider', 'openai');

        // Try to get API key from database first, then fallback to .env
        $apiKey = $this->get("{$provider}_api_key");

        if (! $apiKey) {
            $apiKey = match ($provider) {
                'openai' => config('services.openai.api_key') ?? env('OPENAI_API_KEY'),
                'gemini' => config('services.gemini.api_key') ?? env('GEMINI_API_KEY'),
                'anthropic' => config('services.anthropic.api_key') ?? env('ANTHROPIC_API_KEY'),
                default => null,
            };
        }

        // Get model from database or fallback to .env
        $model = $this->get("{$provider}_model");

        if (! $model) {
            $model = match ($provider) {
                'openai' => env('OPENAI_MODEL', 'gpt-4-turbo'),
                'gemini' => env('GEMINI_MODEL', 'gemini-pro'),
                'anthropic' => env('ANTHROPIC_MODEL', 'claude-3-sonnet'),
                default => null,
            };
        }

        return [
            'provider' => $provider,
            'api_key' => $apiKey,
            'model' => $model,
        ];
    }

    /**
     * Get Telegram bot settings.
     *
     * @return array<string, mixed>
     */
    public function getTelegramSettings(): array
    {
        return [
            'bot_token' => $this->get('telegram_bot_token') ? '********' : null,
            'has_bot_token' => (bool) $this->get('telegram_bot_token'),
            'webhook_url' => $this->get('telegram_webhook_url'),
            'bot_username' => $this->get('telegram_bot_username'),
            'admin_chat_ids' => $this->get('admin_telegram_chat_ids', ''),
        ];
    }

    /**
     * Update Telegram settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTelegramSettings(array $data): void
    {
        if (isset($data['bot_token']) && $data['bot_token']) {
            $this->set('telegram_bot_token', $data['bot_token'], [
                'encrypted' => true,
                'group' => 'telegram',
            ]);
        }

        if (isset($data['webhook_url'])) {
            $this->set('telegram_webhook_url', $data['webhook_url'], ['group' => 'telegram']);
        }

        if (isset($data['bot_username'])) {
            $this->set('telegram_bot_username', $data['bot_username'], ['group' => 'telegram']);
        }

        if (array_key_exists('admin_chat_ids', $data)) {
            $this->set('admin_telegram_chat_ids', $data['admin_chat_ids'] ?? '', ['group' => 'telegram']);
        }
    }

    /**
     * Get general app settings.
     *
     * @return array<string, mixed>
     */
    public function getAppSettings(): array
    {
        return [
            'app_name' => $this->get('app_name', config('app.name')),
            'app_description' => $this->get('app_description', ''),
            'app_locale' => $this->get('app_locale', 'id'),
            'app_timezone' => $this->get('app_timezone', 'Asia/Jakarta'),
            'maintenance_mode' => $this->get('maintenance_mode', false),
        ];
    }

    /**
     * Update general app settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAppSettings(array $data): void
    {
        $settingKeys = ['app_name', 'app_description', 'app_locale', 'app_timezone'];

        foreach ($settingKeys as $key) {
            if (isset($data[$key])) {
                $this->set($key, $data[$key], ['group' => 'app']);
            }
        }

        if (isset($data['maintenance_mode'])) {
            $this->set('maintenance_mode', $data['maintenance_mode'], [
                'type' => 'boolean',
                'group' => 'app',
            ]);
        }
    }

    /**
     * Get subscription settings.
     *
     * @return array<string, mixed>
     */
    public function getSubscriptionSettings(): array
    {
        return [
            'free_trial_days' => (int) $this->get('free_trial_days', 30),
            'monthly_price' => (int) $this->get('monthly_price', 10000),
            'yearly_price' => (int) $this->get('yearly_price', 100000),
            'free_trial_daily_chat_limit' => (int) $this->get('free_trial_daily_chat_limit', 50),
            'full_member_daily_chat_limit' => (int) $this->get('full_member_daily_chat_limit', 500),
            'bank_name' => $this->get('bank_name', ''),
            'bank_account_number' => $this->get('bank_account_number', ''),
            'bank_account_name' => $this->get('bank_account_name', ''),
        ];
    }

    /**
     * Update subscription settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSubscriptionSettings(array $data): void
    {
        $integerSettings = [
            'free_trial_days',
            'monthly_price',
            'yearly_price',
            'free_trial_daily_chat_limit',
            'full_member_daily_chat_limit',
        ];

        foreach ($integerSettings as $key) {
            if (isset($data[$key])) {
                $this->set($key, (int) $data[$key], [
                    'type' => 'integer',
                    'group' => 'subscription',
                ]);
            }
        }

        $stringSettings = ['bank_name', 'bank_account_number', 'bank_account_name'];

        foreach ($stringSettings as $key) {
            if (isset($data[$key])) {
                $this->set($key, $data[$key], ['group' => 'subscription']);
            }
        }
    }

    /**
     * Get email/SMTP settings.
     *
     * @return array<string, mixed>
     */
    public function getEmailSettings(): array
    {
        return [
            'mail_mailer' => $this->get('mail_mailer', 'smtp'),
            'mail_host' => $this->get('mail_host', 'smtp-relay.brevo.com'),
            'mail_port' => (int) $this->get('mail_port', 587),
            'mail_encryption' => $this->get('mail_encryption', 'tls'),
            'mail_username' => $this->get('mail_username', ''),
            'has_mail_password' => (bool) $this->get('mail_password'),
            'mail_from_address' => $this->get('mail_from_address', ''),
            'mail_from_name' => $this->get('mail_from_name', config('app.name')),
        ];
    }

    /**
     * Update email/SMTP settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateEmailSettings(array $data): void
    {
        $stringSettings = [
            'mail_mailer',
            'mail_host',
            'mail_encryption',
            'mail_username',
            'mail_from_address',
            'mail_from_name',
        ];

        foreach ($stringSettings as $key) {
            if (isset($data[$key])) {
                $this->set($key, $data[$key], ['group' => 'email']);
            }
        }

        if (isset($data['mail_port'])) {
            $this->set('mail_port', (int) $data['mail_port'], [
                'type' => 'integer',
                'group' => 'email',
            ]);
        }

        if (isset($data['mail_password']) && $data['mail_password']) {
            $this->set('mail_password', $data['mail_password'], [
                'encrypted' => true,
                'group' => 'email',
            ]);
        }
    }

    /**
     * Get the full SMTP configuration for runtime.
     *
     * @return array<string, mixed>
     */
    public function getSmtpConfig(): array
    {
        return [
            'transport' => $this->get('mail_mailer', 'smtp'),
            'host' => $this->get('mail_host', 'smtp-relay.brevo.com'),
            'port' => (int) $this->get('mail_port', 587),
            'encryption' => $this->get('mail_encryption', 'tls'),
            'username' => $this->get('mail_username'),
            'password' => $this->get('mail_password'),
            'from' => [
                'address' => $this->get('mail_from_address'),
                'name' => $this->get('mail_from_name', config('app.name')),
            ],
        ];
    }
}
