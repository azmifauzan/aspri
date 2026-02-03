<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook
                            {action=info : Action to perform: set, remove, info}
                            {--url= : The webhook URL (required for set action)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Telegram bot webhook (set, remove, info)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramBotService $telegramService): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'set' => $this->setWebhook($telegramService),
            'remove' => $this->removeWebhook($telegramService),
            'info' => $this->showWebhookInfo($telegramService),
            default => $this->invalidAction($action),
        };
    }

    protected function setWebhook(TelegramBotService $telegramService): int
    {
        $url = $this->option('url');

        if (! $url) {
            $url = $this->ask('Enter the webhook URL');
        }

        if (! $url) {
            $this->error('Webhook URL is required.');

            return self::FAILURE;
        }

        $this->info("Setting webhook to: {$url}");

        $result = $telegramService->setWebhook($url);

        if ($result['success']) {
            $this->info('✅ Webhook set successfully!');

            return self::SUCCESS;
        }

        $this->error("❌ Failed to set webhook: {$result['message']}");

        return self::FAILURE;
    }

    protected function removeWebhook(TelegramBotService $telegramService): int
    {
        if (! $this->confirm('Are you sure you want to remove the webhook?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $result = $telegramService->removeWebhook();

        if ($result['success']) {
            $this->info('✅ Webhook removed successfully!');

            return self::SUCCESS;
        }

        $this->error("❌ Failed to remove webhook: {$result['message']}");

        return self::FAILURE;
    }

    protected function showWebhookInfo(TelegramBotService $telegramService): int
    {
        $this->info('Fetching webhook info...');

        $result = $telegramService->getWebhookInfo();

        if (! $result['success']) {
            $this->error("❌ Failed to get webhook info: {$result['message']}");

            return self::FAILURE;
        }

        $info = $result['data'];

        $this->newLine();
        $this->info('📡 Telegram Webhook Info');
        $this->line('─────────────────────────────────');

        $this->table(
            ['Property', 'Value'],
            [
                ['URL', $info->getUrl() ?: '(not set)'],
                ['Has Custom Certificate', $info->hasCustomCertificate() ? 'Yes' : 'No'],
                ['Pending Update Count', $info->getPendingUpdateCount()],
                ['Max Connections', $info->getMaxConnections() ?? 'N/A'],
                ['IP Address', $info->getIpAddress() ?? 'N/A'],
                ['Last Error Date', $info->getLastErrorDate() ? date('Y-m-d H:i:s', $info->getLastErrorDate()) : 'N/A'],
                ['Last Error Message', $info->getLastErrorMessage() ?? 'N/A'],
            ]
        );

        if ($allowedUpdates = $info->getAllowedUpdates()) {
            $this->newLine();
            $this->info('Allowed Updates: '.implode(', ', $allowedUpdates));
        }

        return self::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Available actions: set, remove, info');

        return self::FAILURE;
    }
}
