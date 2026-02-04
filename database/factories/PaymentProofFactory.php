<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentProof>
 */
class PaymentProofFactory extends Factory
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
            'plan_type' => 'monthly',
            'amount' => 10000,
            'transfer_proof_path' => 'payment-proofs/test-proof.jpg',
            'bank_name' => fake()->randomElement(['BCA', 'BNI', 'BRI', 'Mandiri']),
            'account_name' => fake()->name(),
            'transfer_date' => now(),
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the payment proof is for monthly plan.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_type' => 'monthly',
            'amount' => 10000,
        ]);
    }

    /**
     * Indicate that the payment proof is for yearly plan.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_type' => 'yearly',
            'amount' => 100000,
        ]);
    }

    /**
     * Indicate that the payment proof is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => User::factory()->superAdmin(),
        ]);
    }

    /**
     * Indicate that the payment proof is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => User::factory()->superAdmin(),
            'admin_notes' => 'Invalid payment proof',
        ]);
    }
}
