<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan' => 'free_trial',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'price_paid' => 0,
        ];
    }

    /**
     * Indicate that the subscription is a free trial.
     */
    public function freeTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'free_trial',
            'price_paid' => 0,
            'ends_at' => now()->addDays(30),
        ]);
    }

    /**
     * Indicate that the subscription is monthly.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'monthly',
            'price_paid' => 10000,
            'ends_at' => now()->addDays(30),
        ]);
    }

    /**
     * Indicate that the subscription is yearly.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'yearly',
            'price_paid' => 100000,
            'ends_at' => now()->addDays(365),
        ]);
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'ends_at' => now()->subDays(5),
        ]);
    }

    /**
     * Indicate that the subscription is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
