<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Ai\ToolRegistry;
use App\Services\Plugin\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'telegram_chat_id' => 12345678,
        ]);

        $this->registry = app(ToolRegistry::class);
    }

    public function test_definitions_returns_valid_tool_schemas(): void
    {
        $definitions = $this->registry->definitions($this->user);

        $this->assertNotEmpty($definitions);

        foreach ($definitions as $definition) {
            $this->assertArrayHasKey('name', $definition);
            $this->assertArrayHasKey('description', $definition);
            $this->assertArrayHasKey('parameters', $definition);

            $this->assertIsString($definition['name']);
            $this->assertIsString($definition['description']);

            $parameters = $definition['parameters'];
            $this->assertEquals('object', $parameters['type']);
            $this->assertArrayHasKey('properties', $parameters);
            $this->assertArrayHasKey('required', $parameters);
            $this->assertIsArray($parameters['properties']);
            $this->assertIsArray($parameters['required']);
        }
    }

    public function test_definitions_contains_all_eleven_core_tools(): void
    {
        $definitions = $this->registry->definitions($this->user);
        $names = array_map(fn ($d) => $d['name'], $definitions);

        $expected = [
            'create_transaction',
            'view_balance',
            'view_transactions',
            'create_schedule',
            'update_schedule',
            'delete_schedule',
            'view_schedules',
            'create_note',
            'update_note',
            'delete_note',
            'view_notes',
        ];

        foreach ($expected as $name) {
            $this->assertContains($name, $names, "Missing core tool: {$name}");
        }
    }

    public function test_mutating_tools_are_marked_mutates_true_via_resolve(): void
    {
        $mutatingTools = [
            'create_transaction',
            'create_schedule',
            'update_schedule',
            'delete_schedule',
            'create_note',
            'update_note',
            'delete_note',
        ];

        foreach ($mutatingTools as $name) {
            $resolved = $this->registry->resolve($name);
            $this->assertNotNull($resolved, "resolve() returned null for {$name}");
            $this->assertTrue($resolved['mutates'], "{$name} should be mutates: true");
        }
    }

    public function test_read_tools_are_marked_mutates_false_via_resolve(): void
    {
        $readTools = [
            'view_balance',
            'view_transactions',
            'view_schedules',
            'view_notes',
        ];

        foreach ($readTools as $name) {
            $resolved = $this->registry->resolve($name);
            $this->assertNotNull($resolved, "resolve() returned null for {$name}");
            $this->assertFalse($resolved['mutates'], "{$name} should be mutates: false");
        }
    }

    public function test_create_transaction_schema_shape(): void
    {
        $definitions = $this->registry->definitions($this->user);
        $tool = collect($definitions)->firstWhere('name', 'create_transaction');

        $this->assertNotNull($tool);

        $properties = $tool['parameters']['properties'];
        $required = $tool['parameters']['required'];

        $this->assertEquals(['income', 'expense'], $properties['tx_type']['enum']);
        $this->assertEquals('number', $properties['amount']['type']);
        $this->assertArrayHasKey('category', $properties);
        $this->assertArrayHasKey('note', $properties);
        $this->assertArrayHasKey('occurred_at', $properties);

        $this->assertContains('tx_type', $required);
        $this->assertContains('amount', $required);
        $this->assertNotContains('category', $required);
        $this->assertNotContains('note', $required);
        $this->assertNotContains('occurred_at', $required);
    }

    public function test_update_schedule_has_empty_required(): void
    {
        $definitions = $this->registry->definitions($this->user);
        $tool = collect($definitions)->firstWhere('name', 'update_schedule');

        $this->assertNotNull($tool);
        $this->assertEmpty($tool['parameters']['required']);

        $properties = $tool['parameters']['properties'];
        $this->assertArrayHasKey('schedule_id', $properties);
        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('new_title', $properties);
        $this->assertArrayHasKey('start_time', $properties);
        $this->assertArrayHasKey('end_time', $properties);
        $this->assertArrayHasKey('location', $properties);
        $this->assertArrayHasKey('description', $properties);
    }

    public function test_plugin_tools_excluded_when_inactive_and_included_when_active(): void
    {
        // No active plugins yet.
        $definitions = $this->registry->definitions($this->user);
        $names = array_map(fn ($d) => $d['name'], $definitions);

        $this->assertNotContains('plugin_kata_motivasi_quote', $names);

        // Activate kata-motivasi plugin for the user.
        $pluginManager = app(PluginManager::class);
        $pluginManager->syncPlugins();
        $pluginManager->activateForUser('kata-motivasi', $this->user->id);

        $definitions = $this->registry->definitions($this->user);
        $tool = collect($definitions)->firstWhere('name', 'plugin_kata_motivasi_quote');

        $this->assertNotNull($tool);
        $this->assertEquals('object', $tool['parameters']['type']);

        $properties = $tool['parameters']['properties'];
        $required = $tool['parameters']['required'];

        $this->assertArrayHasKey('category', $properties);
        $this->assertNotContains('category', $required);
    }

    public function test_resolve_core_mutating_tool(): void
    {
        $resolved = $this->registry->resolve('create_transaction');

        $this->assertNotNull($resolved);
        $this->assertEquals('finance', $resolved['module']);
        $this->assertEquals('create_transaction', $resolved['action_type']);
        $this->assertTrue($resolved['mutates']);
    }

    public function test_resolve_core_read_tool(): void
    {
        $resolved = $this->registry->resolve('view_balance');

        $this->assertNotNull($resolved);
        $this->assertEquals('finance', $resolved['module']);
        $this->assertEquals('getFinanceSummary', $resolved['method']);
        $this->assertFalse($resolved['mutates']);
    }

    public function test_resolve_plugin_tool(): void
    {
        $pluginManager = app(PluginManager::class);
        $pluginManager->syncPlugins();

        $resolved = $this->registry->resolve('plugin_kata_motivasi_quote');

        $this->assertNotNull($resolved);
        $this->assertEquals('plugin', $resolved['module']);
        $this->assertFalse($resolved['mutates']);
        $this->assertEquals('kata-motivasi', $resolved['plugin_slug']);
        $this->assertEquals('plugin_kata_motivasi_quote', $resolved['action']);
    }

    public function test_resolve_returns_null_for_unknown_tool(): void
    {
        $this->assertNull($this->registry->resolve('nonexistent_tool'));
    }
}
