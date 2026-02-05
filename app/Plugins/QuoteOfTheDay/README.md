# Quote of the Day Plugin

## Deskripsi

Plugin untuk mendapatkan kutipan inspiratif dan motivasi setiap hari dari tokoh-tokoh terkenal. Mulai hari Anda dengan semangat positif!

## Fitur

- ✅ 75,000+ kutipan inspiratif
- ✅ 3,000+ penulis terkenal
- ✅ Filter berdasarkan tema
- ✅ Kutipan harian otomatis
- ✅ Pencarian kutipan
- ✅ Filter panjang kutipan
- ✅ Multiple languages support

## API Yang Digunakan

**Quotable**
- URL: https://quotable.io/
- Gratis 100% tanpa API key
- Unlimited requests (rate limit: 180/min)
- Open source & community-driven

## Cara Penggunaan

### Aktivasi

1. Aktifkan plugin dari dashboard
2. Pilih tema kutipan favorit
3. Atur waktu pengiriman harian
4. Aktifkan terjemahan jika diinginkan

### Perintah Chat

```
"Kasih kutipan hari ini"
"Quote motivasi dong"
"Kutipan tentang kesuksesan"
"Inspirasi hari ini"
"Quote dari Albert Einstein"
```

### Konfigurasi

- **Kutipan Harian**: Kirim otomatis setiap hari
- **Waktu Pengiriman**: Kapan kutipan dikirim
- **Panjang Kutipan**: 
  - Pendek (< 100 karakter)
  - Sedang (100-300 karakter)
  - Panjang (> 300 karakter)
  - Acak
- **Tema**: 
  - Inspirasi
  - Motivasi
  - Kebijaksanaan
  - Kesuksesan
  - Kehidupan
  - Kebahagiaan
  - Dan lainnya
- **Terjemahan**: Otomatis ke Bahasa Indonesia
- **Bio Penulis**: Info singkat tentang penulis

## Contoh Response

```
💡 Quote of the Day

"The only way to do great work is to love what you do."

— Steve Jobs

#inspirational #success #business

📖 Satu-satunya cara untuk melakukan pekerjaan hebat adalah mencintai apa yang Anda lakukan.

_From Quotable API_
```

## Tema Kutipan Tersedia

| Tema | Deskripsi |
|------|-----------|
| inspirational | Kutipan inspiratif umum |
| motivational | Motivasi & semangat |
| wisdom | Kebijaksanaan hidup |
| success | Kesuksesan & pencapaian |
| life | Filosofi kehidupan |
| happiness | Kebahagiaan |
| love | Cinta & hubungan |
| friendship | Persahabatan |
| change | Perubahan & transformasi |
| business | Bisnis & entrepreneurship |

## Penulis Terkenal

Database mencakup kutipan dari:
- Albert Einstein
- Steve Jobs
- Maya Angelou
- Winston Churchill
- Mahatma Gandhi
- Mark Twain
- Oscar Wilde
- Dan 3,000+ penulis lainnya

## Fitur Pencarian

### Cari Berdasarkan Kata Kunci
```
"Kutipan tentang waktu"
"Quote tentang kesempatan"
```

### Cari Berdasarkan Penulis
```
"Quote dari Einstein"
"Kutipan Steve Jobs"
```

## Testing

```bash
php artisan test --filter=QuoteOfTheDayTest
```

## Troubleshooting

### API Tidak Merespon
- Cek koneksi internet
- Quotable API kadang maintenance
- Coba lagi setelah beberapa menit

### Kutipan Tidak Sesuai Tema
- Pilih tema yang lebih spesifik
- Kombinasikan beberapa tema
- Gunakan fitur pencarian

### Terjemahan Tidak Akurat
- Terjemahan saat ini masih sederhana
- Untuk terjemahan lebih baik, integrasikan Google Translate API
- Sementara nikmati kutipan dalam bahasa aslinya

## Enhance Translation

Untuk terjemahan yang lebih akurat, Anda bisa:

1. Integrasi Google Cloud Translation API
2. Gunakan LibreTranslate (open source)
3. Implementasi DeepL API

## API Endpoints

Plugin ini menggunakan:
- `GET /random` - Random quote
- `GET /quotes` - List quotes
- `GET /search/quotes` - Search quotes
- `GET /authors` - List authors

## Rate Limits

- 180 requests per minute
- Unlimited requests per day
- No API key required

## Changelog

### v1.0.0 (2026-02-05)
- Initial release
- Daily quote delivery
- 75,000+ quotes database
- Search functionality
- Multiple themes support
- Author filtering
- Length filtering
- Basic translation support
