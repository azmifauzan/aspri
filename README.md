# ASPRI - Asisten Pribadi Berbasis AI

**ASPRI** adalah aplikasi asisten pribadi cerdas yang dirancang untuk membantu pengelolaan jadwal, keuangan, dan catatan harian Anda melalui interaksi percakapan natural dengan AI.

## ✨ Fitur Utama

- 🤖 **Asisten Pribadi Cerdas** - Mengelola jadwal, keuangan, dan notes melalui percakapan natural
- 💬 **Chat-First Experience** - Semua fitur dapat diakses lewat percakapan dengan AI
- 👤 **Persona Kustom Per User** - Asisten mengikuti preferensi panggilan dan gaya komunikasi Anda
- 📱 **Integrasi Telegram** - Catat pengeluaran, buat note, atau cek jadwal langsung dari Telegram
- 📊 **Dashboard Interaktif** - Visualisasi data keuangan dan jadwal dalam satu tampilan
- 🎁 **Plugin System** - Extend fungsionalitas asisten dengan plugin-plugin powerful
- 💳 **Subscription System** - Fitur premium dengan layanan berlangganan
- 👥 **Admin Panel** - Manajemen user, plugin, jadwal, dan monitoring sistem lengkap

## 🎯 Modul Utama

| Modul | Deskripsi |
|-------|-----------|
| **Dashboard** | Ringkasan aktivitas, status keuangan bulan ini, dan jadwal hari ini |
| **Chat** | Riwayat percakapan dengan asisten AI + integrasi bot Telegram |
| **Notes** | Penyimpanan catatan dengan block-based content editor |
| **Schedule** | Manajemen kalender, event, dan reminder otomatis |
| **Finance** | Pencatatan transaksi, kategori, akun, budgeting, dan laporan |
| **Plugins** | 🎁 Sistem plugin untuk memperluas fitur asisten (motivasi, pengingat, dll) |
| **Subscription** | 💳 Upload bukti pembayaran dan aktivasi fitur premium |
| **Admin Panel** | 👥 User management, payment approval, activity logs, dan monitoring |

## 🛠️ Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Vue 3 + Inertia.js v2 + TypeScript
- **Styling**: Tailwind CSS v4 + Reka UI Components
- **Database**: SQLite (dev) / PostgreSQL (prod)
- **AI**: Multi-provider (OpenAI GPT-4, Google Gemini, Claude Sonnet)
- **Bot**: Telegram Bot API SDK
- **Queue**: Redis / Database
- **Authentication**: Laravel Fortify (Login, Register, 2FA)
- **Email**: SMTP / Mailgun / SendGrid support
- **Icons**: Lucide Icons

## 📋 Prerequisites

- PHP 8.4+ (with extensions: mbstring, openssl, pdo, tokenizer, xml, ctype, json, bcmath, fileinfo)
- Node.js 20+ (with npm)
- Composer 2.x
- SQLite or PostgreSQL
- Redis (optional, for queue and cache)
- HTTPS/SSL certificate (untuk production)

## 🚀 Quick Start

### Installation

```bash
# Clone repository
git clone https://github.com/azmifauzan/aspri.git
cd aspri

# Setup (installs dependencies, creates .env, runs migrations)
composer setup

# Start development server
composer dev
```

Buka http://localhost:8000 di browser.

### Manual Setup

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run build

# Start Laravel server
php artisan serve

# (Opsional) Start Vite for hot reload
npm run dev
```

## ⚙️ Configuration

### Environment Variables

```env
# Application
APP_NAME=ASPRI
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_DATABASE=aspri
# DB_USERNAME=postgres
# DB_PASSWORD=

# AI Provider
AI_PROVIDER=openai
OPENAI_API_KEY=sk-...
# GEMINI_API_KEY=...

# Telegram Bot
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
```

## 📁 Project Structure

```
aspri/
├── app/
│   ├── Actions/              # Fortify actions (registration, profile update, etc)
│   ├── Http/
│   │   ├── Controllers/      # Web & API controllers
│   │   │   ├── Admin/        # Admin panel controllers
│   │   │   ├── Api/          # API controllers  
│   │   │   └── Settings/     # User settings controllers
│   │   ├── Middleware/       # Custom middleware
│   │   └── Requests/         # Form request validation
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic services
│   │   ├── Admin/            # Admin services
│   │   ├── AI/               # AI provider integration
│   │   ├── Chat/             # Chat intent parsing & response
│   │   └── Telegram/         # Telegram bot handlers
│   ├── Jobs/                 # Queue jobs (notifications, scheduled tasks)
│   ├── Plugins/              # Built-in plugin classes
│   ├── Policies/             # Authorization policies
│   ├── Providers/            # Service providers
│   └── Notifications/        # Email & Telegram notifications
├── resources/
│   ├── js/
│   │   ├── actions/          # Wayfinder generated functions
│   │   ├── components/       # Vue components
│   │   │   └── ui/           # Reusable UI components (Reka UI)
│   │   ├── layouts/          # Page layouts
│   │   ├── pages/            # Inertia pages (view layer)
│   │   └── types/            # TypeScript type definitions
│   ├── css/                  # Tailwind CSS & custom styles
│   └── views/                # Blade templates (minimal, mostly for emails)
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders (including plugins)
├── routes/
│   ├── web.php               # Web routes (main app)
│   ├── api.php               # API routes (Telegram webhook, etc)
│   ├── console.php           # Console routes (artisan commands)
│   └── settings.php          # User settings routes
├── docs/                     # Comprehensive documentation
├── tests/
│   ├── Feature/              # Feature tests (HTTP, integration)
│   └── Unit/                 # Unit tests (isolated logic)
└── public/                   # Public assets (compiled JS/CSS)
```

## 📖 Documentation

Dokumentasi lengkap tersedia di folder `docs/`:

- [Architecture](docs/ARCHITECTURE.md) - Arsitektur sistem dan flow data
- [Blueprint](docs/BLUEPRINT.md) - Feature specifications lengkap
- [Database](docs/DATABASE.md) - Database schema dan relationships
- [Plan](docs/PLAN.md) - Implementation plan dan roadmap
- [Phases](docs/PHASES.md) - Development phases progress
- [AI Integration](docs/AI_INTEGRATION.md) - Multi-provider AI integration
- [Telegram](docs/TELEGRAM.md) - Telegram bot integration & webhook
- [Plugins](docs/PLUGINS.md) - Plugin system overview & architecture
- [Plugin Development Guide](docs/PLUGIN_DEVELOPMENT_GUIDE.md) - Panduan membuat plugin
- [Plugin API Reference](docs/PLUGIN_API.md) - Complete API documentation
- [Plugin Usage Examples](docs/PLUGIN_USAGE_EXAMPLES.md) - Real-world plugin examples
- [Subscription](docs/SUBSCRIPTION.md) - Subscription & payment system
- [HTTPS Security](docs/HTTPS_SECURITY.md) - SSL/TLS configuration guide
- [Admin Panel](docs/ADMIN.md) - Admin features dan management

## 🎁 Plugin System

ASPRI dilengkapi dengan sistem plugin yang memungkinkan Anda memperluas kemampuan asisten sesuai kebutuhan.

### Available Plugins

| Plugin | Description | Status |
|--------|-------------|--------|
| 🎯 **Kata Motivasi** | Kirim quote motivasi harian via Telegram | ✅ Active |
| 💧 **Pengingat Minum Air** | Reminder minum air secara berkala | ✅ Active |
| 💰 **Expense Alert** | Notifikasi ketika budget hampir habis | ✅ Active |

### Plugin Features

- ⚙️ **Easy Configuration** - Configure melalui web UI yang intuitif
- ⏰ **Scheduled Tasks** - Jalankan tugas otomatis sesuai jadwal
- 📊 **Activity Logs** - Monitor aktivitas dan debugging plugin
- 🔌 **Plug & Play** - Aktifkan/nonaktifkan kapan saja tanpa pengaruh ke core
- 🛠️ **Developer Friendly** - API lengkap untuk membuat plugin sendiri

### Create Your Own Plugin

```bash
# Generate plugin scaffold
php artisan make:plugin MyAwesomePlugin

# Register plugin
php artisan db:seed --class=PluginSeeder
```

Lihat [Plugin Development Guide](docs/PLUGIN_DEVELOPMENT_GUIDE.md) untuk panduan lengkap.

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=FinanceTest

# Run with coverage
php artisan test --coverage
```

## 🔧 Development

```bash
# Start development server (Laravel + Vite + Queue)
composer dev

# Format PHP code
composer lint

# Format JS/Vue code
npm run format

# Check linting
npm run lint
```

## 🤝 Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Laravel](https://laravel.com)
- [Vue.js](https://vuejs.org)
- [Inertia.js](https://inertiajs.com)
- [Tailwind CSS](https://tailwindcss.com)
- [OpenAI](https://openai.com)

---

**ASPRI** - Siap Mengatur Hidup Lebih Baik? 🚀
