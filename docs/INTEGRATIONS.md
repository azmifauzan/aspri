# Integrasi Chat: Telegram (MVP) & WhatsApp (Rencana)

Fokus awal integrasi adalah Telegram menggunakan library `rubenlagus/TelegramBots`. WhatsApp direncanakan setelah alur Telegram stabil.

## Tujuan Integrasi

- User dapat berinteraksi dengan ASPRI lewat chat.
- Chat dapat membuat/mengubah data modul lain (note/jadwal/keuangan).
- Reminder jadwal dapat dikirim ke Telegram.

## Telegram (MVP)

### Library

- Repo: https://github.com/rubenlagus/TelegramBots

### Mode Operasi

Ada dua mode:

1) **Long Polling (disarankan untuk MVP/dev)**
- Backend menjalankan bot dan menarik update secara periodik.
- Tidak butuh endpoint publik.

2) **Webhook (disarankan untuk produksi)**
- Telegram mengirim update ke endpoint backend.
- Membutuhkan HTTPS endpoint publik.

Untuk MVP, gunakan long polling.

### Linking Akun (Penting)

Karena tidak ada Google login, bot perlu tahu siapa user ASPRI yang sedang chat.

Rancangan linking yang sederhana dan aman:

1. User login di web.
2. User membuka menu “Integrasi Telegram” → klik “Generate Link Code”.
3. Sistem membuat kode sekali pakai (mis. 8–10 karakter) dengan TTL (mis. 10 menit) dan simpan ke tabel `integration_link_codes`.
4. User mengirim ke bot: `/link <CODE>`.
5. Backend memvalidasi code → membuat record `external_identities` untuk provider `telegram`.

Keuntungan:
- Tidak perlu menyimpan password di Telegram
- Tidak perlu Google API
- Link code mudah diputar dan dapat kedaluwarsa

### Format Perintah (MVP)

Minimal command set:

- `/start` → menampilkan bantuan singkat
- `/link <CODE>` → link akun
- `/help` → daftar contoh

Untuk natural language, backend bisa memproses:
- “catat pengeluaran 50rb makan”
- “buat jadwal besok jam 9 meeting”
- “buat note: ide …”

Catatan keamanan (dipertahankan dari versi lama):
- Untuk aksi yang mengubah data (create/update/delete), bot mengirim ringkasan dan meminta konfirmasi **ya** / **batal** sebelum eksekusi.

### Idempotency

Telegram update bisa dikirim ulang. Untuk mencegah duplikasi:
- simpan `external_message_id` (mis. gabungan `chat_id:message_id`) pada `chat_messages`
- jika sudah ada, abaikan

### Keamanan

- Jangan pernah menerima perintah sensitif tanpa user sudah linked.
- Batasi scope perintah sebelum linking (hanya `/start`, `/help`, `/link`).

## WhatsApp (Rencana)

WhatsApp memiliki model integrasi yang berbeda (bergantung provider). Untuk mengurangi rework, buat abstraksi internal:

- `ChatChannelAdapter` (interface)
  - `sendMessage(user, text)`
  - `handleIncomingMessage(payload)`

Implementasi pertama: `TelegramAdapter`.
Implementasi berikutnya: `WhatsAppAdapter`.

## Observability

Minimal logging untuk integrasi:
- link success/fail (tanpa mencetak kode)
- incoming message id
- action yang dibuat (create event/transaction/note)

