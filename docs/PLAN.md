# ASPRI Development Plan

> **Last Updated**: June 10, 2026

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

| Task | Status |
|------|--------|
| New user via Google → profile setup redirect | ✅ |
| New user: Profile + Subscription + WelcomeNotification tercipta | ✅ |
| Existing Google user → login + avatar update | ✅ |
| Email conflict (non-Google existing) → auto-link + login | ✅ |
| Socialite exception → redirect login dengan error flash | ✅ |
| New user has default Profile (`aspri_name = 'ASPRI'`) | ✅ |

6 tests di `tests/Feature/GoogleOAuthTest.php`.

---

## Priority 2: LLM Call Optimization (Phase 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kurangi jumlah call LLM per pesan chat dari 3–6 menjadi 1–2 tanpa menurunkan akurasi, plus tambah konfigurasi fast model (intent) dan fallback model untuk semua slot LLM di admin panel.

**Architecture:** Empat perubahan independen: (1) decorator `ResilientAiProvider` yang menangani model override + retry-with-fallback di level provider; (2) setting baru per provider (`fast_model`, `fallback_model`, `fast_fallback_model`) yang dipakai lewat container binding terpisah untuk intent parsing; (3) hilangkan parse intent duplikat antara `ChatController` (streaming) dan `ChatOrchestrator::processMessage` dengan parameter intent opsional; (4) ganti call LLM `personalizeResponse` untuk respons data-deterministik (list, summary, konfirmasi) dengan template `ResponseTemplates` — angka tidak bisa salah, hemat 1 call per aksi.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11, Vue 3 + Inertia (admin UI), Laravel Pint.

**Latar belakang masalah (audit 10 Juni 2026):**

| Skenario | Call LLM sekarang | Setelah Phase 1 |
|---|---|---|
| Chat umum (streaming) | 2–3 | 1–2 |
| Aksi (mis. "catat pengeluaran 50rb") | 3–6 | 1–2 |
| Konfirmasi "ya" | 2–3 | 1 |

Tiga akar masalah:
1. Intent diparse **dua kali**: `ChatController.php:285` (`parseIntent`) lalu lagi di `ChatOrchestrator.php:92` saat branch non-streaming memanggil `processMessage` (`ChatController.php:316`).
2. `personalizeResponse` (`ChatOrchestrator.php:668`) = 1 call LLM penuh hanya untuk rephrase data deterministik. Risiko akurasi: LLM bisa salah tulis angka. `formatTransactionsList` sudah pakai template murni — pola ini diperluas.
3. Konfirmasi "ya" tetap kena `parseIntent` di controller sebelum keyword check di orchestrator jalan.

**Catatan desain yang sudah diverifikasi:**
- Ketiga provider (`GeminiProvider`, `OpenAiProvider`, `ClaudeProvider`) sudah mendukung override `$options['model']` di `chat()` dan `chatStream()` — decorator tidak perlu menyentuh provider.
- Fallback model = model lain pada **provider yang sama** (API key sama). Cross-provider fallback = YAGNI.
- `TelegramBotService.php:303` memanggil `processMessage` tanpa parse duplikat — tidak perlu diubah (parameter intent opsional, default `null`).
- Persona (AGENTS.md §2.1) tetap dihormati: template menyertakan `call_preference` + nama user + deteksi bahasa ID/EN. Respons percakapan (greeting, help, casual, out-of-scope, success/error pasca-eksekusi) **tetap lewat LLM** agar persona terasa.

---

### Task 1: `ResilientAiProvider` — decorator model override + fallback

**Files:**
- Create: `app/Services/Ai/ResilientAiProvider.php`
- Test: `tests/Unit/ResilientAiProviderTest.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ResilientAiProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResilientAiProviderTest extends TestCase
{
    private function fakeProvider(array $failModels = [], string $reply = 'ok'): AiProviderInterface
    {
        return new class($failModels, $reply) implements AiProviderInterface
        {
            public array $calls = [];

            public function __construct(private array $failModels, private string $reply) {}

            public function chat(array $messages, array $options = []): string|array
            {
                $model = $options['model'] ?? 'default';
                $this->calls[] = $model;

                if (in_array($model, $this->failModels, true)) {
                    throw new RuntimeException("model {$model} failed");
                }

                return $this->reply;
            }

            public function chatStream(array $messages, callable $callback, array $options = []): string
            {
                $model = $options['model'] ?? 'default';
                $this->calls[] = $model;

                if (in_array($model, $this->failModels, true)) {
                    throw new RuntimeException("model {$model} failed");
                }

                $callback($this->reply);

                return $this->reply;
            }
        };
    }

    public function test_chat_applies_primary_model_override(): void
    {
        $inner = $this->fakeProvider();
        $provider = new ResilientAiProvider($inner, 'fast-model');

        $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['fast-model'], $inner->calls);
    }

    public function test_chat_without_model_override_uses_inner_default(): void
    {
        $inner = $this->fakeProvider();
        $provider = new ResilientAiProvider($inner);

        $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['default'], $inner->calls);
    }

    public function test_chat_retries_with_fallback_on_failure(): void
    {
        $inner = $this->fakeProvider(failModels: ['main-model']);
        $provider = new ResilientAiProvider($inner, 'main-model', 'backup-model');

        $result = $provider->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('ok', $result);
        $this->assertSame(['main-model', 'backup-model'], $inner->calls);
    }

    public function test_chat_rethrows_when_no_fallback_configured(): void
    {
        $inner = $this->fakeProvider(failModels: ['main-model']);
        $provider = new ResilientAiProvider($inner, 'main-model');

        $this->expectException(RuntimeException::class);
        $provider->chat([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_chat_does_not_retry_when_fallback_equals_failed_model(): void
    {
        $inner = $this->fakeProvider(failModels: ['same-model']);
        $provider = new ResilientAiProvider($inner, 'same-model', 'same-model');

        $this->expectException(RuntimeException::class);

        try {
            $provider->chat([['role' => 'user', 'content' => 'hi']]);
        } finally {
            $this->assertSame(['same-model'], $inner->calls);
        }
    }

    public function test_chat_stream_retries_with_fallback_when_nothing_emitted(): void
    {
        $inner = $this->fakeProvider(failModels: ['main-model']);
        $provider = new ResilientAiProvider($inner, 'main-model', 'backup-model');

        $chunks = [];
        $result = $provider->chatStream([['role' => 'user', 'content' => 'hi']], function ($c) use (&$chunks) {
            $chunks[] = $c;
        });

        $this->assertSame('ok', $result);
        $this->assertSame(['ok'], $chunks);
        $this->assertSame(['main-model', 'backup-model'], $inner->calls);
    }

    public function test_chat_stream_does_not_retry_after_chunks_emitted(): void
    {
        // Inner emits one chunk then dies — retry would duplicate output to the client.
        $inner = new class implements AiProviderInterface
        {
            public array $calls = [];

            public function chat(array $messages, array $options = []): string|array
            {
                return 'ok';
            }

            public function chatStream(array $messages, callable $callback, array $options = []): string
            {
                $this->calls[] = $options['model'] ?? 'default';
                $callback('partial');
                throw new RuntimeException('died mid-stream');
            }
        };

        $provider = new ResilientAiProvider($inner, 'main-model', 'backup-model');

        $this->expectException(RuntimeException::class);

        try {
            $provider->chatStream([['role' => 'user', 'content' => 'hi']], fn ($c) => null);
        } finally {
            $this->assertSame(['main-model'], $inner->calls);
        }
    }

    public function test_empty_string_model_and_fallback_are_treated_as_null(): void
    {
        $inner = $this->fakeProvider(failModels: ['default']);
        $provider = new ResilientAiProvider($inner, '', '');

        $this->expectException(RuntimeException::class);

        try {
            $provider->chat([['role' => 'user', 'content' => 'hi']]);
        } finally {
            $this->assertSame(['default'], $inner->calls);
        }
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=ResilientAiProviderTest`
Expected: FAIL — `Class "App\Services\Ai\ResilientAiProvider" not found`

- [ ] **Step 3: Implementasi `ResilientAiProvider`**

Create `app/Services/Ai/ResilientAiProvider.php`:

```php
<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

/**
 * Decorator around any AiProviderInterface that:
 * 1. Applies a per-role model override (e.g. a cheaper "fast" model for intent parsing).
 * 2. Retries exactly once with a fallback model when the primary call fails.
 *
 * Streaming is only retried if no chunk has reached the client yet, otherwise
 * the user would see duplicated output.
 */
class ResilientAiProvider implements AiProviderInterface
{
    protected ?string $model;

    protected ?string $fallbackModel;

    public function __construct(
        protected AiProviderInterface $inner,
        ?string $model = null,
        ?string $fallbackModel = null,
    ) {
        $this->model = $model !== '' ? $model : null;
        $this->fallbackModel = $fallbackModel !== '' ? $fallbackModel : null;
    }

    public function chat(array $messages, array $options = []): string|array
    {
        $options = $this->applyModel($options);

        try {
            return $this->inner->chat($messages, $options);
        } catch (\Throwable $e) {
            $fallback = $this->resolveFallback($options);

            if ($fallback === null) {
                throw $e;
            }

            $this->logFallback($options, $fallback, $e);

            return $this->inner->chat($messages, array_merge($options, ['model' => $fallback]));
        }
    }

    public function chatStream(array $messages, callable $callback, array $options = []): string
    {
        $options = $this->applyModel($options);
        $emitted = false;

        $trackingCallback = function ($chunk) use ($callback, &$emitted) {
            $emitted = true;
            $callback($chunk);
        };

        try {
            return $this->inner->chatStream($messages, $trackingCallback, $options);
        } catch (\Throwable $e) {
            $fallback = $this->resolveFallback($options);

            if ($fallback === null || $emitted) {
                throw $e;
            }

            $this->logFallback($options, $fallback, $e);

            return $this->inner->chatStream($messages, $callback, array_merge($options, ['model' => $fallback]));
        }
    }

    protected function applyModel(array $options): array
    {
        if ($this->model !== null && ! isset($options['model'])) {
            $options['model'] = $this->model;
        }

        return $options;
    }

    protected function resolveFallback(array $options): ?string
    {
        if ($this->fallbackModel === null) {
            return null;
        }

        if (($options['model'] ?? null) === $this->fallbackModel) {
            return null;
        }

        return $this->fallbackModel;
    }

    protected function logFallback(array $options, string $fallback, \Throwable $e): void
    {
        Log::warning('AI primary model failed, retrying with fallback model', [
            'primary' => $options['model'] ?? '(provider default)',
            'fallback' => $fallback,
            'error' => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --compact --filter=ResilientAiProviderTest`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/ResilientAiProvider.php tests/Unit/ResilientAiProviderTest.php
git commit -m "feat: add ResilientAiProvider decorator with model override and fallback retry"
```

---

### Task 2: Setting fast model + fallback model di `SettingsService` & `SettingsController`

**Files:**
- Modify: `app/Services/Admin/SettingsService.php:30-106` (`getAiSettings`, `updateAiSettings`) dan `:113-158` (`getActiveAiConfig`)
- Modify: `app/Http/Controllers/Admin/SettingsController.php:38-52` (validasi `updateAi`)
- Test: `tests/Feature/AiModelSettingsTest.php`

Key setting baru (group `ai`, semua nullable string, default kosong = nonaktif):
`gemini_fast_model`, `gemini_fallback_model`, `gemini_fast_fallback_model`, `openai_fast_model`, `openai_fallback_model`, `openai_fast_fallback_model`, `anthropic_fast_model`, `anthropic_fallback_model`, `anthropic_fast_fallback_model`.

Semantik: `fast_model` = model untuk intent parsing (kosong → pakai model utama). `fallback_model` = retry untuk model utama. `fast_fallback_model` = retry untuk fast model (kosong → pakai `fallback_model`).

- [ ] **Step 1: Tulis failing test**

Create `tests/Feature/AiModelSettingsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_save_fast_and_fallback_models(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.ai'), [
            'ai_provider' => 'gemini',
            'gemini_model' => 'gemini-2.5-pro',
            'gemini_fast_model' => 'gemini-2.5-flash-lite',
            'gemini_fallback_model' => 'gemini-2.5-flash',
            'gemini_fast_fallback_model' => 'gemini-2.0-flash',
        ]);

        $response->assertRedirect();

        $settings = app(SettingsService::class)->getAiSettings();
        $this->assertSame('gemini-2.5-flash-lite', $settings['gemini_fast_model']);
        $this->assertSame('gemini-2.5-flash', $settings['gemini_fallback_model']);
        $this->assertSame('gemini-2.0-flash', $settings['gemini_fast_fallback_model']);
    }

    public function test_active_ai_config_exposes_fast_and_fallback_models(): void
    {
        $service = app(SettingsService::class);
        $service->updateAiSettings([
            'ai_provider' => 'gemini',
            'gemini_model' => 'gemini-2.5-pro',
            'gemini_fast_model' => 'gemini-2.5-flash-lite',
            'gemini_fallback_model' => 'gemini-2.5-flash',
            'gemini_fast_fallback_model' => 'gemini-2.0-flash',
        ]);

        $config = $service->getActiveAiConfig();

        $this->assertSame('gemini-2.5-flash-lite', $config['fast_model']);
        $this->assertSame('gemini-2.5-flash', $config['fallback_model']);
        $this->assertSame('gemini-2.0-flash', $config['fast_fallback_model']);
    }

    public function test_fast_fallback_defaults_to_main_fallback_when_empty(): void
    {
        $service = app(SettingsService::class);
        $service->updateAiSettings([
            'ai_provider' => 'gemini',
            'gemini_fallback_model' => 'gemini-2.5-flash',
        ]);

        $config = $service->getActiveAiConfig();

        $this->assertSame('gemini-2.5-flash', $config['fast_fallback_model']);
        $this->assertNull($config['fast_model']);
    }

    public function test_unset_models_default_to_null_in_active_config(): void
    {
        $config = app(SettingsService::class)->getActiveAiConfig();

        $this->assertNull($config['fast_model']);
        $this->assertNull($config['fallback_model']);
        $this->assertNull($config['fast_fallback_model']);
    }
}
```

> Catatan: cek nama route AI settings dengan `php artisan route:list | grep -i settings` — jika bukan `admin.settings.ai`, sesuaikan di test. Cek juga cara `AdminFeatureTest.php` membuat admin user (kolom `is_admin` vs role) dan ikuti pola yang sama.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=AiModelSettingsTest`
Expected: FAIL — key `gemini_fast_model` tidak ada di `getAiSettings()` / `fast_model` tidak ada di `getActiveAiConfig()`

- [ ] **Step 3: Modifikasi `SettingsService::getAiSettings()`**

Di `app/Services/Admin/SettingsService.php`, ubah return `getAiSettings()` (line 32-44) menjadi:

```php
        return [
            'ai_provider' => $this->get('ai_provider', 'gemini'),
            'gemini_model' => $this->get('gemini_model', 'gemini-pro'),
            'gemini_fast_model' => $this->get('gemini_fast_model'),
            'gemini_fallback_model' => $this->get('gemini_fallback_model'),
            'gemini_fast_fallback_model' => $this->get('gemini_fast_fallback_model'),
            'gemini_base_url' => $this->get('gemini_base_url'),
            'openai_model' => $this->get('openai_model', 'gpt-4-turbo'),
            'openai_fast_model' => $this->get('openai_fast_model'),
            'openai_fallback_model' => $this->get('openai_fallback_model'),
            'openai_fast_fallback_model' => $this->get('openai_fast_fallback_model'),
            'openai_base_url' => $this->get('openai_base_url'),
            'anthropic_model' => $this->get('anthropic_model', 'claude-3-sonnet'),
            'anthropic_fast_model' => $this->get('anthropic_fast_model'),
            'anthropic_fallback_model' => $this->get('anthropic_fallback_model'),
            'anthropic_fast_fallback_model' => $this->get('anthropic_fast_fallback_model'),
            'anthropic_base_url' => $this->get('anthropic_base_url'),
            'has_gemini_key' => (bool) $this->get('gemini_api_key'),
            'has_openai_key' => (bool) $this->get('openai_api_key'),
            'has_anthropic_key' => (bool) $this->get('anthropic_api_key'),
            'ai_context_length' => (int) $this->get('ai_context_length', 32000),
        ];
```

- [ ] **Step 4: Modifikasi `SettingsService::updateAiSettings()`**

Tambahkan di akhir method `updateAiSettings()` (sebelum closing brace, setelah blok `ai_context_length` line 103-105):

```php
        foreach (['gemini', 'openai', 'anthropic'] as $provider) {
            foreach (['fast_model', 'fallback_model', 'fast_fallback_model'] as $suffix) {
                $key = "{$provider}_{$suffix}";

                if (array_key_exists($key, $data)) {
                    $this->set($key, $data[$key] ?? '', ['group' => 'ai']);
                }
            }
        }
```

- [ ] **Step 5: Modifikasi `SettingsService::getActiveAiConfig()`**

Ubah return statement (line 152-157) menjadi:

```php
        $fastModel = $this->get("{$provider}_fast_model") ?: null;
        $fallbackModel = $this->get("{$provider}_fallback_model") ?: null;
        $fastFallbackModel = ($this->get("{$provider}_fast_fallback_model") ?: null) ?? $fallbackModel;

        return [
            'provider' => $provider,
            'api_key' => $apiKey,
            'model' => $model,
            'base_url' => $baseUrl,
            'fast_model' => $fastModel,
            'fallback_model' => $fallbackModel,
            'fast_fallback_model' => $fastFallbackModel,
        ];
```

- [ ] **Step 6: Tambah validasi di `SettingsController::updateAi()`**

Di `app/Http/Controllers/Admin/SettingsController.php`, tambahkan ke array validasi (setelah `'anthropic_base_url'` line 49):

```php
            'gemini_fast_model' => ['nullable', 'string'],
            'gemini_fallback_model' => ['nullable', 'string'],
            'gemini_fast_fallback_model' => ['nullable', 'string'],
            'openai_fast_model' => ['nullable', 'string'],
            'openai_fallback_model' => ['nullable', 'string'],
            'openai_fast_fallback_model' => ['nullable', 'string'],
            'anthropic_fast_model' => ['nullable', 'string'],
            'anthropic_fallback_model' => ['nullable', 'string'],
            'anthropic_fast_fallback_model' => ['nullable', 'string'],
```

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run: `php artisan test --compact --filter=AiModelSettingsTest`
Expected: PASS (4 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Services/Admin/SettingsService.php app/Http/Controllers/Admin/SettingsController.php tests/Feature/AiModelSettingsTest.php
git commit -m "feat: add fast model and fallback model settings per AI provider"
```

---

### Task 3: Container bindings — main provider pakai fallback, intent parser pakai fast model

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:33-85`
- Test: `tests/Feature/AiProviderBindingTest.php`

Desain binding:
- `'ai.provider.base'` → provider konkret (match logic yang sekarang ada di binding `AiProviderInterface`).
- `AiProviderInterface::class` → `ResilientAiProvider(base, null, fallback_model)` — dipakai semua konsumen umum (ChatService, ChatOrchestrator, dst).
- `'ai.provider.intent'` → `ResilientAiProvider(base, fast_model, fast_fallback_model)` — dipakai HANYA `IntentParserService`.

- [ ] **Step 1: Tulis failing test**

Create `tests/Feature/AiProviderBindingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\Admin\SettingsService;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ResilientAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_provider_is_wrapped_in_resilient_decorator(): void
    {
        $provider = app(AiProviderInterface::class);

        $this->assertInstanceOf(ResilientAiProvider::class, $provider);
    }

    public function test_intent_provider_binding_exists_and_is_resilient(): void
    {
        $provider = app('ai.provider.intent');

        $this->assertInstanceOf(ResilientAiProvider::class, $provider);
    }

    public function test_intent_provider_uses_fast_model_from_settings(): void
    {
        app(SettingsService::class)->updateAiSettings([
            'ai_provider' => 'gemini',
            'gemini_fast_model' => 'gemini-2.5-flash-lite',
        ]);

        // Re-resolve after settings change (singleton sudah ter-cache di test sebelumnya tidak masalah,
        // tiap test boot app baru).
        $provider = app('ai.provider.intent');

        $reflection = new \ReflectionProperty($provider, 'model');
        $this->assertSame('gemini-2.5-flash-lite', $reflection->getValue($provider));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=AiProviderBindingTest`
Expected: FAIL — `Target class [ai.provider.intent] does not exist` dan assert `ResilientAiProvider` gagal

- [ ] **Step 3: Refactor `AppServiceProvider::register()`**

Ganti blok binding `AiProviderInterface` (line 33-70) dan binding `IntentParserService` (line 80-85) dengan:

```php
        $this->app->singleton('ai.provider.base', function ($app) {
            // Get AI config from database via SettingsService
            // Only if database is available (not during migrations)
            if (Schema::hasTable('system_settings')) {
                $config = $app->make(SettingsService::class)->getActiveAiConfig();

                return match ($config['provider']) {
                    'gemini' => new GeminiProvider(
                        $config['api_key'],
                        $config['model'],
                        $config['base_url']
                    ),
                    'anthropic' => new ClaudeProvider(
                        $config['api_key'],
                        $config['model'],
                        $config['base_url']
                    ),
                    default => new OpenAiProvider(
                        $config['api_key'],
                        $config['model'],
                        $config['base_url'] ?? null
                    ),
                };
            }

            // Fallback to env config during migrations or if table doesn't exist
            return new OpenAiProvider(
                config('services.openai.api_key'),
                config('services.openai.model'),
                config('services.openai.base_url')
            );
        });

        $this->app->singleton(AiProviderInterface::class, function ($app) {
            $config = $this->resolveAiConfig($app);

            return new ResilientAiProvider(
                $app->make('ai.provider.base'),
                null,
                $config['fallback_model'] ?? null,
            );
        });

        $this->app->singleton('ai.provider.intent', function ($app) {
            $config = $this->resolveAiConfig($app);

            return new ResilientAiProvider(
                $app->make('ai.provider.base'),
                $config['fast_model'] ?? null,
                $config['fast_fallback_model'] ?? null,
            );
        });
```

dan ubah binding `IntentParserService` menjadi:

```php
        $this->app->singleton(IntentParserService::class, function ($app) {
            return new IntentParserService(
                $app->make('ai.provider.intent'),
                $app->make(PluginManager::class)
            );
        });
```

Tambahkan helper method di class `AppServiceProvider` (setelah `register()`):

```php
    /**
     * Resolve active AI config, or empty array when the settings table
     * is unavailable (e.g. during migrations).
     *
     * @return array<string, mixed>
     */
    protected function resolveAiConfig($app): array
    {
        if (! Schema::hasTable('system_settings')) {
            return [];
        }

        return $app->make(SettingsService::class)->getActiveAiConfig();
    }
```

Tambahkan import di bagian atas file:

```php
use App\Services\Ai\ResilientAiProvider;
```

- [ ] **Step 4: Jalankan test binding + seluruh suite chat**

Run: `php artisan test --compact --filter=AiProviderBindingTest`
Expected: PASS (3 tests)

Run: `php artisan test --compact --filter=ChatFeatureTest`
Expected: PASS — binding refactor tidak boleh memecahkan test chat yang ada

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Feature/AiProviderBindingTest.php
git commit -m "feat: wire fallback model for main provider and fast model for intent parsing"
```

---

### Task 4: Admin UI — input fast model & fallback model

**Files:**
- Modify: `resources/js/pages/admin/settings/Index.vue` (form state line 34-47, transform line 49-67, kartu provider Gemini line ~406-430, OpenAI line ~432-456, Anthropic line ~458-482)

Tidak ada test otomatis frontend di project ini untuk halaman admin; verifikasi via `npm run type-check` + cek manual.

- [ ] **Step 1: Tambah field ke `aiForm`**

Di `useForm` (line 34), tambahkan setelah field model masing-masing provider:

```ts
const aiForm = useForm({
    ai_provider: props.aiSettings.ai_provider,
    gemini_api_key: '',
    gemini_model: props.aiSettings.gemini_model,
    gemini_fast_model: props.aiSettings.gemini_fast_model,
    gemini_fallback_model: props.aiSettings.gemini_fallback_model,
    gemini_fast_fallback_model: props.aiSettings.gemini_fast_fallback_model,
    gemini_base_url: props.aiSettings.gemini_base_url,
    openai_api_key: '',
    openai_model: props.aiSettings.openai_model,
    openai_fast_model: props.aiSettings.openai_fast_model,
    openai_fallback_model: props.aiSettings.openai_fallback_model,
    openai_fast_fallback_model: props.aiSettings.openai_fast_fallback_model,
    openai_base_url: props.aiSettings.openai_base_url,
    anthropic_api_key: '',
    anthropic_model: props.aiSettings.anthropic_model,
    anthropic_fast_model: props.aiSettings.anthropic_fast_model,
    anthropic_fallback_model: props.aiSettings.anthropic_fallback_model,
    anthropic_fast_fallback_model: props.aiSettings.anthropic_fast_fallback_model,
    anthropic_base_url: props.aiSettings.anthropic_base_url,
    ai_context_length: props.aiSettings.ai_context_length,
});
```

> Sesuaikan dengan field yang sudah ada di file — pola di atas hanya menambah 9 baris `*_fast_model` / `*_fallback_model` / `*_fast_fallback_model`. Update juga interface TypeScript `aiSettings` props jika ada definisinya.

- [ ] **Step 2: Tambah field ke payload transform**

Di `aiForm.transform` (line 49-67), tambahkan per branch provider. Contoh branch gemini:

```ts
        if (data.ai_provider === 'gemini') {
            if (data.gemini_api_key) payload.gemini_api_key = data.gemini_api_key;
            payload.gemini_model = data.gemini_model;
            payload.gemini_fast_model = data.gemini_fast_model;
            payload.gemini_fallback_model = data.gemini_fallback_model;
            payload.gemini_fast_fallback_model = data.gemini_fast_fallback_model;
            payload.gemini_base_url = data.gemini_base_url;
        }
```

Ulangi pola sama untuk branch `openai` dan `anthropic`.

- [ ] **Step 3: Tambah input di kartu tiap provider**

Di dalam kartu Gemini (setelah input `gemini_model` line ~423), tambahkan:

```vue
<div class="grid gap-4 md:grid-cols-3">
    <div class="space-y-2">
        <Label for="gemini_fast_model">Fast Model (Intent)</Label>
        <Input v-model="aiForm.gemini_fast_model" placeholder="gemini-2.5-flash-lite" />
        <p class="text-xs text-muted-foreground">Model murah untuk intent parsing. Kosongkan untuk pakai model utama.</p>
    </div>
    <div class="space-y-2">
        <Label for="gemini_fallback_model">Fallback Model</Label>
        <Input v-model="aiForm.gemini_fallback_model" placeholder="gemini-2.5-flash" />
        <p class="text-xs text-muted-foreground">Dipakai otomatis saat model utama gagal/error.</p>
    </div>
    <div class="space-y-2">
        <Label for="gemini_fast_fallback_model">Fast Fallback Model</Label>
        <Input v-model="aiForm.gemini_fast_fallback_model" placeholder="gemini-2.0-flash" />
        <p class="text-xs text-muted-foreground">Fallback untuk fast model. Kosongkan untuk pakai Fallback Model.</p>
    </div>
</div>
```

Ulangi untuk kartu OpenAI (placeholder: `gpt-4o-mini`, `gpt-4o`, `gpt-4o-mini`) dan Anthropic (placeholder: `claude-haiku-4-5`, `claude-sonnet-4-6`, `claude-haiku-4-5`), dengan prefix field masing-masing.

- [ ] **Step 4: Type-check + verifikasi manual**

Run: `npm run type-check`
Expected: PASS

Verifikasi manual: buka `/admin/settings`, isi fast/fallback model, save, reload — nilai harus persist.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/admin/settings/Index.vue
git commit -m "feat: admin UI inputs for fast model and fallback models per provider"
```

---

### Task 5: `ChatOrchestrator::processMessage` menerima intent pre-parsed

**Files:**
- Modify: `app/Services/Ai/ChatOrchestrator.php:36-94`
- Test: `tests/Feature/ChatOrchestratorIntentTest.php`

- [ ] **Step 1: Tulis failing test**

Create `tests/Feature/ChatOrchestratorIntentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\User;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\ChatOrchestrator;
use App\Services\Ai\IntentParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatOrchestratorIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_parsed_intent_skips_intent_parser(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $this->mock(IntentParserService::class)
            ->shouldNotReceive('parse');

        $this->mock(AiProviderInterface::class)
            ->shouldReceive('chat')
            ->andReturn('Halo! Ada yang bisa dibantu?');

        $intent = [
            'module' => 'general',
            'action' => 'greeting',
            'entities' => [],
            'confidence' => 0.95,
            'requires_confirmation' => false,
        ];

        $result = app(ChatOrchestrator::class)->processMessage($user, 'halo', $thread, [], $intent);

        $this->assertSame('Halo! Ada yang bisa dibantu?', $result['response']);
        $this->assertFalse($result['action_taken']);
    }

    public function test_null_intent_still_parses_internally(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $this->mock(IntentParserService::class)
            ->shouldReceive('parse')
            ->once()
            ->andReturn([
                'module' => 'general',
                'action' => 'greeting',
                'entities' => [],
                'confidence' => 0.9,
                'requires_confirmation' => false,
            ]);

        $this->mock(AiProviderInterface::class)
            ->shouldReceive('chat')
            ->andReturn('Halo!');

        $result = app(ChatOrchestrator::class)->processMessage($user, 'halo', $thread, []);

        $this->assertSame('Halo!', $result['response']);
    }
}
```

> Catatan: jika `ChatThread` belum punya factory, cek bagaimana `ChatFeatureTest.php` membuat thread dan pakai pola yang sama. Mock `IntentParserService`/`AiProviderInterface` via container menggantikan singleton — `ChatOrchestrator` harus di-resolve SETELAH mock dibuat (urutan di test di atas sudah benar).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=ChatOrchestratorIntentTest`
Expected: FAIL — `processMessage` belum menerima argumen ke-5 (atau parse tetap terpanggil)

- [ ] **Step 3: Ubah signature `processMessage`**

Di `app/Services/Ai/ChatOrchestrator.php` line 36, ubah:

```php
    public function processMessage(User $user, string $message, ChatThread $thread, array $conversationHistory = [], ?array $intent = null): array
```

dan line 91-92, ubah:

```php
        // Parse the intent (skip when caller already parsed it, e.g. ChatController streaming)
        $intent ??= $this->intentParser->parse($user, $message, $conversationHistory);
```

Update juga docblock method:

```php
    /**
     * Process a user message and return the assistant response.
     *
     * @param  array|null  $intent  Pre-parsed intent from IntentParserService::parse(); null = parse internally.
     * @return array{response: string, action_taken: bool, pending_action: array|null}
     */
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --compact --filter=ChatOrchestratorIntentTest`
Expected: PASS (2 tests)

Run: `php artisan test --compact --filter=ChatFeatureTest`
Expected: PASS — perilaku default (intent null) tidak berubah

- [ ] **Step 5: Commit**

```bash
git add app/Services/Ai/ChatOrchestrator.php tests/Feature/ChatOrchestratorIntentTest.php
git commit -m "feat: ChatOrchestrator accepts pre-parsed intent to avoid duplicate parsing"
```

---

### Task 6: `ChatController` streaming — cek pending action dulu, hapus parse duplikat

**Files:**
- Modify: `app/Http/Controllers/ChatController.php:279-326` (blok try di dalam stream closure)
- Test: `tests/Feature/ChatStreamIntentTest.php`

Perubahan alur:
1. Cek `PendingAction` SEBELUM `parseIntent` — pesan konfirmasi "ya"/"batal" tidak perlu LLM sama sekali (keyword check ada di `processMessage`).
2. Hasil `parseIntent` di controller dioper ke `processMessage` — parse kedua hilang.

- [ ] **Step 1: Tulis failing test**

Create `tests/Feature/ChatStreamIntentTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ChatThread;
use App\Models\PendingAction;
use App\Models\User;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\IntentParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatStreamIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_message_with_pending_action_never_calls_intent_parser(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        PendingAction::create([
            'user_id' => $user->id,
            'thread_id' => $thread->id,
            'action_type' => 'create_transaction',
            'module' => 'finance',
            'payload' => ['amount' => 50000, 'tx_type' => 'expense', 'category' => 'Makan'],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->mock(IntentParserService::class)
            ->shouldNotReceive('parse');

        $this->mock(AiProviderInterface::class)
            ->shouldReceive('chat')
            ->andReturn('Transaksi tersimpan!');

        $response = $this->actingAs($user)->post(route('chat.stream'), [
            'message' => 'ya',
            'thread_id' => $thread->id,
        ]);

        $response->assertOk();
        $response->streamedContent(); // consume stream so closure runs
    }

    public function test_intent_parsed_once_for_action_message(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $this->mock(IntentParserService::class)
            ->shouldReceive('parse')
            ->once() // sebelumnya 2x: controller + orchestrator
            ->andReturn([
                'module' => 'finance',
                'action' => 'view_balance',
                'entities' => [],
                'confidence' => 0.9,
                'requires_confirmation' => false,
            ]);

        $this->mock(AiProviderInterface::class)
            ->shouldReceive('chat')
            ->andReturn('Saldo kamu Rp1.000.000');

        $response = $this->actingAs($user)->post(route('chat.stream'), [
            'message' => 'cek saldo dong',
            'thread_id' => $thread->id,
        ]);

        $response->assertOk();
        $response->streamedContent();
    }
}
```

> Catatan: cek nama route stream via `php artisan route:list | grep -i chat` (kemungkinan `chat.stream`) dan field request yang divalidasi di `ChatController` (`message` vs `content`, `thread_id`) — sesuaikan test dengan yang ada. Lihat `ChatStreamMemoryDispatchTest.php` sebagai referensi pola test endpoint stream yang sudah jalan.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=ChatStreamIntentTest`
Expected: FAIL — `parse` terpanggil padahal `shouldNotReceive` (test 1), dan terpanggil 2x bukan 1x (test 2)

- [ ] **Step 3: Refactor blok try di stream closure**

Di `app/Http/Controllers/ChatController.php`, ganti isi blok `try` (line 283-326) menjadi:

```php
            try {
                // Check pending action FIRST: confirmation/cancellation replies ("ya"/"batal")
                // are handled by keyword matching inside processMessage and need no LLM parse.
                $pendingAction = PendingAction::where('thread_id', $thread->id)
                    ->pending()
                    ->latest()
                    ->first();

                $intent = null;
                $shouldStream = false;

                if (! $pendingAction) {
                    $intent = $this->chatOrchestrator->parseIntent($user, $messageContent, $history);

                    $streamableGeneralActions = ['query'];
                    $shouldStream = $intent['module'] === 'general'
                        && in_array($intent['action'], $streamableGeneralActions, true);
                }

                // For simple general chat, use streaming
                if ($shouldStream) {
                    // Build messages using ChatService
                    $messages = $this->chatOrchestrator->getChatService()->formatMessages($user, $messageContent, $history, $memoryContext);

                    // Stream response
                    $fullResponse = $this->chatOrchestrator->getAiProvider()->chatStream($messages, function ($chunk) {
                        echo "event: message_chunk\n";
                        echo 'data: '.json_encode(['content' => $chunk])."\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }, [
                        'temperature' => 0.8,
                        'max_tokens' => 1500,
                    ]);
                } else {
                    // For actions, reuse the already-parsed intent (no second parse)
                    $result = $this->chatOrchestrator->processMessage($user, $messageContent, $thread, $history, $intent);
                    $fullResponse = $result['response'];

                    // Send full response at once
                    echo "event: message_chunk\n";
                    echo 'data: '.json_encode(['content' => $fullResponse])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Exception $e) {
```

(Catch block dan sisanya tidak berubah. Variabel `$shouldStream` tetap dipakai blok dispatch memory extraction line 357.)

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --compact --filter=ChatStreamIntentTest`
Expected: PASS (2 tests)

Run: `php artisan test --compact --filter="ChatFeatureTest|ChatStreamMemoryDispatchTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ChatController.php tests/Feature/ChatStreamIntentTest.php
git commit -m "perf: check pending action before intent parse and reuse parsed intent in stream flow"
```

---

### Task 7: `ResponseTemplates` — respons deterministik tanpa LLM

**Files:**
- Create: `app/Services/Ai/ResponseTemplates.php`
- Modify: `app/Services/Ai/ChatOrchestrator.php` — method `handleCancellation` (line 176), `formatFinanceSummary` (line 968), `formatSchedulesList` (line 996), `formatNotesList` (line 1007), `formatTransactionConfirmation` (line 1015), `formatScheduleConfirmation` (line 1023), `formatScheduleUpdateConfirmation` (line 1031), `formatDeleteConfirmation` (line 1047)
- Test: `tests/Unit/ResponseTemplatesTest.php`, tambahan di `tests/Feature/ChatOrchestratorIntentTest.php`

Yang pindah ke template (data deterministik, LLM justru risiko salah angka): finance summary, schedules list, notes list, semua konfirmasi (transaksi/jadwal/update/hapus), pembatalan.
Yang TETAP lewat LLM (persona-heavy): greeting, help, success/error pasca-eksekusi, out-of-scope, casual conversation.

- [ ] **Step 1: Tulis failing unit test**

Create `tests/Unit/ResponseTemplatesTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Ai\ResponseTemplates;
use PHPUnit\Framework\TestCase;

class ResponseTemplatesTest extends TestCase
{
    public function test_detects_indonesian_by_default(): void
    {
        $this->assertSame('id', ResponseTemplates::detectLanguage('catat pengeluaran 50rb untuk makan'));
        $this->assertSame('id', ResponseTemplates::detectLanguage('ya'));
    }

    public function test_detects_english(): void
    {
        $this->assertSame('en', ResponseTemplates::detectLanguage('please show my balance for the month'));
    }

    public function test_finance_summary_contains_exact_numbers(): void
    {
        $output = ResponseTemplates::financeSummary('Kak Budi', [
            'period' => 'this_month',
            'income' => 5000000,
            'expense' => 1250000,
            'net' => 3750000,
            'total_balance' => 10000000,
        ], 'id');

        $this->assertStringContainsString('Kak Budi', $output);
        $this->assertStringContainsString('Rp5.000.000', $output);
        $this->assertStringContainsString('Rp1.250.000', $output);
        $this->assertStringContainsString('Rp3.750.000', $output);
        $this->assertStringContainsString('Rp10.000.000', $output);
    }

    public function test_transaction_confirmation_asks_for_ya(): void
    {
        $output = ResponseTemplates::transactionConfirmation('Kak Budi', [
            'tx_type' => 'expense',
            'amount' => 50000,
            'category' => 'Makan',
            'note' => 'nasi goreng',
            'occurred_at' => '2026-06-10',
        ], 'id');

        $this->assertStringContainsString('Rp50.000', $output);
        $this->assertStringContainsString('Makan', $output);
        $this->assertStringContainsString('"ya"', $output);
        $this->assertStringContainsString('"batal"', $output);
    }

    public function test_schedules_list_renders_all_items(): void
    {
        $output = ResponseTemplates::schedulesList('Kak Budi', [
            ['title' => 'Meeting tim', 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'Zoom'],
            ['title' => 'Makan siang klien', 'start_time' => '12:00', 'end_time' => null, 'location' => null],
        ], 'today', 'id');

        $this->assertStringContainsString('Meeting tim', $output);
        $this->assertStringContainsString('09:00 - 10:00', $output);
        $this->assertStringContainsString('Makan siang klien', $output);
    }

    public function test_empty_schedules_list(): void
    {
        $output = ResponseTemplates::schedulesList('Kak Budi', [], 'today', 'id');

        $this->assertStringContainsString('Tidak ada jadwal', $output);
    }

    public function test_delete_confirmation_warns(): void
    {
        $output = ResponseTemplates::deleteConfirmation('Kak Budi', 'transaksi', 'makan siang', 'id');

        $this->assertStringContainsString('hapus', strtolower($output));
        $this->assertStringContainsString('makan siang', $output);
        $this->assertStringContainsString('"ya"', $output);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --compact --filter=ResponseTemplatesTest`
Expected: FAIL — `Class "App\Services\Ai\ResponseTemplates" not found`

- [ ] **Step 3: Implementasi `ResponseTemplates`**

Create `app/Services/Ai/ResponseTemplates.php`:

```php
<?php

namespace App\Services\Ai;

/**
 * Deterministic response templates for data-heavy replies.
 *
 * Replaces LLM "personalization" round-trips for responses whose content is
 * fully known up front (lists, summaries, confirmations). Guarantees numbers
 * are never mangled and saves one LLM call per action.
 *
 * Persona rules (AGENTS.md §2.1) are honored via the caller-supplied
 * call name ("{call_preference} {name}") and ID/EN language detection.
 */
class ResponseTemplates
{
    /**
     * Detect user language from a message. Defaults to Indonesian.
     */
    public static function detectLanguage(string $message): string
    {
        $haystack = ' '.strtolower(trim($message)).' ';

        $englishMarkers = [
            ' what ', ' how ', ' my ', ' the ', ' show ', ' please ', ' can you ',
            ' create ', ' delete ', ' update ', ' today ', ' tomorrow ', ' schedule ',
            ' note ', ' balance ', ' expense ', ' income ', ' yes ', ' list ',
        ];
        $indonesianMarkers = [
            ' apa ', ' bagaimana ', ' gimana ', ' saya ', ' aku ', ' tolong ',
            ' buat ', ' bikin ', ' hapus ', ' ubah ', ' hari ', ' besok ', ' jadwal ',
            ' catatan ', ' catat ', ' saldo ', ' ya ', ' tidak ', ' yang ', ' untuk ',
            ' dong ', ' cek ', ' lihat ',
        ];

        $en = count(array_filter($englishMarkers, fn ($m) => str_contains($haystack, $m)));
        $id = count(array_filter($indonesianMarkers, fn ($m) => str_contains($haystack, $m)));

        return $en > $id ? 'en' : 'id';
    }

    protected static function rupiah(int|float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    protected static function periodLabel(?string $period, string $lang): string
    {
        return match ($period) {
            'today' => $lang === 'en' ? 'today' : 'hari ini',
            'tomorrow' => $lang === 'en' ? 'tomorrow' : 'besok',
            'this_week' => $lang === 'en' ? 'this week' : 'minggu ini',
            'this_month' => $lang === 'en' ? 'this month' : 'bulan ini',
            default => $lang === 'en' ? 'this month' : 'bulan ini',
        };
    }

    protected static function confirmFooter(string $lang): string
    {
        return $lang === 'en'
            ? "\nReply \"ya\" to confirm or \"batal\" to cancel."
            : "\nBalas \"ya\" untuk konfirmasi atau \"batal\" untuk membatalkan.";
    }

    public static function financeSummary(string $callName, array $data, string $lang): string
    {
        $period = self::periodLabel($data['period'] ?? 'this_month', $lang);

        if ($lang === 'en') {
            return "Here is your finance summary for {$period}, {$callName}:\n\n"
                .'💵 Income: '.self::rupiah($data['income'])."\n"
                .'💸 Expense: '.self::rupiah($data['expense'])."\n"
                .'🧮 Net: '.self::rupiah($data['net'])."\n"
                .'💰 Total balance: '.self::rupiah($data['total_balance']);
        }

        return "Ini ringkasan keuangan {$period}, {$callName}:\n\n"
            .'💵 Pemasukan: '.self::rupiah($data['income'])."\n"
            .'💸 Pengeluaran: '.self::rupiah($data['expense'])."\n"
            .'🧮 Selisih: '.self::rupiah($data['net'])."\n"
            .'💰 Saldo total: '.self::rupiah($data['total_balance']);
    }

    public static function transactionConfirmation(string $callName, array $entities, string $lang): string
    {
        $isIncome = ($entities['tx_type'] ?? 'expense') === 'income';
        $amount = self::rupiah($entities['amount'] ?? 0);
        $category = $entities['category'] ?? ($lang === 'en' ? 'Uncategorized' : 'Belum ditentukan');
        $note = $entities['note'] ?? '-';
        $date = $entities['occurred_at'] ?? ($lang === 'en' ? 'Today' : 'Hari ini');

        if ($lang === 'en') {
            $type = $isIncome ? 'Income' : 'Expense';

            return "{$callName}, save this transaction?\n\n"
                .($isIncome ? '💵' : '💸')." {$type}: {$amount}\n"
                ."📁 Category: {$category}\n"
                ."📝 Note: {$note}\n"
                ."📅 Date: {$date}\n"
                .self::confirmFooter($lang);
        }

        $type = $isIncome ? 'Pemasukan' : 'Pengeluaran';

        return "{$callName}, simpan transaksi ini?\n\n"
            .($isIncome ? '💵' : '💸')." {$type}: {$amount}\n"
            ."📁 Kategori: {$category}\n"
            ."📝 Keterangan: {$note}\n"
            ."📅 Tanggal: {$date}\n"
            .self::confirmFooter($lang);
    }

    public static function scheduleConfirmation(string $callName, array $entities, string $lang): string
    {
        $title = $entities['title'] ?? ($lang === 'en' ? 'Untitled' : 'Tanpa judul');
        $startTime = $entities['start_time'] ?? ($lang === 'en' ? 'Not set' : 'Belum ditentukan');
        $location = $entities['location'] ?? '-';

        if ($lang === 'en') {
            return "{$callName}, create this schedule?\n\n"
                ."📌 Title: {$title}\n"
                ."🕐 Time: {$startTime}\n"
                ."📍 Location: {$location}\n"
                .self::confirmFooter($lang);
        }

        return "{$callName}, buat jadwal ini?\n\n"
            ."📌 Judul: {$title}\n"
            ."🕐 Waktu: {$startTime}\n"
            ."📍 Lokasi: {$location}\n"
            .self::confirmFooter($lang);
    }

    public static function scheduleUpdateConfirmation(string $callName, array $entities, string $lang): string
    {
        $identifier = $entities['title'] ?? $entities['schedule_id'] ?? ($lang === 'en' ? 'that schedule' : 'jadwal tersebut');

        $labels = $lang === 'en'
            ? ['new_title' => 'New title', 'start_time' => 'New start time', 'end_time' => 'New end time', 'location' => 'New location', 'description' => 'New description']
            : ['new_title' => 'Judul baru', 'start_time' => 'Waktu mulai baru', 'end_time' => 'Waktu selesai baru', 'location' => 'Lokasi baru', 'description' => 'Deskripsi baru'];

        $changes = [];
        foreach ($labels as $key => $label) {
            if (isset($entities[$key])) {
                $changes[] = "• {$label}: {$entities[$key]}";
            }
        }

        $changesText = $changes !== []
            ? implode("\n", $changes)
            : ($lang === 'en' ? '• (no change details)' : '• (tidak ada detail perubahan)');

        $head = $lang === 'en'
            ? "{$callName}, update schedule \"{$identifier}\" with these changes?"
            : "{$callName}, ubah jadwal \"{$identifier}\" dengan perubahan ini?";

        return "{$head}\n\n{$changesText}\n".self::confirmFooter($lang);
    }

    public static function deleteConfirmation(string $callName, string $itemType, string $identifier, string $lang): string
    {
        if ($lang === 'en') {
            return "⚠️ {$callName}, are you sure you want to delete {$itemType} \"{$identifier}\"?\n"
                .self::confirmFooter($lang);
        }

        return "⚠️ {$callName}, yakin mau hapus {$itemType} \"{$identifier}\"?\n"
            .self::confirmFooter($lang);
    }

    public static function schedulesList(string $callName, array $schedules, ?string $period, string $lang): string
    {
        if ($schedules === []) {
            $periodLabel = $period ? ' '.self::periodLabel($period, $lang) : '';

            return $lang === 'en'
                ? "No schedules{$periodLabel}, {$callName}. Your time is free! 🎉"
                : "Tidak ada jadwal{$periodLabel}, {$callName}. Waktumu kosong! 🎉";
        }

        $head = $lang === 'en' ? "📅 Here are your schedules, {$callName}:" : "📅 Ini jadwal {$callName}:";
        $lines = [$head, ''];

        foreach ($schedules as $s) {
            $time = ! empty($s['end_time']) ? "{$s['start_time']} - {$s['end_time']}" : $s['start_time'];
            $location = ! empty($s['location'])
                ? ($lang === 'en' ? " at {$s['location']}" : " di {$s['location']}")
                : '';
            $lines[] = "• {$s['title']} — {$time}{$location}";
        }

        return implode("\n", $lines);
    }

    public static function notesList(string $callName, array $notes, string $lang): string
    {
        if ($notes === []) {
            return $lang === 'en'
                ? "You have no saved notes yet, {$callName}."
                : "Belum ada catatan tersimpan, {$callName}.";
        }

        $head = $lang === 'en' ? "📝 Here are your notes, {$callName}:" : "📝 Ini catatan {$callName}:";
        $lines = [$head, ''];

        foreach ($notes as $n) {
            $tags = ! empty($n['tags'])
                ? ' ['.implode(', ', $n['tags']).']'
                : '';
            $lines[] = "**{$n['title']}**{$tags}";
            $lines[] = $n['content'];
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    public static function cancellation(string $callName, string $lang): string
    {
        return $lang === 'en'
            ? "Okay, cancelled, {$callName}. Anything else I can help with?"
            : "Oke, dibatalkan, {$callName}. Ada lagi yang bisa kubantu?";
    }
}
```

- [ ] **Step 4: Jalankan unit test, pastikan lulus**

Run: `php artisan test --compact --filter=ResponseTemplatesTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Tulis failing feature test — view_balance tanpa call LLM**

Tambahkan method berikut ke `tests/Feature/ChatOrchestratorIntentTest.php`:

```php
    public function test_view_balance_uses_template_without_llm_call(): void
    {
        $user = User::factory()->create();
        $thread = ChatThread::factory()->create(['user_id' => $user->id]);

        $this->mock(IntentParserService::class)->shouldNotReceive('parse');

        // No LLM call at all: response must come from ResponseTemplates.
        $this->mock(AiProviderInterface::class)
            ->shouldNotReceive('chat')
            ->shouldNotReceive('chatStream');

        $intent = [
            'module' => 'finance',
            'action' => 'view_balance',
            'entities' => [],
            'confidence' => 0.95,
            'requires_confirmation' => false,
        ];

        $result = app(ChatOrchestrator::class)->processMessage($user, 'cek saldo', $thread, [], $intent);

        $this->assertStringContainsString('Rp', $result['response']);
        $this->assertFalse($result['action_taken']);
    }
```

Run: `php artisan test --compact --filter=ChatOrchestratorIntentTest::test_view_balance_uses_template_without_llm_call`
Expected: FAIL — `chat` terpanggil oleh `personalizeResponse`

- [ ] **Step 6: Ganti format methods di `ChatOrchestrator` ke template**

Tambahkan helper di `ChatOrchestrator` (dekat `personalizeResponse`):

```php
    /**
     * Resolve "{call_preference} {name}" and detected language for templates.
     *
     * @return array{0: string, 1: string}
     */
    protected function templateContext(User $user): array
    {
        $callPref = $user->profile?->call_preference ?? 'Kak';
        $callName = trim("{$callPref} {$user->name}");
        $lang = ResponseTemplates::detectLanguage($this->currentUserMessage);

        return [$callName, $lang];
    }
```

Lalu ganti body method berikut (signature tidak berubah, parameter `$memoryContext` dibiarkan untuk kompatibilitas pemanggil):

```php
    protected function formatFinanceSummary(User $user, array $summary, string $memoryContext = ''): string
    {
        [$callName, $lang] = $this->templateContext($user);

        return ResponseTemplates::financeSummary($callName, $summary, $lang);
    }

    protected function formatSchedulesList(User $user, array $schedules, ?string $period, string $memoryContext = ''): string
    {
        [$callName, $lang] = $this->templateContext($user);

        return ResponseTemplates::schedulesList($callName, $schedules, $period, $lang);
    }

    protected function formatNotesList(User $user, array $notes, string $memoryContext = ''): string
    {
        [$callName, $lang] = $this->templateContext($user);

        return ResponseTemplates::notesList($callName, $notes, $lang);
    }

    protected function formatTransactionConfirmation(User $user, array $entities, string $memoryContext = ''): string
    {
        [$callName, $lang] = $this->templateContext($user);

        return ResponseTemplates::transactionConfirmation($callName, $entities, $lang);
    }

    protected function formatScheduleConfirmation(User $user, array $entities, string $memoryContext = ''): string
    {
        [$callName, $lang] = $this->templateContext($user);

        return ResponseTemplates::scheduleConfirmation($callName, $entities, $lang);
    }

    protected function formatScheduleUpdateConfirmation(User $user, array $entities, string $memoryContext = ''): string
    {
        [$callName, $lang] = $this->templateContext($user);

        return ResponseTemplates::scheduleUpdateConfirmation($callName, $entities, $lang);
    }

    protected function formatDeleteConfirmation(User $user, string $itemType, array $entities, string $memoryContext = ''): string
    {
        $identifier = $entities['title'] ?? $entities['description'] ?? $entities['note_id'] ?? $entities['schedule_id'] ?? $entities['transaction_id'] ?? 'item tersebut';
        [$callName, $lang] = $this->templateContext($user);

        return ResponseTemplates::deleteConfirmation($callName, $itemType, (string) $identifier, $lang);
    }
```

Dan ganti body `handleCancellation` (line 176-191) menjadi:

```php
    protected function handleCancellation(PendingAction $pendingAction, string $memoryContext = ''): array
    {
        $pendingAction->cancel();

        [$callName, $lang] = $this->templateContext($pendingAction->user);

        return [
            'response' => ResponseTemplates::cancellation($callName, $lang),
            'action_taken' => false,
            'pending_action' => null,
        ];
    }
```

Method `buildFinanceSummaryContext`, `buildSchedulesContext`, `buildNotesContext`, `buildTransactionConfirmationContext`, `buildScheduleConfirmationContext`, `buildScheduleUpdateConfirmationContext`, `buildNoteConfirmationContext`, `buildDeleteConfirmationContext` di `ChatOrchestrator` menjadi dead code — hapus, beserta case-nya di `buildResponseContext` (sisakan `success`, `error`, `greeting`, `help`, `out_of_scope`, `unknown`, `default`).

- [ ] **Step 7: Jalankan seluruh test terkait**

Run: `php artisan test --compact --filter="ChatOrchestratorIntentTest|ResponseTemplatesTest|ChatFeatureTest|ScheduleIntentTest"`
Expected: PASS. Jika ada test lama yang meng-assert respons hasil LLM untuk list/konfirmasi, perbarui assertion-nya ke format template (cek isi angka/judul, bukan kalimat persis).

- [ ] **Step 8: Commit**

```bash
git add app/Services/Ai/ResponseTemplates.php app/Services/Ai/ChatOrchestrator.php tests/Unit/ResponseTemplatesTest.php tests/Feature/ChatOrchestratorIntentTest.php
git commit -m "perf: deterministic templates for lists, summaries, and confirmations (no LLM round-trip)"
```

---

### Task 8: Verifikasi akhir + dokumentasi

**Files:**
- Modify: `docs/CURRENT_STATUS.md` (tambah catatan optimasi)
- Modify: `docs/ARCHITECTURE.md` (jika ada diagram alur chat, perbarui jumlah call LLM)

- [ ] **Step 1: Format + type-check**

Run: `vendor/bin/pint --dirty`
Expected: semua file terformat, no errors

Run: `npm run type-check`
Expected: PASS

- [ ] **Step 2: Full test suite**

Run: `php artisan test --compact`
Expected: PASS semua (sebelumnya 34+ tests; sekarang bertambah ~19 test baru)

- [ ] **Step 3: Update dokumentasi**

Di `docs/CURRENT_STATUS.md`, tambahkan ke bagian fitur/perubahan terbaru:

```markdown
### LLM Call Optimization (Phase 1) — Juni 2026
- Intent parsing hanya 1x per pesan (sebelumnya 2x di alur streaming).
- Konfirmasi "ya"/"batal" tidak memanggil LLM untuk intent parsing.
- Respons deterministik (list, summary, konfirmasi) pakai `ResponseTemplates`, bukan LLM.
- Admin dapat set **Fast Model** (intent parsing) dan **Fallback Model** (retry otomatis saat model utama gagal) per provider di Admin Settings.
- Call LLM per pesan: chat umum 1–2 (sebelumnya 2–3), aksi 1–2 (sebelumnya 3–6), konfirmasi 1 (sebelumnya 2–3).
```

- [ ] **Step 4: Commit**

```bash
git add docs/CURRENT_STATUS.md docs/ARCHITECTURE.md
git commit -m "docs: record LLM call optimization phase 1"
```

---

## Priority 3 (Future): LLM Phase 2 — Native Tool-Use Rewrite

> Belum dieksekusi. Buat plan terpisah (brainstorming + writing-plans) sebelum mulai.

**Tujuan:** Ganti arsitektur intent-parser-terpisah dengan satu agent loop berbasis native tool calling — pola standar industri (Anthropic tool use / OpenAI function calling).

**Sketsa:**
- Satu call `chat()` dengan definisi tools: `create_transaction`, `view_balance`, `view_transactions`, `create_schedule`, `update_schedule`, `delete_schedule`, `view_schedules`, `create_note`, `update_note`, `delete_note`, `view_notes`, + tools dari plugin aktif.
- Model jawab langsung (chat biasa, streamed) ATAU return tool call → eksekusi → hasil masuk konteks → respons final.
- Mutasi tetap lewat Confirmation Flow (AGENTS.md §2.2): tool call mutasi → buat `PendingAction` → respons konfirmasi dari call yang sama. Konfirmasi "ya" → eksekusi.
- Persona + memory context di system prompt call yang sama — `personalizeResponse` dan `IntentParserService` dihapus total.
- Hasil: 1–2 call per pesan, semua streamable, akurasi entity extraction naik (model melihat seluruh percakapan).
- **Konfigurasi model menyusut**: alur chat utama cukup 1 model + 1 fallback (`{provider}_model` + `{provider}_fallback_model`). Setting `fast_model`/`fast_fallback_model` tidak lagi dipakai untuk intent parsing (yang hilang total). Evaluasi saat itu: pertahankan `fast_model` hanya jika masih berguna untuk background job (mis. `ExtractConversationMemories`, generate judul thread) — kalau tidak, hapus setting + UI-nya.

**Prasyarat:** Phase 1 selesai dan stabil di production; perlu desain ulang `ChatOrchestrator` + `IntentParserService` + alur streaming SSE untuk tool call loop.

---

## Planned (Backlog)

- **WhatsApp Integration** — via WhatsApp Business API atau Twilio
- **Payment Gateway** — Midtrans/Xendit untuk subscription otomatis
- **Mobile App** — React Native wrapper atau PWA
