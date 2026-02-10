<?php

namespace Database\Factories;

use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinanceTransaction>
 */
class FinanceTransactionFactory extends Factory
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
            'account_id' => FinanceAccount::factory(),
            'category_id' => FinanceCategory::factory(),
            'tx_type' => fake()->randomElement(['income', 'expense']),
            'amount' => fake()->numberBetween(10000, 5000000),
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'note' => fake()->optional()->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the transaction is income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'income',
            'category_id' => FinanceCategory::factory()->income(),
        ]);
    }

    /**
     * Indicate that the transaction is expense.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'expense',
            'category_id' => FinanceCategory::factory()->expense(),
        ]);
    }
}
