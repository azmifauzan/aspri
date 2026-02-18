<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PromoCode>
 */
class PromoCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'duration_days' => 30,
            'max_usages' => 100,
            'usage_count' => 0,
            'is_active' => true,
            'expires_at' => now()->addDays(30),
            'created_by' => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(5),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function fullyRedeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_usages' => 1,
            'usage_count' => 1,
        ]);
    }

    public function singleUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_usages' => 1,
        ]);
    }
}
