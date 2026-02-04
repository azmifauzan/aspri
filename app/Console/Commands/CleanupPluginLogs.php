<?php

namespace App\Console\Commands;

use App\Services\Plugin\PluginSchedulerService;
use Illuminate\Console\Command;

class CleanupPluginLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugins:cleanup-logs {--days=30 : Number of days to keep logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old plugin log entries';

    /**
     * Execute the console command.
     */
    public function handle(PluginSchedulerService $scheduler): int
    {
        $days = (int) $this->option('days');

        $this->info("Cleaning up plugin logs older than {$days} days...");

        $deleted = $scheduler->cleanupOldLogs($days);

        $this->info("Deleted {$deleted} log entries.");

        return Command::SUCCESS;
    }
}
