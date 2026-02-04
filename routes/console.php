<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Plugin schedule processing - runs every minute
Schedule::command('plugins:process-schedules')->everyMinute();

// Plugin log cleanup - runs daily at 3am
Schedule::command('plugins:cleanup-logs --days=30')->dailyAt('03:00');
