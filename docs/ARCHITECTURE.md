
# Arsitektur ASPRI (Next Gen)

Dokumen ini menjelaskan rancangan arsitektur untuk ASPRI versi baru dengan stack:
- Backend: Java 25, Spring Boot 4.0.1
- AI: Spring AI (provider-agnostic)
- Frontend: Angular 21
- Database: PostgreSQL (dapat menggunakan Supabase atau PostgreSQL biasa)
- Auth: **Manual dengan BCrypt + JWT (fully portable, tanpa dependency eksternal)**
- Integrasi chat: Telegram (MVP), WhatsApp (rencana)

## Tujuan

- Menyediakan asisten pribadi untuk membantu pengelolaan **jadwal** dan **keuangan**.
- Tidak bergantung pada Google API untuk **daftar/login/calendar**.
- **Autentikasi portable**: menggunakan BCrypt + JWT manual agar bisa pindah ke PostgreSQL manapun tanpa dependency ke auth provider eksternal.
- Memungkinkan interaksi via **web app** dan **Telegram** (awal), dengan jalur pengembangan WhatsApp.
- Setelah login, **Dashboard** menampilkan statistik dan chart jadwal/keuangan.

## Prinsip Produk (dipertahankan dari versi lama)

- **Chat-first**: semua fitur utama (note/jadwal/keuangan) dapat diakses lewat percakapan, baik dari Web Chat maupun Telegram.
- **Persona per user**: gaya komunikasi asisten mengikuti `aspri_persona` dan memanggil user sesuai `call_preference`.
- **Safe mutations**: aksi yang mengubah data harus meminta konfirmasi (ya/batal) sebelum dieksekusi.

## Ruang Lingkup Modul

ASPRI terdiri dari 5 modul utama:

1. **Dashboard**
	- Menyajikan ringkasan metrik jadwal dan keuangan.
	- Mengambil data teragregasi dari modul Jadwal & Keuangan.

2. **Chat**
	- Menyimpan percakapan pengguna dengan asisten.
	- Menjadi pintu masuk untuk instruksi natural language, baik dari Web maupun Telegram.

3. **Note**
	- Menggantikan modul dokumen lama.
	- Semua note disimpan di database (tidak menggunakan MinIO).
	- Mendukung berbagai bentuk konten (blok) dan pencarian.

4. **Jadwal**
	- Kalender internal, event, pengingat (reminder), dan (opsional) event berulang.
	- Pengingat dapat dikirim ke in-app dan Telegram.

5. **Keuangan**
	- Pencatatan transaksi, kategori, akun, budgeting.
	- Menyediakan agregasi untuk kebutuhan Dashboard.

## Komponen Sistem

### 1) Frontend (Angular 21)

- Single Page Application (SPA) untuk:
  - registrasi/login
  - dashboard
  - CRUD note/jadwal/keuangan
  - UI chat

**UI/UX reference**:
- Tampilan web setelah login mengacu ke **TailAdmin** (https://tailadmin.com/) sebagai referensi layout admin (sidebar, topbar, cards, chart, table).

**Tema**:
- Mendukung **Light** dan **Dark** theme.
- Theme toggle tersedia di UI (mis. di topbar) dan preference disimpan (mis. localStorage) agar konsisten.

**Bahasa (I18N)**:
- Mendukung 2 bahasa: **Bahasa Indonesia** dan **English**.
- UI text menggunakan mekanisme i18n (translation keys), bukan hard-coded string.
- `profiles.locale` menjadi sumber preferensi bahasa (fallback `id-ID`).

**Auth**:
- Frontend menggunakan **backend API manual authentication** untuk sign-in / sign-up.
- **TIDAK menggunakan Supabase Auth atau OAuth provider eksternal**.
- Backend mengelola password hashing (BCrypt) dan JWT token generation sendiri.
- Token akses (JWT) disimpan di localStorage dan dikirim ke backend via header `Authorization: Bearer <token>`.
- Frontend tidak menyimpan credentials atau secret apapun.

### 2) Backend (Spring Boot 4.0.1)

Backend bertindak sebagai:
- API utama untuk modul Dashboard/Chat/Note/Jadwal/Keuangan.
- Mengelola autentikasi dan otorisasi secara manual (tanpa dependency ke provider eksternal).
- Worker untuk integrasi Telegram (menerima pesan, memproses, menyimpan, dan mengirim balasan).

Tambahan komponen internal:
- **AI Gateway (Spring AI)**: lapisan untuk memanggil LLM secara *provider-agnostic* agar kode tidak terkunci ke satu vendor.

**Poin desain penting**:
- **Manual Authentication**: BCrypt untuk password hashing, JJWT library untuk JWT token generation dan validation.
- JWT secret key harus strong (min 32 chars) dan disimpan di environment variable (`JWT_SECRET`).
- `JwtAuthenticationFilter` di Spring Security validate setiap request dan extract user ID dari JWT subject.
- `JwtTokenProvider` service handle token generation, validation, dan extraction.
- Backend mengakses PostgreSQL melalui JDBC (portable ke server PostgreSQL manapun).
- Semua pemanggilan LLM dilakukan via Spring AI (`ChatModel`/`EmbeddingModel`) dan dipilih via konfigurasi.
- **Tidak ada dependency ke Supabase Auth atau OAuth provider eksternal**.

**Authentication Flow**:
1. User registrasi: Backend hash password dengan BCrypt → simpan di `user_profiles.password_hash`
2. User login: Backend verify password → generate JWT token → return ke frontend
3. Frontend simpan JWT di localStorage → kirim via `Authorization: Bearer <token>` header
4. Backend validate JWT → extract user_id → enforce data isolation per user

### 3) Database (PostgreSQL)

- Menyimpan data aplikasi (profile dengan password hash, note, event, transaksi, chat logs, dsb.).
- Dapat menggunakan **PostgreSQL server manapun**: Supabase, AWS RDS, self-hosted, Docker, dll.
- **Fully portable**: tidak ada dependency ke Supabase-specific features atau Auth system.
- Password user disimpan sebagai BCrypt hash di kolom `password_hash` pada tabel `user_profiles`.
- User ID menggunakan VARCHAR (generated UUID atau custom format), bukan foreign key ke `auth.users`.

**Database Migration**:
- Menggunakan **Flyway** untuk version control schema.
- Migration files di `backend/src/main/resources/db/migration/`.
- Auto-apply on application startup (controlled by `spring.flyway.enabled=true`).

### 4) Integrasi Telegram (MVP)

- Menggunakan library: https://github.com/rubenlagus/TelegramBots
- Dua opsi integrasi:
  - **Long polling** (lebih mudah untuk dev/MVP)
  - **Webhook** (lebih baik untuk produksi)

Untuk MVP, default yang paling sederhana adalah long polling.

**Telegram Linking Flow**:
1. User login di web → klik "Link Telegram"
2. Backend generate kode one-time dengan TTL (mis. 5 menit)
3. User kirim `/link <CODE>` ke Telegram bot
4. Backend validate kode → mapping Telegram chat_id ke user_id
5. Simpan di tabel `external_identities` untuk tracking

## Alur Utama

### A. Registrasi & Login (Manual Authentication)

**Registrasi**:
1. User mengisi form registrasi (email, password, nama) di Frontend.
2. Frontend kirim POST `/api/auth/register` ke Backend.
3. Backend validasi input → hash password dengan BCrypt → simpan ke database.
4. Backend generate JWT token (access + refresh) → return ke Frontend.
5. Frontend simpan token di localStorage → redirect ke dashboard.

**Login**:
1. User mengisi form login (email, password) di Frontend.
2. Frontend kirim POST `/api/auth/login` ke Backend.
3. Backend cari user by email → verify password dengan BCrypt.
4. Jika valid: generate JWT token → return ke Frontend.
5. Frontend simpan token → redirect ke dashboard.

**Protected Routes**:
1. Frontend kirim request dengan header `Authorization: Bearer <token>`.
2. Backend `JwtAuthenticationFilter` intercept request → validate token.
3. Extract user_id dari JWT subject → set di SecurityContext.
4. Controller/Service akses user_id untuk data isolation.

### B. Dashboard

1. Frontend memanggil endpoint dashboard (mis. `/api/dashboard/summary`) dengan JWT token.
2. Backend validate token → extract user_id.
3. Backend menjalankan query agregasi dari tabel jadwal & keuangan (filtered by user_id).
4. Frontend menampilkan statistik & chart.

### C. Chat dari Telegram

1. User chat bot Telegram.
2. Backend menerima update via long polling atau webhook.
3. Backend memetakan chat Telegram → user ASPRI (via proses linking di tabel `external_identities`).
4. Backend menyimpan pesan ke tabel chat.
5. Backend menjalankan pemrosesan (rule-based/LLM/intent parser).
6. Backend membuat/ubah note/event/transaksi bila perlu.
7. Backend membalas ke Telegram.

Catatan bahasa:
- Balasan asisten mengikuti bahasa user (ID/EN) dan preferensi `profiles.preferred_language` bila tersedia.

Catatan untuk step 6 (mutasi data):
- Jika intent menghasilkan create/update/delete, backend tidak langsung mengeksekusi.
- Backend menyimpan *pending action* dan mengirim ringkasan + meminta konfirmasi.
- Jika user membalas **ya**, eksekusi dilakukan; jika **batal**, aksi dibatalkan.

## Boundary dan Kontrak

- Frontend tidak langsung melakukan operasi CRUD ke Postgres; operasi modul dilakukan lewat Backend API.
- Frontend hanya menyimpan JWT token; tidak ada credentials atau secret.
- Backend handle semua business logic dan data access.
- PostgreSQL dapat di-host di mana saja: Supabase, AWS RDS, GCP Cloud SQL, self-hosted, Docker.
- **Tidak ada dependency ke Supabase Auth**: sistem auth fully manual dan portable.

## AI (Spring AI)

- Backend menyediakan layanan domain seperti `AssistantService`/`ChatOrchestrator` yang hanya bergantung pada interface Spring AI.
- Provider LLM dipilih via config (mis. OpenAI/Azure/Anthropic/Ollama) tanpa mengubah kode domain.
- Configuration example:
  ```yaml
  spring:
    ai:
      openai:
        api-key: ${SPRING_AI_OPENAI_API_KEY}
        model: gpt-4
      # Atau gunakan provider lain
      anthropic:
        api-key: ${SPRING_AI_ANTHROPIC_API_KEY}
        model: claude-3-sonnet
  ```
- Detail rancangan konfigurasi ada di [docs/AI.md](AI.md).

## Keamanan

### Authentication & Authorization
- **JWT-based authentication**: tokens generated dan validated oleh backend sendiri.
- JWT secret harus strong dan random (minimum 32 characters, stored in environment variable).
- Tokens include user_id di JWT subject untuk data isolation.
- Token expiration: access token (24h), refresh token (7 days) - configurable.

### Data Isolation
- Semua entitas domain memiliki `user_id` dan backend menerapkan isolasi data per user.
- Query HARUS filter by user_id yang didapat dari authenticated JWT.
- Repository layer enforce user_id filtering untuk mencegah data leakage.

### External Integration Security
- Link Telegram ke user dilakukan dengan kode sekali pakai (one-time code) dengan TTL.
- Code disimpan di tabel `integration_link_codes` dengan expiry timestamp.
- Setelah digunakan, code di-invalidate (flag `used_at`).

### Password Security
- BCrypt hashing dengan default strength (10 rounds).
- Password minimal length: 6 characters (dapat dikonfigurasi lebih ketat).
- Tidak ada plain text password disimpan di database atau logs.

## Deployment Ringkas

### Development (Local)
```bash
# Backend
cd backend
mvn spring-boot:run

# Frontend
cd frontend
npm start

# Database
docker run -p 5432:5432 -e POSTGRES_PASSWORD=postgres postgres:17-alpine
```

### Docker Compose (Recommended)
```bash
# Setup
cp .env.example .env
# Edit .env with your configuration

# Run all services
docker-compose up -d

# Access:
# - Frontend: http://localhost
# - Backend: http://localhost:8080
# - Database: localhost:5432
```

### Production
- Use strong JWT_SECRET (generate with `openssl rand -base64 48`)
- Set strong database password
- Enable HTTPS (use reverse proxy)
- Configure proper CORS origins
- Enable rate limiting
- Regular security updates
- Database backups

## Tech Stack Summary

| Component | Technology | Version |
|-----------|-----------|---------|
| Backend Language | Java | 25 |
| Backend Framework | Spring Boot | 4.0.1 |
| Frontend Framework | Angular | 21 |
| Database | PostgreSQL | 14+ |
| Authentication | BCrypt + JWT | Manual |
| AI Integration | Spring AI | Latest |
| Telegram Bot | TelegramBots | Latest |
| Containerization | Docker | Latest |
| Orchestration | Docker Compose | Latest |
| Database Migration | Flyway | Spring Boot default |

## Dokumentasi Terkait

Detail untuk setiap aspek sistem:
- [Database Schema](DATABASE.md) - Rancangan tabel dan relasi
- [Authentication System](AUTH.md) - Detail implementasi auth
- [AI Integration](AI.md) - Spring AI configuration dan usage
- [Chat Integrations](INTEGRATIONS.md) - Telegram & WhatsApp integration
- [Note Module](NOTE.md) - Advanced note system design
- [Frontend Architecture](FRONTEND.md) - Angular app structure
