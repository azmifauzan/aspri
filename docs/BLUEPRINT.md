# ASPRI Blueprint

> **Last Updated**: May 2026  
> Status: Production-ready. Fitur inti selesai. Rencana selanjutnya di [PLAN.md](PLAN.md).

## Vision Statement

ASPRI adalah asisten pribadi cerdas berbasis AI yang membantu pengguna mengelola jadwal dan keuangan harian melalui interaksi percakapan natural, baik via web maupun messaging platform (Telegram).

## Core Principles

### 1. Chat-First Experience
- Semua fitur utama dapat diakses melalui percakapan
- Natural language processing untuk semua perintah
- Konfirmasi sebelum aksi yang mengubah data

### 2. Persona Per User
- Asisten mengikuti `aspri_persona` user (pria, wanita, kucing, custom, dll.)
- Memanggil user sesuai `call_preference` (Kak, Bapak, Ibu, dll.)
- Personalisasi yang konsisten di semua channel (web + Telegram)

### 3. Multi-Platform Integration
- Web application sebagai hub utama
- Telegram sebagai channel aktif (terimplementasi)
- WhatsApp (future roadmap)

### 4. Data Privacy & Security
- Isolasi data ketat per user_id
- Tidak ada dependensi third-party auth
- Enkripsi data sensitif (API keys via `encrypted` flag di system_settings)

### 5. Extensibility via Plugins
- Arsitektur plugin yang modular dan extensible
- 15 built-in plugins, siap ditambah lebih banyak
- Setiap plugin bisa punya jadwal, konfigurasi, dan integrasi chat sendiri

## Feature Status

### Dashboard Module ✅ DONE

| Feature | Status | Notes |
|---------|--------|-------|
| Monthly Summary | ✅ Done | Income, expense, balance |
| Today's Schedule | ✅ Done | Daftar event hari ini |
| Quick Actions | ✅ Done | Shortcut add expense, new event |
| Weekly Chart | ✅ Done | Chart pengeluaran 7 hari |
| Recent Activity | ✅ Done | Timeline aktivitas |
| Subscription Card | ✅ Done | Status trial/premium |
| Telegram Card | ✅ Done | Status link + instruksi |

### Admin Module ✅ DONE

| Feature | Status | Notes |
|---------|--------|-------|
| Admin Dashboard | ✅ Done | User stats, system health |
| User Management | ✅ Done | CRUD, activate/deactivate |
| AI Provider Settings | ✅ Done | Gemini/OpenAI/Claude + API keys |
| System Settings | ✅ Done | App name, locale, timezone |
| Telegram Settings | ✅ Done | Bot token, webhook URL |
| Activity Logs | ✅ Done | Admin action tracking |
| Promo Codes | ✅ Done | Generate, list, manage |
| Payment Management | ✅ Done | Review payment proofs |
| Queue Monitor | ✅ Done | Queue status overview |

### Chat Module ✅ DONE (core)

| Feature | Status | Notes |
|---------|--------|-------|
| Web Chat UI | ✅ Done | Thread-based interface |
| Chat History | ✅ Done | Multiple threads per user |
| Intent Recognition | ✅ Done | Finance, schedule, notes, plugin |
| Action Confirmation | ✅ Done | Keyword + AI detection |
| Telegram Integration | ✅ Done | Full webhook-based |
| Conversation Context | ✅ Done | History dalam 1 thread (20 pesan terakhir) |
| Cross-Session Memory | 🔲 Planned | Lihat PLAN.md |
| Plugin-Chat Bridge | ✅ Done | Plugin actions via chat |

### Notes Module ✅ DONE

| Feature | Status | Notes |
|---------|--------|-------|
| Create/Edit Note | ✅ Done | CRUD lengkap |
| Text Content | ✅ Done | LongText content |
| Tags | ✅ Done | JSON array |
| Pin Notes | ✅ Done | is_pinned flag |
| Soft Delete | ✅ Done | deleted_at |
| Color Coding | ✅ Done | color field |
| Block-based Editor | 🔲 Planned | Future enhancement |
| Backlinks | 🔲 Planned | Future enhancement |

### Schedule Module ✅ DONE

| Feature | Status | Notes |
|---------|--------|-------|
| Calendar View | ✅ Done | Monthly calendar |
| Create/Edit Event | ✅ Done | CRUD dengan detail |
| All-day Events | ✅ Done | is_all_day flag |
| Location | ✅ Done | location field |
| Completion Tracking | ✅ Done | is_completed flag |
| Recurring Events | ✅ Done | RRULE string (is_recurring + recurrence_rule) |
| Reminders | 🔲 Planned | Belum ada event_reminders table |
| Multiple Calendars | 🔲 Planned | Future enhancement |

### Finance Module ✅ DONE

| Feature | Status | Notes |
|---------|--------|-------|
| Add Transaction | ✅ Done | income/expense |
| Categories | ✅ Done | Per tx_type |
| Account Management | ✅ Done | cash/bank/e-wallet |
| Monthly Report | ✅ Done | Summary di dashboard |
| Payment Proof | ✅ Done | Attachment upload |
| Budget Tracking | 🔲 Planned | Belum ada finance_budgets table |
| Export Data | 🔲 Planned | CSV/PDF future |

### Plugin System ✅ DONE

| Feature | Status | Notes |
|---------|--------|-------|
| Plugin Architecture | ✅ Done | PluginInterface + BasePlugin |
| Plugin Registry | ✅ Done | Discovery + DB registry |
| 15 Built-in Plugins | ✅ Done | Production-ready |
| Per-user Activation | ✅ Done | user_plugins table |
| Configuration Schema | ✅ Done | JSON schema per plugin |
| Scheduled Tasks | ✅ Done | ProcessPluginSchedules |
| Activity Logging | ✅ Done | plugin_logs table |
| Plugin Ratings | ✅ Done | plugin_ratings table |
| Public Plugin Explorer | ✅ Done | /explore-plugins route |

### Subscription System ✅ DONE

| Feature | Status | Notes |
|---------|--------|-------|
| Trial Period | ✅ Done | trial_ends_at |
| Premium Subscription | ✅ Done | Manual activation by admin |
| Payment Proof Upload | ✅ Done | payment_proofs table |
| Promo Codes | ✅ Done | Generate + redeem |
| Chat Usage Metering | ✅ Done | ChatUsageLog |
| Stripe/Midtrans Integration | 🔲 Planned | Future payment gateway |

## User Stories

### As a new user:
1. ✅ Saya bisa mendaftar dengan email dan password
2. ✅ Saya harus mengisi preferensi panggilan dan persona asisten saat registrasi
3. ✅ Saya bisa menghubungkan akun Telegram via kode unik
4. ✅ Saya menerima email selamat datang setelah registrasi

### As an active user (Chat):
1. ✅ Saya bisa chat dengan asisten via web (thread-based)
2. ✅ Saya bisa chat dengan asisten via Telegram
3. ✅ Saya bisa mencatat pengeluaran dengan perintah "Beli kopi 25rb"
4. ✅ Asisten akan konfirmasi sebelum menyimpan/mengubah/menghapus data
5. ✅ Asisten memanggil saya sesuai call_preference di semua response
6. ✅ Asisten merespons dalam bahasa yang saya gunakan (Indonesia/Inggris)
7. 🔲 Asisten mengingat preferensi dan informasi penting dari percakapan sebelumnya

### As an active user (Finance):
1. ✅ Saya bisa melihat ringkasan keuangan di dashboard
2. ✅ Saya bisa menambah transaksi dari menu keuangan
3. ✅ Saya bisa melihat chart pengeluaran mingguan
4. ✅ Saya bisa mengelola kategori dan akun keuangan

### As an active user (Schedule):
1. ✅ Saya bisa melihat jadwal hari ini di dashboard
2. ✅ Saya bisa membuat event dengan detail (lokasi, waktu, deskripsi)
3. ✅ Saya bisa mengatur event berulang
4. 🔲 Saya menerima notifikasi reminder via Telegram

### As an active user (Plugins):
1. ✅ Saya bisa mengeksplor dan mengaktifkan plugin dari halaman plugins
2. ✅ Saya bisa mengkonfigurasi plugin sesuai kebutuhan
3. ✅ Saya bisa menggunakan plugin via chat (e.g., "lihat cuaca Jakarta")

### As an admin:
1. ✅ Saya bisa melihat statistik penggunaan sistem
2. ✅ Saya bisa mengelola user (activate/deactivate/promote)
3. ✅ Saya bisa mengkonfigurasi provider AI dan API key
4. ✅ Saya bisa mereview dan approve pembayaran subscription
5. ✅ Saya bisa membuat dan mengelola promo code

## API Endpoints (Actual Routes)

### Authentication (Fortify)
- `POST /register` - User registration + profile creation
- `POST /login` - User login
- `POST /logout` - User logout
- `POST /forgot-password` - Password reset request
- `POST /reset-password` - Password reset

### Dashboard
- `GET /dashboard` - Dashboard dengan semua widget data

### Chat
- `GET /chat` - Chat page dengan thread list
- `GET /chat/{thread}` - View specific thread
- `POST /chat/message` - Send message (returns AI response)
- `DELETE /chat/{thread}` - Delete thread

### Notes
- `GET /notes` - List semua notes
- `POST /notes` - Create note
- `PATCH /notes/{note}` - Update note
- `DELETE /notes/{note}` - Delete note (soft delete)

### Schedule
- `GET /schedule` - Calendar view
- `GET /schedule/events` - List events (JSON for calendar)
- `POST /schedule/events` - Create event
- `PATCH /schedule/events/{schedule}` - Update event
- `DELETE /schedule/events/{schedule}` - Delete event

### Finance
- `GET /finance` - Finance overview
- `GET /finance/transactions` - Transaction list
- `POST /finance/transactions` - Create transaction
- `PATCH /finance/transactions/{transaction}` - Update
- `DELETE /finance/transactions/{transaction}` - Delete
- `GET /finance/categories` - Categories list
- `POST /finance/categories` - Create category
- `GET /finance/accounts` - Accounts list
- `POST /finance/accounts` - Create account

### Settings
- `GET /settings/profile` - Profile & persona settings
- `PATCH /settings/profile` - Update profile
- `DELETE /settings/profile` - Delete account
- `PUT /settings/password` - Update password
- `GET /settings/telegram` - Telegram linking page
- `POST /settings/telegram/generate-code` - Generate link code
- `GET /settings/two-factor` - 2FA management

### Plugins
- `GET /plugins` - Plugin management (user's plugins)
- `GET /explore-plugins` - Public plugin explorer
- `POST /plugins/{plugin}/activate` - Activate plugin
- `DELETE /plugins/{plugin}/deactivate` - Deactivate
- `GET /plugins/{plugin}` - Plugin detail
- `PATCH /plugins/{plugin}/configure` - Update config
- `POST /plugins/{plugin}/rate` - Rate plugin

### Subscription
- `GET /subscription` - Subscription status
- `POST /subscription/upload-proof` - Upload payment proof
- `POST /subscription/redeem-promo` - Redeem promo code

### Admin
- `GET /admin` - Admin dashboard
- `GET /admin/users` - User list
- `POST /admin/users` - Create user
- `PATCH /admin/users/{user}` - Update user
- `DELETE /admin/users/{user}` - Delete user
- `POST /admin/users/{user}/toggle-active` - Toggle active
- `POST /admin/users/{user}/promote` - Promote to admin
- `GET /admin/settings` - System settings
- `POST /admin/settings/ai` - Update AI settings
- `POST /admin/settings/telegram` - Update Telegram settings
- `POST /admin/settings/general` - Update general settings
- `GET /admin/payments` - Payment proofs list
- `POST /admin/payments/{payment}/approve` - Approve payment
- `GET /admin/promo-codes` - Promo codes
- `POST /admin/promo-codes` - Create promo code
- `GET /admin/activity` - Activity logs
- `GET /admin/queues` - Queue monitor

### API (Public)
- `POST /api/telegram/webhook` - Telegram bot webhook

## Database Schema Summary

Lihat [DATABASE.md](DATABASE.md) untuk detail lengkap.

### Implemented Tables (33 migrations):
- `users` - Auth + telegram fields + role + subscription fields
- `profiles` - User preferences & persona (ALL fields required)
- `password_reset_tokens`, `sessions` - Laravel default
- `cache`, `jobs`, `job_batches`, `failed_jobs` - Laravel default
- `chat_threads`, `chat_messages` - Chat system
- `pending_actions` - Confirmation flow
- `notes` - Notes with JSON tags
- `schedules` - Events/calendar
- `finance_accounts`, `finance_categories`, `finance_transactions` - Finance
- `payment_proofs` - Subscription payment evidence
- `system_settings` - Global configuration
- `activity_logs` - Admin audit trail
- `subscriptions`, `promo_codes`, `promo_code_redemptions` - Subscription
- `chat_usage_logs` - Usage metering
- `plugins`, `user_plugins`, `plugin_configurations`, `plugin_schedules`, `plugin_logs`, `plugin_ratings` - Plugin system
