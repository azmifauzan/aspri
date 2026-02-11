<?php

namespace Tests\Unit;

use App\Services\Plugin\BasePlugin;
use App\Services\Plugin\Contracts\PluginInterface;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginComplianceTest extends TestCase
{
    public function test_plugins_follow_chat_and_schedule_contracts(): void
    {
        $pluginsPath = app_path('Plugins');
        $directories = File::directories($pluginsPath);

        $this->assertNotEmpty($directories);

        foreach ($directories as $directory) {
            $pluginName = basename($directory);
            $pluginClass = "App\\Plugins\\{$pluginName}\\{$pluginName}Plugin";

            $this->assertTrue(class_exists($pluginClass), "Missing plugin class: {$pluginClass}");

            /** @var PluginInterface $plugin */
            $plugin = app($pluginClass);

            $this->assertInstanceOf(BasePlugin::class, $plugin);
            $this->assertTrue($plugin->supportsChatIntegration(), "{$pluginClass} must support chat integration.");

            $intents = $plugin->getChatIntents();
            $this->assertIsArray($intents, "{$pluginClass} must return chat intents.");
            $this->assertNotEmpty($intents, "{$pluginClass} must define at least one chat intent.");

            $normalizedSlug = str_replace('-', '_', $plugin->getSlug());

            foreach ($intents as $intent) {
                $this->assertArrayHasKey('action', $intent, "{$pluginClass} intent missing action.");
                $this->assertArrayHasKey('description', $intent, "{$pluginClass} intent missing description.");
                $this->assertArrayHasKey('entities', $intent, "{$pluginClass} intent missing entities.");
                $this->assertArrayHasKey('examples', $intent, "{$pluginClass} intent missing examples.");

                $this->assertIsString($intent['action']);
                $this->assertStringStartsWith("plugin_{$normalizedSlug}_", $intent['action']);
                $this->assertIsArray($intent['entities']);
                $this->assertIsArray($intent['examples']);
                $this->assertNotEmpty($intent['examples']);
            }

            if ($plugin->supportsScheduling()) {
                $schedule = $plugin->getDefaultSchedule();

                $this->assertIsArray($schedule, "{$pluginClass} must define default schedule.");
                $this->assertArrayHasKey('type', $schedule);
                $this->assertArrayHasKey('value', $schedule);
            }
        }
    }
}
