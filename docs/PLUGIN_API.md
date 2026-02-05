# Plugin API Reference

Complete API reference for ASPRI Plugin System.

## Table of Contents

1. [REST API Endpoints](#rest-api-endpoints)
2. [BasePlugin Methods](#baseplugin-methods)
3. [PluginManager API](#pluginmanager-api)
4. [Configuration API](#configuration-api)
5. [Scheduling API](#scheduling-api)
6. [Logging API](#logging-api)
7. [Database Schema](#database-schema)

## REST API Endpoints

### List All Plugins

```http
GET /plugins
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "kata-motivasi",
      "name": "Kata Motivasi",
      "description": "Send motivational quotes daily",
      "version": "1.0.0",
      "author": "ASPRI Team",
      "icon": "sparkles",
      "is_active": true,
      "is_user_activated": true,
      "activated_at": "2026-02-01T10:00:00Z"
    }
  ]
}
```

### Get Plugin Details

```http
GET /plugins/{slug}
```

**Parameters:**
- `slug` (string, required): Plugin slug identifier

**Response:**
```json
{
  "data": {
    "id": 1,
    "slug": "kata-motivasi",
    "name": "Kata Motivasi",
    "description": "Send motivational quotes daily",
    "version": "1.0.0",
    "author": "ASPRI Team",
    "icon": "sparkles",
    "is_active": true,
    "config_schema": {
      "delivery_time": {
        "type": "time",
        "label": "Waktu Pengiriman",
        "default": "07:00",
        "required": true
      }
    },
    "current_config": {
      "delivery_time": "08:00",
      "enabled": true
    }
  }
}
```

### Activate Plugin

```http
POST /plugins/{slug}/activate
```

**Parameters:**
- `slug` (string, required): Plugin slug identifier

**Response:**
```json
{
  "message": "Plugin activated successfully",
  "data": {
    "is_active": true,
    "activated_at": "2026-02-05T10:00:00Z"
  }
}
```

**Errors:**
- `404`: Plugin not found
- `400`: Plugin already activated
- `500`: Activation failed

### Deactivate Plugin

```http
POST /plugins/{slug}/deactivate
```

**Parameters:**
- `slug` (string, required): Plugin slug identifier

**Response:**
```json
{
  "message": "Plugin deactivated successfully",
  "data": {
    "is_active": false
  }
}
```

### Get Plugin Configuration

```http
GET /plugins/{slug}/config
```

**Response:**
```json
{
  "data": {
    "delivery_time": "08:00",
    "categories": ["general", "business"],
    "enabled": true
  }
}
```

### Update Plugin Configuration

```http
POST /plugins/{slug}/config
PUT /plugins/{slug}/config
```

**Request Body:**
```json
{
  "delivery_time": "09:00",
  "categories": ["health", "productivity"],
  "enabled": true
}
```

**Response:**
```json
{
  "message": "Configuration updated successfully",
  "data": {
    "delivery_time": "09:00",
    "categories": ["health", "productivity"],
    "enabled": true
  }
}
```

**Errors:**
- `422`: Validation failed
- `404`: Plugin not found

### Reset Plugin Configuration

```http
DELETE /plugins/{slug}/config
```

**Response:**
```json
{
  "message": "Configuration reset to defaults",
  "data": {
    "delivery_time": "07:00",
    "categories": ["general"],
    "enabled": true
  }
}
```

### Get Plugin Logs

```http
GET /plugins/{slug}/logs
```

**Query Parameters:**
- `level` (string, optional): Filter by log level (debug, info, warning, error)
- `per_page` (integer, optional): Items per page (default: 20)
- `page` (integer, optional): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "level": "info",
      "message": "Quote sent successfully",
      "context": {
        "quote_id": 42,
        "delivery_method": "telegram"
      },
      "created_at": "2026-02-05T08:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150
  }
}
```

### Get Plugin Schedules

```http
GET /plugins/{slug}/schedules
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "schedule_type": "daily",
      "schedule_value": "08:00",
      "last_run_at": "2026-02-05T08:00:00Z",
      "next_run_at": "2026-02-06T08:00:00Z",
      "is_active": true
    }
  ]
}
```

## BasePlugin Methods

### Metadata Methods

#### getName(): string

Returns the display name of the plugin.

```php
public function getName(): string
{
    return 'My Awesome Plugin';
}
```

#### getSlug(): string

Returns the unique identifier (slug) for the plugin.

```php
public function getSlug(): string
{
    return 'my-awesome-plugin';
}
```

#### getDescription(): string

Returns a brief description of the plugin's functionality.

```php
public function getDescription(): string
{
    return 'This plugin does amazing things!';
}
```

#### getVersion(): string

Returns the current version of the plugin (semantic versioning).

```php
public function getVersion(): string
{
    return '1.2.3'; // MAJOR.MINOR.PATCH
}
```

#### getAuthor(): string

Returns the author's name or organization.

```php
public function getAuthor(): string
{
    return 'ASPRI Team';
}
```

#### getIcon(): string

Returns the Heroicon name to use as plugin icon.

```php
public function getIcon(): string
{
    return 'sparkles'; // Any Heroicon name
}
```

### Lifecycle Methods

#### install(): void

Called once when plugin is installed (seeded to database).

```php
public function install(): void
{
    // Seed default data
    // Create necessary database records
    // One-time setup tasks
}
```

#### uninstall(): void

Called when plugin is removed from the system (admin only).

```php
public function uninstall(): void
{
    // Clean up plugin data
    // Remove schedules
    // Remove configurations (optional)
}
```

#### activate(): void

Called when a user activates the plugin for their account.

```php
public function activate(): void
{
    $user = auth()->user();
    
    // Create user-specific schedules
    $this->createSchedule($user->id, [
        'schedule_type' => 'daily',
        'schedule_value' => '09:00',
    ]);
    
    // Initialize user configuration
    // Send welcome message
}
```

#### deactivate(): void

Called when a user deactivates the plugin.

```php
public function deactivate(): void
{
    $user = auth()->user();
    
    // Cancel schedules
    $this->deleteSchedules($user->id);
    
    // Keep configuration for potential reactivation
    // Send goodbye message (optional)
}
```

### Configuration Methods

#### getConfigSchema(): array

Returns the configuration schema defining available settings.

```php
public function getConfigSchema(): array
{
    return [
        'field_name' => [
            'type' => 'text|textarea|number|boolean|select|multiselect|time|date|color',
            'label' => 'Field Label',
            'placeholder' => 'Placeholder text',
            'default' => 'default_value',
            'required' => true|false,
            'options' => [], // For select/multiselect
            'min' => 0, // For number
            'max' => 100, // For number
            'step' => 1, // For number
            'rows' => 5, // For textarea
            'condition' => 'other_field === true', // Conditional display
        ],
    ];
}
```

#### getDefaultConfig(): array

Returns the default configuration values.

```php
public function getDefaultConfig(): array
{
    return [
        'enabled' => true,
        'frequency' => 'daily',
        'time' => '09:00',
    ];
}
```

#### validateConfig(array $config): bool

Validates configuration before saving.

```php
public function validateConfig(array $config): bool
{
    // Check required fields
    if (!isset($config['time'])) {
        return false;
    }
    
    // Validate format
    if (!preg_match('/^\d{2}:\d{2}$/', $config['time'])) {
        return false;
    }
    
    // Custom business logic
    if (isset($config['max_count']) && $config['max_count'] < 1) {
        return false;
    }
    
    return true;
}
```

### Execution Method

#### execute(int $userId, array $metadata): void

Called by the scheduler when a scheduled task should run.

```php
public function execute(int $userId, array $metadata): void
{
    try {
        $user = User::find($userId);
        $config = $this->getConfig($userId);
        
        // Perform scheduled task
        $this->doSomething($user, $config, $metadata);
        
        // Log success
        $this->log($userId, 'info', 'Task completed successfully');
        
    } catch (\Exception $e) {
        // Log error
        $this->log($userId, 'error', 'Task failed: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
```

### Helper Methods

#### getConfig(int $userId): array

Get current configuration for a user.

```php
$config = $this->getConfig($userId);
// Returns: ['time' => '09:00', 'enabled' => true, ...]
```

#### updateConfig(int $userId, array $config): void

Update configuration for a user.

```php
$this->updateConfig($userId, [
    'time' => '10:00',
    'enabled' => false,
]);
```

#### createSchedule(int $userId, array $scheduleData): void

Create a new schedule for this plugin.

```php
$this->createSchedule($userId, [
    'schedule_type' => 'daily', // daily|interval|cron|weekly
    'schedule_value' => '09:00', // Time or cron expression
    'metadata' => [
        'custom_field' => 'value',
    ],
]);
```

#### deleteSchedules(int $userId): void

Delete all schedules for this user.

```php
$this->deleteSchedules($userId);
```

#### log(int $userId, string $level, string $message, array $context = []): void

Log plugin activity.

```php
$this->log($userId, 'info', 'Operation completed', [
    'duration_ms' => 123,
    'items_processed' => 5,
]);
```

**Log Levels:**
- `debug`: Detailed debugging information
- `info`: Informational messages
- `warning`: Warning messages
- `error`: Error messages

## PluginManager API

Access via `app(PluginManager::class)` or dependency injection.

### loadPlugins(): Collection

Load all registered plugins.

```php
$manager = app(PluginManager::class);
$plugins = $manager->loadPlugins();

foreach ($plugins as $plugin) {
    echo $plugin->getName();
}
```

### getPlugin(string $slug): ?PluginInterface

Get a specific plugin by slug.

```php
$plugin = $manager->getPlugin('kata-motivasi');

if ($plugin) {
    echo $plugin->getDescription();
}
```

### activatePlugin(string $slug, int $userId): bool

Activate a plugin for a user.

```php
$success = $manager->activatePlugin('kata-motivasi', $userId);
```

### deactivatePlugin(string $slug, int $userId): bool

Deactivate a plugin for a user.

```php
$success = $manager->deactivatePlugin('kata-motivasi', $userId);
```

### isPluginActive(string $slug, int $userId): bool

Check if a plugin is active for a user.

```php
if ($manager->isPluginActive('kata-motivasi', $userId)) {
    // Plugin is active
}
```

### getActivePlugins(int $userId): Collection

Get all active plugins for a user.

```php
$activePlugins = $manager->getActivePlugins($userId);
```

### runScheduledTasks(): void

Execute all due scheduled tasks (called by scheduler).

```php
// In console kernel or schedule command
$manager->runScheduledTasks();
```

## Configuration API

### PluginConfiguration Model

```php
use App\Models\PluginConfiguration;

// Get configuration
$config = PluginConfiguration::where('plugin_id', $pluginId)
    ->where('user_id', $userId)
    ->where('config_key', 'time')
    ->first();

// Value is JSON encoded
$value = json_decode($config->config_value);

// Set configuration
PluginConfiguration::updateOrCreate(
    [
        'plugin_id' => $pluginId,
        'user_id' => $userId,
        'config_key' => 'time',
    ],
    [
        'config_value' => json_encode('09:00'),
    ]
);
```

## Scheduling API

### PluginSchedule Model

```php
use App\Models\PluginSchedule;

// Create schedule
PluginSchedule::create([
    'plugin_id' => $pluginId,
    'user_id' => $userId,
    'schedule_type' => 'daily',
    'schedule_value' => '09:00',
    'next_run_at' => now()->addDay()->setTime(9, 0),
    'is_active' => true,
    'metadata' => json_encode(['key' => 'value']),
]);

// Get pending schedules
$pending = PluginSchedule::where('next_run_at', '<=', now())
    ->where('is_active', true)
    ->get();

// Update after execution
$schedule->update([
    'last_run_at' => now(),
    'next_run_at' => $this->calculateNextRun($schedule),
]);
```

### Schedule Types

| Type | Value Format | Description |
|------|--------------|-------------|
| `daily` | `HH:MM` | Run daily at specific time |
| `interval` | `{minutes}` | Run every X minutes |
| `cron` | Cron expression | Custom cron schedule |
| `weekly` | `{day},{HH:MM}` | Run weekly on specific day |

## Logging API

### PluginLog Model

```php
use App\Models\PluginLog;

// Create log
PluginLog::create([
    'plugin_id' => $pluginId,
    'user_id' => $userId,
    'level' => 'info',
    'message' => 'Operation completed',
    'context' => json_encode(['duration' => 123]),
]);

// Query logs
$logs = PluginLog::where('plugin_id', $pluginId)
    ->where('user_id', $userId)
    ->where('level', 'error')
    ->orderBy('created_at', 'desc')
    ->get();

// Get recent logs
$recentLogs = PluginLog::recent($pluginId, $userId, 10);
```

## Database Schema

### plugins

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| slug | varchar(100) | Unique identifier |
| name | varchar(255) | Display name |
| description | text | Description |
| version | varchar(20) | Version number |
| author | varchar(255) | Author name |
| icon | varchar(255) | Heroicon name |
| class_name | varchar(255) | PHP class name |
| is_system | boolean | System plugin flag |
| is_active | boolean | Active flag |
| installed_at | timestamp | Installation time |
| activated_at | timestamp | Activation time |

### user_plugins

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Foreign key to users |
| plugin_id | bigint | Foreign key to plugins |
| is_active | boolean | Active flag |
| activated_at | timestamp | Activation time |

### plugin_configurations

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| plugin_id | bigint | Foreign key to plugins |
| user_id | bigint | Foreign key to users |
| config_key | varchar(100) | Configuration key |
| config_value | text | JSON value |

### plugin_schedules

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| plugin_id | bigint | Foreign key to plugins |
| user_id | bigint | Foreign key to users |
| schedule_type | varchar(50) | Schedule type |
| schedule_value | varchar(255) | Schedule value |
| last_run_at | timestamp | Last execution |
| next_run_at | timestamp | Next execution |
| is_active | boolean | Active flag |
| metadata | json | Additional data |

### plugin_logs

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| plugin_id | bigint | Foreign key to plugins |
| user_id | bigint | Foreign key to users |
| level | varchar(20) | Log level |
| message | text | Log message |
| context | json | Additional context |
| created_at | timestamp | Creation time |

## Events

### PluginActivated

Fired when a plugin is activated.

```php
use App\Events\PluginActivated;

Event::listen(PluginActivated::class, function ($event) {
    $userId = $event->userId;
    $plugin = $event->plugin;
    
    // Do something
});
```

### PluginDeactivated

Fired when a plugin is deactivated.

```php
use App\Events\PluginDeactivated;

Event::listen(PluginDeactivated::class, function ($event) {
    $userId = $event->userId;
    $plugin = $event->plugin;
    
    // Do something
});
```

### PluginConfigUpdated

Fired when plugin configuration is updated.

```php
use App\Events\PluginConfigUpdated;

Event::listen(PluginConfigUpdated::class, function ($event) {
    $userId = $event->userId;
    $plugin = $event->plugin;
    $config = $event->config;
    
    // Do something
});
```

## Error Codes

| Code | Message | Description |
|------|---------|-------------|
| 404 | Plugin not found | Plugin slug doesn't exist |
| 400 | Plugin already activated | User already activated plugin |
| 400 | Plugin not activated | Cannot deactivate inactive plugin |
| 422 | Validation failed | Configuration validation failed |
| 500 | Activation failed | Plugin activation error |
| 500 | Execution failed | Scheduled task execution error |

## Rate Limits

- API endpoints: 60 requests per minute per user
- Configuration updates: 10 per minute per plugin per user
- Log queries: 30 per minute per user

## Versioning

API follows semantic versioning. Current version: **v1**

Breaking changes will increment major version.
