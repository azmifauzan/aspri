# Weather Forecast Plugin

## Deskripsi

Plugin untuk mendapatkan prakiraan cuaca akurat dari Open-Meteo API. Mendukung prakiraan hingga 7 hari ke depan dengan detail lengkap.

## Fitur

- ✅ Cuaca realtime
- ✅ Prakiraan 7 hari
- ✅ Prakiraan per jam
- ✅ Alert hujan otomatis
- ✅ Update pagi hari
- ✅ Lokasi custom

## API Yang Digunakan

**Open-Meteo**
- URL: https://open-meteo.com/
- Gratis 100% tanpa API key
- Unlimited requests (fair use)
- Data akurat dari berbagai sumber meteorologi

## Cara Penggunaan

### Aktivasi

1. Aktifkan plugin dari dashboard
2. Set lokasi default (nama kota + koordinat)
3. Aktifkan notifikasi pagi jika diinginkan

### Perintah Chat

```
"Cuaca hari ini?"
"Bagaimana cuaca besok?"
"Apakah akan hujan?"
"Prakiraan cuaca minggu ini"
"Suhu sekarang berapa?"
```

### Konfigurasi

- **Lokasi Default**: Kota dan negara
- **Koordinat**: Latitude & Longitude untuk akurasi
- **Prakiraan Pagi**: Kirim update otomatis setiap pagi
- **Waktu Pengiriman**: Jam pengiriman prakiraan
- **Alert Hujan**: Notifikasi jika ada kemungkinan hujan
- **Satuan**: Celsius/Fahrenheit, Km/h/m/s/mph

## Contoh Response

```
🌤️ Cuaca di Jakarta, Indonesia

🌡️ 28°C (terasa 31°C)
☁️ Berawan Sebagian
💧 Kelembaban: 75%
💨 Angin: 12 km/h

Hari Ini
📊 25°C - 32°C
🌧️ Kemungkinan hujan: 60%

☔ Jangan lupa bawa payung!

_Data dari Open-Meteo_
```

## Mendapatkan Koordinat

Untuk akurasi lokasi, gunakan:
- Google Maps: Klik kanan → koordinat muncul
- Latitude.to: Cari nama kota
- OpenStreetMap: Zoom ke lokasi

## Testing

```bash
php artisan test --filter=WeatherForecastTest
```

## Troubleshooting

### Data Tidak Akurat
- Pastikan koordinat benar
- Open-Meteo menggunakan data dari berbagai sumber
- Refresh setelah beberapa menit

### Notifikasi Tidak Terkirim
- Cek pengaturan Telegram
- Pastikan bot sudah terhubung
- Cek schedule di admin panel

## Changelog

### v1.0.0 (2026-02-05)
- Initial release
- Real-time weather data
- 7-day forecast
- Rain alerts
- Morning notifications
