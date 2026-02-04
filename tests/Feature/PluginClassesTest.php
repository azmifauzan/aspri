<?php

namespace Tests\Feature;

use App\Models\User;
use App\Plugins\ExpenseAlert\ExpenseAlertPlugin;
use App\Plugins\KataMotivasi\KataMotivasiPlugin;
use App\Plugins\PengingatMinumAir\PengingatMinumAirPlugin;
use App\Services\Plugin\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginClassesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected PluginManager $pluginManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'telegram_chat_id' => 12345678,
        ]);

        $this->pluginManager = app(PluginManager::class);
    }

    public function test_kata_motivasi_plugin_has_correct_metadata(): void
    {
        $plugin = new KataMotivasiPlugin;

        $this->assertEquals('kata-motivasi', $plugin->getSlug());
        $this->assertEquals('Kata Motivasi', $plugin->getName());
        $this->assertEquals('1.0.0', $plugin->getVersion());
        $this->assertEquals('sparkles', $plugin->getIcon());
        $this->assertTrue($plugin->supportsScheduling());
    }

    public function test_kata_motivasi_plugin_has_valid_config_schema(): void
    {
        $plugin = new KataMotivasiPlugin;
        $schema = $plugin->getConfigSchema();

        $this->assertArrayHasKey('delivery_time', $schema);
        $this->assertArrayHasKey('categories', $schema);
        $this->assertArrayHasKey('include_author', $schema);
        $this->assertArrayHasKey('include_custom', $schema);

        $this->assertEquals('time', $schema['delivery_time']['type']);
        $this->assertEquals('multiselect', $schema['categories']['type']);
        $this->assertEquals('boolean', $schema['include_author']['type']);
    }

    public function test_kata_motivasi_plugin_has_default_schedule(): void
    {
        $plugin = new KataMotivasiPlugin;
        $schedule = $plugin->getDefaultSchedule();

        $this->assertNotNull($schedule);
        $this->assertEquals('daily', $schedule['type']);
        $this->assertEquals('07:00', $schedule['value']);
    }

    public function test_pengingat_minum_air_plugin_has_correct_metadata(): void
    {
        $plugin = new PengingatMinumAirPlugin;

        $this->assertEquals('pengingat-minum-air', $plugin->getSlug());
        $this->assertEquals('Pengingat Minum Air', $plugin->getName());
        $this->assertEquals('1.0.0', $plugin->getVersion());
        $this->assertEquals('droplets', $plugin->getIcon());
        $this->assertTrue($plugin->supportsScheduling());
    }

    public function test_pengingat_minum_air_plugin_has_valid_config_schema(): void
    {
        $plugin = new PengingatMinumAirPlugin;
        $schema = $plugin->getConfigSchema();

        $this->assertArrayHasKey('daily_target', $schema);
        $this->assertArrayHasKey('reminder_interval', $schema);
        $this->assertArrayHasKey('start_time', $schema);
        $this->assertArrayHasKey('end_time', $schema);

        $this->assertEquals('number', $schema['daily_target']['type']);
        $this->assertEquals(8, $schema['daily_target']['default']);
        $this->assertEquals(4, $schema['daily_target']['min']);
        $this->assertEquals(20, $schema['daily_target']['max']);
    }

    public function test_pengingat_minum_air_plugin_has_interval_schedule(): void
    {
        $plugin = new PengingatMinumAirPlugin;
        $schedule = $plugin->getDefaultSchedule();

        $this->assertNotNull($schedule);
        $this->assertEquals('interval', $schedule['type']);
        $this->assertEquals('120', $schedule['value']); // 2 hours
    }

    public function test_expense_alert_plugin_has_correct_metadata(): void
    {
        $plugin = new ExpenseAlertPlugin;

        $this->assertEquals('expense-alert', $plugin->getSlug());
        $this->assertEquals('Expense Alert', $plugin->getName());
        $this->assertEquals('1.0.0', $plugin->getVersion());
        $this->assertEquals('bell-ring', $plugin->getIcon());
        $this->assertTrue($plugin->supportsScheduling());
    }

    public function test_expense_alert_plugin_has_valid_config_schema(): void
    {
        $plugin = new ExpenseAlertPlugin;
        $schema = $plugin->getConfigSchema();

        $this->assertArrayHasKey('monthly_budget', $schema);
        $this->assertArrayHasKey('alert_thresholds', $schema);
        $this->assertArrayHasKey('daily_summary', $schema);
        $this->assertArrayHasKey('include_breakdown', $schema);

        $this->assertEquals('number', $schema['monthly_budget']['type']);
        $this->assertEquals(5000000, $schema['monthly_budget']['default']);
        $this->assertEquals('multiselect', $schema['alert_thresholds']['type']);
        $this->assertContains('75', $schema['alert_thresholds']['options']);
        $this->assertContains('100', $schema['alert_thresholds']['options']);
    }

    public function test_expense_alert_plugin_has_daily_schedule(): void
    {
        $plugin = new ExpenseAlertPlugin;
        $schedule = $plugin->getDefaultSchedule();

        $this->assertNotNull($schedule);
        $this->assertEquals('daily', $schedule['type']);
        $this->assertEquals('20:00', $schedule['value']);
    }

    public function test_plugin_manager_discovers_all_plugins(): void
    {
        $plugins = $this->pluginManager->getRegisteredPlugins();

        $this->assertArrayHasKey('kata-motivasi', $plugins);
        $this->assertArrayHasKey('pengingat-minum-air', $plugins);
        $this->assertArrayHasKey('expense-alert', $plugins);
    }

    public function test_plugin_manager_can_install_plugins(): void
    {
        $this->pluginManager->syncPlugins();

        $this->assertDatabaseHas('plugins', ['slug' => 'kata-motivasi']);
        $this->assertDatabaseHas('plugins', ['slug' => 'pengingat-minum-air']);
        $this->assertDatabaseHas('plugins', ['slug' => 'expense-alert']);
    }

    public function test_plugin_manager_can_activate_plugin_for_user(): void
    {
        $this->pluginManager->syncPlugins();

        $userPlugin = $this->pluginManager->activateForUser('kata-motivasi', $this->user->id);

        $this->assertTrue($userPlugin->is_active);
        $this->assertEquals($this->user->id, $userPlugin->user_id);
    }

    public function test_plugin_manager_can_deactivate_plugin_for_user(): void
    {
        $this->pluginManager->syncPlugins();

        $this->pluginManager->activateForUser('kata-motivasi', $this->user->id);
        $userPlugin = $this->pluginManager->deactivateForUser('kata-motivasi', $this->user->id);

        $this->assertFalse($userPlugin->is_active);
    }

    public function test_plugin_validates_config_correctly(): void
    {
        $plugin = new KataMotivasiPlugin;

        // Valid config
        $errors = $plugin->validateConfig([
            'delivery_time' => '08:00',
            'categories' => ['general', 'business'],
            'include_author' => true,
        ]);
        $this->assertEmpty($errors);

        // Invalid categories
        $errors = $plugin->validateConfig([
            'categories' => ['invalid_category'],
        ]);
        $this->assertArrayHasKey('categories', $errors);
    }

    public function test_plugin_provides_default_config(): void
    {
        $plugin = new KataMotivasiPlugin;
        $defaults = $plugin->getDefaultConfig();

        $this->assertEquals('07:00', $defaults['delivery_time']);
        $this->assertEquals(['general'], $defaults['categories']);
        $this->assertTrue($defaults['include_author']);
    }
}
