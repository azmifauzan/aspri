<?php

namespace Database\Seeders;

use App\Services\Plugin\PluginManager;
use Illuminate\Database\Seeder;

class PluginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use PluginManager to discover and sync plugins from the Plugins directory
        // This ensures database records match actual plugin classes
        $pluginManager = app(PluginManager::class);
        $pluginManager->syncPlugins();
    }
}
