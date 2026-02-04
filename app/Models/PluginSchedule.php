<?php

namespace App\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PluginSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_plugin_id',
        'schedule_type',
        'schedule_value',
        'last_run_at',
        'next_run_at',
        'is_active',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the user plugin that owns this schedule.
     */
    public function userPlugin(): BelongsTo
    {
        return $this->belongsTo(UserPlugin::class);
    }

    /**
     * Check if schedule is due to run.
     */
    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->next_run_at) {
            return true;
        }

        return $this->next_run_at->isPast();
    }

    /**
     * Calculate and update next run time.
     */
    public function calculateNextRun(): Carbon
    {
        $now = Carbon::now();

        $nextRun = match ($this->schedule_type) {
            'cron' => $this->calculateCronNextRun($now),
            'interval' => $this->calculateIntervalNextRun($now),
            'daily' => $this->calculateDailyNextRun($now),
            'weekly' => $this->calculateWeeklyNextRun($now),
            default => $now->copy()->addDay(),
        };

        $this->update(['next_run_at' => $nextRun]);

        return $nextRun;
    }

    /**
     * Mark schedule as run and calculate next run.
     */
    public function markAsRun(): void
    {
        $this->update(['last_run_at' => Carbon::now()]);
        $this->calculateNextRun();
    }

    /**
     * Calculate next run for cron expression.
     */
    protected function calculateCronNextRun(Carbon $now): Carbon
    {
        try {
            $cron = new CronExpression($this->schedule_value);

            return Carbon::instance($cron->getNextRunDate($now->toDateTime()));
        } catch (\Exception $e) {
            return $now->copy()->addDay();
        }
    }

    /**
     * Calculate next run for interval (in minutes).
     */
    protected function calculateIntervalNextRun(Carbon $now): Carbon
    {
        $minutes = (int) $this->schedule_value;

        return $now->copy()->addMinutes(max(1, $minutes));
    }

    /**
     * Calculate next run for daily schedule.
     * schedule_value format: "HH:MM" or "HH:MM,HH:MM" for multiple times
     */
    protected function calculateDailyNextRun(Carbon $now): Carbon
    {
        $times = explode(',', $this->schedule_value);
        $nextRuns = [];

        foreach ($times as $time) {
            $time = trim($time);
            [$hour, $minute] = explode(':', $time);
            $nextRun = $now->copy()->setTime((int) $hour, (int) $minute);

            if ($nextRun->isPast()) {
                $nextRun->addDay();
            }

            $nextRuns[] = $nextRun;
        }

        usort($nextRuns, fn ($a, $b) => $a->timestamp - $b->timestamp);

        return $nextRuns[0];
    }

    /**
     * Calculate next run for weekly schedule.
     * schedule_value format: "DAY:HH:MM" (e.g., "MON:09:00")
     */
    protected function calculateWeeklyNextRun(Carbon $now): Carbon
    {
        $parts = explode(':', $this->schedule_value);
        $dayOfWeek = strtoupper($parts[0] ?? 'MON');
        $hour = (int) ($parts[1] ?? 9);
        $minute = (int) ($parts[2] ?? 0);

        $dayMap = [
            'SUN' => Carbon::SUNDAY,
            'MON' => Carbon::MONDAY,
            'TUE' => Carbon::TUESDAY,
            'WED' => Carbon::WEDNESDAY,
            'THU' => Carbon::THURSDAY,
            'FRI' => Carbon::FRIDAY,
            'SAT' => Carbon::SATURDAY,
        ];

        $targetDay = $dayMap[$dayOfWeek] ?? Carbon::MONDAY;
        $nextRun = $now->copy()->setTime($hour, $minute);

        while ($nextRun->dayOfWeek !== $targetDay || $nextRun->isPast()) {
            $nextRun->addDay();
        }

        return $nextRun;
    }
}
