# ASPRI Development Plan

> **Last Updated**: June 9, 2026

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

## Planned (Backlog)

- **WhatsApp Integration** — via WhatsApp Business API atau Twilio
- **Payment Gateway** — Midtrans/Xendit untuk subscription otomatis
- **Mobile App** — React Native wrapper atau PWA
