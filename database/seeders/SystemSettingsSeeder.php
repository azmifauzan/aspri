<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // AI Settings
            ['key' => 'ai_provider', 'value' => 'gemini', 'type' => 'string', 'group' => 'ai', 'description' => 'Active AI provider'],
            ['key' => 'gemini_model', 'value' => 'gemini-pro', 'type' => 'string', 'group' => 'ai', 'description' => 'Gemini model to use'],
            ['key' => 'openai_model', 'value' => 'gpt-4-turbo', 'type' => 'string', 'group' => 'ai', 'description' => 'OpenAI model to use'],
            ['key' => 'anthropic_model', 'value' => 'claude-3-sonnet', 'type' => 'string', 'group' => 'ai', 'description' => 'Anthropic model to use'],

            // App Settings
            ['key' => 'app_name', 'value' => 'ASPRI', 'type' => 'string', 'group' => 'app', 'description' => 'Application name'],
            ['key' => 'app_description', 'value' => 'Asisten Pribadi Berbasis AI', 'type' => 'string', 'group' => 'app', 'description' => 'Application description'],
            ['key' => 'app_locale', 'value' => 'id', 'type' => 'string', 'group' => 'app', 'description' => 'Default locale'],
            ['key' => 'app_timezone', 'value' => 'Asia/Jakarta', 'type' => 'string', 'group' => 'app', 'description' => 'Default timezone'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'app', 'description' => 'Maintenance mode status'],

            // Subscription Settings
            ['key' => 'free_trial_days', 'value' => '7', 'type' => 'integer', 'group' => 'subscription', 'description' => 'Number of free trial days'],
            ['key' => 'monthly_price', 'value' => '50000', 'type' => 'integer', 'group' => 'subscription', 'description' => 'Monthly subscription price in IDR'],
            ['key' => 'yearly_price', 'value' => '500000', 'type' => 'integer', 'group' => 'subscription', 'description' => 'Yearly subscription price in IDR'],
            ['key' => 'free_trial_daily_chat_limit', 'value' => '50', 'type' => 'integer', 'group' => 'subscription', 'description' => 'Daily chat limit for free trial users'],
            ['key' => 'full_member_daily_chat_limit', 'value' => '500', 'type' => 'integer', 'group' => 'subscription', 'description' => 'Daily chat limit for paid members'],
            ['key' => 'bank_name', 'value' => '', 'type' => 'string', 'group' => 'subscription', 'description' => 'Bank name for payment transfer'],
            ['key' => 'bank_account_number', 'value' => '', 'type' => 'string', 'group' => 'subscription', 'description' => 'Bank account number'],
            ['key' => 'bank_account_name', 'value' => '', 'type' => 'string', 'group' => 'subscription', 'description' => 'Bank account holder name'],

            // Telegram Admin Notifications
            ['key' => 'admin_telegram_chat_ids', 'value' => '', 'type' => 'string', 'group' => 'telegram', 'description' => 'Comma-separated admin Telegram chat IDs for notifications'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
