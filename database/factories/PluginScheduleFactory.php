<?php

namespace Database\Factories;

use App\Models\PluginSchedule;
use App\Models\UserPlugin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PluginSchedule>
 */
class PluginScheduleFactory extends Factory
{
    protected $model = PluginSchedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_plugin_id' => UserPlugin::factory(),
            'schedule_type' => 'daily',
            'schedule_value' => '07:00',
            'last_run_at' => null,
            'next_run_at' => now()->addDay(),
            'is_active' => true,
            'metadata' => null,
        ];
    }

    /**
     * Create an interval-based schedule.
     */
    public function interval(int $minutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'interval',
            'schedule_value' => (string) $minutes,
        ]);
    }

    /**
     * Create a weekly schedule.
     */
    public function weekly(string $day = 'MON', string $time = '09:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'weekly',
            'schedule_value' => "{$day}:{$time}",
        ]);
    }

    /**
     * Create a cron-based schedule.
     */
    public function cron(string $expression = '0 7 * * *'): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_type' => 'cron',
            'schedule_value' => $expression,
        ]);
    }

    /**
     * Mark the schedule as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
