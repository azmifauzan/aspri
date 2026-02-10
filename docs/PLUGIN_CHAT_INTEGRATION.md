# Plugin Chat Integration Guide

## Overview

Plugins can integrate with ASPRI's chat system, allowing users to interact with plugin features through natural language conversation. The chat integration system is fully scalable - you don't need to modify core files to add new chat-enabled plugins.

## How It Works

1. User sends a message in chat
2. AI Intent Parser detects user's intent and identifies if a plugin should handle it
3. **Intent Parser automatically discovers active plugins** that support chat integration
4. Plugin receives the intent and entities, processes the request
5. Plugin returns a formatted response to the user

## Enabling Chat Integration

To enable chat integration in your plugin, implement three key methods:

### 1. supportsChatIntegration()

Tell the system your plugin supports chat integration:

```php
public function supportsChatIntegration(): bool
{
    return true;
}
```

### 2. getChatIntents()

Define what intents your plugin can handle:

```php
public function getChatIntents(): array
{
    return [
        [
            'action' => 'plugin_yourplugin_actionname',
            'description' => 'Clear description of what this does',
            'entities' => [
                'entity_name' => 'type|null',
                // ... more entities
            ],
            'examples' => [
                'example user message 1',
                'example user message 2',
                // ... more examples
            ],
        ],
        // ... more intents
    ];
}
```

### 3. handleChatIntent()

Handle the intent execution:

```php
public function handleChatIntent(int $userId, string $action, array $entities): array
{
    // Validate action
    if ($action !== 'plugin_yourplugin_actionname') {
        return [
            'success' => false,
            'message' => 'Action not supported',
        ];
    }

    try {
        // Process request
        $result = $this->processAction($userId, $entities);
        
        return [
            'success' => true,
            'message' => 'Your formatted response',
            'data' => $result, // optional
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => 'User-friendly error message',
        ];
    }
}
```

## Intent Definition Structure

### Action (required)

Unique identifier for the intent. Use format: `plugin_{slug}_{action}`

**Examples:**
- `plugin_currency_convert`
- `plugin_weather_forecast`
- `plugin_translator_translate`

### Description (required)

Clear, concise description of what this intent does. The AI uses this to determine when to trigger your plugin.

**Good examples:**
- "Convert currency from one type to another"
- "Get weather forecast for a specific location"
- "Translate text from one language to another"

**Bad examples:**
- "Does currency stuff" (too vague)
- "This action converts currencies using exchange rates from various APIs and returns the result" (too long)

### Entities (required)

Dictionary of parameters your plugin expects from the user's message.

**Entity types:**
- `string` - Required text value
- `number` - Required numeric value
- `boolean` - Required true/false value
- `string|null` - Optional text value
- `number|null` - Optional numeric value
- `boolean|null` - Optional boolean value

**Example:**
```php
'entities' => [
    'amount' => 'number|null',       // Optional amount
    'from' => 'string|null',          // Optional source
    'to' => 'string',                 // Required target
    'date' => 'string|null',          // Optional date
]
```

### Examples (required)

Array of sample user messages that should trigger this intent. Include variations in:
- Different languages (Indonesian and English)
- Different phrasings
- Formal and informal language
- With and without optional entities

**Example:**
```php
'examples' => [
    'convert 100 USD to EUR',
    'berapa kurs IDR ke USD sekarang',
    'how much is 50 dollar in rupiah',
    '1000 rupiah ke dolar',
    'kurs SGD hari ini',
]
```

## Complete Example: Currency Converter

```php
<?php

namespace App\Plugins\CurrencyConverter;

use App\Services\Plugin\BasePlugin;
use Illuminate\Support\Facades\Http;

class CurrencyConverterPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'Currency Converter';
    }

    public function getSlug(): string
    {
        return 'currency-converter';
    }

    public function getDescription(): string
    {
        return 'Convert currencies with realtime exchange rates';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getIcon(): string
    {
        return 'banknote';
    }

    public function supportsChatIntegration(): bool
    {
        return true;
    }

    public function getChatIntents(): array
    {
        return [
            [
                'action' => 'plugin_currency_convert',
                'description' => 'Convert currency from one type to another',
                'entities' => [
                    'amount' => 'number|null',
                    'from' => 'string|null',
                    'to' => 'string|null',
                ],
                'examples' => [
                    'berapa kurs IDR ke USD sekarang',
                    'convert 100 dollar to rupiah',
                    '1000 rupiah ke dolar',
                    'berapa nilai 50 euro dalam yen',
                    'kurs SGD hari ini',
                    'how much is 500 USD in EUR',
                ],
            ],
        ];
    }

    public function handleChatIntent(int $userId, string $action, array $entities): array
    {
        if ($action !== 'plugin_currency_convert') {
            return [
                'success' => false,
                'message' => 'Action not supported',
            ];
        }

        // Get user's configuration
        $config = $this->getUserConfig($userId);
        
        // Extract entities with defaults
        $amount = $entities['amount'] ?? 1;
        $from = strtoupper($entities['from'] ?? $config['base_currency'] ?? 'IDR');
        $to = strtoupper($entities['to'] ?? 'USD');

        // Perform conversion
        try {
            $response = Http::timeout(10)->get("https://api.exchangerate-api.com/v4/latest/{$from}");
            
            if (!$response->successful()) {
                throw new \Exception('Failed to fetch exchange rates');
            }

            $data = $response->json();
            
            if (!isset($data['rates'][$to])) {
                return [
                    'success' => false,
                    'message' => "Mata uang {$to} tidak ditemukan.",
                ];
            }

            $rate = $data['rates'][$to];
            $result = $amount * $rate;

            // Log the action
            $this->log($userId, 'info', "Converted {$amount} {$from} to {$result} {$to}");

            // Format response
            $message = sprintf(
                "💱 **Konversi Mata Uang**\n\n" .
                "%s %s = **%s %s**\n\n" .
                "Nilai tukar: 1 %s = %s %s\n\n" .
                "_Data realtime dari ExchangeRate-API_",
                number_format($amount, 2, ',', '.'),
                $from,
                number_format($result, 2, ',', '.'),
                $to,
                $from,
                number_format($rate, 4),
                $to
            );

            return [
                'success' => true,
                'message' => $message,
                'data' => [
                    'amount' => $amount,
                    'from' => $from,
                    'to' => $to,
                    'rate' => $rate,
                    'result' => round($result, 2),
                ],
            ];
        } catch (\Exception $e) {
            $this->log($userId, 'error', 'Conversion failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '❌ Maaf, gagal mengambil data kurs. Silakan coba lagi nanti.',
            ];
        }
    }

    // Other required methods...
    public function getConfigSchema(): array { /* ... */ }
    public function execute(int $userId, array $config, array $context = []): void { /* ... */ }
}
```

## Multiple Intents Example

A plugin can support multiple chat intents:

```php
public function getChatIntents(): array
{
    return [
        [
            'action' => 'plugin_weather_current',
            'description' => 'Get current weather for a location',
            'entities' => [
                'location' => 'string',
                'unit' => 'string|null',
            ],
            'examples' => [
                'how is the weather in Jakarta',
                'cuaca di Bandung sekarang',
                'temperature in New York',
            ],
        ],
        [
            'action' => 'plugin_weather_forecast',
            'description' => 'Get weather forecast for upcoming days',
            'entities' => [
                'location' => 'string',
                'days' => 'number|null',
            ],
            'examples' => [
                'weather forecast for tomorrow in Bali',
                'prakiraan cuaca 3 hari ke depan di Surabaya',
                '5 day forecast for Singapore',
            ],
        ],
    ];
}

public function handleChatIntent(int $userId, string $action, array $entities): array
{
    switch ($action) {
        case 'plugin_weather_current':
            return $this->handleCurrentWeather($userId, $entities);
            
        case 'plugin_weather_forecast':
            return $this->handleForecast($userId, $entities);
            
        default:
            return [
                'success' => false,
                'message' => 'Unknown action',
            ];
    }
}
```

## Best Practices

### 1. Always Validate Entities

```php
public function handleChatIntent(int $userId, string $action, array $entities): array
{
    // Check required entities
    if (empty($entities['location'])) {
        return [
            'success' => false,
            'message' => 'Mohon sebutkan lokasi yang ingin dicek cuacanya.',
        ];
    }
    
    // Provide defaults for optional entities
    $unit = $entities['unit'] ?? 'celsius';
    $days = $entities['days'] ?? 3;
    
    // Continue with processing...
}
```

### 2. Format Responses Clearly

Use markdown formatting for better readability:

```php
$message = sprintf(
    "🌤 **Cuaca di %s**\n\n" .
    "Suhu: %s°C\n" .
    "Kondisi: %s\n" .
    "Kelembaban: %s%%\n\n" .
    "_Diperbarui: %s_",
    $location,
    $temp,
    $condition,
    $humidity,
    now()->format('H:i')
);
```

### 3. Handle Errors Gracefully

Never throw exceptions - return error responses:

```php
try {
    $result = $this->dangerousOperation();
    
    return [
        'success' => true,
        'message' => 'Success message',
        'data' => $result,
    ];
} catch (\Exception $e) {
    $this->log($userId, 'error', 'Operation failed: ' . $e->getMessage());
    
    return [
        'success' => false,
        'message' => 'Maaf, terjadi kesalahan. Silakan coba lagi nanti.',
    ];
}
```

### 4. Support Multiple Languages

Provide examples in both Indonesian and English:

```php
'examples' => [
    // Indonesian
    'berapa cuaca di Jakarta',
    'prakiraan cuaca hari ini',
    'suhu berapa di Bandung',
    
    // English
    'weather in Jakarta',
    'today weather forecast',
    'temperature in Bandung',
]
```

### 5. Use Configuration

Respect user's plugin configuration:

```php
public function handleChatIntent(int $userId, string $action, array $entities): array
{
    // Get user's configuration
    $config = $this->getUserConfig($userId);
    
    // Use configured defaults
    $from = $entities['from'] ?? $config['base_currency'];
    $unit = $entities['unit'] ?? $config['default_unit'];
    
    // Process...
}
```

### 6. Log Everything

Log all chat interactions for debugging:

```php
// Log successful operations
$this->log($userId, 'info', "Action executed", [
    'action' => $action,
    'entities' => $entities,
    'result' => 'success',
]);

// Log errors
$this->log($userId, 'error', "Action failed", [
    'action' => $action,
    'error' => $e->getMessage(),
]);
```

## Testing Chat Integration

### 1. Test via Web Interface

1. Navigate to `/chat`
2. Type your example messages
3. Verify plugin responds correctly

### 2. Test Different Scenarios

```
✅ Test with all entities provided
✅ Test with missing optional entities
✅ Test with invalid entities
✅ Test in Indonesian
✅ Test in English
✅ Test with different phrasings
```

### 3. Debug Intent Parsing

Add logging in your handleChatIntent:

```php
public function handleChatIntent(int $userId, string $action, array $entities): array
{
    Log::debug('Plugin chat intent received', [
        'plugin' => $this->getSlug(),
        'action' => $action,
        'entities' => $entities,
        'user_id' => $userId,
    ]);
    
    // Your logic...
}
```

### 4. Check Logs

```bash
tail -f storage/logs/laravel.log
```

## Common Issues & Solutions

### Issue: AI doesn't recognize my plugin

**Solution:** Check that:
1. Plugin is activated for the user
2. `supportsChatIntegration()` returns `true`
3. `getChatIntents()` returns non-empty array
4. Examples are clear and varied

### Issue: Wrong entities extracted

**Solution:**
1. Add more specific examples
2. Make entity descriptions clearer
3. Test with different phrasings

### Issue: Plugin returns error

**Solution:**
1. Check logs for detailed error message
2. Verify all required entities are present
3. Test plugin logic independently

## Advanced: Contextual Responses

Use conversation history for context-aware responses:

```php
public function handleChatIntent(int $userId, string $action, array $entities): array
{
    $user = User::find($userId);
    
    // Check user's recent interactions
    $lastQuery = $user->chatMessages()
        ->where('role', 'user')
        ->latest()
        ->first();
    
    // Provide context-aware response
    if ($lastQuery && str_contains($lastQuery->content, 'cuaca')) {
        $message = "Berikut update cuaca terbaru:\n\n" . $weatherData;
    } else {
        $message = "Cuaca saat ini:\n\n" . $weatherData;
    }
    
    return [
        'success' => true,
        'message' => $message,
    ];
}
```

## Summary

Chat integration is **completely scalable**:
- ✅ No need to modify core files
- ✅ Define intents in your plugin class
- ✅ System automatically discovers active plugins
- ✅ AI learns from your examples
- ✅ Full control over response formatting

Start building chat-enabled plugins today!
