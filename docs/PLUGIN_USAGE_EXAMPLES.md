# Plugin Usage Examples

Complete examples of using ASPRI Plugin System in various scenarios.

## Table of Contents

1. [Basic Plugin Usage](#basic-plugin-usage)
2. [Configuration Examples](#configuration-examples)
3. [Scheduling Examples](#scheduling-examples)
4. [Integration Examples](#integration-examples)
5. [Real-World Scenarios](#real-world-scenarios)

## Basic Plugin Usage

### Activating a Plugin

**Via Web UI:**
1. Go to `/plugins`
2. Find the plugin you want
3. Click "Activate"
4. Configure settings
5. Save

**Via Code:**
```php
use App\Services\Plugin\PluginManager;

$pluginManager = app(PluginManager::class);
$pluginManager->activatePlugin('kata-motivasi', auth()->id());
```

### Configuring a Plugin

**Update configuration:**
```php
$plugin = new KataMotivasiPlugin();
$plugin->updateConfig(auth()->id(), [
    'delivery_time' => '08:00',
    'categories' => ['health', 'productivity'],
    'enabled' => true,
]);
```

**Get current configuration:**
```php
$config = $plugin->getConfig(auth()->id());
echo $config['delivery_time']; // '08:00'
```

## Configuration Examples

### Simple Text Configuration

```php
public function getConfigSchema(): array
{
    return [
        'greeting_message' => [
            'type' => 'text',
            'label' => 'Greeting Message',
            'placeholder' => 'Enter your greeting...',
            'default' => 'Good morning!',
            'required' => true,
        ],
    ];
}
```

**Usage:**
```php
$config = $this->getConfig($userId);
$greeting = $config['greeting_message'];
```

### Multi-Select with Options

```php
public function getConfigSchema(): array
{
    return [
        'notification_channels' => [
            'type' => 'multiselect',
            'label' => 'Notification Channels',
            'options' => [
                'telegram' => 'Telegram',
                'email' => 'Email',
                'web' => 'Web Notification',
            ],
            'default' => ['telegram'],
            'required' => true,
        ],
    ];
}
```

**Usage:**
```php
$config = $this->getConfig($userId);
$channels = $config['notification_channels']; // ['telegram', 'web']

if (in_array('telegram', $channels)) {
    $this->sendTelegram($userId, $message);
}

if (in_array('email', $channels)) {
    $this->sendEmail($user->email, $message);
}
```

### Conditional Fields

```php
public function getConfigSchema(): array
{
    return [
        'use_custom_time' => [
            'type' => 'boolean',
            'label' => 'Use Custom Time',
            'default' => false,
        ],
        'custom_time' => [
            'type' => 'time',
            'label' => 'Custom Time',
            'default' => '09:00',
            'condition' => 'use_custom_time === true',
            'required' => false,
        ],
    ];
}
```

### Number with Validation

```php
public function getConfigSchema(): array
{
    return [
        'reminder_count' => [
            'type' => 'number',
            'label' => 'Daily Reminders',
            'min' => 1,
            'max' => 10,
            'step' => 1,
            'default' => 3,
            'required' => true,
        ],
    ];
}

public function validateConfig(array $config): bool
{
    if ($config['reminder_count'] < 1 || $config['reminder_count'] > 10) {
        throw new \InvalidArgumentException('Reminder count must be between 1 and 10');
    }
    return true;
}
```

## Scheduling Examples

### Daily at Fixed Time

```php
public function activate(): void
{
    $config = $this->getConfig(auth()->id());
    
    $this->createSchedule(auth()->id(), [
        'schedule_type' => 'daily',
        'schedule_value' => $config['delivery_time'], // '08:00'
        'metadata' => [
            'action' => 'send_reminder',
        ],
    ]);
}

public function execute(int $userId, array $metadata): void
{
    // This runs every day at 08:00
    $this->sendReminder($userId);
}
```

### Multiple Times Per Day

```php
public function activate(): void
{
    $config = $this->getConfig(auth()->id());
    $times = $config['reminder_times']; // ['08:00', '12:00', '18:00']
    
    foreach ($times as $time) {
        $this->createSchedule(auth()->id(), [
            'schedule_type' => 'daily',
            'schedule_value' => $time,
            'metadata' => [
                'time_slot' => $time,
            ],
        ]);
    }
}
```

### Interval-Based (Every X Minutes)

```php
public function activate(): void
{
    $this->createSchedule(auth()->id(), [
        'schedule_type' => 'interval',
        'schedule_value' => '30', // Every 30 minutes
        'metadata' => [
            'action' => 'check_updates',
        ],
    ]);
}
```

### Cron Expression

```php
public function activate(): void
{
    // Every Monday at 9 AM
    $this->createSchedule(auth()->id(), [
        'schedule_type' => 'cron',
        'schedule_value' => '0 9 * * 1',
        'metadata' => [
            'action' => 'weekly_summary',
        ],
    ]);
}
```

### Conditional Scheduling

```php
public function activate(): void
{
    $config = $this->getConfig(auth()->id());
    
    // Only create schedule if enabled
    if ($config['enabled']) {
        $this->createSchedule(auth()->id(), [
            'schedule_type' => 'daily',
            'schedule_value' => $config['time'],
        ]);
    }
}

public function updateConfiguration(int $userId, array $config): void
{
    parent::updateConfig($userId, $config);
    
    // Update schedules when config changes
    $this->deleteSchedules($userId);
    
    if ($config['enabled']) {
        $this->createSchedule($userId, [
            'schedule_type' => 'daily',
            'schedule_value' => $config['time'],
        ]);
    }
}
```

## Integration Examples

### Telegram Integration

```php
use App\Services\TelegramService;

public function execute(int $userId, array $metadata): void
{
    $user = User::find($userId);
    $telegram = app(TelegramService::class);
    
    try {
        $message = $this->generateMessage($user);
        $telegram->sendMessage($userId, $message);
        
        $this->log($userId, 'info', 'Message sent via Telegram');
    } catch (\Exception $e) {
        $this->log($userId, 'error', 'Failed to send Telegram message', [
            'error' => $e->getMessage(),
        ]);
    }
}
```

### Finance Module Integration

```php
use App\Models\FinanceTransaction;
use App\Models\FinanceCategory;

public function execute(int $userId, array $metadata): void
{
    $user = User::find($userId);
    $config = $this->getConfig($userId);
    
    // Get this month's expenses
    $monthlyExpenses = FinanceTransaction::where('user_id', $userId)
        ->where('type', 'expense')
        ->whereYear('transaction_date', now()->year)
        ->whereMonth('transaction_date', now()->month)
        ->sum('amount');
    
    // Get budget
    $budget = $config['monthly_budget'];
    $percentage = ($monthlyExpenses / $budget) * 100;
    
    // Send alert if over threshold
    if ($percentage >= $config['alert_threshold']) {
        $this->sendAlert($userId, [
            'expenses' => $monthlyExpenses,
            'budget' => $budget,
            'percentage' => $percentage,
        ]);
    }
}
```

### Schedule Module Integration

```php
use App\Models\Event;

public function execute(int $userId, array $metadata): void
{
    // Get today's events
    $todayEvents = Event::whereHas('calendar', function($q) use ($userId) {
        $q->where('user_id', $userId);
    })
    ->whereDate('start_time', today())
    ->orderBy('start_time')
    ->get();
    
    if ($todayEvents->isNotEmpty()) {
        $message = "Your schedule for today:\n\n";
        
        foreach ($todayEvents as $event) {
            $time = $event->start_time->format('H:i');
            $message .= "• {$time} - {$event->title}\n";
        }
        
        $this->sendMessage($userId, $message);
    }
}
```

### Chat/AI Integration

```php
use App\Services\AI\AiProviderInterface;

public function execute(int $userId, array $metadata): void
{
    $ai = app(AiProviderInterface::class);
    $config = $this->getConfig($userId);
    
    // Generate personalized content using AI
    $prompt = "Generate a motivational quote about {$config['theme']}";
    
    $response = $ai->generateText($prompt, [
        'max_tokens' => 100,
        'temperature' => 0.8,
    ]);
    
    $this->sendMessage($userId, $response);
}
```

## Real-World Scenarios

### Scenario 1: Water Drinking Reminder

**Requirements:**
- Remind user to drink water every 2 hours
- Only during working hours (8 AM - 6 PM)
- Track daily consumption
- Send summary at end of day

**Implementation:**

```php
class PengingatMinumAirPlugin extends BasePlugin
{
    public function getConfigSchema(): array
    {
        return [
            'daily_target' => [
                'type' => 'number',
                'label' => 'Daily Target (glasses)',
                'min' => 1,
                'max' => 20,
                'default' => 8,
            ],
            'interval_minutes' => [
                'type' => 'number',
                'label' => 'Reminder Interval (minutes)',
                'min' => 30,
                'max' => 240,
                'step' => 30,
                'default' => 120,
            ],
            'start_time' => [
                'type' => 'time',
                'label' => 'Start Time',
                'default' => '08:00',
            ],
            'end_time' => [
                'type' => 'time',
                'label' => 'End Time',
                'default' => '18:00',
            ],
        ];
    }

    public function activate(): void
    {
        $config = $this->getConfig(auth()->id());
        
        // Create interval-based reminder
        $this->createSchedule(auth()->id(), [
            'schedule_type' => 'interval',
            'schedule_value' => $config['interval_minutes'],
            'metadata' => [
                'action' => 'send_reminder',
                'start_time' => $config['start_time'],
                'end_time' => $config['end_time'],
            ],
        ]);
        
        // Create end-of-day summary
        $this->createSchedule(auth()->id(), [
            'schedule_type' => 'daily',
            'schedule_value' => $config['end_time'],
            'metadata' => [
                'action' => 'send_summary',
            ],
        ]);
    }

    public function execute(int $userId, array $metadata): void
    {
        $now = now();
        $currentTime = $now->format('H:i');
        
        // Check if within active hours
        if ($currentTime < $metadata['start_time'] || $currentTime > $metadata['end_time']) {
            return;
        }
        
        if ($metadata['action'] === 'send_reminder') {
            $this->sendReminder($userId);
        } elseif ($metadata['action'] === 'send_summary') {
            $this->sendDailySummary($userId);
        }
    }

    protected function sendReminder(int $userId): void
    {
        $config = $this->getConfig($userId);
        $consumed = $this->getTodayConsumption($userId);
        $remaining = $config['daily_target'] - $consumed;
        
        $message = "💧 Time to drink water!\n\n";
        $message .= "Today: {$consumed}/{$config['daily_target']} glasses\n";
        
        if ($remaining > 0) {
            $message .= "Remaining: {$remaining} glasses";
        } else {
            $message .= "🎉 Target achieved!";
        }
        
        app(TelegramService::class)->sendMessage($userId, $message);
        
        $this->log($userId, 'info', 'Reminder sent', [
            'consumed' => $consumed,
            'target' => $config['daily_target'],
        ]);
    }

    protected function sendDailySummary(int $userId): void
    {
        $config = $this->getConfig($userId);
        $consumed = $this->getTodayConsumption($userId);
        $percentage = ($consumed / $config['daily_target']) * 100;
        
        $message = "📊 Daily Water Consumption Summary\n\n";
        $message .= "Consumed: {$consumed}/{$config['daily_target']} glasses\n";
        $message .= "Achievement: " . round($percentage) . "%\n\n";
        
        if ($percentage >= 100) {
            $message .= "🎉 Great job! Target achieved!";
        } else {
            $message .= "💪 Keep it up tomorrow!";
        }
        
        app(TelegramService::class)->sendMessage($userId, $message);
    }

    protected function getTodayConsumption(int $userId): int
    {
        // Get from plugin configuration
        $key = 'consumption_' . now()->format('Y-m-d');
        $consumption = PluginConfiguration::where('user_plugin_id', $this->getUserPluginId($userId))
            ->where('config_key', $key)
            ->value('config_value');
        
        return $consumption ? (int) json_decode($consumption) : 0;
    }

    public function recordConsumption(int $userId, int $glasses): void
    {
        $current = $this->getTodayConsumption($userId);
        $new = $current + $glasses;
        
        $key = 'consumption_' . now()->format('Y-m-d');
        
        PluginConfiguration::updateOrCreate(
            [
                'user_plugin_id' => $this->getUserPluginId($userId),
                'config_key' => $key,
            ],
            [
                'config_value' => json_encode($new),
            ]
        );
        
        $this->log($userId, 'info', 'Consumption recorded', [
            'glasses' => $glasses,
            'total' => $new,
        ]);
    }
}
```

### Scenario 2: Budget Alert Plugin

**Requirements:**
- Monitor expenses by category
- Alert when reaching 80%, 90%, 100% of budget
- Weekly spending report
- Category-specific alerts

**Implementation:**

```php
class ExpenseAlertPlugin extends BasePlugin
{
    public function getConfigSchema(): array
    {
        return [
            'alert_thresholds' => [
                'type' => 'multiselect',
                'label' => 'Alert Thresholds',
                'options' => [
                    '50' => '50% of budget',
                    '75' => '75% of budget',
                    '90' => '90% of budget',
                    '100' => '100% of budget',
                ],
                'default' => ['75', '90', '100'],
            ],
            'monitored_categories' => [
                'type' => 'multiselect',
                'label' => 'Monitor Categories',
                'options' => $this->getCategoryOptions(),
                'default' => [],
            ],
            'weekly_report' => [
                'type' => 'boolean',
                'label' => 'Send Weekly Report',
                'default' => true,
            ],
        ];
    }

    protected function getCategoryOptions(): array
    {
        $categories = FinanceCategory::where('type', 'expense')
            ->pluck('name', 'id')
            ->toArray();
        
        return $categories;
    }

    public function activate(): void
    {
        $config = $this->getConfig(auth()->id());
        
        // Check budgets daily at 8 PM
        $this->createSchedule(auth()->id(), [
            'schedule_type' => 'daily',
            'schedule_value' => '20:00',
            'metadata' => [
                'action' => 'check_budgets',
            ],
        ]);
        
        // Weekly report on Sunday at 6 PM
        if ($config['weekly_report']) {
            $this->createSchedule(auth()->id(), [
                'schedule_type' => 'cron',
                'schedule_value' => '0 18 * * 0', // Sunday 6 PM
                'metadata' => [
                    'action' => 'weekly_report',
                ],
            ]);
        }
    }

    public function execute(int $userId, array $metadata): void
    {
        if ($metadata['action'] === 'check_budgets') {
            $this->checkBudgets($userId);
        } elseif ($metadata['action'] === 'weekly_report') {
            $this->sendWeeklyReport($userId);
        }
    }

    protected function checkBudgets(int $userId): void
    {
        $config = $this->getConfig($userId);
        $categories = $config['monitored_categories'];
        
        if (empty($categories)) {
            // Monitor all categories
            $categories = FinanceCategory::where('type', 'expense')
                ->pluck('id')
                ->toArray();
        }
        
        foreach ($categories as $categoryId) {
            $this->checkCategoryBudget($userId, $categoryId, $config);
        }
    }

    protected function checkCategoryBudget(int $userId, int $categoryId, array $config): void
    {
        $category = FinanceCategory::find($categoryId);
        
        if (!$category || !$category->budget_amount) {
            return;
        }
        
        // Get this month's spending
        $spent = FinanceTransaction::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', now()->month)
            ->sum('amount');
        
        $budget = $category->budget_amount;
        $percentage = ($spent / $budget) * 100;
        
        // Check if any threshold is crossed
        foreach ($config['alert_thresholds'] as $threshold) {
            if ($percentage >= (int) $threshold && !$this->hasAlertBeenSent($userId, $categoryId, $threshold)) {
                $this->sendBudgetAlert($userId, $category, $spent, $budget, $percentage, $threshold);
                $this->markAlertSent($userId, $categoryId, $threshold);
            }
        }
    }

    protected function sendBudgetAlert(int $userId, FinanceCategory $category, float $spent, float $budget, float $percentage, string $threshold): void
    {
        $emoji = $this->getEmoji($percentage);
        
        $message = "{$emoji} Budget Alert\n\n";
        $message .= "Category: {$category->name}\n";
        $message .= "Spent: Rp " . number_format($spent, 0, ',', '.') . "\n";
        $message .= "Budget: Rp " . number_format($budget, 0, ',', '.') . "\n";
        $message .= "Usage: " . round($percentage) . "%\n\n";
        
        if ($percentage >= 100) {
            $message .= "⚠️ Budget exceeded!";
        } else {
            $remaining = $budget - $spent;
            $message .= "Remaining: Rp " . number_format($remaining, 0, ',', '.');
        }
        
        app(TelegramService::class)->sendMessage($userId, $message);
        
        $this->log($userId, 'warning', "Budget alert sent: {$category->name}", [
            'category_id' => $category->id,
            'spent' => $spent,
            'budget' => $budget,
            'percentage' => $percentage,
            'threshold' => $threshold,
        ]);
    }

    protected function getEmoji(float $percentage): string
    {
        if ($percentage >= 100) return '🔴';
        if ($percentage >= 90) return '🟠';
        if ($percentage >= 75) return '🟡';
        return '🟢';
    }

    protected function hasAlertBeenSent(int $userId, int $categoryId, string $threshold): bool
    {
        $key = "alert_{$categoryId}_{$threshold}_" . now()->format('Y-m');
        
        $exists = PluginConfiguration::where('user_plugin_id', $this->getUserPluginId($userId))
            ->where('config_key', $key)
            ->exists();
        
        return $exists;
    }

    protected function markAlertSent(int $userId, int $categoryId, string $threshold): void
    {
        $key = "alert_{$categoryId}_{$threshold}_" . now()->format('Y-m');
        
        PluginConfiguration::create([
            'user_plugin_id' => $this->getUserPluginId($userId),
            'config_key' => $key,
            'config_value' => json_encode(now()->toDateTimeString()),
        ]);
    }

    protected function sendWeeklyReport(int $userId): void
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        
        $expenses = FinanceTransaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$startOfWeek, $endOfWeek])
            ->with('category')
            ->get();
        
        $totalSpent = $expenses->sum('amount');
        $byCategory = $expenses->groupBy('category_id');
        
        $message = "📊 Weekly Spending Report\n";
        $message .= now()->startOfWeek()->format('M d') . " - " . now()->endOfWeek()->format('M d') . "\n\n";
        $message .= "Total: Rp " . number_format($totalSpent, 0, ',', '.') . "\n\n";
        $message .= "By Category:\n";
        
        foreach ($byCategory as $categoryId => $transactions) {
            $category = $transactions->first()->category;
            $amount = $transactions->sum('amount');
            $percentage = ($amount / $totalSpent) * 100;
            
            $message .= "• {$category->name}: Rp " . number_format($amount, 0, ',', '.');
            $message .= " (" . round($percentage) . "%)\n";
        }
        
        app(TelegramService::class)->sendMessage($userId, $message);
        
        $this->log($userId, 'info', 'Weekly report sent', [
            'total_spent' => $totalSpent,
            'categories_count' => $byCategory->count(),
        ]);
    }
}
```

## Testing Your Plugin

### Unit Test Example

```php
namespace Tests\Unit\Plugins;

use Tests\TestCase;
use App\Plugins\MyPlugin\MyPlugin;

class MyPluginTest extends TestCase
{
    protected MyPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new MyPlugin();
    }

    public function test_config_schema_is_valid(): void
    {
        $schema = $this->plugin->getConfigSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('enabled', $schema);
        $this->assertEquals('boolean', $schema['enabled']['type']);
    }

    public function test_validates_config_correctly(): void
    {
        // Valid config
        $this->assertTrue($this->plugin->validateConfig([
            'time' => '09:00',
            'enabled' => true,
        ]));

        // Invalid config
        $this->assertFalse($this->plugin->validateConfig([
            'time' => 'invalid',
            'enabled' => true,
        ]));
    }
}
```

### Feature Test Example

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PluginActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_activate_plugin(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $response = $this->actingAs($user)
            ->post("/plugins/{$plugin->slug}/activate");

        $response->assertRedirect();
        
        $this->assertDatabaseHas('user_plugins', [
            'user_id' => $user->id,
            'plugin_id' => $plugin->id,
            'is_active' => true,
        ]);
    }

    public function test_plugin_config_is_saved(): void
    {
        $user = User::factory()->create();
        $plugin = Plugin::factory()->create();

        $this->actingAs($user)
            ->post("/plugins/{$plugin->slug}/activate");

        $response = $this->actingAs($user)
            ->post("/plugins/{$plugin->slug}/config", [
                'time' => '10:00',
                'enabled' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('plugin_configurations', [
            'config_key' => 'time',
            'config_value' => '"10:00"',
        ]);
    }
}
```

## Conclusion

These examples cover the most common use cases for ASPRI plugins. For more advanced scenarios, refer to:

- [Plugin Development Guide](PLUGIN_DEVELOPMENT_GUIDE.md)
- [Plugin API Reference](PLUGIN_API.md)
- [Existing Plugin Source Code](../../app/Plugins/)

Happy plugin development! 🚀
