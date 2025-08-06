# Cara Menjalankan Backend ASPRI (Refactored Version)

## Perubahan Terbaru

Backend ASPRI telah direfactor dengan perubahan signifikan:

1. **Embedding Model**: Beralih dari OpenAI ke LangChain Google GenAI
2. **Document Storage**: Beralih dari database ke MinIO object storage
3. **Vector Storage**: Beralih dari MariaDB ke ChromaDB
4. **Configuration Management**: Sistem konfigurasi untuk file limits
5. **Sync Operations**: Update/delete dokumen sekarang sync dengan ChromaDB

## Prerequisites

### 1. MinIO Setup
MinIO diperlukan untuk penyimpanan dokumen:

```bash
# Download dan jalankan MinIO server
# Windows
curl -O https://dl.min.io/server/minio/release/windows-amd64/minio.exe
./minio.exe server ./minio-data --console-address ":9001"

# Linux/macOS
wget https://dl.min.io/server/minio/release/linux-amd64/minio
chmod +x minio
./minio server ./minio-data --console-address ":9001"

# Atau menggunakan Docker
docker run -p 9000:9000 -p 9001:9001 \
  -e "MINIO_ROOT_USER=minioadmin" \
  -e "MINIO_ROOT_PASSWORD=minioadmin" \
  -v ./minio-data:/data \
  minio/minio server /data --console-address ":9001"
```

### 2. Google AI API Key
Dapatkan API key dari Google AI Studio:
1. Buka [Google AI Studio](https://aistudio.google.com/)
2. Buat project baru atau pilih yang sudah ada
3. Generate API key
4. Simpan API key untuk konfigurasi environment
### 3. ChromaDB Setup
ChromaDB server harus berjalan terpisah:

```bash
# Install ChromaDB server
pip install chromadb

# Jalankan ChromaDB server
chroma run --host localhost --port 8000

# Atau menggunakan Docker
docker run -p 8000:8000 chromadb/chroma:latest
```

## Setup Instructions

### 1. Environment Setup

```bash
cd backend
```

Copy template environment variables:
```bash
cp .env.template .env
```

Edit file `.env` dengan konfigurasi Anda:
```env
# Database Configuration
DATABASE_URL=mysql+aiomysql://aspri_user:aspri_password@localhost:3306/aspri_db

# JWT Configuration
SECRET_KEY=your-super-secret-jwt-key-here
ALGORITHM=HS256
ACCESS_TOKEN_EXPIRE_MINUTES=30

# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret

# Google AI Configuration (untuk LangChain GenAI embeddings)
GOOGLE_API_KEY=your-google-ai-api-key-here

# MinIO Configuration
MINIO_ENDPOINT=localhost:9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_SECURE=false
MINIO_BUCKET_NAME=documents

# ChromaDB Configuration
CHROMADB_HOST=localhost
CHROMADB_PORT=8000
CHROMADB_COLLECTION_NAME=document_embeddings
```

### 2. Install Dependencies

```bash
# Buat virtual environment
python -m venv venv

# Aktifkan virtual environment
# Windows
venv\Scripts\activate
# macOS/Linux
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

### 3. Database Migration

Jalankan migrasi database untuk menerapkan perubahan model:

```bash
# Jalankan migrasi
alembic upgrade head

# Initialize default configurations
python scripts/init_config.py
```

### 4. Start Services

Pastikan semua layanan eksternal berjalan:

1. **Database** (MariaDB/MySQL)
2. **MinIO Server** (port 9000)
3. **ChromaDB Server** (port 8000)

### 5. Run Backend

```bash
uvicorn app.main:app --host 0.0.0.0 --port 8888 --reload
```

## API Endpoints Baru

### Configuration Management

- `GET /config/limits` - Mendapatkan batas file dan dokumen
- `GET /config/{config_key}` - Mendapatkan nilai konfigurasi
- `PUT /config/{config_key}` - Update nilai konfigurasi

### Document API (Updated)

Semua endpoint dokumen tetap sama, tetapi sekarang:
- Dokumen disimpan di MinIO (bukan database)
- Embeddings disimpan di ChromaDB (bukan MariaDB)
- Ada pengecekan batas file size dan jumlah dokumen
- Update/delete dokumen otomatis sync dengan ChromaDB

## Configuration Defaults

Konfigurasi default yang diatur:

- `max_file_size_bytes`: 52,428,800 (50MB)
- `max_documents_per_user`: 100
- `minio_bucket_name`: "documents"
- `chromadb_collection_name`: "document_embeddings"

## Troubleshooting

### MinIO Connection Issues
```bash
# Periksa apakah MinIO berjalan
curl http://localhost:9000/minio/health/live

# Periksa bucket
curl -X GET http://localhost:9000/documents \
  -H "Authorization: AWS4-HMAC-SHA256 ..."
```

### ChromaDB Issues
```bash
# Pastikan ChromaDB server berjalan
curl http://localhost:8000/api/v1/heartbeat

# Periksa collections
curl http://localhost:8000/api/v1/collections

# Restart ChromaDB server jika ada masalah
chroma run --host localhost --port 8000
```

### Google AI API Issues
```bash
# Test API key
curl -X POST "https://generativelanguage.googleapis.com/v1beta/models/embedding-001:embedContent?key=YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"model": "models/embedding-001", "content": {"parts": [{"text": "test"}]}}'
```

### Migration Issues
```bash
# Jika ada masalah dengan migrasi, reset database
alembic downgrade base
alembic upgrade head
python scripts/init_config.py
```

## Docker Compose (Updated)

**Note**: Docker Compose configuration has been moved to the project root directory (`aspri/docker-compose.yml`) for full-stack orchestration including frontend.

To run all services with Docker from the project root:

```bash
cd aspri
docker-compose up --build
```

This will start:
- **Frontend** (React/Vite) on port 3000
- **Backend** (FastAPI) on port 8888
- **MariaDB** on port 3306
- **MinIO** on ports 9000 (API) and 9001 (Console)
- **ChromaDB** on port 8000

The updated `docker-compose.yml` includes:

```yaml
version: '3.8'
services:
  db:
    image: mariadb:11.8
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: aspri_db
      MYSQL_USER: aspri_user
      MYSQL_PASSWORD: aspri_password
    ports:
      - "3306:3306"
    volumes:
      - mariadb_data:/var/lib/mysql
    networks:
      - aspri-network

  chromadb:
    image: chromadb/chroma:latest
    ports:
      - "8000:8000"
    environment:
      CHROMA_SERVER_HOST: 0.0.0.0
      CHROMA_SERVER_PORT: 8000
    volumes:
      - chromadb_data:/chroma/chroma
    networks:
      - aspri-network

  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin
    ports:
      - "9000:9000"
      - "9001:9001"
    volumes:
      - minio_data:/data
    networks:
      - aspri-network

  backend:
    build: 
      context: ./backend
      dockerfile: Dockerfile
    ports:
      - "8888:8888"
    environment:
      DATABASE_URL: mysql+aiomysql://aspri_user:aspri_password@db:3306/aspri_db
      MINIO_ENDPOINT: minio:9000
      CHROMADB_HOST: chromadb
      CHROMADB_PORT: 8000
    depends_on:
      - db
      - minio
      - chromadb
    volumes:
      - ./backend:/app
    networks:
      - aspri-network

  frontend:
    build: 
      context: ./frontend
      dockerfile: Dockerfile
    ports:
      - "3000:80"
    depends_on:
      - backend
    networks:
      - aspri-network

volumes:
  mariadb_data:
  minio_data:
  chromadb_data:

networks:
  aspri-network:
    driver: bridge
```

## Testing

Setelah setup selesai, test API:

```bash
# Health check
curl http://localhost:8888/health

# Get document limits
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  http://localhost:8888/config/limits

# Upload document (akan disimpan ke MinIO dan embeddings ke ChromaDB)
curl -X POST http://localhost:8888/documents/upload \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@test_document.pdf"
```