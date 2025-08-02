# Cara Menjalankan Backend ASPRI

## Masalah yang Diketahui

Saat ini ada beberapa masalah dengan environment pengembangan:

1. Environment conda `aspri-backend` tidak dapat diaktifkan dengan benar
2. Python tidak terinstal dengan benar di sistem
3. Perintah Docker Compose dibatasi

## Solusi yang Disarankan

### 1. Menggunakan Docker (Direkomendasikan)

Jika Docker Desktop berjalan dengan benar, Anda dapat menjalankan backend dengan perintah berikut:

```bash
cd backend
docker-compose up -d
```

Perintah ini akan menjalankan dua layanan:
- Database MariaDB
- Backend FastAPI

### 2. Menggunakan Virtual Environment Python Standar

Jika Anda ingin menjalankan backend secara langsung dengan Python:

1. Pastikan Python 3.11+ terinstal dengan benar
2. Buat virtual environment:
   ```bash
   cd backend
   python -m venv venv
   # Windows
   venv\Scripts\activate
   # macOS/Linux
   source venv/bin/activate
   ```

3. Instal dependensi:
   ```bash
   pip install -r requirements.txt
   ```

4. Pastikan file `.env` sudah dibuat dengan konfigurasi yang benar (lihat `.env.example`)

5. Jalankan migrasi database:
   ```bash
   alembic upgrade head
   ```

6. Jalankan server:
   ```bash
   uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
   ```

### 3. Konfigurasi Environment Variables

Pastikan file `backend/.env` berisi konfigurasi berikut:

```env
# Database Configuration
DATABASE_URL=mysql+aiomysql://aspri_user:aspri_password@localhost:3306/aspri_db

# JWT Configuration
SECRET_KEY=your-super-secret-jwt-key-here-change-in-production
ACCESS_TOKEN_EXPIRE_MINUTES=30

# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret

# Application Configuration
DEBUG=True
ENVIRONMENT=development
```

### 4. Troubleshooting

#### Masalah dengan Conda Environment
Jika Anda mengalami masalah dengan conda environment:
1. Pastikan conda sudah diinisialisasi dengan benar:
   ```bash
   conda init
   ```
2. Restart terminal Anda
3. Aktifkan environment:
   ```bash
   conda activate aspri-backend
   ```

#### Masalah dengan Python
Jika Python tidak ditemukan:
1. Pastikan Python terinstal dari python.org atau Microsoft Store
2. Tambahkan Python ke PATH environment variable
3. Restart terminal Anda

#### Masalah dengan Docker
Jika Docker Compose tidak dapat dijalankan:
1. Pastikan Docker Desktop berjalan dengan benar
2. Periksa apakah Docker daemon berjalan
3. Restart Docker Desktop jika perlu