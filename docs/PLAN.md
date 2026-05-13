# ASPRI Development Plan

> **Last Updated**: May 2026  
> Rencana ini difokuskan pada fitur-fitur prioritas tinggi berikutnya, terutama **Conversation Memory System**.

---

## Priority 1: Conversation Memory System

### Latar Belakang & Masalah

Saat ini, ASPRI hanya mengirimkan 20 pesan terakhir dari thread yang sedang aktif sebagai context ke LLM. Ketika user membuka thread baru atau berganti sesi, LLM tidak memiliki ingatan apapun tentang percakapan sebelumnya.

**Masalah nyata:**
- User harus menjelaskan ulang preferensinya setiap sesi baru
- LLM tidak tahu gaya hidup atau kebiasaan user (e.g., "gaji tiap tanggal 25")
- Tidak ada continuity across sessions
- Asisten terasa "lupa" padahal sudah banyak riwayat percakapan

### Referensi Best Practice Frontier LLM

Frontier LLM seperti Claude (claude.ai), ChatGPT, dan Gemini Advanced menerapkan memory dengan prinsip berikut:

1. **Hierarchical Memory Tiers**
   - *Working memory*: Pesan dalam thread aktif (sudah ada)
   - *Episodic memory*: Ringkasan percakapan-percakapan sebelumnya
   - *Semantic memory*: Fakta-fakta penting yang diekstrak (user preferences, key facts)

2. **Selective Extraction**
   - Tidak semua pesan perlu diingat; hanya informasi yang relevan dan penting
   - Contoh: "gaji saya 15 juta" → simpan. "terima kasih" → tidak perlu disimpan
   - Gunakan AI sendiri untuk menentukan apa yang penting

3. **Memory Deduplication & Merging**
   - Memory yang redundan atau bertentangan harus digabung/diupdate
   - "user prefers morning reminders" vs "user changed to evening" → update, bukan duplikat

4. **Context Budget Management**
   - Token untuk memory diambil dari context window yang ada
   - Prioritas: System prompt > Recent conversation > Long-term memory
   - Alokasi budget berdasarkan configured context length

5. **Compaction (Memory Compression)**
   - Ketika memory melebihi threshold, ringkas yang lama menjadi summary
   - Pertahankan yang paling recent dan paling penting
   - Mirip dengan teknik "sliding window" + "rolling summary" yang dipakai Claude

6. **Memory Scoring & Decay**
   - Setiap memory punya importance score (1-5)
   - Memory lama yang tidak pernah relevan mendapat decay
   - Memory yang sering direferensikan mendapat boost

---

## Feature 1: Conversation Memory System

### 1.1 Database — Tabel `conversation_memories`

```php
// Migration: create_conversation_memories_table
Schema::create('conversation_memories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('memory_type');
    // 'fact'       — Fakta tentang user (preferensi, kebiasaan, data penting)
    // 'event'      — Kejadian/keputusan signifikan dari percakapan lalu
    // 'summary'    — Ringkasan percakapan (hasil compaction)
    // 'preference' — Preferensi yang user nyatakan secara eksplisit
    $table->text('content');           // Isi memory dalam natural language
    $table->string('source_thread_id')->nullable(); // Thread asal memory
    $table->integer('importance')->default(3); // 1 (low) - 5 (high)
    $table->integer('access_count')->default(0); // Berapa kali memory ini diakses
    $table->timestamp('last_accessed_at')->nullable();
    $table->timestamp('valid_until')->nullable(); // Null = permanent
    $table->boolean('is_active')->default(true);
    $table->json('metadata')->nullable(); // Module terkait, tags, dsb.
    $table->timestamps();

    $table->index(['user_id', 'is_active', 'importance']);
    $table->index(['user_id', 'memory_type']);
    $table->index(['user_id', 'last_accessed_at']);
});
```

### 1.2 Model `ConversationMemory`

- Buat via `php artisan make:model ConversationMemory --factory`
- Relationships: `belongsTo(User::class)`
- Scopes: `active()`, `byType()`, `mostImportant()`
- Method: `recordAccess()` — update `access_count` dan `last_accessed_at`

### 1.3 Service `ConversationMemoryService`

**Tanggung jawab:**

```
app/Services/Ai/ConversationMemoryService.php
```

#### Method: `extractMemoriesFromThread(ChatThread $thread, User $user): void`
- Dipanggil secara async (queued job) setelah percakapan selesai (thread tidak aktif > X menit)
- Kirim ringkasan thread ke AI dengan prompt khusus
- AI mengembalikan JSON array of memories: `[{type, content, importance, metadata}]`
- Simpan ke `conversation_memories`, skip yang redundan dengan fuzzy match
- Hapus/non-aktifkan memory lama yang bertentangan

#### Method: `buildMemoryContext(User $user, int $tokenBudget): string`
- Ambil memories yang paling relevan dan penting dalam budget token tertentu
- Prioritas: importance DESC → last_accessed_at DESC → created_at DESC
- Return sebagai formatted string untuk diinjeksi ke system prompt
- Update `last_accessed_at` dan `access_count` untuk semua memory yang diambil

#### Method: `shouldCompact(User $user, int $contextLength): bool`
- Check apakah total token memory user mendekati threshold
- Threshold: memory tokens > `context_length * MEMORY_RATIO` (default 0.15)

#### Method: `compact(User $user): void`
- Kirim semua memories aktif user ke AI untuk diringkas
- Hapus memories lama, simpan ringkasan baru dengan `memory_type = 'summary'`
- Pertahankan memories dengan `importance >= 4` (tidak di-compact)
- Log kompaksi ke activity_logs

#### Method: `estimateTokenCount(string $text): int`
- Estimasi jumlah token menggunakan heuristik sederhana
- Aturan umum: ~4 karakter per token untuk bahasa Inggris, ~2-3 untuk Indonesia

### 1.4 Job `ExtractConversationMemories`

```
app/Jobs/ExtractConversationMemories.php
```

- Implements `ShouldQueue`
- Menerima `ChatThread $thread`
- Dipanggil dari `ChatController` setelah setiap response (atau dengan delay)
- Strategi trigger: setelah thread tidak mendapat pesan selama 15 menit, atau ketika thread di-close

### 1.5 Update `ChatService::buildSystemPrompt()`

Tambahkan memory context ke system prompt:

```php
public function buildSystemPrompt(User $user, string $memoryContext = ''): string
{
    // ... existing prompt ...

    if (!empty($memoryContext)) {
        $prompt .= "\n\nInformasi penting yang kamu ingat tentang user ini:\n" . $memoryContext;
    }

    return $prompt;
}
```

### 1.6 Update `ChatOrchestrator::processMessage()`

```php
public function processMessage(User $user, string $message, ChatThread $thread, array $conversationHistory = []): array
{
    // Ambil memory context di awal
    $contextLength = $this->settingsService->get('ai_context_length', 32000);
    $memoryBudget = (int)($contextLength * 0.15); // 15% untuk memory
    $memoryContext = $this->memoryService->buildMemoryContext($user, $memoryBudget);

    // ... rest of processing dengan $memoryContext diteruskan ke ChatService ...
}
```

---

## Feature 2: Context Length Setting di Admin Panel

### 2.1 Admin Settings — Tambah Field Context Length

Update `SettingsService::getAiSettings()` untuk include:

```php
'ai_context_length' => (int) $this->get('ai_context_length', 32000),
```

Update `SettingsService::updateAiSettings()` untuk handle:

```php
if (array_key_exists('ai_context_length', $data) && $data['ai_context_length']) {
    $this->set('ai_context_length', (int) $data['ai_context_length'], ['group' => 'ai']);
}
```

### 2.2 Admin Settings Page — Vue Component Update

Update `resources/js/pages/admin/settings/Index.vue` (atau file settings AI):
- Tambahkan input field **"Context Length (tokens)"** di bagian AI Provider Settings
- Input type: number, min: 4096, max: 2000000
- Tooltip/helper text: "Panjang context window model AI yang digunakan. Dipakai untuk menghitung budget memory percakapan."
- Tampilkan preset cepat: Gemini Pro (32k), GPT-4 Turbo (128k), Claude 3 (200k)

### 2.3 Form Validation

Update atau buat Form Request untuk validasi:
```php
'ai_context_length' => ['nullable', 'integer', 'min:4096', 'max:2000000'],
```

---

## Feature 3: Context Budget Management

### 3.1 Token Budget Allocation

Dengan context length `L` yang dikonfigurasi:

| Komponen | Alokasi | Keterangan |
|----------|---------|-----------|
| System prompt + persona | ~15% | Fixed, bisa diestimasi |
| Long-term memory | 15% | `conversation_memories` |
| Active conversation history | 60% | Pesan dalam thread aktif |
| Response buffer | 10% | Ruang untuk AI response |

### 3.2 Dynamic History Limit

`ChatService::formatMessages()` saat ini hardcode `array_slice($conversationHistory, -20)`. Ganti dengan kalkulasi dinamis:

```php
public function formatMessages(User $user, string $userMessage, array $conversationHistory = [], string $memoryContext = ''): array
{
    $contextLength = $this->settingsService->get('ai_context_length', 32000);

    // Estimasi token system prompt
    $systemPrompt = $this->buildSystemPrompt($user, $memoryContext);
    $systemTokens = $this->estimateTokenCount($systemPrompt);

    // Budget untuk history (60% dari total, minus system prompt yang sudah dipakai)
    $historyBudget = (int)($contextLength * 0.60) - $systemTokens;

    // Ambil history dari yang terbaru, sampai budget habis
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    $tokenUsed = 0;
    $reversedHistory = array_reverse($conversationHistory);
    $selectedHistory = [];

    foreach ($reversedHistory as $msg) {
        $msgTokens = $this->estimateTokenCount($msg['content'] ?? '');
        if ($tokenUsed + $msgTokens > $historyBudget) break;
        $selectedHistory[] = $msg;
        $tokenUsed += $msgTokens;
    }

    foreach (array_reverse($selectedHistory) as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }

    $messages[] = ['role' => 'user', 'content' => $userMessage];
    return $messages;
}
```

### 3.3 Compaction Trigger

Panggil `ConversationMemoryService::compact()` secara background ketika:
- Setelah `extractMemoriesFromThread()` selesai
- Memory count user > threshold (misal 50 active memories)
- Atau via artisan command untuk manual trigger: `php artisan aspri:compact-memories`

---

## Feature 4: Memory Extraction Prompt Design

### 4.1 Prompt untuk Memory Extraction

```
Kamu adalah sistem memory untuk asisten AI. Analisis percakapan berikut dan ekstrak
informasi penting yang harus diingat tentang user ini untuk percakapan mendatang.

Percakapan:
{conversation_text}

Ekstrak informasi dalam format JSON array. Setiap item harus memiliki:
- "type": "fact" | "preference" | "event" | "pattern"
- "content": Ringkasan singkat dalam 1-2 kalimat (bahasa yang sama dengan user)
- "importance": 1-5 (5 = sangat penting, 1 = mungkin berguna)
- "metadata": {"module": "finance|schedule|general", "tags": []}

Panduan kepentingan:
- 5: Informasi keuangan kritis, preferensi utama, data yang sering digunakan
- 4: Kebiasaan rutin, preferensi yang dinyatakan eksplisit
- 3: Informasi berguna tapi tidak kritis
- 2: Konteks tambahan
- 1: Informasi yang mungkin sudah usang

Jangan ekstrak: salam biasa, konfirmasi pendek, pertanyaan teknis satu-kali.

Kembalikan HANYA JSON array, tanpa penjelasan tambahan.
```

### 4.2 Prompt untuk Memory Compaction

```
Kamu adalah sistem memory. Ringkas kumpulan memories berikut menjadi set yang lebih kompak.
Pertahankan semua informasi penting, hilangkan duplikat, gabungkan yang tumpang tindih.

Memories saat ini:
{memories_text}

Kembalikan JSON array dari memories yang sudah dipadatkan, format sama seperti input.
Maksimal {max_count} memories. Prioritaskan importance tinggi dan informasi terbaru.
```

---


## Implementation Phases

### Phase A: Foundation (Estimasi: 3-4 hari)

| Task | Estimate |
|------|----------|
| Buat migration `conversation_memories` | 1 jam |
| Buat model `ConversationMemory` + factory | 1 jam |
| Buat service `ConversationMemoryService` (skeleton) | 2 jam |
| Implementasi `extractMemoriesFromThread()` | 3 jam |
| Implementasi `buildMemoryContext()` | 2 jam |
| Buat job `ExtractConversationMemories` | 1 jam |
| Update `ChatService::buildSystemPrompt()` | 1 jam |
| Update `ChatOrchestrator::processMessage()` | 2 jam |
| Tulis feature tests untuk memory service | 3 jam |

### Phase B: Context Budget (Estimasi: 2 hari)

| Task | Estimate |
|------|----------|
| Tambah `ai_context_length` ke `SettingsService` | 1 jam |
| Update admin settings Vue page | 2 jam |
| Implementasi dynamic history limit di `ChatService` | 2 jam |
| Implementasi `estimateTokenCount()` | 1 jam |
| Update tests | 2 jam |

### Phase C: Compaction (Estimasi: 2 hari)

| Task | Estimate |
|------|----------|
| Implementasi `compact()` di `ConversationMemoryService` | 3 jam |
| Implementasi `shouldCompact()` | 1 jam |
| Buat artisan command `aspri:compact-memories` | 1 jam |
| Integrasi ke extraction flow | 1 jam |
| Tests | 2 jam |

### Phase D: Polish (Estimasi: 1-2 hari)

| Task | Estimate |
|------|----------|
| Admin view: per-user memory stats | 2 jam |
| End-to-end testing | 2 jam |
| Dokumentasi update | 1 jam |

---

## Technical Decisions & Constraints

### Token Estimation
Estimasi token tidak perlu presisi sempurna. Gunakan heuristik:
- ~4 chars/token untuk Inggris
- ~2.5 chars/token untuk Bahasa Indonesia
- Buffer 20% untuk keamanan

### Memory Extraction Timing
- **Opsi 1** (Recommended): Dispatch job `ExtractConversationMemories` setelah setiap AI response, dengan delay 15 menit. Jika ada pesan baru sebelum delay habis, cancel job sebelumnya.
- **Opsi 2**: Trigger manual saat user close/archive thread
- Mulai dengan Opsi 1 karena lebih seamless

### Memory Storage Format
- Simpan dalam natural language (bukan structured JSON di `content`)
- Mudah dibaca oleh LLM langsung tanpa parsing tambahan
- Contoh: "User lebih suka diingatkan di pagi hari pukul 07.00 untuk jadwal hari ini"

### Deduplication Strategy
- Simple: Cek exact match sebelum simpan
- Better: Kirim memory baru + existing memories ke AI untuk deteksi duplikat
- Start simple, upgrade ke better jika needed

### Provider Compatibility
- Semua AI provider (Gemini, OpenAI, Claude) mendukung extraction prompt
- Gunakan provider yang sama yang sedang aktif untuk extraction
- Fallback: jika extraction gagal, skip silently (tidak block flow utama)

---

## Success Metrics

Setelah implementasi, ukur:
1. **Continuity score**: User tidak perlu re-explain preference di sesi baru
2. **Memory relevance**: % memory yang actually dipakai dalam responses
3. **Context efficiency**: Rata-rata token usage vs batas context
4. **User satisfaction**: Feedback apakah asisten terasa "lebih mengenal" mereka

---

## Other Planned Features (Lower Priority)

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