# ASPRI Development Plan

> **Last Updated**: June 9, 2026

---

## Priority 1: Google OAuth (Sign In & Register)

Tambahkan opsi login dan registrasi via Google ke halaman auth yang sudah ada.

### Phase A: Backend

| Task | Status |
|------|--------|
| Install `laravel/socialite` | ⬜ |
| Add `google_id` + `google_avatar` columns ke `users` via migration | ⬜ |
| Register Google provider di `config/services.php` | ⬜ |
| `SocialiteController` — `redirect()` + `callback()` | ⬜ |
| Callback logic: find-or-create user, auto-verify email, skip password | ⬜ |
| Handle new user flow: redirect ke profile setup jika profil belum diisi | ⬜ |
| Route: `GET /auth/google` → redirect, `GET /auth/google/callback` → callback | ⬜ |

### Phase B: Frontend

| Task | Status |
|------|--------|
| Tombol "Lanjutkan dengan Google" di `auth/Login.vue` | ⬜ |
| Tombol "Daftar dengan Google" di `auth/Register.vue` | ⬜ |
| Styling konsisten dengan Reka UI + Tailwind 4 | ⬜ |
| Handle redirect error dari callback (flash error di session) | ⬜ |

### Phase C: Tests & Polish

| Task | Status |
|------|--------|
| Feature test: user baru via Google → profil setup redirect | ⬜ |
| Feature test: user existing via Google → login & redirect ke dashboard | ⬜ |
| Feature test: email yang sudah ada (non-Google) → error graceful | ⬜ |
| Guard: user dengan Google login tidak bisa reset password via email | ⬜ |

### Detail teknis

**Migration tambahan:**
```php
$table->string('google_id')->nullable()->unique();
$table->string('google_avatar')->nullable();
```

**Callback flow:**
1. Cek `google_id` → jika ada, login langsung
2. Cek `email` → jika ada tanpa `google_id`, flash error "email sudah terdaftar, login biasa"
3. Jika tidak ada → buat user baru (`email_verified_at = now()`, `password = null`)
4. Cek profil → jika belum ada, redirect ke `/profile/setup`, else `/dashboard`

**Guard untuk password reset:**
```php
// Jika user->password === null, tolak permintaan reset password
```

---

## Planned (Backlog)

- **WhatsApp Integration** — via WhatsApp Business API atau Twilio
- **Payment Gateway** — Midtrans/Xendit untuk subscription otomatis
- **Mobile App** — React Native wrapper atau PWA
