# News Headlines Plugin

## Deskripsi

Plugin untuk mendapatkan berita terkini dari berbagai sumber terpercaya menggunakan NewsAPI. Dapatkan update berita sesuai kategori minat Anda.

## Fitur

- ✅ Berita dari 70,000+ sumber
- ✅ Filter per kategori
- ✅ Ringkasan pagi & sore
- ✅ Multi-negara support
- ✅ Search berita tertentu
- ✅ Link ke artikel lengkap

## API Yang Digunakan

**NewsAPI**
- URL: https://newsapi.org/
- **Gratis**: 100 requests/hari (perlu registrasi)
- **Developer**: 1,000 requests/hari ($449/bulan)
- 70,000+ news sources worldwide

## Cara Setup

### 1. Dapatkan API Key (GRATIS)

1. Buka https://newsapi.org/register
2. Daftar dengan email
3. Konfirmasi email
4. Copy API key dari dashboard
5. Paste ke konfigurasi plugin

### 2. Aktivasi

1. Aktifkan plugin dari dashboard
2. Masukkan API key
3. Pilih negara (default: Indonesia)
4. Pilih kategori berita yang diminati
5. Atur jadwal pengiriman

## Cara Penggunaan

### Perintah Chat

```
"Berita hari ini"
"Berita teknologi terbaru"
"Cari berita tentang AI"
"Berita bisnis Indonesia"
"Headline terkini"
```

### Konfigurasi

- **API Key**: Wajib dari newsapi.org (gratis)
- **Negara**: Indonesia, US, UK, dll
- **Kategori**: 
  - Umum
  - Bisnis
  - Teknologi
  - Hiburan
  - Olahraga
  - Sains
  - Kesehatan
- **Ringkasan Pagi**: Update berita pagi
- **Ringkasan Sore**: Update berita sore
- **Jumlah Berita**: 3-10 headline per update

## Contoh Response

```
🌅 Berita Pagi Hari Ini

1. Indonesia Luncurkan Satelit Baru
📰 CNN Indonesia
🔗 https://cnnindonesia.com/...

2. Breakthrough AI Terbaru dari OpenAI
📰 Detik
🔗 https://detik.com/...

3. Harga Emas Naik 5% Hari Ini
📰 Kompas
🔗 https://kompas.com/...

_Powered by NewsAPI_
```

## Kategori Tersedia

| Kategori | Deskripsi |
|----------|-----------|
| general | Berita umum |
| business | Bisnis & ekonomi |
| technology | Teknologi & gadget |
| entertainment | Hiburan & selebriti |
| sports | Olahraga |
| science | Sains & penelitian |
| health | Kesehatan |

## Negara Didukung

- 🇮🇩 Indonesia
- 🇺🇸 United States
- 🇬🇧 United Kingdom
- 🇦🇺 Australia
- 🇨🇦 Canada
- 🇲🇾 Malaysia
- 🇸🇬 Singapore
- Dan 50+ negara lainnya

## Limitasi Free Tier

- ✅ 100 requests per hari
- ✅ Berita hingga 1 bulan ke belakang
- ✅ Akses ke semua endpoints
- ❌ Tidak untuk produksi komersial
- ❌ Rate limit: 1 request/detik

## Tips Menghemat Requests

1. Aktifkan hanya 1-2 ringkasan per hari
2. Pilih kategori favorit saja (max 3)
3. Set jumlah headline 3-5 saja
4. Gunakan cache untuk query berulang

## Testing

```bash
php artisan test --filter=NewsHeadlinesTest
```

## Troubleshooting

### Error: "API key invalid"
- Pastikan API key sudah dikonfigurasi
- Cek apakah key sudah diaktifkan (cek email konfirmasi)
- Login ke newsapi.org dan generate key baru

### Error: "Rate limit exceeded"
- Free tier: 100 requests/hari
- Tunggu hingga reset (midnight UTC)
- Kurangi frekuensi update
- Upgrade ke paid tier jika perlu

### Berita Tidak Relevan
- Gunakan kategori yang lebih spesifik
- Coba search dengan keyword tertentu
- Filter berdasarkan negara/bahasa

### Sumber Berita Indonesia Sedikit
- NewsAPI memiliki sumber terbatas untuk Indonesia
- Set country = "id" untuk sumber lokal
- Gunakan search untuk topik spesifik

## Upgrade ke Paid

Jika 100 requests/hari tidak cukup:

- **Business**: $449/bulan (1000 req/hari)
- **Enterprise**: Custom pricing
- Info: https://newsapi.org/pricing

## Changelog

### v1.0.0 (2026-02-05)
- Initial release
- Top headlines support
- Category filtering
- Search functionality
- Morning & evening briefs
- Multi-country support
