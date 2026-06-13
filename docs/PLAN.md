# ASPRI Development Plan

> **Last Updated**: June 13, 2026

---

## Priority 1: Google OAuth — DONE ✅

### Phase A: Backend ✅

| Task | Status |
|------|--------|
| Install `laravel/socialite` | ✅ |
| Add `google_id` + `google_avatar` columns ke `users` via migration | ✅ `2026_06_09_162313_add_google_columns_to_users_table.php` |
| Make `password` nullable via migration | ✅ `2026_06_09_164919_make_users_password_nullable.php` |
| Register Google provider di `config/services.php` | ✅ |
| `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` di `.env.example` | ✅ |
| `SocialiteController` — `redirect()` + `callback()` | ✅ `app/Http/Controllers/Auth/SocialiteController.php` |
| Callback: find-or-create user, auto-link existing email, `forceFill` email_verified_at | ✅ |
| Callback new user: create default Profile, `createFreeTrial()`, `WelcomeNotification`, admin Telegram notify | ✅ |
| Route: `GET /auth/google` + `GET /auth/google/callback` | ✅ `routes/web.php` |
| Guard: Google-only accounts tidak bisa reset password via email | ✅ `ResetUserPassword.php` |

### Phase B: Frontend ✅

| Task | Status |
|------|--------|
| Tombol "Lanjutkan dengan Google" di `auth/Login.vue` | ✅ |
| Tombol "Daftar dengan Google" di `auth/Register.vue` | ✅ |
| Error handling via flash `status` session | ✅ |

### Phase C: Tests ✅

6 tests di `tests/Feature/GoogleOAuthTest.php`.

---

## Priority 2: Native Tool-Use Rewrite — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **Why this replaces the old "Phase 1" optimization plan:** Phase 1 was going to dedup intent parsing and add a `fast_model` slot dedicated to intent parsing. Phase 2 (this plan) **deletes intent parsing entirely**, so all of that work would be thrown away — double work. We go straight to the native tool-use architecture. The pieces of Phase 1 that survive both designs (the `ResilientAiProvider` decorator, the per-provider fallback/fast model settings + admin UI, and `ResponseTemplates`) are kept here as foundation tasks; the throwaway pieces (pre-parsed-intent plumbing, ChatController parse dedup) are dropped.

**Goal:** Ganti arsitektur intent-parser-terpisah dengan satu agent loop berbasis native tool calling — pola standar industri (Anthropic tool use / OpenAI function calling / Gemini function declarations). Target: respons ASPRI lebih cepat, persona tetap terasa, dan akurasi (terutama angka) terjamin.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11, Vue 3 + Inertia (admin UI), Laravel Pint.

### Best-practice rationale (kecepatan + persona + akurasi)

Tiga prinsip dari panduan agent design (Anthropic tool-use + agent-design):

1. **Tools mengembalikan data terstruktur, bukan prosa.** Loop agent satu kali: model dapat definisi tools + system prompt (persona + memory context), lalu **either** menjawab langsung (chat biasa, streamed) **or** memanggil tool. Tidak ada lagi `IntentParserService` dua tahap dan tidak ada lagi `personalizeResponse` (round-trip LLM kedua hanya untuk merangkai data).

2. **Respons hibrida — ini kunci cepat + akurat + persona:**
   - **Data deterministik** (konfirmasi transaksi/jadwal, list, summary keuangan) → di-render oleh `ResponseTemplates` dari hasil tool, **tanpa** call LLM kedua. Hemat 1 call/aksi, angka dijamin tidak salah (LLM tidak pernah menyentuh nominal), latensi turun. Persona tetap lewat `{call_preference} {nama}` + deteksi bahasa ID/EN.
   - **Respons percakapan** (greeting, help, casual, out-of-scope, acknowledgment pasca-eksekusi) → digenerate model langsung, **streamed**. Di sinilah persona terasa hidup.

3. **Model bertingkat (tiered):** model utama (kuat, mis. `claude-opus-4-8` / model yang dikonfigurasi admin) untuk agent loop chat; **fast model** (murah) untuk background job (ekstraksi memori percakapan `ConversationMemoryService`, generate judul thread). `fast_model` dari Phase 1 **tidak dibuang** — di-repurpose untuk background job ini.

4. **Prompt caching:** system prompt (persona + tool definitions) besar dan konstan → cache. Anthropic `cache_control: {type: "ephemeral"}` (TTL 5 menit) di blok system + tools (urutan render `tools` → `system` → `messages`, jadi keduanya di prefix yang sama). Ini penurunan latensi + biaya terbesar karena tool schema + persona dikirim tiap pesan. Lihat Task 9.

### Realita provider (terverifikasi dari kode)

- Ketiga provider (`GeminiProvider`, `OpenAiProvider`, `ClaudeProvider`) **sudah** mendukung `$options['functions']` di `chat()` dan mengembalikan `['function_name' => ..., 'arguments' => [...]]` saat model memanggil tool, atau `string` saat menjawab teks.
- **Batasan:** provider mengembalikan **satu** tool call (`$data[...][0]`), bukan parallel tool calls, dan `chatStream()` **tidak** mengirim `functions` (streaming = teks saja). Konsekuensi: **agent loop di-orkestrasi di PHP** — `chat(tools)` → kalau function_call, eksekusi → feed hasil sebagai message → ulangi sampai model balas teks (batasi max iterasi, mis. 3). Respons teks final boleh di-stream via `chatStream()` pass kedua (atau dikirim utuh).
- `ActionExecutorService` (`app/Services/Ai/ActionExecutorService.php`, 649 baris) sudah mengeksekusi aksi finance/schedule/notes — **dipertahankan** sebagai implementasi tool. Tool registry memetakan nama tool → method executor ini.
- Mutasi tetap lewat Confirmation Flow (AGENTS.md §2.2): tool call mutasi → buat `PendingAction` → respons konfirmasi (dari `ResponseTemplates`, bukan call kedua). Konfirmasi "ya" → eksekusi.

### Arsitektur target

```
User message
   │
   ▼
ChatOrchestrator::processMessage
   │  (system prompt: persona + memory context; tools: registry)
   ▼
PendingAction? ──ya──► keyword "ya"/"batal" ──► execute / cancel ──► ResponseTemplates  (0 LLM call)
   │ tidak
   ▼
provider.chat(messages, {functions: tools})   ◄── prompt cached
   │
   ├─ text response ──────────────► stream ke user (persona)            (1 LLM call)
   │
   └─ function_call
        ├─ READ tool   → ActionExecutor → ResponseTemplates render data  (1 LLM call)
        └─ MUTATE tool → PendingAction + ResponseTemplates konfirmasi    (1 LLM call)
```

Hasil: 1 call/pesan untuk aksi & chat (sebelumnya 3–6), 0 call untuk konfirmasi "ya"/"batal", angka dijamin akurat, semua chat streamable.

### Catatan desain terverifikasi

- Fallback model = model lain pada **provider yang sama** (API key sama). Cross-provider fallback = YAGNI.
- `TelegramBotService.php` memanggil `processMessage` — signature `processMessage` tidak menambah parameter wajib baru, jadi pemanggil Telegram tidak perlu diubah.
- Persona (AGENTS.md §2.1) dihormati di dua tempat: system prompt agent loop (untuk respons percakapan) dan `ResponseTemplates` (untuk data deterministik).

---

### Task 1: `ResilientAiProvider` — decorator model override + fallback

**Files:**
- Create: `app/Services/Ai/ResilientAiProvider.php`
- Test: `tests/Unit/ResilientAiProviderTest.php`

Decorator membungkus `AiProviderInterface`: (1) menerapkan override model per-peran (mis. fast model untuk background job); (2) retry sekali dengan fallback model saat call utama gagal. Streaming hanya di-retry kalau belum ada chunk yang terkirim (hindari output ganda).

- [ ] **Step 1: Tulis failing test** — `tests/Unit/ResilientAiProviderTest.php` dengan fake provider yang merekam `$options['model']` per call dan bisa dipaksa gagal pada model tertentu. Cakupan: primary model override diterapkan; tanpa override pakai default inner; retry ke fallback saat primary gagal; rethrow saat tidak ada fallback; tidak retry kalau fallback == model yang gagal; `chatStream` retry saat belum ada chunk; `chatStream` **tidak** retry setelah chunk terkirim; string kosong `''` diperlakukan sebagai `null`.

```php
public function __construct(
    protected AiProviderInterface $inner,
    ?string $model = null,
    ?string $fallbackModel = null,
) {
    $this->model = $model !== '' ? $model : null;
    $this->fallbackModel = $fallbackModel !== '' ? $fallbackModel : null;
}
```

`chat()`: `applyModel($options)` → `try inner->chat` → `catch` resolve fallback (null kalau tidak ada / sama dengan model yang dipakai) → retry sekali dengan `array_merge($options, ['model' => $fallback])`. `chatStream()`: bungkus callback dengan tracker `$emitted`; di catch, retry hanya kalau `$fallback !== null && ! $emitted`. `applyModel`: set `$options['model'] = $this->model` hanya kalau model di-set dan `$options['model']` belum ada (caller boleh override). Log warning saat fallback.

- [ ] **Step 2:** `php artisan test --compact --filter=ResilientAiProviderTest` → FAIL (class belum ada).
- [ ] **Step 3:** Implementasi `ResilientAiProvider` sesuai di atas.
- [ ] **Step 4:** Test PASS (8 tests).
- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/ResilientAiProvider.php tests/Unit/ResilientAiProviderTest.php
git commit -m "feat: add ResilientAiProvider decorator with model override and fallback retry"
```

---

### Task 2: Setting fast model + fallback model di `SettingsService` & `SettingsController`

**Files:**
- Modify: `app/Services/Admin/SettingsService.php` (`getAiSettings`, `updateAiSettings`, `getActiveAiConfig`)
- Modify: `app/Http/Controllers/Admin/SettingsController.php` (`updateAi` validation)
- Test: `tests/Feature/AiModelSettingsTest.php`

Setting baru (group `ai`, nullable string, default kosong = nonaktif): `{provider}_fast_model`, `{provider}_fallback_model`, `{provider}_fast_fallback_model` untuk `gemini`/`openai`/`anthropic`.

Semantik (Phase 2):
- `fallback_model` = retry untuk model utama agent loop (Task 3).
- `fast_model` = model murah untuk **background job** (memory extraction, judul thread) — Task 3.
- `fast_fallback_model` = retry untuk fast model (kosong → pakai `fallback_model`).

- [ ] **Step 1:** Tulis failing test — admin bisa simpan ketiga model; `getActiveAiConfig()` expose `fast_model`/`fallback_model`/`fast_fallback_model`; `fast_fallback` default ke `fallback_model` saat kosong; unset → null.
  > Cek nama route AI settings via `php artisan route:list | grep -i settings` (kemungkinan `admin.settings.ai`) dan cara `AdminFeatureTest.php` membuat admin user (kolom `is_admin` vs role); ikuti pola yang ada.
- [ ] **Step 2:** FAIL (key belum ada).
- [ ] **Step 3:** `getAiSettings()` — tambah 9 key (`{provider}_fast_model`, `{provider}_fallback_model`, `{provider}_fast_fallback_model`).
- [ ] **Step 4:** `updateAiSettings()` — loop provider × suffix, `set` kalau `array_key_exists`.
- [ ] **Step 5:** `getActiveAiConfig()` — return `fast_model`, `fallback_model`, `fast_fallback_model` (`fast_fallback ?: fallback`).
- [ ] **Step 6:** `SettingsController::updateAi()` — tambah 9 rule `['nullable', 'string']`.
- [ ] **Step 7:** Test PASS.
- [ ] **Step 8: Commit**

```bash
git add app/Services/Admin/SettingsService.php app/Http/Controllers/Admin/SettingsController.php tests/Feature/AiModelSettingsTest.php
git commit -m "feat: add fast model and fallback model settings per AI provider"
```

---

### Task 3: Container bindings — main provider pakai fallback, fast provider untuk background job

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/AiProviderBindingTest.php`

Desain binding:
- `'ai.provider.base'` → provider konkret (match logic binding `AiProviderInterface` yang sekarang).
- `AiProviderInterface::class` → `ResilientAiProvider(base, null, fallback_model)` — dipakai semua konsumen umum (ChatService, ChatOrchestrator).
- `'ai.provider.fast'` → `ResilientAiProvider(base, fast_model, fast_fallback_model)` — dipakai background job (`ConversationMemoryService`, generate judul thread). **Bukan** untuk intent parsing (yang dihapus total).

> Catatan: binding lama `IntentParserService` **dihapus** di Task 6 saat servicenya dihapus. Di task ini cukup tambah `ai.provider.fast` dan wire fallback ke main provider.

- [ ] **Step 1:** Failing test — `app(AiProviderInterface::class)` instanceof `ResilientAiProvider`; `app('ai.provider.fast')` instanceof `ResilientAiProvider`; fast provider pakai `fast_model` dari settings (reflection `model` property).
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Refactor `AppServiceProvider::register()` — tambah `ai.provider.base`, bungkus `AiProviderInterface` dengan fallback, tambah `ai.provider.fast`. Helper `resolveAiConfig($app)` mengembalikan `getActiveAiConfig()` atau `[]` saat tabel settings belum ada (migrasi). Import `ResilientAiProvider`.
- [ ] **Step 4:** Bind konsumen background job ke `ai.provider.fast` (mis. `ConversationMemoryService` jika ia inject `AiProviderInterface`; cek constructor-nya dan tambah binding eksplisit). Test binding PASS + suite chat existing tetap PASS.
- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Feature/AiProviderBindingTest.php
git commit -m "feat: wire fallback model for main provider and fast model for background jobs"
```

---

### Task 4: Admin UI — input fast model & fallback model

**Files:**
- Modify: `resources/js/pages/admin/settings/Index.vue` (form state, transform, kartu tiap provider)

Tambah 3 input per provider: **Fast Model (Background)**, **Fallback Model**, **Fast Fallback Model**. Tidak ada test frontend otomatis; verifikasi via `npm run type-check` + manual.

- [ ] **Step 1:** Tambah 9 field ke `aiForm` (`*_fast_model`, `*_fallback_model`, `*_fast_fallback_model`). Update interface TS `aiSettings` props.
- [ ] **Step 2:** Tambah field ke `aiForm.transform` per branch provider.
- [ ] **Step 3:** Tambah input di kartu Gemini / OpenAI / Anthropic. Placeholder contoh — Gemini: `gemini-2.5-flash-lite` / `gemini-2.5-flash` / `gemini-2.0-flash`; OpenAI: `gpt-4o-mini` / `gpt-4o` / `gpt-4o-mini`; Anthropic: `claude-haiku-4-5` / `claude-opus-4-8` / `claude-haiku-4-5`. Label "Fast Model" → helptext "Model murah untuk background job (ekstraksi memori, judul thread)".
- [ ] **Step 4:** `npm run type-check` PASS; verifikasi manual save+reload persist.
- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/admin/settings/Index.vue
git commit -m "feat: admin UI inputs for fast model and fallback models per provider"
```

---

### Task 5: `ToolRegistry` — definisi tool + mapping ke executor

**Files:**
- Create: `app/Services/Ai/ToolRegistry.php`
- Test: `tests/Unit/ToolRegistryTest.php`

Registry membangun array `functions` (schema JSON: `name`, `description`, `parameters`) untuk dikirim ke `provider.chat($messages, ['functions' => $registry->definitions($user)])`, plus metadata per tool: `module`, `mutates` (bool — menentukan Confirmation Flow), dan mapping ke method `ActionExecutorService`.

Tool inti: `create_transaction`, `view_balance`, `view_transactions`, `create_schedule`, `update_schedule`, `delete_schedule`, `view_schedules`, `create_note`, `update_note`, `delete_note`, `view_notes`. Plus tool dari plugin aktif user (`PluginManager::getActivePluginsForUser` → `getChatIntents()`/tool defs).

- [ ] **Step 1:** Failing test — `definitions($user)` mengembalikan array tool valid (punya `name`/`description`/`parameters`); tool mutasi ditandai `mutates: true` (`create_*`, `update_*`, `delete_*`); read ditandai `false`; plugin aktif menambah tool, plugin nonaktif tidak; `resolve($name)` mengembalikan metadata + target executor.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Implementasi `ToolRegistry`. Schema parameters mengikuti entity yang sudah dipakai `ActionExecutorService` (mis. `create_transaction`: `tx_type` enum income/expense, `amount` number, `category` string, `note` string, `occurred_at` date). Plugin tools digabung dari `PluginManager`.
- [ ] **Step 4:** Test PASS.
- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/ToolRegistry.php tests/Unit/ToolRegistryTest.php
git commit -m "feat: ToolRegistry defines native tool schemas mapped to ActionExecutorService"
```

---

### Task 6: `ResponseTemplates` — respons deterministik tanpa LLM

**Files:**
- Create: `app/Services/Ai/ResponseTemplates.php`
- Test: `tests/Unit/ResponseTemplatesTest.php`

Render data-heavy replies tanpa round-trip LLM. Persona via `{call_preference} {nama}` + deteksi bahasa ID/EN. Menjamin angka tidak pernah salah.

Method: `detectLanguage(string): 'id'|'en'` (default `id`), `financeSummary`, `transactionConfirmation`, `scheduleConfirmation`, `scheduleUpdateConfirmation`, `deleteConfirmation`, `schedulesList`, `notesList`, `cancellation`. Helper internal `rupiah()` (`number_format(.,0,',','.')`), `periodLabel()`, `confirmFooter()` (`Balas "ya" / "batal"`).

- [ ] **Step 1:** Failing unit test — deteksi ID default & EN; `financeSummary` memuat `Rp5.000.000` dst persis; `transactionConfirmation` memuat nominal + kategori + `"ya"`/`"batal"`; `schedulesList` render semua item + empty state; `deleteConfirmation` ada peringatan + identifier + `"ya"`.
- [ ] **Step 2:** FAIL (class belum ada).
- [ ] **Step 3:** Implementasi `ResponseTemplates` (static methods; ID + EN branch tiap method).
- [ ] **Step 4:** Test PASS.
- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/ResponseTemplates.php tests/Unit/ResponseTemplatesTest.php
git commit -m "feat: deterministic ResponseTemplates for lists, summaries, and confirmations"
```

---

### Task 7: Agent loop di `ChatOrchestrator::processMessage`

**Files:**
- Modify: `app/Services/Ai/ChatOrchestrator.php`
- Test: `tests/Feature/ChatOrchestratorToolUseTest.php`

Inti rewrite. `processMessage` jadi satu agent loop:

1. **Cek `PendingAction` dulu** — kalau ada dan pesan = keyword konfirmasi (`ya`/`ok`/`betul`/...) → eksekusi via `ActionExecutorService` → `ResponseTemplates::...` (success). Kalau keyword batal → `cancel()` + `ResponseTemplates::cancellation`. **0 call LLM.**
2. Build system prompt: persona (AGENTS.md §2.1, `call_preference` + nama + bahasa) + memory context (`ConversationMemoryService`).
3. Loop (max 3 iterasi): `provider->chat($messages, ['functions' => $registry->definitions($user)])`.
   - **Text response** → return sebagai respons (akan di-stream oleh controller, Task 8).
   - **function_call MUTATE** → buat `PendingAction` (module, action, payload=arguments, expires) → return `ResponseTemplates::{transaction|schedule|delete}Confirmation`. Stop loop. **0 call LLM kedua.**
   - **function_call READ** → eksekusi via `ActionExecutorService` → kalau hasilnya data terstruktur yang punya template (saldo, list) → `ResponseTemplates::{financeSummary|schedulesList|notesList}` dan stop. Kalau butuh framing percakapan → append hasil sebagai message dan lanjut loop (model merangkai).
4. Return shape tetap `['response' => string, 'action_taken' => bool, 'pending_action' => array|null]`.

Hapus `personalizeResponse` dan semua `build*Context` / `format*` yang memanggil LLM untuk data deterministik (digantikan `ResponseTemplates`).

- [ ] **Step 1:** Failing test — `ChatOrchestratorToolUseTest`:
  - `view_balance` → `IntentParserService` tidak dipanggil (sudah dihapus), `chat` dipanggil sekali mengembalikan function_call `view_balance`, `ResponseTemplates` dipakai → respons memuat `Rp`, `action_taken=false`, **tidak ada call LLM kedua** (`chat` di-assert `once`).
  - `create_transaction` → function_call mutasi → `PendingAction` tercipta, respons konfirmasi memuat nominal + `"ya"`, `pending_action` tidak null.
  - Pesan "ya" dengan `PendingAction` pending → `chat`/`chatStream` `shouldNotReceive` (0 LLM call), transaksi tereksekusi, respons success.
  - Greeting → `chat` mengembalikan teks → respons = teks itu (persona lewat LLM).
  > Cek factory `ChatThread`/`PendingAction`; mock `AiProviderInterface` via container — resolve `ChatOrchestrator` SETELAH mock dibuat.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Implementasi agent loop. Inject `ToolRegistry`, `ResponseTemplates` (static, tak perlu inject), `ActionExecutorService` (sudah ada). Helper `templateContext(User): [callName, lang]`.
- [ ] **Step 4:** Test PASS + suite chat existing PASS (sesuaikan assertion lama yang meng-assert kalimat LLM untuk list/konfirmasi → cek angka/judul, bukan kalimat persis).
- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/ChatOrchestrator.php tests/Feature/ChatOrchestratorToolUseTest.php
git commit -m "feat: single tool-use agent loop in ChatOrchestrator (replaces intent parser + personalizeResponse)"
```

---

### Task 8: `ChatController` streaming — agent loop + stream teks final

**Files:**
- Modify: `app/Http/Controllers/ChatController.php`
- Test: `tests/Feature/ChatStreamToolUseTest.php`

Alur stream baru:
1. Cek `PendingAction` dulu → konfirmasi "ya"/"batal" lewat `processMessage` (0 LLM call), kirim respons utuh.
2. Tidak ada pending → panggil `processMessage`. Kalau respons berasal dari **text generation** (chat percakapan) → stream via `chatStream` (system prompt + history, persona). Kalau respons berasal dari **tool/template** (aksi, konfirmasi, list) → kirim utuh sebagai satu `message_chunk` (sudah final, deterministik).

   > Implementasi praktis: `processMessage` mengembalikan flag `streamed` (apakah respons perlu/boleh di-stream). Untuk respons percakapan, orchestrator boleh menyerahkan streaming ke controller (controller panggil `chatStream` dengan messages + system prompt yang sama). Hindari double-call: kalau orchestrator sudah punya teks final, jangan generate ulang — kirim utuh.

3. Hapus blok `parseIntent` lama di controller (intent parsing sudah tidak ada).

- [ ] **Step 1:** Failing test — `ChatStreamToolUseTest`:
  - Konfirmasi "ya" + `PendingAction` → `chat`/`chatStream` `shouldNotReceive`, response `assertOk`, stream dikonsumsi.
  - Pesan aksi → `chat` dipanggil (function_call), respons konfirmasi terkirim, tidak ada call LLM kedua.
  - Greeting → respons streamed.
  > Cek route stream (`chat.stream`) + field request (`message`/`content`, `thread_id`). Lihat `ChatStreamMemoryDispatchTest.php` sebagai referensi pola.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Refactor stream closure di `ChatController`. Pertahankan blok dispatch memory extraction (`ConversationMemoryService` / `ExtractConversationMemories`) — sekarang via `ai.provider.fast`.
- [ ] **Step 4:** Test PASS + `ChatFeatureTest` + `ChatStreamMemoryDispatchTest` PASS.
- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ChatController.php tests/Feature/ChatStreamToolUseTest.php
git commit -m "perf: stream tool-use agent loop, skip LLM for confirmations"
```

---

### Task 9: Prompt caching system prompt + tool definitions (ClaudeProvider)

**Files:**
- Modify: `app/Services/Ai/ClaudeProvider.php`
- Test: `tests/Unit/ClaudeProviderCacheTest.php`

System prompt (persona) + tool definitions dikirim tiap pesan dan konstan → tandai `cache_control: {type: "ephemeral"}` di blok system terakhir dan/atau tool terakhir (urutan render `tools` → `system`, satu prefix). Penurunan latensi + biaya terbesar.

> Gemini & OpenAI: caching implisit/otomatis di sisi server (atau belum perlu marker) — fokus task ini ClaudeProvider. Pastikan system prompt **byte-stable** (jangan interpolasi timestamp/UUID ke system prompt — pindah ke message). Audit silent invalidator.

- [ ] **Step 1:** Failing test — saat `functions` + system di-set, payload `ClaudeProvider` menempelkan `cache_control` di blok system (dan/atau tool definition terakhir). Assert struktur payload (mock HTTP / inspect request).
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Implementasi — bungkus system jadi array block dengan `cache_control` ephemeral; pastikan tool list deterministik (urutan stabil). Verifikasi tidak ada `now()`/UUID di prefix.
- [ ] **Step 4:** Test PASS. (Opsional: verifikasi `usage.cache_read_input_tokens` > 0 pada call kedua di environment nyata.)
- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/ClaudeProvider.php tests/Unit/ClaudeProviderCacheTest.php
git commit -m "perf: prompt-cache system prompt and tool definitions in ClaudeProvider"
```

---

### Task 10: Hapus `IntentParserService` + kode mati

**Files:**
- Delete: `app/Services/Ai/IntentParserService.php` (1384 baris)
- Modify: `app/Providers/AppServiceProvider.php` (hapus binding `IntentParserService`), `ChatOrchestrator.php` (hapus inject + sisa `personalizeResponse`/`build*Context` mati), grep semua referensi.
- Test: jalankan full suite.

- [ ] **Step 1:** `grep -rn "IntentParserService\|personalizeResponse\|parseIntent" app/ tests/` — daftar semua referensi.
- [ ] **Step 2:** Hapus service + binding + import + pemanggilan. Hapus method/test yang khusus menguji intent parser dua tahap (sudah digantikan tool-use test).
- [ ] **Step 3:** Hapus `fast_model` semantik "intent" dari komentar/docs (sekarang background job).
- [ ] **Step 4:** `php artisan test --compact` PASS semua.
- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "refactor: remove IntentParserService and dead personalize/intent code"
```

---

### Task 11: Verifikasi akhir + dokumentasi

**Files:**
- Modify: `docs/CURRENT_STATUS.md`, `docs/ARCHITECTURE.md`

- [ ] **Step 1:** `vendor/bin/pint --dirty` + `npm run type-check` PASS.
- [ ] **Step 2:** `php artisan test --compact` PASS semua.
- [ ] **Step 3:** Update `docs/CURRENT_STATUS.md`:

```markdown
### Native Tool-Use Rewrite — Juni 2026
- Arsitektur chat: satu agent loop berbasis native tool calling (Anthropic/OpenAI/Gemini function calling). `IntentParserService` + `personalizeResponse` dihapus.
- Respons hibrida: data deterministik (saldo, list, konfirmasi) via `ResponseTemplates` (0 call LLM, angka dijamin akurat); respons percakapan via LLM (streamed, persona).
- Mutasi tetap lewat Confirmation Flow; "ya"/"batal" 0 call LLM.
- Model bertingkat: model utama untuk agent loop, fast model untuk background job (memory extraction, judul thread). Admin set Fast / Fallback / Fast-Fallback model per provider.
- Prompt caching system prompt + tool definitions (ClaudeProvider).
- Call LLM per pesan: chat/aksi 1 (sebelumnya 3–6), konfirmasi 0.
```

- [ ] **Step 4:** Update `docs/ARCHITECTURE.md` diagram alur chat (kalau ada).
- [ ] **Step 5: Commit**

```bash
git add docs/CURRENT_STATUS.md docs/ARCHITECTURE.md
git commit -m "docs: record native tool-use chat architecture"
```

---

## Planned (Backlog)

- **WhatsApp Integration** — via WhatsApp Business API atau Twilio
- **Payment Gateway** — Midtrans/Xendit untuk subscription otomatis
- **Mobile App** — React Native wrapper atau PWA
