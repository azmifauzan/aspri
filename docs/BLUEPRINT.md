# ASPRI Blueprint

## Vision Statement

ASPRI adalah asisten pribadi cerdas berbasis AI yang membantu pengguna mengelola jadwal dan keuangan harian melalui interaksi percakapan natural, baik via web maupun messaging platform.

## Core Principles

### 1. Chat-First Experience
- Semua fitur utama dapat diakses melalui percakapan
- Natural language processing untuk perintah
- Konfirmasi sebelum aksi yang mengubah data

### 2. Persona Per User
- Asisten mengikuti `aspri_persona` user
- Memanggil user sesuai `call_preference`
- Personalisasi pengalaman interaksi

### 3. Multi-Platform Integration
- Web application sebagai hub utama
- Telegram sebagai channel pertama
- WhatsApp (future roadmap)

### 4. Data Privacy & Security
- Isolasi data per user
- No third-party auth dependency
- Encrypted sensitive data

## Feature Specifications

### Dashboard Module

| Feature | Priority | Description |
|---------|----------|-------------|
| Monthly Summary | High | Total pengeluaran, income, dan selisih bulan ini |
| Today's Schedule | High | Daftar event dan meeting hari ini |
| Quick Actions | High | Shortcut untuk add expense, create event |
| Weekly Chart | Medium | Visualisasi pengeluaran mingguan |
| Recent Activity | Medium | Timeline aktivitas terbaru |

**UI Reference**: 
![Dashboard Mockup](../archieve/uploaded_media_0_1769343967610.png)

### Admin Module (Super Admin)

| Feature | Priority | Description |
|---------|----------|-------------|
| Admin Dashboard | High | User stats, system health, usage metrics |
| User Management | High | CRUD users, toggle active, reset password |
| AI Provider Settings | High | Configure Gemini/OpenAI/Claude, API keys |
| System Settings | Medium | App name, locale, timezone, maintenance |
| Telegram Settings | Medium | Bot token, webhook URL configuration |
| Activity Logs | Low | Track admin actions |

### Chat Module

| Feature | Priority | Description |
|---------|----------|-------------|
| Web Chat | High | UI chat di web application |
| Chat History | High | Riwayat percakapan tersimpan |
| Intent Recognition | High | Parse perintah natural language |
| Action Confirmation | High | Konfirmasi untuk create/update/delete |
| Telegram Integration | High | Bot Telegram terintegrasi |
| Context Awareness | Medium | Chat memahami konteks sebelumnya |

### Notes Module

| Feature | Priority | Description |
|---------|----------|-------------|
| Create/Edit Note | High | Basic CRUD notes |
| Block-based Content | Medium | Support berbagai tipe block |
| Tags | Medium | Kategorisasi dengan tag |
| Search | Medium | Full-text search notes |
| Backlinks | Low | Link antar notes |
| Version History | Low | Track perubahan note |

### Schedule Module

| Feature | Priority | Description |
|---------|----------|-------------|
| Calendar View | High | Tampilan kalender bulanan |
| Create Event | High | Tambah event dengan detail |
| Reminders | High | Pengingat via web dan Telegram |
| Event List | High | Daftar event yang akan datang |
| Recurring Events | Medium | Event berulang (RRULE) |
| Multiple Calendars | Low | Kategorisasi kalender |

### Finance Module

| Feature | Priority | Description |
|---------|----------|-------------|
| Add Transaction | High | Catat income/expense |
| Categories | High | Kategori transaksi |
| Account Management | High | Multiple financial accounts |
| Monthly Report | High | Laporan bulanan |
| Budget Tracking | Medium | Target budget per kategori |
| Charts & Analytics | Medium | Visualisasi data keuangan |
| Export Data | Low | Export ke CSV/PDF |

## User Stories

### As a new user:
1. Saya bisa mendaftar dengan email dan password
2. Saya bisa mengatur preferensi panggilan dan persona asisten
3. Saya bisa menghubungkan akun Telegram saya

### As an active user (Chat):
1. Saya bisa chat dengan asisten via web
2. Saya bisa chat dengan asisten via Telegram
3. Saya bisa mencatat pengeluaran dengan perintah "Beli kopi 25rb"
4. Asisten akan konfirmasi sebelum menyimpan data
5. Asisten memanggil saya sesuai preferensi

### As an active user (Finance):
1. Saya bisa melihat ringkasan keuangan di dashboard
2. Saya bisa menambah transaksi dari menu keuangan
3. Saya bisa melihat chart pengeluaran mingguan
4. Saya bisa melihat transaksi per kategori

### As an active user (Schedule):
1. Saya bisa melihat jadwal hari ini di dashboard
2. Saya bisa membuat event baru
3. Saya bisa mengatur reminder
4. Saya menerima notifikasi via Telegram

## API Endpoints

### Authentication
- `POST /register` - User registration
- `POST /login` - User login
- `POST /logout` - User logout
- `POST /forgot-password` - Password reset request
- `POST /reset-password` - Password reset

### Dashboard
- `GET /dashboard` - Dashboard page with summary data

### Chat
- `GET /chat` - Chat page with threads
- `GET /chat/{thread}` - Specific thread
- `POST /chat/message` - Send message
- `POST /api/telegram/webhook` - Telegram webhook

### Notes
- `GET /notes` - List notes
- `GET /notes/{note}` - View note
- `POST /notes` - Create note
- `PUT /notes/{note}` - Update note
- `DELETE /notes/{note}` - Delete note

### Schedule
- `GET /schedule` - Calendar view
- `GET /schedule/events` - List events (JSON)
- `POST /schedule/events` - Create event
- `PUT /schedule/events/{event}` - Update event
- `DELETE /schedule/events/{event}` - Delete event

### Finance
- `GET /finance` - Finance overview
- `GET /finance/transactions` - List transactions
- `POST /finance/transactions` - Create transaction
- `PUT /finance/transactions/{transaction}` - Update
- `DELETE /finance/transactions/{transaction}` - Delete
- `GET /finance/categories` - List categories
- `GET /finance/accounts` - List accounts
- `GET /finance/report` - Generate report

## Database Schema Summary

Lihat [DATABASE.md](DATABASE.md) untuk detail lengkap.

### Core Tables:
- `users` - User authentication
- `profiles` - User preferences & persona settings
- `external_identities` - Telegram/WhatsApp linking
- `chat_threads` & `chat_messages` - Chat history
- `notes` & `note_blocks` - Notes with block content
- `calendars` & `events` - Schedule management
- `event_reminders` - Reminder settings
- `finance_accounts` - Financial accounts
- `finance_categories` - Transaction categories
- `finance_transactions` - Transaction records
