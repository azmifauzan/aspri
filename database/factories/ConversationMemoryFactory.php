<?php

namespace Database\Factories;

use App\Models\ConversationMemory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationMemory>
 */
class ConversationMemoryFactory extends Factory
{
    protected $model = ConversationMemory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'memory_type' => $this->faker->randomElement(['fact', 'preference', 'event', 'pattern', 'summary']),
            'content' => $this->faker->sentence(),
            'source_thread_id' => null,
            'importance' => $this->faker->numberBetween(1, 5),
            'access_count' => 0,
            'last_accessed_at' => null,
            'valid_until' => null,
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
