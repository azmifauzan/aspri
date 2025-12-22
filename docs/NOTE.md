# Rancangan Fitur Note (Advanced)

Tujuan modul Note adalah menggantikan modul dokumen lama: seluruh konten disimpan di database (tanpa MinIO), namun tetap “advance” agar user bisa menyimpan note dalam berbagai bentuk.

## Konsep Inti

- Note disusun sebagai **blok** (block-based note) agar fleksibel.
- Tiap note memiliki metadata (judul, status, pin, tag) dan konten (blok-blok berurutan).
- Mendukung **versi** (history) agar user bisa rollback.
- Mendukung **pencarian** (minimal: judul + konten teks).

## Jenis Konten yang Didukung

Blok disimpan pada tabel `note_blocks` (lihat `docs/DATABASE.md`). `block_type` + `data` (JSON) menentukan bentuknya.

### A) Text / Markdown
- `block_type`: `text` atau `markdown`
- `data` contoh:

```json
{ "text": "Meeting jam 10", "format": "plain" }
```

atau

```json
{ "markdown": "## Judul\n- poin 1\n- poin 2" }
```

### B) Checklist / Todo
- `block_type`: `checklist`
- `data`:

```json
{
  "items": [
    { "id": "a", "text": "Bayar listrik", "done": false },
    { "id": "b", "text": "Isi bensin", "done": true }
  ]
}
```

### C) Table sederhana
- `block_type`: `table`
- `data`:

```json
{
  "columns": ["Item", "Qty", "Harga"],
  "rows": [
    ["Kopi", 2, 30000],
    ["Roti", 1, 15000]
  ]
}
```

### D) Link / Bookmark
- `block_type`: `link`
- `data`:

```json
{ "url": "https://example.com", "title": "Referensi", "note": "Baca nanti" }
```

### E) Code snippet
- `block_type`: `code`
- `data`:

```json
{ "language": "sql", "code": "select now();" }
```

### F) Attachment (opsi dengan batasan)
Karena requirement menyebut “semua disimpan di DB”, attachment dapat disimpan sebagai `bytea` di JSON (base64) atau dipisah ke tabel khusus.

Rekomendasi untuk menjaga DB tetap sehat:
- batasi ukuran attachment (mis. 1–5 MB per file)
- utamakan attachment ringan: gambar kecil, PDF kecil, voice note pendek

Skema sederhana (opsional):
- `block_type`: `file`
- `data`:

```json
{
  "filename": "invoice.pdf",
  "mimeType": "application/pdf",
  "size": 12345,
  "contentBase64": "..."
}
```

Jika nanti kebijakan berubah, attachment dapat dipindahkan ke Supabase Storage tanpa mengubah UX besar-besaran.

## Fitur Advance yang Disarankan

### 1) Tagging
- User bisa membuat tag (mis. `kesehatan`, `kerja`, `finance`).
- Note bisa memiliki banyak tag.

### 2) Backlink antar note
- Menautkan note ke note lain (mis. note “Rencana 2026” menaut ke “Budget 2026”).
- Disimpan di `note_links`.

### 3) Versioning / History
- Setiap perubahan penting menyimpan snapshot (JSON) pada `note_versions`.
- Mendukung rollback.

### 4) Full-text search (minimal viable)
- Cari berdasarkan `notes.title` dan isi text/markdown dari `note_blocks`.
- Implementasi awal bisa dilakukan di backend:
  - query note title + filter block text
  - berikutnya bisa ditingkatkan dengan `tsvector` + GIN index.

### 5) Pin & Archive
- `pinned` untuk note penting.
- `archived` untuk merapikan tanpa menghapus permanen.

## Integrasi Note dengan Chat

Contoh intent dari chat:
- “Catat: ide bisnis baru …” → membuat note baru
- “Tambahkan checklist belanja … ke note Belanja” → menambah blok checklist

Ini membuat chat menjadi cara input tercepat tanpa perlu membuka UI editor penuh.
