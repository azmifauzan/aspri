# ASPRI Plugin System

## Overview

Sistem plugin ASPRI memungkinkan perluasan kemampuan asisten pribadi diluar fitur-fitur utama (Chat, Finance, Schedule, Notes). Plugin dapat dikembangkan untuk menambah fungsionalitas baru yang spesifik dan dapat diaktifkan/dinonaktifkan oleh pengguna.

## Core Concepts

### Plugin Architecture

Plugin adalah modul independen yang:
- Memiliki namespace tersendiri dalam `app/Plugins/`
- Dapat memiliki konfigurasi yang disimpan dalam database
- Dapat menjadwalkan tugas (scheduled tasks)
- Dapat mengirim notifikasi via Telegram
- Dapat berinteraksi dengan AI assistant
- Memiliki lifecycle: install, activate, deactivate, uninstall

### Plugin Interface

Setiap plugin harus mengimplementasikan `PluginInterface`:

```php
interface PluginInterface
{
    public function getName(): string;
    public function getSlug(): string;
    public function getDescription(): string;
    public function getVersion(): string;
    public function getAuthor(): string;
    public function getIcon(): string;
    
    public function install(): void;
    public function uninstall(): void;
    public function activate(): void;
    public function deactivate(): void;
    
    public function getConfigSchema(): array;
    public function getDefaultConfig(): array;
    public function validateConfig(array $config): bool;
}
```

## Database Schema

### Tabel: `plugins`

Menyimpan metadata plugin yang tersedia dalam sistem.

```sql
CREATE TABLE plugins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    version VARCHAR(20) NOT NULL,
    author VARCHAR(255),
    icon VARCHAR(255),
    class_name VARCHAR(255) NOT NULL,
    is_system BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT FALSE,
    installed_at TIMESTAMP NULL,
    activated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
);
```

### Tabel: `plugin_configurations`

Tabel general yang menyimpan konfigurasi untuk semua plugin. Menggunakan JSON untuk fleksibilitas.

```sql
CREATE TABLE plugin_configurations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NOT NULL, -- JSON value
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_plugin_key (user_id, plugin_id, config_key),
    INDEX idx_plugin_user (plugin_id, user_id)
);
```

### Tabel: `plugin_schedules`

Menyimpan jadwal eksekusi untuk plugin yang membutuhkan scheduled tasks.

```sql
CREATE TABLE plugin_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    schedule_type VARCHAR(50) NOT NULL, -- 'cron', 'interval', 'daily', 'weekly'
    schedule_value VARCHAR(255) NOT NULL, -- cron expression atau interval value
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSON, -- additional data needed for execution
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_next_run (next_run_at, is_active)
);
```

### Tabel: `plugin_logs`

Logging aktivitas plugin untuk debugging dan monitoring.

```sql
CREATE TABLE plugin_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    level VARCHAR(20) NOT NULL, -- 'info', 'warning', 'error', 'debug'
    message TEXT NOT NULL,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_plugin_level (plugin_id, level),
    INDEX idx_created_at (created_at)
);
```

## UI Structure

### 1. Plugin List Page (`/plugins`)

**Layout**: Grid card dengan informasi:
- Icon plugin
- Nama plugin
- Deskripsi singkat (sinopsi)
- Status: Active/Inactive badge
- Tombol: Activate/Deactivate, Configure (jika active)
- Tag: System/User plugin

**Features**:
- Filter: All, Active, Inactive
- Search plugin by name
- Sort by: Name, Status, Recently Added

### 2. Plugin Configuration Page (`/plugins/{slug}/configure`)

**Layout**: Form konfigurasi dinamis berdasarkan schema plugin

**Features**:
- Dynamic form fields based on plugin config schema
- Validation messages
- Save configuration
- Reset to default
- Preview/test functionality
- Activity log

### 3. Landing Page Integration

**Section**: "Extend Your Assistant"

Menampilkan:
- Hero: "Customize ASPRI with Powerful Plugins"
- Featured plugins (top 3-4 most popular)
- Categories/Tags
- Link to full plugin directory
- Call-to-action: "Discover Plugins"

## Example Plugin: "Kata Motivasi"

### Plugin Overview

Plugin yang mengirimkan kata-kata motivasi secara berkala melalui Telegram bot.

### Features

1. **Scheduled Messages**: Kirim motivasi di waktu yang ditentukan
2. **Custom Schedule**: User dapat mengatur frekuensi (daily, specific times)
3. **Quote Categories**: Motivasi umum, bisnis, kesehatan, produktivitas
4. **Custom Messages**: User dapat menambah quotes sendiri
5. **Delivery Channel**: Via Telegram bot

### Configuration Schema

```json
{
    "schedule_type": {
        "type": "select",
        "label": "Frekuensi Pengiriman",
        "options": ["daily", "twice_daily", "custom"],
        "default": "daily",
        "required": true
    },
    "delivery_time": {
        "type": "time",
        "label": "Waktu Pengiriman",
        "default": "07:00",
        "required": true,
        "multiple": true
    },
    "categories": {
        "type": "multiselect",
        "label": "Kategori Motivasi",
        "options": ["general", "business", "health", "productivity", "spiritual"],
        "default": ["general"],
        "required": true
    },
    "include_custom": {
        "type": "boolean",
        "label": "Sertakan Quotes Custom",
        "default": false
    },
    "custom_quotes": {
        "type": "textarea",
        "label": "Quotes Custom (satu per baris)",
        "default": "",
        "condition": "include_custom === true"
    },
    "enabled": {
        "type": "boolean",
        "label": "Aktifkan Plugin",
        "default": true
    }
}
```

### Implementation Structure

```
app/Plugins/KataMotivasi/
├── KataMotivasiPlugin.php (implements PluginInterface)
├── Commands/
│   └── SendMotivationQuoteCommand.php
├── Services/
│   ├── QuoteRepository.php
│   └── DeliveryService.php
├── Database/
│   └── quotes.json (pre-populated quotes)
└── Views/
    └── config-form.vue (optional custom config UI)
```

### Workflow

1. **Installation**: 
   - Seed default quotes to plugin_configurations
   - Register scheduled command

2. **Activation**:
   - User configures delivery time and preferences
   - Create schedule entry in plugin_schedules
   - Validate Telegram connection

3. **Execution**:
   - Scheduled command runs at specified time
   - Select random quote based on categories
   - Send via Telegram bot using TelegramService
   - Log activity to plugin_logs

4. **Configuration Update**:
   - User changes schedule via web or Telegram
   - Update plugin_configurations and plugin_schedules
   - Next run time recalculated

## Suggested Plugins

### 1. Plugin: "Pengingat Minum Air"

**Description**: Mengirimkan pengingat untuk minum air secara berkala

**Features**:
- Set target harian (misal: 8 gelas)
- Pengingat berkala (setiap 1-2 jam)
- Track konsumsi harian via chat
- Notifikasi pencapaian target
- Statistik mingguan

**Configuration**:
- Daily target (ml or glasses)
- Reminder interval
- Active hours (start-end time)
- Reminder message customization
- Progress tracking enabled/disabled

**Use Case**: 
- Kesehatan dan wellness
- Meningkatkan awareness konsumsi air
- Habit building

**Technical**:
- Schedule: Interval-based (every X minutes)
- Storage: Daily consumption in plugin_configurations
- Notification: Telegram bot
- Interaction: User can reply "sudah" or "1 gelas" via chat

### 2. Plugin: "Expense Alert"

**Description**: Notifikasi otomatis ketika pengeluaran mendekati atau melebihi budget

**Features**:
- Monitor daily/weekly/monthly spending
- Alert ketika mencapai threshold (misal: 80% budget)
- Budget summary end of period
- Category-specific alerts
- Spending pattern insights

**Configuration**:
- Budget thresholds (percentage: 50%, 75%, 90%, 100%)
- Alert channels (web notification, Telegram)
- Monitor categories (specific or all)
- Alert frequency (immediate, daily summary, weekly)
- Spending insights enabled

**Use Case**:
- Financial discipline
- Budget control
- Prevent overspending
- Financial awareness

**Technical**:
- Trigger: After each transaction recorded
- Check: Compare with budget from finance module
- Storage: Alert history and preferences
- Notification: Multi-channel (web + Telegram)
- Integration: Deep integration with Finance module

## Implementation Phases

### Phase 1: Core Plugin System (Week 1-2) ✅ COMPLETED

**Backend**:
- [x] Create database migrations for plugin tables
- [x] Implement PluginInterface and BasePlugin abstract class
- [x] Create PluginManager service (register, load, activate, deactivate)
- [x] Create PluginRepository for database operations
- [x] Implement plugin discovery system
- [x] Create seeder for system plugins

**Frontend**:
- [x] Create plugin list page with card grid
- [x] Implement activate/deactivate functionality
- [x] Create basic plugin detail modal

**Testing**:
- [x] Unit tests for PluginManager
- [x] Feature tests for plugin activation workflow

### Phase 2: Configuration System (Week 2-3) ✅ COMPLETED

**Backend**:
- [x] Implement dynamic config schema parser
- [x] Create PluginConfigurationService
- [x] Add validation for config values
- [x] Implement config versioning

**Frontend**:
- [x] Create dynamic form builder for plugin config
- [x] Implement configuration save/reset
- [x] Add config validation UI
- [x] Create config history view

**Testing**:
- [x] Test config CRUD operations
- [x] Test schema validation
- [x] Test various config field types

### Phase 3: Scheduling System (Week 3-4) ✅ COMPLETED

**Backend**:
- [x] Implement PluginScheduler service
- [x] Create schedule processor command
- [x] Add cron job registration
- [x] Implement execution logging

**Frontend**:
- [x] Create schedule configuration UI
- [x] Display next run time
- [x] Show execution history

**Testing**:
- [x] Test schedule creation and execution
- [x] Test various schedule types (cron, interval, etc.)

### Phase 4: Example Plugin - Kata Motivasi (Week 4-5) ✅ COMPLETED

**Backend**:
- [x] Create KataMotivasiPlugin class
- [x] Implement SendMotivationQuoteCommand
- [x] Create QuoteRepository with default quotes
- [x] Integrate with TelegramService
- [x] Add quote management API

**Frontend**:
- [x] Create custom config form
- [x] Add quote preview
- [x] Implement custom quote management UI

**Testing**:
- [x] Test quote sending mechanism
- [x] Test schedule integration
- [x] Test Telegram delivery

### Phase 5: Additional Plugins (Week 5-6) ✅ COMPLETED

**Backend**:
- [x] Implement "Pengingat Minum Air" plugin
- [x] Implement "Expense Alert" plugin
- [x] Create plugin command generator (artisan make:plugin)

**Testing**:
- [x] Test each plugin functionality
- [x] Integration tests with existing modules

### Phase 6: Landing Page Integration (Week 6) ✅ COMPLETED

**Frontend**:
- [x] Add plugin section to landing page
- [x] Create plugin showcase component
- [x] Add plugin category navigation
- [x] Implement plugin search on landing

**Marketing**:
- [x] Write plugin descriptions
- [x] Create plugin icons/illustrations
- [x] Add screenshots/demos

### Phase 7: Documentation & Polish (Week 7) ✅ COMPLETED

**Documentation**:
- [x] Write plugin development guide
- [x] Create API documentation
- [x] Add usage examples
- [ ] Create video tutorials

**Polish**:
- [x] Performance optimization
- [x] Error handling improvements
- [x] UI/UX refinements
- [x] Security audit

## API Endpoints

### Plugin Management

```
GET     /plugins                    # List all available plugins
GET     /plugins/{slug}             # Get plugin details
POST    /plugins/{slug}/activate    # Activate plugin
POST    /plugins/{slug}/deactivate  # Deactivate plugin
GET     /plugins/{slug}/config      # Get plugin configuration
POST    /plugins/{slug}/config      # Update plugin configuration
DELETE  /plugins/{slug}/config      # Reset to default config
GET     /plugins/{slug}/logs        # Get plugin execution logs
```

### Admin Endpoints

```
POST    /admin/plugins              # Install new plugin
DELETE  /admin/plugins/{slug}       # Uninstall plugin
GET     /admin/plugins/stats        # Plugin usage statistics
```

### Public API (Landing Page)

```
GET     /api/public/plugins         # List featured plugins (public)
GET     /api/public/plugins/{slug}  # Get plugin details (public)
```

## Plugin Development Guide

### Creating a New Plugin

1. **Create Plugin Directory**:
   ```
   app/Plugins/YourPluginName/
   ```

2. **Implement PluginInterface**:
   ```php
   class YourPlugin extends BasePlugin implements PluginInterface
   {
       // Implementation
   }
   ```

3. **Register Plugin**:
   Add to `database/seeders/PluginSeeder.php`

4. **Create Configuration Schema**:
   Define in `getConfigSchema()` method

5. **Implement Core Logic**:
   Add services, commands, repositories as needed

6. **Test Plugin**:
   Create feature tests in `tests/Feature/Plugins/`

### Best Practices

1. **Isolation**: Plugin harus independen, tidak mengganggu core functionality
2. **Configuration**: Semua setting melalui plugin_configurations table
3. **Logging**: Log semua aktivitas penting ke plugin_logs
4. **Error Handling**: Graceful failure, jangan crash application
5. **Performance**: Gunakan queue untuk operasi berat
6. **Security**: Validate semua input, sanitize output
7. **Versioning**: Maintain backward compatibility

## Security Considerations

1. **Plugin Validation**: Only admin can install/uninstall system plugins
2. **User Isolation**: Plugin config per-user, tidak bisa akses config user lain
3. **Code Review**: System plugins must pass security review
4. **Sandboxing**: Consider sandboxing for user-submitted plugins (future)
5. **API Rate Limiting**: Prevent plugin abuse
6. **Data Access**: Plugin hanya bisa akses data user yang activate

## Performance Optimization

1. **Lazy Loading**: Load plugin hanya ketika dibutuhkan
2. **Caching**: Cache plugin metadata dan config
3. **Queue**: Background jobs untuk scheduled tasks
4. **Database Indexing**: Proper indexes on plugin tables
5. **Asset Optimization**: Minify plugin assets

## Marketing & Landing Page

### Value Propositions

1. **"Personalize Your Experience"**
   - Customize ASPRI to match your lifestyle
   - Add features that matter to you

2. **"Growing Ecosystem"**
   - New plugins added regularly
   - Community-driven extensions

3. **"No Bloat, Just What You Need"**
   - Activate only the plugins you use
   - Keep your assistant lean and fast

### Plugin Showcase Section

```
Hero Section:
"Supercharge Your Personal Assistant"
Tagline: "Extend ASPRI with powerful plugins tailored to your needs"

Featured Plugins Grid:
- Kata Motivasi (with icon and brief description)
- Pengingat Minum Air
- Expense Alert
- [Future plugins]

Call-to-Action:
"Get Started Free" → Sign up
"Explore All Plugins" → Plugin directory
```

## Future Enhancements

1. **Plugin Marketplace**: Browse and install community plugins
2. **Plugin Analytics**: Track usage and performance metrics
3. **Plugin Dependencies**: Manage inter-plugin dependencies
4. **Plugin API**: RESTful API for plugin interactions
5. **Webhooks**: External integrations via webhooks
6. **Plugin SDK**: Development toolkit for easier plugin creation
7. **Plugin Templates**: Starter templates for common plugin types
8. **User-Submitted Plugins**: Allow users to develop and share plugins
9. **Plugin Rating & Reviews**: Community feedback system
10. **Paid Plugins**: Premium plugin monetization (future)

## Conclusion

Sistem plugin ASPRI dirancang untuk memberikan fleksibilitas dan extensibility tanpa mengorbankan core functionality. Dengan architecture yang modular dan database schema yang general, plugin dapat dikembangkan untuk berbagai use case sambil menjaga konsistensi dan keamanan sistem.

Plugin marketplace dan ecosystem akan menjadi competitive advantage yang membedakan ASPRI dari personal assistant lainnya, memberikan value proposition yang kuat untuk user retention dan acquisition.

## Implementation Status

**🎉 PHASE 7 COMPLETED - February 5, 2026**

All 7 phases of plugin system development have been successfully completed:

✅ **Phase 1**: Core plugin system with database, models, and services  
✅ **Phase 2**: Dynamic configuration system with validation  
✅ **Phase 3**: Scheduling system with multiple schedule types  
✅ **Phase 4**: Kata Motivasi example plugin  
✅ **Phase 5**: Additional plugins (Pengingat Minum Air, Expense Alert)  
✅ **Phase 6**: Landing page integration with featured plugins  
✅ **Phase 7**: Complete documentation and polish  

### Test Results
- **35 tests passed** covering all plugin functionality
- All code formatted with Laravel Pint
- Full test coverage for:
  - Plugin activation/deactivation
  - Configuration management
  - Scheduling system
  - Plugin execution
  - Landing page integration

### Documentation Completed
- ✅ [Plugin Development Guide](PLUGIN_DEVELOPMENT_GUIDE.md) - Complete guide for building plugins
- ✅ [Plugin API Reference](PLUGIN_API.md) - Full API documentation
- ✅ [Plugin Usage Examples](PLUGIN_USAGE_EXAMPLES.md) - Real-world scenarios and code examples
- ✅ README.md updated with plugin system information
- ⏳ Video tutorials (pending)
