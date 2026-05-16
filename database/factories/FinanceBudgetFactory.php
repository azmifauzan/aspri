<?php

namespace Database\Factories;

use App\Models\FinanceBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinanceBudget>
 */
class FinanceBudgetFactory extends Factory
{
    protected $model = FinanceBudget::class;

    public function definition(): array
    {
        $now = now();

        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'period_year' => $now->year,
            'period_month' => $now->month,
            'amount' => $this->faker->randomFloat(2, 100000, 5000000),
            'alert_threshold_pct' => 80,
            'is_active' => true,
        ];
    }
}
