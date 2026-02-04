<?php

namespace App\Console\Commands;

use App\Services\Plugin\PluginSchedulerService;
use Illuminate\Console\Command;

class ProcessPluginSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugins:process-schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all due plugin schedules';

    /**
     * Execute the console command.
     */
    public function handle(PluginSchedulerService $scheduler): int
    {
        $this->info('Processing plugin schedules...');

        $processed = $scheduler->processDueSchedules();

        $this->info("Processed {$processed} schedule(s).");

        return Command::SUCCESS;
    }
}
