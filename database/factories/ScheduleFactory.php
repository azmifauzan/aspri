<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 month');
        $end = (clone $start)->modify('+1 hour');

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'start_time' => $start,
            'end_time' => $end,
            'location' => fake()->optional()->city(),
            'is_completed' => false,
            'is_recurring' => false,
            'recurrence_rule' => null,
            'is_all_day' => false,
        ];
    }
}
