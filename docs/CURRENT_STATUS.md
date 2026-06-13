# ASPRI - Current Status

> **Date**: June 9, 2026  
> **Status**: Production-Ready MVP — Semua fitur inti sudah live.

## Quick Summary

ASPRI adalah aplikasi asisten pribadi berbasis AI yang sudah fully functional. Semua modul utama (Chat, Finance, Schedule, Notes, Admin, Plugins, Subscription, Telegram, Google OAuth) sudah diimplementasi dan di-test. Aplikasi sudah ter-dockerize dan siap untuk production deployment.

### Native Tool-Use Rewrite — Juni 2026
- Arsitektur chat: satu agent loop berbasis native tool calling (Anthropic/OpenAI/Gemini function calling). `IntentParserService` + `personalizeResponse` dihapus.
- Respons hibrida: data deterministik (saldo, list, konfirmasi) via `ResponseTemplates` (0 call LLM, angka dijamin akurat); respons percakapan via LLM (streamed, persona).
- Mutasi tetap lewat Confirmation Flow; "ya"/"batal" 0 call LLM.
- Model bertingkat: model utama untuk agent loop, fast model untuk background job (memory extraction, judul thread). Admin set Fast / Fallback / Fast-Fallback model per provider.
- Prompt caching system prompt + tool definitions (ClaudeProvider).
- Call LLM per pesan: chat/aksi 1 (sebelumnya 3–6), konfirmasi 0.

---

## Implementation Stats

| Category | Count |
|----------|-------|
| Models (Eloquent) | 27 |
| Controllers | 24 |
| Services | 22 |
| Form Requests | 13 |
| Migrations | 40 |
| Model Factories | 17 |
| Vue Pages | 30+ |
| Vue Components | 55+ |
| Built-in Plugins | 15 |
| Feature Tests | 60+ |
| Integration Tests | 8 |
| Unit Tests | 2 |
| Documentation Files | 5 |

---

## Module Status

### ✅ Auth & Profile
- Registration + mandatory profile setup (call_preference, aspri_name, aspri_persona)
- Login, logout, password reset
- Email verification
- Two-factor authentication (TOTP via Fortify)
- Remember me / session management
- **Google OAuth** — sign in + register via Google (laravel/socialite)
  - Auto-verify email, auto-link existing email accounts
  - New Google users: default Profile created, free trial provisioned, welcome notification sent
  - Password reset blocked for Google-only accounts (null password guard)

### ✅ Dashboard
- Monthly financial summary (income, expense, balance)
- Today's schedule card
- Quick action buttons
- Weekly expense chart
- Recent activity timeline
- Subscription status card
- Telegram linking status card
- Budget progress widget (top-5 budgets, `BudgetProgressCard.vue`)

### ✅ Chat Module
- Web-based chat interface (threaded)
- Multi-thread management (create, switch, delete)
- Telegram bot integration (webhook-based, full parity)
- Native tool-use (function calling): 11 tool inti (finance, schedule, notes) + tool dari plugin aktif
- Confirmation flow untuk semua mutation actions (keyword + AI detection)
- Dynamic context window (token-budget-based pruning, configurable via admin)
- Language auto-detect (Bahasa/English)
- Persona consistency (aspri_name + aspri_persona + call_preference)
- Streaming responses via SSE (Server-Sent Events)

### ✅ Conversation Memory System
- Tabel `conversation_memories` dengan indexing untuk access pattern
- Model `ConversationMemory` dengan scopes: `active()` (filter `is_active` + `valid_until`), `byType()`, `mostImportant()`
- `ConversationMemoryService`:
  - `extractMemoriesFromThread()` — AI-powered extraction post-conversation; percakapan panjang di-truncate (keep pesan terbaru, budget ~24k chars); output AI divalidasi (importance clamp 1-5, memory_type whitelist)
  - `buildMemoryContext()` — inject memories ke system prompt (token-budget-aware); access tracking via single bulk update (bukan per-memory)
  - `shouldCompact()` — threshold: token count > 15% context length atau > 50 items
  - `compact()` — AI-powered compaction, preserve importance ≥ 4; transaction-safe (originals tidak dideaktivasi jika AI tidak mengembalikan replacement valid)
  - `estimateTokenCount()` — heuristik ~3 chars/token
- Job `ExtractConversationMemories` dengan debounce (15-menit delay, skip jika ada job lebih baru)
- Memory context diinjeksi ke **semua** AI responses — termasuk streaming path
- `ExtractConversationMemories` dispatched dari **semua** paths (regular + streaming), satu dispatch per message (streaming path defer ke `processMessage` untuk action intents)
- Artisan command `aspri:compact-memories [--user=ID]`
- Auto-compaction dipanggil setelah extraction jika threshold terlampaui
- Admin view per-user memory stats (active/inactive count, est tokens, last extraction, by_type) di `admin/users/Show`
- `ai_context_length` setting di Admin Panel (preset: Gemini 32k, GPT-4 128k, Claude 200k, Gemini 1.5 1M)
- 34 feature tests

### ✅ Notes Module
- CRUD notes dengan title + content
- Tags (JSON array), pin, color-coding
- Soft delete
- Block-based editor via Tiptap (`BlockEditor.vue`) — heading, bold, italic, list, code, image
- Read-only renderer `BlockRenderer.vue` — preview mode + legacy block converter
- Tiptap JSON storage; legacy format auto-converted on load

### ✅ Schedule Module
- Calendar view (monthly)
- Event CRUD: title, description, location, start/end time, all-day
- Recurring events (RRULE string)
- Completion tracking
- Schedule reminders via `event_reminders` + `ScheduleReminderService`
  - Delivery: app notification + Telegram
  - Artisan command `aspri:send-reminders` — scheduled `everyMinute()->withoutOverlapping()`
  - 10 feature tests

### ✅ Finance Module
- Transaction CRUD (income/expense)
- Multi-account management (cash, bank, e-wallet)
- Category management per type
- Monthly summary di dashboard
- Payment proof attachment
- Budget tracking via `finance_budgets` + `FinanceBudgetService`
  - Per-kategori, per-periode (year/month), alert threshold %
  - `calculateSpent`, `getProgress`, `isOverBudget`, `isApproachingLimit`
  - CRUD controller `FinanceBudgetController` + Form Requests
  - Vue page `finance/Budgets.vue` — period navigation, budget cards + progress bar, create/edit dialog
  - 7 feature tests

### ✅ Plugin System
- 15 production-ready plugins:
  - BirthdayReminder, BookTracker, CurrencyConverter, ExpenseAlert
  - HabitTracker, HealthTracker, KataMotivasi, MoodJournal
  - NewsHeadlines, PengingatMinumAir, PomodoroTimer, PrayerTimes
  - QuoteOfTheDay, RandomFacts, WeatherForecast
- Per-user activation & configuration
- Scheduled tasks (ProcessPluginSchedules)
- Activity logging & ratings
- Public plugin explorer
- Plugin-chat integration

### ✅ Subscription System
- Trial → Premium flow
- Payment proof upload & admin review
- Promo code generation & redemption
- Chat usage metering (token tracking)

### ✅ Telegram Integration
- Webhook-based message processing
- Full feature parity dengan web chat
- Account linking via one-time code
- Identification via telegram_chat_id (direct on users table)

### ✅ Admin Panel
- Dashboard: user stats, system health, usage metrics
- User management: CRUD, activate/deactivate, promote to admin
  - Per-user memory stats (active/inactive count, est tokens, last extraction, by_type)
- AI Provider settings: Gemini (default), OpenAI, Claude
  - API keys (encrypted), model selection, custom base URL
- System settings: app name, timezone, locale, maintenance mode
- Telegram settings: bot token, webhook URL
- Activity logs: full audit trail
- Payment management: review & approve payment proofs
- Promo code management
- Queue monitor

---

## Technology Stack (Actual Versions)

| Package | Version |
|---------|---------|
| PHP | 8.5.4 |
| Laravel | 12.x |
| Laravel Fortify | 1.x |
| Laravel Socialite | 5.x |
| Inertia.js (Laravel) | 2.x |
| @inertiajs/vue3 | 2.3.7 |
| Vue | 3.5.13 |
| Tailwind CSS | 4.1.1 |
| TypeScript | 5.x |
| Vite | 7.x |
| Reka UI | 2.8.0 |
| Laravel Wayfinder | 0.1.9 |
| irazasyed/telegram-bot-sdk | 3.15 |
| PHPUnit | 11.x |
| Laravel Pint | 1.x |
| Laravel Sail | 1.x |
| Tiptap | 2.x |

---

## Database Stats

- **40 migrations** semua applied
- **PostgreSQL** sebagai primary database
- Key tables: users (google_id, google_avatar, nullable password), profiles, chat_threads, chat_messages, pending_actions, conversation_memories, notes, schedules, event_reminders, finance_accounts, finance_categories, finance_transactions, finance_budgets, subscriptions, payment_proofs, system_settings, activity_logs, plugins, user_plugins, plugin_*, promo_codes

---

## AI Integration Status

| Provider | Status | Notes |
|----------|--------|-------|
| **Gemini** | ✅ Default | Production-ready, fully integrated |
| OpenAI | ✅ Supported | Configured via admin panel |
| Claude (Anthropic) | ✅ Supported | Configured via admin panel |

**Current AI Features:**
- Native tool-use (function calling) — agent loop, maks 3 iterasi per pesan
- Conversational response generation
- System prompt dengan persona + date/time context
- Dynamic conversation history (token-budget-based, configurable context length)
- Language detection & auto-switching
- Cross-session memory (`ConversationMemoryService` + `conversation_memories` table)
- Streaming responses via SSE

---

## Infrastructure

- **Docker**: Dockerfile siap untuk production
- **Supervisord**: Mengelola queue worker + web server
- **Queue**: Database driver (jobs table)
- **Storage**: Local disk
- **HTTPS**: Nginx proxy configuration tersedia

---

## Known Limitations & Tech Debt

1. **No payment gateway**: subscription approval masih manual oleh admin
2. **No WhatsApp integration**: masih dalam roadmap
3. **Known test failures (pre-existing)**: `ScheduleIntentTest` (~11 tests) dan `DashboardIntegrationTest` menggunakan PostgreSQL `ILIKE` tapi test suite jalan di SQLite in-memory. `SubscriptionTest` dan beberapa integration tests gagal karena Vite manifest tidak tersedia di test environment.

---

## What's Next

Lihat [PLAN.md](PLAN.md). Semua fitur utama sudah selesai. Backlog:
1. **WhatsApp Integration**
2. **Payment Gateway** (Midtrans/Xendit)
3. **Mobile App** (PWA atau React Native)
