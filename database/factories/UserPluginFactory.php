<?php

namespace Database\Factories;

use App\Models\Plugin;
use App\Models\User;
use App\Models\UserPlugin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPlugin>
 */
class UserPluginFactory extends Factory
{
    protected $model = UserPlugin::class;

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
            'is_active' => false,
            'activated_at' => null,
        ];
    }

    /**
     * Indicate that the user plugin is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }
}
