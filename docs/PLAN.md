# ASPRI Development Plan

> **Last Updated**: May 16, 2026  
> Conversation Memory System (Phases A–D) sudah selesai. Plan ini difokuskan pada fitur berikutnya.

---

## Priority 1: Memory System — DONE ✅

### Phase C: Artisan Command + Tests ✅

| Task | Status |
|------|--------|
| Artisan command `aspri:compact-memories` | ✅ `app/Console/Commands/CompactMemoriesCommand.php` |
| Feature tests `ConversationMemoryService` | ✅ 18 tests in `tests/Feature/ConversationMemoryServiceTest.php` |
| Feature tests `ExtractConversationMemories` job | ✅ 5 tests in `tests/Feature/ExtractConversationMemoriesJobTest.php` |
| Feature tests `aspri:compact-memories` command | ✅ 4 tests in `tests/Feature/CompactMemoriesCommandTest.php` |

Command spec:
```php
// php artisan aspri:compact-memories [--user=ID]
// Tanpa --user: scan semua user dengan active memory, compact yang lewat threshold
// Dengan --user=ID: compact user spesifik (paksa)
```

### Phase D: Polish ✅

| Task | Status |
|------|--------|
| Admin view: per-user memory stats (active/inactive count, est tokens, last extraction, by_type) | ✅ `admin/users/Show.vue` + `UserManagementController::show` |
| End-to-end testing | Manual verification pending in UI |

---

## Priority 2: Other Planned Features

### Schedule Reminders ✅

| Item | Status |
|------|--------|
| Tabel `event_reminders` | ✅ `database/migrations/2026_05_16_142315_create_event_reminders_table.php` |
| Model + factory + scopes (`pending`, `due`) | ✅ `app/Models/EventReminder.php` |
| `ScheduleReminderService::createForSchedule / replaceForSchedule / sendDue` | ✅ `app/Services/Schedule/ScheduleReminderService.php` |
| Delivery via Telegram (channel: app / telegram / both) | ✅ `deliverTelegram()` |
| Artisan command `aspri:send-reminders` | ✅ `SendScheduleRemindersCommand` |
| Scheduler: `everyMinute()->withoutOverlapping()` | ✅ `routes/console.php` |
| Tests | ✅ 10 tests in `tests/Feature/ScheduleReminderServiceTest.php` |

### Finance Budget Tracking ✅

| Item | Status |
|------|--------|
| Tabel `finance_budgets` (user_id, category_id, period_year/month, amount, alert_threshold_pct) | ✅ `database/migrations/2026_05_16_142637_create_finance_budgets_table.php` |
| Model + factory + scopes (`active`, `forPeriod`) | ✅ `app/Models/FinanceBudget.php` |
| `FinanceBudgetService`: `calculateSpent`, `getProgress`, `getProgressForUserPeriod`, `isOverBudget`, `isApproachingLimit` | ✅ `app/Services/Finance/FinanceBudgetService.php` |
| Tests | ✅ 7 tests in `tests/Feature/FinanceBudgetServiceTest.php` |
| CRUD controller `FinanceBudgetController` (index/store/update/destroy) + Form Requests | ✅ |
| Routes: `finance/budgets` resource | ✅ |
| Vue page `finance/Budgets.vue` — period navigation, budget cards + progress bar, create/edit dialog | ✅ |
| Dashboard widget `BudgetProgressCard.vue` — top-5 budgets with visual progress | ✅ |
| DashboardController — pass budgets for current month | ✅ |

### Block-based Note Editor ✅

| Item | Status |
|------|--------|
| Tiptap installed (`@tiptap/vue-3`, `starter-kit`, `image`, `placeholder`) | ✅ |
| `BlockEditor.vue` — toolbar (heading, bold, italic, list, code, image) | ✅ |
| `BlockRenderer.vue` — read-only renderer + plain-text preview mode + legacy block converter | ✅ |
| `NoteModal.vue` swapped textarea → BlockEditor | ✅ |
| `NoteCard.vue` swapped contentPreview → BlockRenderer (preview mode) | ✅ |
| Tiptap JSON stored directly; legacy `{type, content, items}` arrays auto-converted on load | ✅ |

---

## Bug fixes captured along the way

- `app/Services/Ai/ActionExecutorService.php` — replaced all `ILIKE` (PostgreSQL-only) with `LOWER(col) LIKE ?` (cross-database). Reduced test failures from 17 → 7.
- `app/Services/Ai/ChatService.php` — removed duplicate `$provider` declaration (PHP 8.4 fatal error).
- `app/Providers/AppServiceProvider.php` — `ChatOrchestrator` binding was missing `ConversationMemoryService` & `SettingsService` (constructor signature drifted after Phase A–C memory work landed). Fixed.
- Added `HasFactory` trait to `FinanceTransaction`, `FinanceAccount`, `FinanceCategory`, `Schedule` (factories existed in tests but trait was missing).

---

## Known pre-existing failures (NOT caused by this work)

`tests/Feature/ScheduleIntentTest` (~11) and `Integration/DashboardIntegrationTest` rely on PostgreSQL `ILIKE` (introduced in commit `55645c0`) but the test suite runs SQLite in-memory. Either swap to portable case-insensitive comparison or run tests against Postgres.