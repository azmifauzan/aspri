<?php

namespace Database\Factories;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plugin>
 */
class PluginFactory extends Factory
{
    protected $model = Plugin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => ucwords($name),
            'description' => fake()->paragraph(),
            'version' => fake()->semver(),
            'author' => 'ASPRI Team',
            'icon' => 'puzzle-piece',
            'class_name' => 'App\\Plugins\\'.Str::studly($name).'\\'.Str::studly($name).'Plugin',
            'is_system' => true,
            'config_schema' => [
                'enabled' => [
                    'type' => 'boolean',
                    'label' => 'Aktifkan',
                    'default' => true,
                    'required' => true,
                ],
            ],
            'default_config' => [
                'enabled' => true,
            ],
            'installed_at' => now(),
        ];
    }

    /**
     * Indicate that the plugin is not installed.
     */
    public function notInstalled(): static
    {
        return $this->state(fn (array $attributes) => [
            'installed_at' => null,
        ]);
    }

    /**
     * Indicate that the plugin is a user plugin.
     */
    public function userPlugin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => false,
        ]);
    }
}
