# ASPRI Admin Module

## Overview

Admin module menyediakan antarmuka untuk super admin mengelola konfigurasi sistem ASPRI.

## Access Control

| Role | Access |
|------|--------|
| Super Admin | Full access ke semua fitur admin |
| Admin | Limited access (user management only) |
| User | No access |

## Admin Features

### 1. Dashboard Admin
- Total users statistics
- Active users today/week/month
- Chat message count
- AI usage metrics
- System health status

### 2. User Management
- List semua users
- View user details
- Edit user profile
- Deactivate/activate user
- Reset user password
- View user activity logs

### 3. AI Provider Settings

Konfigurasi AI provider yang digunakan sistem.

| Setting | Description | Default |
|---------|-------------|---------|
| `ai_provider` | Active provider | `gemini` |
| `gemini_api_key` | Gemini API key | - |
| `gemini_model` | Gemini model | `gemini-pro` |
| `openai_api_key` | OpenAI API key | - |
| `openai_model` | OpenAI model | `gpt-4-turbo` |
| `anthropic_api_key` | Claude API key | - |
| `anthropic_model` | Claude model | `claude-3-sonnet` |

```php
// Database: system_settings table
Schema::create('system_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->string('type')->default('string'); // string, boolean, json
    $table->boolean('is_encrypted')->default(false);
    $table->timestamps();
});
```

**UI untuk AI Settings:**
- Select dropdown untuk active provider
- API key input fields (masked)
- Model selection per provider
- Test connection button
- Save settings

### 4. Telegram Bot Configuration
- Bot token management
- Webhook URL configuration
- Bot status monitoring
- Send test message
- View bot statistics

### 5. System Configuration
- Application name & description
- Default locale & timezone
- Rate limiting settings
- Cache configuration
- Maintenance mode toggle

### 6. Usage Analytics
- AI API calls per day/week/month
- Cost estimation
- Most active users
- Popular features
- Error rate monitoring

## Database Changes

### system_settings
Untuk menyimpan konfigurasi sistem.

```php
Schema::create('system_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->string('type')->default('string');
    $table->boolean('is_encrypted')->default(false);
    $table->string('description')->nullable();
    $table->timestamps();
});
```

### users (update)
Tambah role field.

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('user'); // user, admin, super_admin
    $table->boolean('is_active')->default(true);
});
```

### activity_logs
Track admin activities.

```php
Schema::create('activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('action');
    $table->string('model_type')->nullable();
    $table->unsignedBigInteger('model_id')->nullable();
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->timestamps();
    
    $table->index(['model_type', 'model_id']);
});
```

## API Endpoints

### Admin Dashboard
- `GET /admin` - Admin dashboard

### User Management
- `GET /admin/users` - List users
- `GET /admin/users/{user}` - View user
- `PUT /admin/users/{user}` - Update user
- `POST /admin/users/{user}/toggle-active` - Toggle active status
- `POST /admin/users/{user}/reset-password` - Reset password

### Settings
- `GET /admin/settings` - View settings page
- `PUT /admin/settings` - Update settings
- `POST /admin/settings/test-ai` - Test AI connection
- `POST /admin/settings/test-telegram` - Test Telegram bot

### Analytics
- `GET /admin/analytics` - Usage analytics page
- `GET /admin/analytics/data` - Analytics data (JSON)

## Middleware

```php
// app/Http/Middleware/AdminMiddleware.php
class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }
        
        if (!in_array(auth()->user()->role, ['admin', 'super_admin'])) {
            abort(403, 'Access denied');
        }
        
        return $next($request);
    }
}

// app/Http/Middleware/SuperAdminMiddleware.php
class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403, 'Super admin access required');
        }
        
        return $next($request);
    }
}
```

## Service Classes

### SettingsService

```php
namespace App\Services;

class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        $value = $setting->is_encrypted 
            ? decrypt($setting->value) 
            : $setting->value;
            
        return match($setting->type) {
            'boolean' => (bool) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
    
    public function set(string $key, mixed $value, array $options = []): void
    {
        $type = $options['type'] ?? 'string';
        $encrypted = $options['encrypted'] ?? false;
        
        $storeValue = match($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
        
        if ($encrypted) {
            $storeValue = encrypt($storeValue);
        }
        
        SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storeValue,
                'type' => $type,
                'is_encrypted' => $encrypted,
            ]
        );
    }
    
    public function getAiProvider(): string
    {
        return $this->get('ai_provider', 'gemini');
    }
    
    public function getAiConfig(): array
    {
        $provider = $this->getAiProvider();
        
        return [
            'provider' => $provider,
            'api_key' => $this->get("{$provider}_api_key"),
            'model' => $this->get("{$provider}_model"),
        ];
    }
}
```

## Default Settings Seeder

```php
// database/seeders/SystemSettingsSeeder.php
class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'ai_provider', 'value' => 'gemini', 'type' => 'string'],
            ['key' => 'gemini_model', 'value' => 'gemini-pro', 'type' => 'string'],
            ['key' => 'openai_model', 'value' => 'gpt-4-turbo', 'type' => 'string'],
            ['key' => 'anthropic_model', 'value' => 'claude-3-sonnet', 'type' => 'string'],
            ['key' => 'app_locale', 'value' => 'id', 'type' => 'string'],
            ['key' => 'app_timezone', 'value' => 'Asia/Jakarta', 'type' => 'string'],
        ];
        
        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
```
