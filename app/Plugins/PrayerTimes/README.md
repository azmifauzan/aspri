# Prayer Times Plugin

## Deskripsi

Plugin untuk mendapatkan jadwal waktu solat yang akurat berdasarkan lokasi pengguna. Menggunakan AlAdhan API dengan berbagai metode perhitungan.

## Fitur

- ✅ Jadwal solat 5 waktu
- ✅ Waktu terbit matahari
- ✅ Pengingat sebelum waktu solat
- ✅ Notifikasi saat adzan
- ✅ Jadwal harian otomatis
- ✅ Tanggal Hijriyah
- ✅ Multiple calculation methods

## API Yang Digunakan

**AlAdhan (Islamic Prayer Times API)**
- URL: https://aladhan.com/
- Gratis 100% tanpa API key
- Unlimited requests
- Data akurat dengan berbagai metode perhitungan

## Cara Penggunaan

### Aktivasi

1. Aktifkan plugin dari dashboard
2. Set lokasi dan koordinat
3. Pilih metode perhitungan (default: Kemenag Indonesia)
4. Aktifkan pengingat jika diinginkan

### Perintah Chat

```
"Jadwal solat hari ini"
"Jam berapa Maghrib?"
"Kapan waktu Dzuhur?"
"Solat berikutnya jam berapa?"
```

### Konfigurasi

- **Lokasi**: Nama kota/daerah
- **Koordinat**: Latitude & Longitude
- **Metode Perhitungan**: 
  - Kementerian Agama Indonesia (default)
  - Muslim World League
  - Umm Al-Qura Makkah
  - Islamic Society of North America
  - dll.
- **Pengingat**: 5/10/15/30 menit sebelum waktu solat
- **Notifikasi Adzan**: Alert tepat saat waktu masuk
- **Jadwal Harian**: Kirim jadwal setiap pagi

## Metode Perhitungan

| Metode | Keterangan |
|--------|------------|
| Kemenag Indonesia | Khusus Indonesia (Recommended) |
| Muslim World League | Umum digunakan secara global |
| Umm Al-Qura | Digunakan di Arab Saudi |
| ISNA | Amerika Utara |
| Egyptian Authority | Mesir dan Afrika |

## Contoh Response

```
🕌 Jadwal Solat Hari Ini

📍 Jakarta, Indonesia
📅 05 Feb 2026
📆 7 Sya'ban 1447 H

🌅 Subuh: 04:32
🌄 Terbit: 05:52
☀️ Dzuhur: 12:04
🌤️ Ashar: 15:22
🌆 Maghrib: 18:06
🌙 Isya: 19:16

⏰ Solat berikutnya: Dzuhur (12:04)
```

## Mendapatkan Koordinat Akurat

1. Buka Google Maps
2. Cari lokasi Anda
3. Klik kanan → koordinat akan muncul
4. Copy koordinat ke plugin config

## Testing

```bash
php artisan test --filter=PrayerTimesTest
```

## Troubleshooting

### Waktu Tidak Akurat
- Pastikan koordinat sudah benar
- Pilih metode perhitungan yang sesuai wilayah
- Untuk Indonesia, gunakan "Kemenag Indonesia"

### Pengingat Tidak Muncul
- Cek apakah schedule sudah aktif
- Pastikan Telegram bot terhubung
- Verifikasi timezone server

### Perbedaan dengan Jadwal Lokal
- Setiap metode perhitungan punya toleransi berbeda
- Gunakan metode yang sesuai dengan masjid lokal
- Perbedaan 1-2 menit adalah normal

## Changelog

### v1.0.0 (2026-02-05)
- Initial release
- 5 daily prayers + sunrise
- Multiple calculation methods
- Hijri calendar support
- Reminder system
- Daily schedule notifications
