# Random Facts Plugin

## Deskripsi

Plugin untuk mendapatkan fakta menarik dan unik setiap hari. Perluas wawasan dengan pengetahuan baru yang menghibur dan edukatif dari berbagai topik!

## Fitur

- ✅ Ribuan fakta menarik
- ✅ Fakta harian otomatis
- ✅ Multiple facts per request
- ✅ Smart emoji categorization
- ✅ Fun & educational balance
- ✅ Animal facts support
- ✅ Berbagai kategori topik

## API Yang Digunakan

**API Ninjas**
- URL: https://api-ninjas.com/
- **Gratis**: 50,000 requests/bulan (perlu registrasi)
- Multiple endpoints (facts, animals, trivia, dll)
- Fast & reliable

## Cara Setup

### 1. Dapatkan API Key (GRATIS)

1. Buka https://api-ninjas.com/register
2. Daftar dengan email
3. Verifikasi email
4. Login → Profile → API Key
5. Copy API key
6. Paste ke konfigurasi plugin

### 2. Aktivasi

1. Aktifkan plugin dari dashboard
2. Masukkan API key
3. Atur jumlah fakta per hari
4. Set waktu pengiriman

## Cara Penggunaan

### Perintah Chat

```
"Fakta menarik dong"
"Kasih tau fakta acak"
"Fun fact hari ini"
"Tahukah kamu?"
"Fakta hewan"
"3 fakta unik"
```

### Konfigurasi

- **API Key**: Wajib dari api-ninjas.com (gratis)
- **Fakta Harian**: Kirim otomatis setiap hari
- **Waktu Pengiriman**: Kapan fakta dikirim
- **Jumlah Fakta**: 1-5 fakta per pengiriman
- **Mode Fun Facts**: Prioritas fakta menghibur
- **Fokus Edukatif**: Sertakan fakta mendidik

## Contoh Response

```
🧠 Fakta Menarik Hari Ini

1. 🌍 The Earth's core is as hot as the surface of the Sun, reaching temperatures of about 5,700°C (10,300°F).

2. 🐙 Octopuses have three hearts - two pump blood to the gills, while the third pumps it to the rest of the body.

3. 🧠 The human brain uses 20% of the body's energy but only makes up 2% of its mass.

💡 Tahukah Anda?
_Powered by API Ninjas_
```

## Kategori Fakta

Plugin ini mengambil fakta dari berbagai topik:

- 🌍 Alam & Bumi
- 🚀 Luar Angkasa
- 🐾 Hewan
- 👤 Tubuh Manusia
- 🔬 Sains
- 📜 Sejarah
- 🌎 Geografi
- 💻 Teknologi
- 🎨 Seni & Budaya
- Dan banyak lagi!

## Smart Emoji

Plugin secara otomatis menambahkan emoji yang relevan berdasarkan konten fakta:

- 🚀 Space & astronomy
- 🌊 Ocean & water
- 🐾 Animals
- 🧠 Brain & psychology
- ⚡ Speed & energy
- 💎 Precious materials
- 📚 Books & knowledge
- Dan 30+ emoji lainnya

## Fitur Tambahan

### Animal Facts

```
"Fakta hewan"
"Info tentang hewan"
```

Response:
```
🐾 Fakta Hewan

African Elephant

📋 Tipe: Mammal
🏞️ Habitat: Savanna
⏳ Umur: 60-70 years
```

## Limitasi Free Tier

- ✅ 50,000 requests/bulan
- ✅ Akses semua endpoints
- ✅ No credit card required
- ✅ Commercial use allowed

## Tips Menghemat Requests

1. Set fakta harian 1x per hari saja
2. Jumlah fakta 2-3 sudah cukup
3. Tidak perlu request saat user inactive
4. Cache facts yang sudah diambil

## Testing

```bash
php artisan test --filter=RandomFactsTest
```

## Troubleshooting

### Error: "API key invalid"
- Pastikan API key sudah benar
- Login ke api-ninjas.com dan verify key
- Generate key baru jika perlu

### Error: "Rate limit exceeded"
- Free tier: 50,000 requests/bulan
- Cek usage di dashboard API Ninjas
- Tunggu hingga bulan berikutnya
- Atau upgrade ke paid tier

### Fakta Terlalu Panjang
- Beberapa fakta memang detail
- Tidak ada filter panjang di API
- Bisa ditambahkan truncation di plugin

### Fakta Kurang Menarik
- API mengirim fakta random
- Tidak ada filter quality di API
- Request multiple facts lalu pilih yang menarik

## API Endpoints Lain

API Ninjas menyediakan banyak endpoints menarik:

- `/v1/facts` - Random facts
- `/v1/animals` - Animal info
- `/v1/trivia` - Trivia questions
- `/v1/jokes` - Random jokes
- `/v1/quotes` - Famous quotes
- `/v1/riddles` - Brain teasers
- Dan 50+ endpoints lainnya

## Extend Plugin

Anda bisa menambahkan endpoint lain:

```php
public function getJoke(int $userId): array
{
    $response = Http::withHeaders([
        'X-Api-Key' => $config['api_key'],
    ])->get('https://api.api-ninjas.com/v1/jokes');
    
    // ... process response
}
```

## Upgrade ke Paid

Jika 50,000 requests/bulan tidak cukup:

- **Starter**: $25/bulan (500K requests)
- **Pro**: $100/bulan (2M requests)  
- **Business**: Custom pricing

## Changelog

### v1.0.0 (2026-02-05)
- Initial release
- Random facts support
- Daily fact delivery
- Smart emoji categorization
- Animal facts support
- Configurable fact count
- Fun & educational modes
