<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinanceAccount>
 */
class FinanceAccountFactory extends Factory
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
            'name' => fake()->randomElement(['Cash', 'BCA', 'Mandiri', 'BRI', 'GoPay', 'OVO', 'Dana']),
            'type' => fake()->randomElement(['cash', 'bank', 'e-wallet']),
            'currency' => 'IDR',
            'initial_balance' => fake()->numberBetween(0, 10000000),
        ];
    }

    /**
     * Indicate that the account is cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cash',
            'name' => 'Cash',
        ]);
    }

    /**
     * Indicate that the account is a bank account.
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank',
        ]);
    }

    /**
     * Indicate that the account is an e-wallet.
     */
    public function ewallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'e-wallet',
        ]);
    }
}
