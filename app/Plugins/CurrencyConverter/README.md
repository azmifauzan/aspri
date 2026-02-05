# Currency Converter Plugin

## Deskripsi

Plugin untuk konversi mata uang dengan nilai tukar realtime. Mendukung 150+ mata uang dari seluruh dunia.

## Fitur

- ✅ Konversi mata uang realtime
- ✅ Update harian otomatis
- ✅ Mata uang favorit
- ✅ Notifikasi perubahan signifikan
- ✅ Mendukung 150+ mata uang

## API Yang Digunakan

**ExchangeRate-API**
- URL: https://www.exchangerate-api.com/
- Gratis: 1,500 requests/bulan
- No API key required untuk tier gratis

## Cara Penggunaan

### Aktivasi

1. Aktifkan plugin dari dashboard
2. Pilih mata uang dasar (default: IDR)
3. Pilih mata uang favorit untuk tracking

### Perintah Chat

```
"Berapa kurs USD hari ini?"
"Convert 100 USD ke IDR"
"Tukar 50000 IDR ke SGD"
"Nilai tukar Euro sekarang"
```

### Konfigurasi

- **Mata Uang Dasar**: Mata uang utama untuk referensi
- **Mata Uang Favorit**: Mata uang yang sering digunakan
- **Update Otomatis**: Kirim update harian
- **Waktu Update**: Jam pengiriman update
- **Notifikasi**: Alert saat perubahan signifikan
- **Threshold**: Persentase perubahan untuk notifikasi

## Contoh Response

```
💱 100.00 USD = 1,575,000.00 IDR

Nilai tukar: 1 USD = 15,750.0000 IDR

_Data realtime dari ExchangeRate-API_
```

## Testing

```bash
php artisan test --filter=CurrencyConverterTest
```

## Troubleshooting

### API Tidak Merespon
- Cek koneksi internet
- API gratis terbatas 1,500 requests/bulan
- Tunggu beberapa saat dan coba lagi

### Mata Uang Tidak Ditemukan
- Pastikan kode mata uang benar (contoh: USD, EUR, IDR)
- Gunakan kode ISO 4217 standar

## Changelog

### v1.0.0 (2026-02-05)
- Initial release
- Support 150+ currencies
- Daily auto-update
- Real-time conversion
