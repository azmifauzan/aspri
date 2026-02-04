<?php

namespace Database\Factories;

use App\Models\PluginConfiguration;
use App\Models\UserPlugin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PluginConfiguration>
 */
class PluginConfigurationFactory extends Factory
{
    protected $model = PluginConfiguration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_plugin_id' => UserPlugin::factory(),
            'config_key' => fake()->word(),
            'config_value' => fake()->word(),
        ];
    }
}
