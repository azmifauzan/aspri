<?php

namespace Database\Seeders;

use App\Models\Plugin;
use App\Models\PluginRating;
use App\Models\User;
use Illuminate\Database\Seeder;

class PluginRatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all plugins and users
        $plugins = Plugin::all();
        $users = User::whereNull('role')->get(); // Only regular users, not admins

        if ($plugins->isEmpty() || $users->isEmpty()) {
            $this->command->info('Skipping plugin ratings: No plugins or users found.');

            return;
        }

        // Create ratings for each plugin from random users
        foreach ($plugins as $plugin) {
            // Randomly select 40-80% of users to rate this plugin
            $ratingCount = rand((int) ($users->count() * 0.4), (int) ($users->count() * 0.8));
            $randomUsers = $users->random(min($ratingCount, $users->count()));

            foreach ($randomUsers as $user) {
                PluginRating::factory()->create([
                    'user_id' => $user->id,
                    'plugin_id' => $plugin->id,
                ]);
            }
        }

        $this->command->info('Plugin ratings seeded successfully!');
    }
}
