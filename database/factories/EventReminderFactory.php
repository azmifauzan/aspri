<?php

namespace Database\Factories;

use App\Models\EventReminder;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventReminder>
 */
class EventReminderFactory extends Factory
{
    protected $model = EventReminder::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'user_id' => User::factory(),
            'minutes_before' => 30,
            'channel' => EventReminder::CHANNEL_APP,
            'scheduled_for' => now()->addHour(),
            'is_sent' => false,
            'sent_at' => null,
            'error' => null,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => [
            'scheduled_for' => now()->subMinute(),
            'is_sent' => false,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }

    public function telegram(): static
    {
        return $this->state(fn () => ['channel' => EventReminder::CHANNEL_TELEGRAM]);
    }
}
