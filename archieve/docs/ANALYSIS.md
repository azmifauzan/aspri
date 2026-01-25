
# Analisa Kebutuhan (ASPRI Next Gen)

## Ringkasan

ASPRI Next Gen adalah aplikasi asisten pribadi untuk pengelolaan **jadwal** dan **keuangan**, dengan akses melalui **web app** dan integrasi chat (fokus awal Telegram). Versi baru tidak lagi bergantung pada Google API untuk autentikasi maupun kalender.

Asisten cerdas menggunakan **LLM** dan implementasinya harus **provider-agnostic** menggunakan **Spring AI**, sehingga provider dapat diganti lewat konfigurasi backend tanpa mengubah kode domain.

Selain itu, **persona ASPRI dari versi lama dipertahankan**:
- Konsep **chat-first**: semua fitur dapat diakses lewat percakapan (UI tetap tersedia sebagai alternatif).
- Asisten menggunakan `aspri_name` dan `aspri_persona` milik user.
- Asisten memanggil user sesuai `call_preference` dan konsisten.
- Asisten mengikuti bahasa user (Indonesia/Inggris).
- Untuk aksi yang mengubah data (buat/ubah/hapus), asisten meminta **konfirmasi** sebelum eksekusi.

## Stakeholder & Persona

- **User Personal**: individu yang ingin mencatat jadwal, pengeluaran, dan catatan harian.
- **Admin/Operator** (opsional): pengelola deployment/konfigurasi.

## Kebutuhan Fungsional

### 1) Autentikasi & Akun

- Registrasi dan login tanpa Google API.
- Manajemen profil dasar: nama tampilan, timezone, preferensi.
- Mekanisme link akun chat (Telegram) ke akun ASPRI.

### 2) Dashboard

- Menampilkan statistik & chart terkait:
	- jadwal (jumlah event hari ini/minggu ini, upcoming)
	- keuangan (total pemasukan/pengeluaran, breakdown kategori, tren)

Catatan UI:
- Tampilan dashboard mengacu pada referensi TailAdmin.
- Mendukung light/dark theme dan 2 bahasa (ID/EN).

### 3) Chat

- Chat di web: melihat dan mengirim pesan.
- Chat Telegram: menerima dan membalas pesan.
- Penyimpanan riwayat percakapan.
- Mencatat aksi yang dihasilkan dari chat (mis. “buat event”, “catat transaksi”, “buat note”).
- Mendukung pemrosesan berbasis LLM (via Spring AI) untuk intent extraction dan respons.
- Untuk intent yang menimbulkan side-effect, sistem wajib menjalankan alur confirm/cancel.

### 4) Note (pengganti dokumen)

- Penyimpanan note di database (tanpa MinIO).
- Note mendukung berbagai bentuk konten (lihat `docs/NOTE.md`).
- Pencarian note (minimal: pencarian judul + konten).

### 5) Jadwal

- CRUD kalender & event.
- Reminder/pengingat (minimal: satu reminder per event).

### 6) Keuangan

- CRUD akun, kategori, transaksi.
- Laporan ringkas untuk kebutuhan dashboard.

## Kebutuhan Non-Fungsional

- **Keamanan**: isolasi data per user, token JWT diverifikasi.
- **Reliabilitas**: integrasi Telegram harus tahan terhadap retry/duplicate update.
- **Kinerja**: dashboard menampilkan agregasi cepat (gunakan index / view bila perlu).
- **Auditability**: minimal logging untuk aksi penting (login, link telegram, create transaksi/event).
- **Portabilitas AI**: integrasi LLM tidak boleh mengunci pada satu provider; gunakan Spring AI dan konfigurasi untuk memilih provider/model.
- **Kontrol Biaya AI**: batasi panjang prompt/response, rate limiting, serta logging yang aman (hindari menyimpan prompt yang berisi data sensitif bila tidak diperlukan).
- **Konsistensi Persona**: semua respons (web/telegram) harus tetap selaras dengan `aspri_persona` dan preferensi panggilan user.
- **I18N Konsisten**: aplikasi mendukung 2 bahasa (ID/EN) dan respons asisten harus mengikuti bahasa user.
- **Theme Konsisten**: aplikasi mendukung 2 tema (light/dark) di seluruh halaman setelah login.

## MVP yang Disarankan

MVP fokus ke end-to-end yang utuh:

1. Auth (Supabase Auth): sign-up/sign-in + profil.
2. Linking Telegram:
	 - generate kode link di web
	 - `/link <kode>` di Telegram
3. Modul Keuangan (transaksi sederhana) + dashboard ringkas.
4. Modul Jadwal (event sederhana) + reminder sederhana.
5. Modul Note (blok dasar: teks/markdown/checklist/link).
6. Chat: simpan percakapan + intent parser minimal (rule-based) untuk membuat transaksi/event/note.

Catatan MVP AI:
- Mulai dengan *hybrid*: rule-based untuk perintah yang tegas, dan LLM untuk variasi natural language.
- Semua pemanggilan AI lewat Spring AI (provider dipilih via konfigurasi).
- Terapkan confirm/cancel untuk create/update/delete agar aman.

## Risiko & Mitigasi

- **RLS vs JDBC**: Supabase RLS lebih natural untuk akses via PostgREST, namun backend memakai JDBC.
	- Mitigasi: backend wajib enforce `user_id` pada semua query; RLS dapat dipakai sebagai lapisan tambahan bila pola akses langsung dari client ditambahkan.

- **Penyimpanan file di DB**: menyimpan attachment besar di Postgres dapat membebani storage dan backup.
	- Mitigasi: batasi ukuran attachment; prefer simpan metadata + content kecil (atau gunakan Supabase Storage bila kebijakan berubah).

- **Telegram reliability**: duplicate message atau out-of-order update.
	- Mitigasi: simpan `update_id`/message id dan lakukan idempotency check.

- **Vendor lock-in AI**: perubahan provider memerlukan refactor besar.
	- Mitigasi: gunakan Spring AI sebagai abstraksi; isolasi prompt + parsing pada modul khusus.

- **Prompt injection / data leakage**: user bisa menyisipkan instruksi untuk mengambil data sensitif.
	- Mitigasi: enforcement `user_id` di semua query, minimal context, dan redaction log.

- **Timezone**: jadwal dan reminder sensitif timezone.
	- Mitigasi: simpan timestamp dalam UTC + timezone di profil; konversi di UI.

## Catatan Migrasi dari Versi Lama (folder `archieve/`)

- Versi lama (Python/React) digunakan sebagai referensi fitur/flow, tetapi:
	- dokumen + minio diganti menjadi note di database
	- autentikasi Google OAuth dihapus
	- struktur API dan model domain disusun ulang sesuai modul baru

## Pertanyaan Terbuka (untuk keputusan produk)

1. Provider LLM apa yang dipakai untuk MVP (mis. OpenAI/Azure/Anthropic/Ollama), dan model apa default-nya?
2. Apakah reminder perlu push via Telegram saja, atau juga email/in-app notification?
3. Apakah note membutuhkan sharing antar user (kolaborasi) atau single-user saja?
