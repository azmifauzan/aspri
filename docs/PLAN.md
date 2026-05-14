# ASPRI Development Plan

> **Last Updated**: May 14, 2026  
> Conversation Memory System (Phases A–C core) sudah diimplementasi. Plan ini difokuskan pada polish memory system dan fitur-fitur berikutnya.

---

## Priority 1: Memory System — Remaining Tasks (Phase C–D)

Implementasi inti sudah selesai. Yang tersisa:

### Phase C: Artisan Command + Tests

| Task | Estimate |
|------|----------|
| Buat artisan command `aspri:compact-memories` | 1 jam |
| Feature tests untuk `ConversationMemoryService` | 3 jam |
| Feature tests untuk `ExtractConversationMemories` job | 2 jam |

Command spec:
```php
// php artisan aspri:compact-memories [--user=ID]
// Tanpa --user: proses semua user yang memenuhi threshold
// Dengan --user=ID: compact untuk user tertentu
```

### Phase D: Polish

| Task | Estimate |
|------|----------|
| Admin view: per-user memory stats (count, token usage, last extraction) | 2 jam |
| End-to-end testing | 2 jam |

---

## Priority 2: Other Planned Features (Lower Priority)

### Schedule Reminders
- Tambah tabel `event_reminders`
- Kirim reminder via Telegram sebelum event
- Konfigurasi: berapa menit sebelumnya, channel (app/telegram)
- Artisan command untuk proses reminder queue

### Finance Budget Tracking
- Tambah tabel `finance_budgets`
- Budget per kategori per bulan
- Alert ketika mendekati/melebihi budget (via ExpenseAlert plugin atau built-in)
- Dashboard widget untuk budget progress

### Block-based Note Editor
- Ganti simple textarea dengan block editor (Tiptap atau ProseMirror)
- Support: heading, paragraph, list, code block, image
- Simpan sebagai JSON blocks, render dengan renderer