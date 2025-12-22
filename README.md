# ASPRI (Asisten Pribadi) - Next Gen

ASPRI adalah aplikasi asisten pribadi cerdas yang dirancang untuk membantu pengelolaan jadwal dan keuangan harian Anda. Versi ini merupakan evolusi dari sistem sebelumnya, dibangun ulang dengan teknologi enterprise-grade untuk performa, skalabilitas, dan independensi yang lebih baik.

## 🌟 Fitur Utama

*   **Asisten Pribadi Cerdas**: Mengelola jadwal dan keuangan melalui percakapan natural.
*   **Chat-first + Persona Per User**: Konsep chat-first dipertahankan; asisten mengikuti `aspri_persona`, memanggil user sesuai `call_preference`, dan meminta konfirmasi untuk aksi yang mengubah data.
*   **Kemandirian Platform**: Sistem autentikasi dan kalender mandiri (tidak bergantung pada Google API atau OAuth provider eksternal).
*   **Authentication Portable**: BCrypt + JWT manual - dapat di-deploy ke PostgreSQL server manapun tanpa dependency eksternal.
*   **Integrasi Chat**: Terintegrasi dengan **Telegram** sebagai fokus awal (dan WhatsApp di masa depan). Anda bisa mencatat pengeluaran, membuat note, atau cek jadwal langsung dari aplikasi chat.
*   **Dashboard Interaktif**: Visualisasi data keuangan dan jadwal dalam satu tampilan yang informatif.
*   **5 Modul Utama**:
    1.  **Dashboard**: Ringkasan aktivitas dan status keuangan.
    2.  **Chat**: Riwayat percakapan dengan asisten + integrasi bot.
    3.  **Note**: Penyimpanan note advanced (semua di database; menggantikan modul dokumen lama yang memakai MinIO).
    4.  **Jadwal**: Manajemen kalender, event, dan reminder.
    5.  **Keuangan**: Pencatatan transaksi, budgeting, dan laporan keuangan.

## 🛠️ Tech Stack

### Backend
*   **Language**: Java 25
*   **Framework**: Spring Boot 4.0.1
*   **Authentication**: BCrypt + JWT (Manual - No external provider)
*   **AI Orchestration**: Spring AI (provider-agnostic)
*   **Database**: PostgreSQL (portable - any PostgreSQL server)
*   **Migration**: Flyway
*   **Integration**: Telegram Bots API (rubenlagus/TelegramBots)

### Frontend
*   **Framework**: Angular 21
*   **UI Reference**: TailAdmin (https://tailadmin.com/)
*   **Styling**: Tailwind CSS v4
*   **I18N**: 2 bahasa (Bahasa Indonesia & English)
*   **Theme**: Light & Dark
*   **State Management**: Angular Signals

### DevOps
*   **Containerization**: Docker + Docker Compose
*   **Database**: PostgreSQL 17
*   **Web Server**: Nginx (for frontend)

## 📁 Struktur Project

*   `/backend`: Source code backend (Spring Boot)
*   `/frontend`: Source code frontend (Angular)
*   `/docs`: Dokumentasi teknis (Arsitektur, Database, AI, Auth)
*   `/archieve`: Versi lama aplikasi (Python/React) sebagai referensi
*   `docker-compose.yml`: Orchestration untuk seluruh stack
*   `.env.example`: Template konfigurasi environment

## 📚 Dokumentasi

Detail teknis dapat ditemukan di folder `docs/`:
*   [Setup Guide](SETUP.md) - Panduan instalasi lengkap
*   [Arsitektur Sistem](docs/ARCHITECTURE.md) - Overview arsitektur
*   [Desain Database](docs/DATABASE.md) - Schema dan struktur data
*   [Authentication System](docs/AUTH.md) - Detail sistem autentikasi
*   [Spring AI Integration](docs/AI.md) - Konfigurasi AI provider-agnostic
*   [Chat Integrations](docs/INTEGRATIONS.md) - Telegram & WhatsApp
*   [Note Module](docs/NOTE.md) - Rancangan note advanced

## 🚀 Quick Start

### Menggunakan Docker Compose (Recommended)

```bash
# 1. Clone repository
git clone <repository-url>
cd aspri

# 2. Setup environment
cp .env.example .env
# Edit .env dan ubah JWT_SECRET & POSTGRES_PASSWORD

# 3. Jalankan semua services
docker-compose up -d

# 4. Akses aplikasi
# Frontend: http://localhost
# Backend API: http://localhost:8080
# PostgreSQL: localhost:5432
```

### Development (Manual)

#### Backend
```bash
cd backend
mvn spring-boot:run
```

#### Frontend
```bash
cd frontend
npm install
npm start
```

#### Database
```bash
# Menggunakan Docker
docker run -d \
  --name aspri-postgres \
  -e POSTGRES_DB=aspri \
  -e POSTGRES_PASSWORD=postgres \
  -p 5432:5432 \
  postgres:17-alpine
```

## 🔐 Authentication

ASPRI menggunakan **manual authentication** (bukan Supabase Auth atau OAuth):
- Password di-hash dengan BCrypt
- JWT token generated dan validated oleh backend sendiri
- Fully portable ke PostgreSQL server manapun
- Tidak ada dependency ke provider eksternal

## 🔑 Environment Variables

Konfigurasi utama yang perlu diset (lihat `.env.example` untuk lengkapnya):

```bash
# Database
POSTGRES_PASSWORD=your-secure-password

# JWT (WAJIB DIGANTI!)
JWT_SECRET=your-secret-key-minimum-32-characters

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:4200

# Optional: Spring AI
SPRING_AI_OPENAI_API_KEY=your-key
SPRING_AI_OPENAI_MODEL=gpt-4

# Optional: Telegram Bot
TELEGRAM_BOT_TOKEN=your-bot-token
```

