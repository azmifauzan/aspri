# Copilot Instructions — ASPRI

Dokumen ini adalah panduan untuk GitHub Copilot saat membuat atau mengubah kode di repository ini.

## Konteks Project

- Backend: Java 25, Spring Boot 4.0.1
- Frontend: Angular 21
- Referensi UI admin: TailAdmin (https://tailadmin.com/) sebagai acuan layout setelah login
- I18N: 2 bahasa (Bahasa Indonesia & English)
- Theme: Light & Dark
- Database: PostgreSQL (dapat menggunakan Supabase atau PostgreSQL biasa, tanpa dependency ke Supabase Auth)
- Integrasi chat: Telegram (MVP) menggunakan `rubenlagus/TelegramBots`, WhatsApp menyusul
- Modul utama: Dashboard, Chat, Note, Jadwal, Keuangan
- Tidak bergantung pada Google API (daftar/login/calendar)
- Asisten cerdas memakai LLM **provider-agnostic** menggunakan **Spring AI** (jangan mengunci ke satu vendor)
- **Autentikasi: Manual dengan BCrypt + JWT (portable ke database PostgreSQL manapun)**

## Aturan Utama (Wajib)

1) Jangan gunakan Google OAuth / Google Calendar API.
2) Semua data user harus terisolasi per `user_id`.
3) Semua pemanggilan LLM harus lewat Spring AI abstractions (`ChatModel`, dll). Jangan panggil SDK vendor secara langsung di domain layer.
4) Integrasi Telegram harus idempotent (hindari double-processing untuk update yang sama).
5) Note tidak menggunakan MinIO. Konten note disimpan di database (block-based model).
6) Persona ASPRI versi lama harus dipertahankan:
  - Chat-first: semua fitur utama bisa diakses lewat percakapan.
  - Match bahasa user (ID/EN).
  - Panggil user sesuai `call_preference` dan konsisten.
  - Gunakan `aspri_name` + `aspri_persona` per user pada prompt.
  - Untuk create/update/delete, wajib minta konfirmasi **ya**/**batal** sebelum eksekusi.
7) Aplikasi web wajib mendukung 2 bahasa (ID/EN) dan 2 tema (light/dark).

## Konvensi Backend (Spring Boot)

- Struktur paket yang disarankan:
  - `...api` (controller/rest)
  - `...service` (domain service)
  - `...domain` (entity/aggregate)
  - `...repo` (repository/JPA)
  - `...integration.telegram` (bot adapter)
  - `...ai` (orchestrator/prompt/config)

- Auth:
  - **MANUAL authentication dengan BCrypt password hashing + JWT token generation**
  - **TIDAK menggunakan Supabase Auth atau OAuth external provider**
  - Password hash disimpan di `user_profiles.password_hash` menggunakan BCrypt
  - JWT token generated di backend menggunakan JJWT library (HMAC SHA-256)
  - JWT secret key harus strong (minimum 32 characters) dan disimpan di environment variable
  - Token validation dilakukan dengan `JwtAuthenticationFilter` di Spring Security
  - User ID dari JWT subject digunakan sebagai `user_id` untuk query database
  - Sistem auth fully portable: bisa pindah ke PostgreSQL server manapun tanpa dependency external

- Database:
  - Target PostgreSQL.
  - Gunakan Flyway atau Liquibase untuk migration (pilih salah satu dan konsisten).
  - Hindari query tanpa filter `user_id`.

- API design:
  - REST JSON.
  - Gunakan DTO request/response.
  - Validasi input dengan Bean Validation.

## Konvensi AI (Spring AI)

- Buat 1 lapisan orchestrator:
  - menerima input channel (web/telegram)
  - memanggil Spring AI `ChatModel`
  - meminta structured output (JSON) dan memvalidasi sebelum eksekusi aksi

- Persona wajib disuntikkan dari data user:
  - `aspri_name`, `aspri_persona`, `call_preference`
  - bahasa mengikuti user

- Mutasi data lewat chat wajib aman:
  - simpan pending action
  - kirim ringkasan
  - eksekusi hanya setelah konfirmasi

- Jangan hardcode provider.
  - Konfigurasi provider/model lewat `application.yml` dan env.

- Guardrails:
  - Batasi prompt/context.
  - Jangan log secret.
  - Redact PII bila logging percakapan diperlukan.

## Konvensi Telegram

- Gunakan mekanisme link account:
  - Web generate kode sekali pakai dengan TTL
  - Telegram: `/link <CODE>`

- Idempotency:
  - Simpan `external_message_id` atau kombinasi `chat_id:message_id` untuk dedup.

## Konvensi Frontend (Angular)

- Gunakan Angular 21 best practices.
- **TIDAK menggunakan Supabase client library** - semua API calls lewat backend
- Frontend hanya menyimpan JWT token di localStorage
- Kirim token via `Authorization: Bearer <token>` header untuk authenticated requests
- Jangan simpan credentials atau secret apapun di frontend code atau environment files

- I18N:
  - Jangan hardcode string UI; gunakan translation keys.
  - Minimal dukung `id` dan `en` (atau `id-ID` dan `en-US`) secara konsisten.
  - Bahasa respons asisten mengikuti bahasa user.

- Theme:
  - Wajib ada light/dark theme.
  - Jangan hardcode warna di component; gunakan token/utilities (Tailwind/CSS variables) agar theme konsisten.
  - Simpan preferensi theme user (mis. di profile atau localStorage) dan apply global.

## Testing

- Backend: utamakan unit test untuk service + integration test untuk repository/API.
- Frontend: minimal component/service tests bila ada.

## Dokumentasi

- Jika menambah modul besar atau mengubah skema DB, update dokumen:
  - `docs/ARCHITECTURE.md`
  - `docs/DATABASE.md`
  - `docs/AI.md`
  - `docs/INTEGRATIONS.md`
