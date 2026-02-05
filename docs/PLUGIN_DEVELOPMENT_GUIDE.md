# Plugin Development Guide

## Overview

This guide will walk you through creating a custom plugin for ASPRI. Plugins extend the assistant's capabilities with specialized features that users can activate or deactivate based on their needs.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Plugin Structure](#plugin-structure)
3. [Core Concepts](#core-concepts)
4. [Creating Your First Plugin](#creating-your-first-plugin)
5. [Configuration Schema](#configuration-schema)
6. [Scheduling Tasks](#scheduling-tasks)
7. [Logging & Debugging](#logging--debugging)
8. [Best Practices](#best-practices)
9. [Testing](#testing)
10. [Publishing](#publishing)

## Quick Start

### Prerequisites

- PHP 8.2+
- Laravel 12
- ASPRI development environment setup

### Generate Plugin Scaffold

```bash
php artisan make:plugin MyAwesomePlugin
```

This creates:
```
app/Plugins/MyAwesomePlugin/
├── MyAwesomePlugin.php
├── config-schema.json
└── README.md
```

## Plugin Structure

### Minimal Plugin Structure

```
app/Plugins/YourPlugin/
├── YourPlugin.php              # Main plugin class (required)
├── config-schema.json          # Configuration schema (optional)
├── README.md                   # Plugin documentation (recommended)
├── Services/                   # Business logic services
├── Commands/                   # Artisan commands
└── Database/                   # Seeders, default data
```

### Advanced Plugin Structure

```
app/Plugins/YourPlugin/
├── YourPlugin.php
├── config-schema.json
├── README.md
├── Services/
│   ├── YourService.php
│   └── YourRepository.php
├── Commands/
│   └── YourCommand.php
├── Jobs/
│   └── YourJob.php
├── Database/
│   ├── seeders/
│   └── data.json
├── Tests/
│   └── YourPluginTest.php
└── resources/
    └── views/
        └── config-form.vue    # Custom config UI
```

## Core Concepts

### PluginInterface

Every plugin must implement `PluginInterface`:

```php
namespace App\Services\Plugin\Contracts;

interface PluginInterface
{
    // Metadata
    public function getName(): string;
    public function getSlug(): string;
    public function getDescription(): string;
    public function getVersion(): string;
    public function getAuthor(): string;
    public function getIcon(): string;
    
    // Lifecycle
    public function install(): void;
    public function uninstall(): void;
    public function activate(): void;
    public function deactivate(): void;
    
    // Configuration
    public function getConfigSchema(): array;
    public function getDefaultConfig(): array;
    public function validateConfig(array $config): bool;
}
```

### BasePlugin

Extend `BasePlugin` for convenience:

```php
namespace App\Plugins\MyPlugin;

use App\Services\Plugin\BasePlugin;

class MyPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'My Awesome Plugin';
    }

    public function getSlug(): string
    {
        return 'my-awesome-plugin';
    }

    public function getDescription(): string
    {
        return 'This plugin does awesome things!';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'Your Name';
    }

    public function getIcon(): string
    {
        return 'sparkles'; // Heroicon name
    }

    public function install(): void
    {
        // Run on plugin installation
        // e.g., seed default data
    }

    public function uninstall(): void
    {
        // Cleanup on uninstallation
        // e.g., remove plugin data
    }

    public function activate(): void
    {
        // Run when user activates plugin
        // e.g., create schedules
    }

    public function deactivate(): void
    {
        // Run when user deactivates plugin
        // e.g., cancel schedules
    }
}
```

## Creating Your First Plugin

Let's create a "Daily Reminder" plugin that sends a custom reminder message daily.

### Step 1: Create Plugin Class

```php
// app/Plugins/DailyReminder/DailyReminderPlugin.php

namespace App\Plugins\DailyReminder;

use App\Services\Plugin\BasePlugin;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class DailyReminderPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Daily Reminder';
    }

    public function getSlug(): string
    {
        return 'daily-reminder';
    }

    public function getDescription(): string
    {
        return 'Send yourself a daily reminder message at a specific time.';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getAuthor(): string
    {
        return 'ASPRI Team';
    }

    public function getIcon(): string
    {
        return 'bell';
    }

    public function getConfigSchema(): array
    {
        return [
            'reminder_time' => [
                'type' => 'time',
                'label' => 'Reminder Time',
                'default' => '09:00',
                'required' => true,
            ],
            'reminder_message' => [
                'type' => 'textarea',
                'label' => 'Reminder Message',
                'default' => 'Don\'t forget to review your goals today!',
                'required' => true,
            ],
            'enabled' => [
                'type' => 'boolean',
                'label' => 'Enable Reminders',
                'default' => true,
            ],
        ];
    }

    public function getDefaultConfig(): array
    {
        return [
            'reminder_time' => '09:00',
            'reminder_message' => 'Don\'t forget to review your goals today!',
            'enabled' => true,
        ];
    }

    public function validateConfig(array $config): bool
    {
        if (!isset($config['reminder_time']) || !preg_match('/^\d{2}:\d{2}$/', $config['reminder_time'])) {
            return false;
        }

        if (!isset($config['reminder_message']) || empty($config['reminder_message'])) {
            return false;
        }

        return true;
    }

    public function activate(): void
    {
        // Create schedule for this plugin
        $user = auth()->user();
        $config = $this->getConfig($user->id);

        $this->createSchedule($user->id, [
            'schedule_type' => 'daily',
            'schedule_value' => $config['reminder_time'],
            'metadata' => [
                'message' => $config['reminder_message'],
            ],
        ]);

        Log::info("Daily Reminder activated for user {$user->id}");
    }

    public function deactivate(): void
    {
        // Remove schedules
        $user = auth()->user();
        $this->deleteSchedules($user->id);

        Log::info("Daily Reminder deactivated for user {$user->id}");
    }

    public function execute(int $userId, array $metadata): void
    {
        // This method is called by the scheduler
        $telegramService = app(TelegramService::class);
        
        try {
            $telegramService->sendMessage($userId, $metadata['message']);
            
            $this->log($userId, 'info', 'Reminder sent successfully', $metadata);
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Failed to send reminder: ' . $e->getMessage(), $metadata);
        }
    }
}
```

### Step 2: Register Plugin

Add to `database/seeders/PluginSeeder.php`:

```php
use App\Plugins\DailyReminder\DailyReminderPlugin;

public function run(): void
{
    $plugins = [
        // ... existing plugins
        [
            'plugin' => new DailyReminderPlugin(),
            'is_system' => true,
        ],
    ];

    // ... rest of seeder
}
```

### Step 3: Create Command (Optional)

For manual testing:

```php
// app/Plugins/DailyReminder/Commands/SendReminderCommand.php

namespace App\Plugins\DailyReminder\Commands;

use App\Models\User;
use App\Plugins\DailyReminder\DailyReminderPlugin;
use Illuminate\Console\Command;

class SendReminderCommand extends Command
{
    protected $signature = 'plugin:daily-reminder {userId}';
    protected $description = 'Send daily reminder to a user';

    public function handle(): int
    {
        $userId = $this->argument('userId');
        $user = User::find($userId);

        if (!$user) {
            $this->error('User not found');
            return 1;
        }

        $plugin = new DailyReminderPlugin();
        $config = $plugin->getConfig($userId);

        $plugin->execute($userId, ['message' => $config['reminder_message']]);

        $this->info('Reminder sent!');
        return 0;
    }
}
```

### Step 4: Test Your Plugin

```bash
# Run seeder
php artisan db:seed --class=PluginSeeder

# Activate plugin via UI or:
php artisan tinker
> $user = User::first();
> $plugin = new \App\Plugins\DailyReminder\DailyReminderPlugin();
> $plugin->activate();

# Test command
php artisan plugin:daily-reminder 1
```

## Configuration Schema

### Supported Field Types

```php
public function getConfigSchema(): array
{
    return [
        // Text input
        'text_field' => [
            'type' => 'text',
            'label' => 'Text Field',
            'placeholder' => 'Enter text...',
            'default' => '',
            'required' => false,
        ],

        // Textarea
        'description' => [
            'type' => 'textarea',
            'label' => 'Description',
            'rows' => 5,
            'default' => '',
            'required' => false,
        ],

        // Number
        'count' => [
            'type' => 'number',
            'label' => 'Count',
            'min' => 0,
            'max' => 100,
            'step' => 1,
            'default' => 10,
            'required' => true,
        ],

        // Boolean/Toggle
        'enabled' => [
            'type' => 'boolean',
            'label' => 'Enable Feature',
            'default' => true,
        ],

        // Select dropdown
        'frequency' => [
            'type' => 'select',
            'label' => 'Frequency',
            'options' => [
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
            ],
            'default' => 'daily',
            'required' => true,
        ],

        // Multi-select
        'categories' => [
            'type' => 'multiselect',
            'label' => 'Categories',
            'options' => [
                'health' => 'Health',
                'work' => 'Work',
                'personal' => 'Personal',
            ],
            'default' => ['personal'],
            'required' => false,
        ],

        // Time picker
        'time' => [
            'type' => 'time',
            'label' => 'Time',
            'default' => '09:00',
            'required' => true,
        ],

        // Date picker
        'date' => [
            'type' => 'date',
            'label' => 'Date',
            'default' => null,
            'required' => false,
        ],

        // Color picker
        'color' => [
            'type' => 'color',
            'label' => 'Color',
            'default' => '#3B82F6',
        ],

        // Conditional fields
        'custom_enabled' => [
            'type' => 'boolean',
            'label' => 'Enable Custom',
            'default' => false,
        ],
        'custom_value' => [
            'type' => 'text',
            'label' => 'Custom Value',
            'default' => '',
            'condition' => 'custom_enabled === true', // Only shown when custom_enabled is true
        ],
    ];
}
```

## Scheduling Tasks

### Schedule Types

```php
// Daily at specific time
$this->createSchedule($userId, [
    'schedule_type' => 'daily',
    'schedule_value' => '09:00',
]);

// Every X minutes
$this->createSchedule($userId, [
    'schedule_type' => 'interval',
    'schedule_value' => '30', // 30 minutes
]);

// Cron expression
$this->createSchedule($userId, [
    'schedule_type' => 'cron',
    'schedule_value' => '0 9 * * 1', // Every Monday at 9 AM
]);

// Weekly on specific day
$this->createSchedule($userId, [
    'schedule_type' => 'weekly',
    'schedule_value' => 'monday,09:00',
]);
```

### Execute Method

The scheduler calls your plugin's `execute()` method:

```php
public function execute(int $userId, array $metadata): void
{
    // Your scheduled task logic here
    // Access user: $user = User::find($userId);
    // Access metadata: $metadata['key']
    
    try {
        // Do something
        $this->log($userId, 'info', 'Task executed successfully');
    } catch (\Exception $e) {
        $this->log($userId, 'error', 'Task failed: ' . $e->getMessage());
    }
}
```

## Logging & Debugging

### Using Plugin Logger

```php
// Log levels: 'debug', 'info', 'warning', 'error'

// Simple log
$this->log($userId, 'info', 'Something happened');

// Log with context
$this->log($userId, 'debug', 'Processing data', [
    'item_count' => 5,
    'duration_ms' => 123,
]);

// Log error
$this->log($userId, 'error', 'Failed to process', [
    'error' => $exception->getMessage(),
    'trace' => $exception->getTraceAsString(),
]);
```

### Viewing Logs

Users can view plugin logs in the UI:
- Go to Plugins → [Your Plugin] → Configure
- View "Activity Log" tab

Or via database:
```sql
SELECT * FROM plugin_logs 
WHERE plugin_id = ? 
ORDER BY created_at DESC;
```

## Best Practices

### 1. Plugin Independence

✅ **Good**: Self-contained functionality
```php
class MyPlugin extends BasePlugin
{
    public function execute(int $userId, array $metadata): void
    {
        // Use services, not direct model manipulation
        $service = new MyPluginService();
        $service->doSomething($userId);
    }
}
```

❌ **Bad**: Tightly coupled with core models
```php
public function execute(int $userId, array $metadata): void
{
    // Don't directly manipulate core models
    User::find($userId)->update(['some_field' => 'value']);
}
```

### 2. Configuration Validation

✅ **Good**: Validate all config inputs
```php
public function validateConfig(array $config): bool
{
    if (!isset($config['time']) || !preg_match('/^\d{2}:\d{2}$/', $config['time'])) {
        return false;
    }
    
    if (isset($config['max_count']) && $config['max_count'] < 1) {
        return false;
    }
    
    return true;
}
```

### 3. Error Handling

✅ **Good**: Graceful failure with logging
```php
public function execute(int $userId, array $metadata): void
{
    try {
        $this->doSomething();
    } catch (\Exception $e) {
        $this->log($userId, 'error', 'Execution failed', [
            'error' => $e->getMessage(),
        ]);
        // Don't rethrow - let plugin fail gracefully
    }
}
```

### 4. Performance

✅ **Good**: Use queues for heavy operations
```php
public function execute(int $userId, array $metadata): void
{
    // Dispatch heavy work to queue
    dispatch(new ProcessPluginJob($userId, $metadata));
}
```

### 5. User Privacy

✅ **Good**: Only access user's own data
```php
public function execute(int $userId, array $metadata): void
{
    $user = User::find($userId);
    $userTransactions = $user->transactions; // ✅ User's own data
}
```

❌ **Bad**: Access other users' data
```php
public function execute(int $userId, array $metadata): void
{
    $allUsers = User::all(); // ❌ Don't access all users
}
```

## Testing

### Unit Test Example

```php
// tests/Unit/Plugins/DailyReminderTest.php

namespace Tests\Unit\Plugins;

use App\Plugins\DailyReminder\DailyReminderPlugin;
use Tests\TestCase;

class DailyReminderTest extends TestCase
{
    public function test_plugin_metadata(): void
    {
        $plugin = new DailyReminderPlugin();

        $this->assertEquals('Daily Reminder', $plugin->getName());
        $this->assertEquals('daily-reminder', $plugin->getSlug());
        $this->assertEquals('1.0.0', $plugin->getVersion());
    }

    public function test_config_validation(): void
    {
        $plugin = new DailyReminderPlugin();

        // Valid config
        $this->assertTrue($plugin->validateConfig([
            'reminder_time' => '09:00',
            'reminder_message' => 'Test message',
            'enabled' => true,
        ]));

        // Invalid time format
        $this->assertFalse($plugin->validateConfig([
            'reminder_time' => '9:00', // Should be 09:00
            'reminder_message' => 'Test',
            'enabled' => true,
        ]));

        // Missing message
        $this->assertFalse($plugin->validateConfig([
            'reminder_time' => '09:00',
            'reminder_message' => '',
            'enabled' => true,
        ]));
    }

    public function test_default_config(): void
    {
        $plugin = new DailyReminderPlugin();
        $config = $plugin->getDefaultConfig();

        $this->assertArrayHasKey('reminder_time', $config);
        $this->assertArrayHasKey('reminder_message', $config);
        $this->assertArrayHasKey('enabled', $config);
    }
}
```

### Feature Test Example

```php
// tests/Feature/Plugins/DailyReminderFeatureTest.php

namespace Tests\Feature\Plugins;

use App\Models\Plugin;
use App\Models\User;
use App\Plugins\DailyReminder\DailyReminderPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReminderFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_activate_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'slug' => 'daily-reminder',
            'class_name' => DailyReminderPlugin::class,
        ]);

        $response = $this->actingAs($user)
            ->post("/plugins/{$plugin->slug}/activate");

        $response->assertRedirect();
        
        $this->assertDatabaseHas('user_plugins', [
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'is_active' => true,
        ]);
    }

    public function test_user_can_configure_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create([
            'slug' => 'daily-reminder',
            'class_name' => DailyReminderPlugin::class,
        ]);

        $response = $this->actingAs($user)
            ->post("/plugins/{$plugin->slug}/config", [
                'reminder_time' => '10:00',
                'reminder_message' => 'Custom reminder',
                'enabled' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('plugin_configurations', [
            'plugin_id' => $plugin->id,
            'user_id' => $user->id,
            'config_key' => 'reminder_time',
            'config_value' => '"10:00"',
        ]);
    }

    public function test_scheduled_task_executes(): void
    {
        $user = User::factory()->create();
        $plugin = new DailyReminderPlugin();

        // Mock Telegram service
        $this->mock(\App\Services\TelegramService::class)
            ->shouldReceive('sendMessage')
            ->once()
            ->with($user->id, 'Test message');

        $plugin->execute($user->id, ['message' => 'Test message']);

        // Check log was created
        $this->assertDatabaseHas('plugin_logs', [
            'user_id' => $user->id,
            'level' => 'info',
        ]);
    }
}
```

## Publishing

### 1. Documentation

Create a README.md in your plugin directory:

```markdown
# Daily Reminder Plugin

Send yourself daily reminders at a specific time via Telegram.

## Features

- Schedule daily reminders
- Customize reminder message
- Enable/disable anytime

## Configuration

- **Reminder Time**: When to send the reminder (24-hour format)
- **Reminder Message**: Your custom reminder text
- **Enable Reminders**: Turn reminders on/off

## Usage

1. Activate the plugin
2. Set your reminder time and message
3. Receive daily reminders via Telegram!

## Requirements

- Telegram account linked to ASPRI
- Active Telegram bot

## Support

For issues or questions, contact [support@aspri.com](mailto:support@aspri.com)
```

### 2. Version Control

Follow semantic versioning (MAJOR.MINOR.PATCH):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes

### 3. Changelog

Maintain a changelog:

```markdown
# Changelog

## [1.1.0] - 2026-02-15
### Added
- Multiple reminders per day
- Weekend skip option

### Fixed
- Timezone handling bug

## [1.0.0] - 2026-02-01
### Added
- Initial release
- Daily reminder functionality
```

## Examples

See example plugins in `app/Plugins/`:

- **KataMotivasi**: Sends motivational quotes
- **PengingatMinumAir**: Water drinking reminders
- **ExpenseAlert**: Budget alert notifications

## Support

- Documentation: [docs/PLUGINS.md](PLUGINS.md)
- API Reference: [docs/PLUGIN_API.md](PLUGIN_API.md)
- Issues: [GitHub Issues](https://github.com/azmifauzan/aspri/issues)

## License

All plugins follow the main ASPRI license (MIT).
