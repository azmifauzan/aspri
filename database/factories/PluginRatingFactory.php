<?php

namespace Database\Factories;

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PluginRating>
 */
class PluginRatingFactory extends Factory
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
            'plugin_id' => Plugin::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'review' => fake()->optional(0.6)->sentence(10),
        ];
    }
}
