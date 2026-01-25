# ASPRI - Asisten Pribadi Berbasis AI

![ASPRI Banner](docs/images/banner.png)

**ASPRI** adalah aplikasi asisten pribadi cerdas yang dirancang untuk membantu pengelolaan jadwal dan keuangan harian Anda melalui interaksi percakapan natural.

## ✨ Fitur Utama

- 🤖 **Asisten Pribadi Cerdas** - Mengelola jadwal dan keuangan melalui percakapan natural
- 💬 **Chat-First Experience** - Semua fitur dapat diakses lewat percakapan
- 👤 **Persona Per User** - Asisten mengikuti preferensi dan gaya komunikasi Anda
- 📱 **Integrasi Telegram** - Catat pengeluaran, buat note, atau cek jadwal langsung dari Telegram
- 📊 **Dashboard Interaktif** - Visualisasi data keuangan dan jadwal dalam satu tampilan

## 🎯 5 Modul Utama

| Modul | Deskripsi |
|-------|-----------|
| **Dashboard** | Ringkasan aktivitas dan status keuangan |
| **Chat** | Riwayat percakapan dengan asisten + integrasi bot |
| **Notes** | Penyimpanan note dengan block-based content |
| **Schedule** | Manajemen kalender, event, dan reminder |
| **Finance** | Pencatatan transaksi, budgeting, dan laporan |

## 🛠️ Tech Stack

- **Backend**: Laravel 12
- **Frontend**: Vue 3 + Inertia.js v2
- **Styling**: Tailwind CSS v4
- **Database**: SQLite (dev) / PostgreSQL (prod)
- **AI**: OpenAI GPT-4 / Google Gemini
- **Bot**: Telegram Bot API

## 📋 Prerequisites

- PHP 8.2+
- Node.js 20+
- Composer
- SQLite or PostgreSQL

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
│   ├── Http/Controllers/
│   ├── Models/
│   ├── Services/
│   │   ├── AI/
│   │   ├── Chat/
│   │   └── Telegram/
│   └── Jobs/
├── resources/
│   ├── js/
│   │   ├── pages/
│   │   └── components/
│   └── css/
├── database/
│   └── migrations/
├── routes/
├── docs/                  # Documentation
└── tests/
```

## 📖 Documentation

Dokumentasi lengkap tersedia di folder `docs/`:

- [Architecture](docs/ARCHITECTURE.md) - Arsitektur sistem
- [Blueprint](docs/BLUEPRINT.md) - Feature specifications
- [Database](docs/DATABASE.md) - Database schema
- [Plan](docs/PLAN.md) - Implementation plan
- [Phases](docs/PHASES.md) - Development phases
- [AI Integration](docs/AI_INTEGRATION.md) - AI integration guide
- [Telegram](docs/TELEGRAM.md) - Telegram bot integration

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
