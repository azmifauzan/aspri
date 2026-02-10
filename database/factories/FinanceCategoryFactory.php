<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinanceCategory>
 */
class FinanceCategoryFactory extends Factory
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
            'name' => fake()->randomElement(['Salary', 'Freelance', 'Investment', 'Food', 'Transport', 'Entertainment', 'Shopping', 'Bills']),
            'tx_type' => fake()->randomElement(['income', 'expense']),
            'icon' => fake()->randomElement(['wallet', 'trending-up', 'trending-down', 'shopping-cart', 'coffee']),
            'color' => fake()->hexColor(),
        ];
    }

    /**
     * Indicate that the category is for income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'income',
        ]);
    }

    /**
     * Indicate that the category is for expense.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'tx_type' => 'expense',
        ]);
    }
}
