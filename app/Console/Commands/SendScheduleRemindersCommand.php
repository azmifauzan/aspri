<?php

namespace App\Console\Commands;

use App\Services\Schedule\ScheduleReminderService;
use Illuminate\Console\Command;

class SendScheduleRemindersCommand extends Command
{
    protected $signature = 'aspri:send-reminders';

    protected $description = 'Process due event reminders and deliver them via configured channels (app, telegram)';

    public function handle(ScheduleReminderService $service): int
    {
        $this->info('Processing due reminders...');

        $result = $service->sendDue();

        $this->info("Done. Sent: {$result['sent']}, failed: {$result['failed']}.");

        return Command::SUCCESS;
    }
}
