# Arsitektur AI (Spring AI)

Dokumen ini menjelaskan rancangan “asisten cerdas” ASPRI dengan **Spring AI** agar integrasi LLM **tidak spesifik ke satu provider**.

## Tujuan

- Memungkinkan pemilihan provider/model LLM via konfigurasi (tanpa ubah kode domain).
- Memisahkan *domain logic* (jadwal/keuangan/note) dari *AI orchestration*.
- Mengurangi risiko vendor lock-in dan memudahkan eksperimen.

## Prinsip Desain

- Kode aplikasi hanya bergantung pada abstraksi Spring AI:
  - `ChatModel` untuk respons chat
  - `EmbeddingModel` (opsional) untuk search/semantic features di masa depan
- Provider-specific dependency dipasang sebagai modul/starter dan dipilih lewat `application.yml` / env.
- Prompt dikelola terpusat (template + output schema) agar konsisten.

## Persona ASPRI (Dipertahankan)

Perilaku persona dari aplikasi versi lama wajib dipertahankan:

- Asisten memiliki `aspri_name` dan `aspri_persona` per user.
- Asisten memanggil user sesuai `call_preference` dan konsisten.
- Asisten mengikuti bahasa user (Bahasa Indonesia atau English).
- Asisten tidak mengulang namanya kecuali diminta.
- Jika user menyapa, asisten menjelaskan dirinya secara singkat (nama + persona).

Implementasi: saat membangun prompt, orchestrator menyuntikkan variabel persona dari tabel `profiles`.

## Komponen di Backend

- `AssistantOrchestrator`
  - menerima input (web/telegram)
  - memanggil `ChatModel`
  - melakukan parsing intent (struktur output)
  - memanggil service domain (finance/schedule/note)

- `PromptCatalog`
  - menyimpan template prompt
  - versi prompt (untuk tracking perubahan)

- `AiConfiguration`
  - menyediakan bean Spring AI berdasarkan provider yang dipilih

## Strategi Provider-Agnostic

### Opsi A (paling sederhana)

Gunakan satu `ChatModel` aktif dari konfigurasi Spring AI.

Kelebihan: mudah.
Kekurangan: switching provider biasanya butuh mengubah dependencies/build, tapi kode domain tetap aman.

### Opsi B (multi-provider via feature flag)

Definisikan interface internal mis. `AiClient` dan implementasi yang membungkus Spring AI. Pilihan provider ditentukan via config `aspri.ai.provider`.

Kelebihan: satu binary bisa mendukung beberapa provider (jika semua dependency dipasang).
Kekurangan: ukuran dependency lebih besar.

## Structured Output (disarankan)

Agar hasil LLM dapat dieksekusi aman, gunakan pola structured output:

- LLM diminta menghasilkan JSON dengan schema yang jelas, contoh:

```json
{
  "intent": "create_expense",
  "payload": {
    "amount": 50000,
    "category": "makan",
    "occurredAt": "2025-12-22T12:00:00+07:00",
    "note": "ayam geprek"
  }
}
```

Backend memvalidasi JSON (mis. Jakarta Bean Validation / JSON schema) sebelum menjalankan aksi.

## Policy Konfirmasi untuk Aksi Side-Effect

Sama seperti versi lama, untuk intent yang menimbulkan perubahan data (create/update/delete), gunakan pola:

1) LLM mengembalikan `intent` + `payload`.
2) Backend membuat ringkasan aksi yang akan dilakukan.
3) Backend meminta user untuk membalas **ya** (konfirmasi) atau **batal**.
4) Hanya setelah konfirmasi, backend mengeksekusi perubahan.

Ini berlaku konsisten untuk channel Web dan Telegram.

Catatan i18n:
- Pesan ringkasan dan instruksi konfirmasi (**ya**/**batal**) harus mengikuti bahasa user (ID/EN).

## Konfigurasi (contoh)

Nama property final bisa ditentukan saat implementasi, contoh arah:

- `ASPRI_AI_PROVIDER` = `openai|azure-openai|anthropic|ollama`
- `ASPRI_AI_MODEL` = nama model
- `ASPRI_AI_TEMPERATURE` = `0.2`

Pastikan secret (API key) hanya lewat environment/secret manager.

## Guardrails

- Batasi context yang dikirim ke model (minimal sesuai kebutuhan).
- Jangan kirim token/secret dan data sensitif yang tidak perlu.
- Terapkan rate limit per user/channel.
- Simpan log AI secara aman: redaction PII bila diperlukan.

## Roadmap

- MVP: intent extraction + reply via Spring AI, hybrid dengan rule-based.
- Next: embedding untuk pencarian note (semantik) jika dibutuhkan.
