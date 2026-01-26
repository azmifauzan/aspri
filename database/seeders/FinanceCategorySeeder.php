<?php

namespace Database\Seeders;

use App\Models\FinanceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinanceCategorySeeder extends Seeder
{
    /**
     * Default expense categories
     */
    protected array $expenseCategories = [
        ['name' => 'Makanan & Minuman', 'icon' => 'utensils', 'color' => '#ef4444'],
        ['name' => 'Transportasi', 'icon' => 'car', 'color' => '#f97316'],
        ['name' => 'Belanja', 'icon' => 'shopping-bag', 'color' => '#eab308'],
        ['name' => 'Tagihan', 'icon' => 'receipt', 'color' => '#84cc16'],
        ['name' => 'Hiburan', 'icon' => 'gamepad-2', 'color' => '#22c55e'],
        ['name' => 'Kesehatan', 'icon' => 'heart-pulse', 'color' => '#14b8a6'],
        ['name' => 'Pendidikan', 'icon' => 'graduation-cap', 'color' => '#06b6d4'],
        ['name' => 'Lainnya', 'icon' => 'circle-ellipsis', 'color' => '#6b7280'],
    ];

    /**
     * Default income categories
     */
    protected array $incomeCategories = [
        ['name' => 'Gaji', 'icon' => 'briefcase', 'color' => '#22c55e'],
        ['name' => 'Bonus', 'icon' => 'gift', 'color' => '#14b8a6'],
        ['name' => 'Investasi', 'icon' => 'trending-up', 'color' => '#06b6d4'],
        ['name' => 'Freelance', 'icon' => 'laptop', 'color' => '#3b82f6'],
        ['name' => 'Hadiah', 'icon' => 'sparkles', 'color' => '#8b5cf6'],
        ['name' => 'Lainnya', 'icon' => 'circle-ellipsis', 'color' => '#6b7280'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users
        $users = User::all();

        foreach ($users as $user) {
            $this->createCategoriesForUser($user);
        }
    }

    /**
     * Create default categories for a specific user
     */
    public function createCategoriesForUser(User $user): void
    {
        // Create expense categories
        foreach ($this->expenseCategories as $category) {
            FinanceCategory::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'tx_type' => 'expense',
                    'name' => $category['name'],
                ],
                [
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                ]
            );
        }

        // Create income categories
        foreach ($this->incomeCategories as $category) {
            FinanceCategory::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'tx_type' => 'income',
                    'name' => $category['name'],
                ],
                [
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                ]
            );
        }
    }
}
