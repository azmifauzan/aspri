# ASPRI - Setup Guide

## Prerequisites

- **Node.js 22+** & npm
- **Java 25**
- **PostgreSQL 14+** (atau gunakan Docker)
- **Maven 3.9+**
- **Docker & Docker Compose** (opsional, untuk deployment mudah)

## Authentication System

ASPRI menggunakan **autentikasi manual** dengan:
- BCrypt untuk password hashing
- JWT untuk token generation dan validation
- **TIDAK menggunakan Supabase Auth atau OAuth provider eksternal**
- Fully portable ke PostgreSQL server manapun tanpa dependency eksternal

## Setup Methods

Ada 3 cara untuk menjalankan ASPRI:

### Method 1: Docker Compose (Recommended - Paling Mudah)
### Method 2: Local Development (Manual)
### Method 3: Production Deployment

---

## Method 1: Docker Compose (Recommended)

Cara paling mudah untuk menjalankan keseluruhan stack (frontend, backend, database).

### 1. Clone & Configure

```bash
git clone <repository-url>
cd aspri

# Copy environment example
cp .env.example .env
```

### 2. Edit `.env` File

Buka file `.env` dan **WAJIB** ubah nilai berikut untuk production:

```bash
# Database
POSTGRES_PASSWORD=ubah_password_ini

# JWT (WAJIB DIGANTI!)
JWT_SECRET=buat_secret_key_minimum_32_karakter_yang_kuat_dan_unik
```

### 3. Build & Run

```bash
# Build dan jalankan semua services
docker-compose up -d

# Lihat logs
docker-compose logs -f

# Stop services
docker-compose down

# Stop dan hapus volumes (reset database)
docker-compose down -v
```

### 4. Access Application

- **Frontend**: http://localhost (port 80)
- **Backend API**: http://localhost:8080
- **PostgreSQL**: localhost:5432

### 5. Create First User

Karena tidak ada Supabase Auth, registrasi dilakukan via API endpoint:

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "fullName": "Your Name"
  }'
```

---

## Method 2: Local Development (Manual)

### Database Setup

#### Option A: PostgreSQL Local

```bash
# Install PostgreSQL 14+
# Buat database
createdb aspri

# Set environment variables
export DATABASE_URL=jdbc:postgresql://localhost:5432/aspri
export DATABASE_USERNAME=postgres
export DATABASE_PASSWORD=postgres
```

#### Option B: Docker PostgreSQL Only

```bash
docker run -d \
  --name aspri-postgres \
  -e POSTGRES_DB=aspri \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD=postgres \
  -p 5432:5432 \
  postgres:17-alpine
```

### Backend Setup

### 1. Configure Environment

Buat file `.env` di root project atau set environment variables:

```bash
# Database
DATABASE_URL=jdbc:postgresql://localhost:5432/aspri
DATABASE_USERNAME=postgres
DATABASE_PASSWORD=postgres

# JWT (WAJIB DIGANTI untuk production!)
JWT_SECRET=your-secret-key-minimum-32-characters-for-security
JWT_EXPIRATION=86400000
JWT_REFRESH_EXPIRATION=604800000

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:4200
```

Atau copy dari example:

```bash
cp .env.example .env
# Edit .env sesuai kebutuhan
```

### 2. Install Dependencies

```bash
cd backend
mvn clean install
```

### 3. Run Backend

```bash
cd backend
mvn spring-boot:run
```

Backend akan berjalan di `http://localhost:8080`

### 4. Verify Backend

Test endpoint health:

```bash
curl http://localhost:8080/api/health
```

## Frontend Setup

### 1. Update Environment Configuration

Edit file environment untuk development jika API URL berbeda:

[frontend/src/environments/environment.ts](frontend/src/environments/environment.ts)

```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8080/api'
};
```

### 2. Install Dependencies

```bash
cd frontend
npm install
```

### 3. Run Frontend

```bash
cd frontend
npm start
```

Frontend akan berjalan di `http://localhost:4200`

---

## Method 3: Production Deployment

### Using Docker Compose

Untuk production, pastikan:

1. **Ubah JWT Secret** di `.env`:
```bash
JWT_SECRET=$(openssl rand -base64 32)
```

2. **Set Strong Database Password**:
```bash
POSTGRES_PASSWORD=<strong-password>
```

3. **Update CORS Origins**:
```bash
CORS_ALLOWED_ORIGINS=https://your-domain.com
```

4. **Update Frontend API URL**:
Edit [frontend/src/environments/environment.prod.ts](frontend/src/environments/environment.prod.ts):
```typescript
export const environment = {
  production: true,
  apiUrl: 'https://api.your-domain.com/api'
};
```

5. **Deploy**:
```bash
docker-compose up -d
```

### Using Kubernetes

Lihat folder `k8s/` untuk Kubernetes manifests (coming soon).

---

## Testing the Application

### 1. Register New User

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!",
    "fullName": "Test User"
  }'
```

Response:
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "refreshToken": "eyJhbGciOiJIUzI1NiIs...",
    "user": {
      "userId": "...",
      "email": "test@example.com",
      "fullName": "Test User"
    }
  }
}
```

### 2. Login

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'
```

### 3. Access Protected Endpoints

```bash
curl -X GET http://localhost:8080/api/auth/profile \
  -H "Authorization: Bearer <your-jwt-token>"
```

### 4. Test via Frontend

1. Buka browser ke frontend URL
2. Klik "Daftar" untuk registrasi
3. Isi form registrasi (email, password, nama)
4. Setelah berhasil, otomatis login
5. Anda akan diarahkan ke dashboard

## Features Status

### Implemented ✅
- ✅ Manual authentication (BCrypt + JWT)
- ✅ User registration & login
- ✅ User profile management
- ✅ Multi-language (ID/EN) support
- ✅ Dark/Light theme
- ✅ Responsive design
- ✅ Database migrations (Flyway)
- ✅ Docker containerization

### In Development 🔄
- 🔄 Dashboard with statistics
- 🔄 Chat with AI assistant (Spring AI)
- 🔄 Note management
- 🔄 Schedule/Calendar
- 🔄 Finance tracking

### Planned 📋
- 📋 Telegram bot integration
- 📋 WhatsApp integration
- 📋 Email notifications
- 📋 Advanced reporting

## Project Structure

### Backend (Spring Boot)
```
backend/
├── src/main/java/id/my/aspri/backend/
│   ├── api/              # REST Controllers & DTOs
│   │   ├── dto/          # Request/Response DTOs
│   │   ├── AuthController.java
│   │   └── HealthController.java
│   ├── service/          # Business Logic
│   │   ├── AuthenticationService.java
│   │   └── UserProfileService.java
│   ├── domain/           # Entities/Domain Models
│   │   └── UserProfile.java
│   ├── repo/             # JPA Repositories
│   │   └── UserProfileRepository.java
│   ├── config/           # Configuration Classes
│   │   └── SecurityConfig.java
│   └── core/             # Core Utilities
│       └── security/     # JWT & Auth Filters
│           ├── JwtTokenProvider.java
│           └── JwtAuthenticationFilter.java
├── src/main/resources/
│   ├── application.yml   # Configuration
│   └── db/migration/     # Flyway Migrations
│       ├── V1__create_user_profiles.sql
│       └── V2__add_password_hash_to_user_profiles.sql
├── Dockerfile
├── .dockerignore
└── pom.xml
```

### Frontend (Angular)
```
frontend/
├── src/app/
│   ├── pages/            # Page Components
│   │   ├── landing/      # Landing page
│   │   ├── auth/         # Login, Register
│   │   └── dashboard/    # Main dashboard
│   ├── services/         # Angular Services
│   │   ├── auth.service.ts
│   │   ├── api.service.ts
│   │   └── theme.service.ts
│   ├── guards/           # Route Guards
│   │   └── auth.guard.ts
│   └── app.config.ts     # App configuration
├── src/assets/
│   └── i18n/             # Translation files
│       ├── en.json       # English
│       └── id.json       # Indonesian
├── src/environments/     # Environment configs
│   ├── environment.ts
│   └── environment.prod.ts
├── Dockerfile
├── .dockerignore
├── nginx.conf            # Nginx configuration
├── angular.json
├── package.json
└── tailwind.config.js
```

### Documentation
```
docs/
├── ARCHITECTURE.md       # System architecture
├── DATABASE.md          # Database schema design
├── AUTH.md              # Authentication details
├── AI.md                # Spring AI integration
├── INTEGRATIONS.md      # Chat integrations (Telegram/WhatsApp)
├── NOTE.md              # Note module design
└── FRONTEND.md          # Frontend architecture
```

### Docker Files
```
├── docker-compose.yml   # Docker Compose orchestration
├── .env.example         # Environment variables template
└── .env                 # Your local configuration (gitignored)
```

## Troubleshooting

### Docker Issues

**Container won't start:**
```bash
# Check logs
docker-compose logs backend
docker-compose logs frontend
docker-compose logs postgres

# Restart specific service
docker-compose restart backend

# Rebuild after code changes
docker-compose up -d --build
```

**Port already in use:**
```bash
# Change ports in .env file
BACKEND_PORT=8081
FRONTEND_PORT=8080
POSTGRES_PORT=5433
```

### Backend Issues

**Database connection failed:**
- Pastikan PostgreSQL running
- Cek DATABASE_URL, username, password di `.env` atau environment variables
- Test koneksi: `psql -h localhost -U postgres -d aspri`

**Flyway migration error:**
```bash
# Reset database (HATI-HATI: akan hapus semua data!)
docker-compose down -v
docker-compose up -d
```

**JWT validation error:**
- Pastikan JWT_SECRET sama antara yang generate token dan yang validate
- JWT_SECRET harus minimal 32 karakter
- Restart backend setelah mengubah JWT_SECRET

### Frontend Issues

**Cannot connect to backend:**
- Pastikan backend running di port yang benar
- Cek `environment.ts` - `apiUrl` harus sesuai backend
- Buka browser console untuk error CORS atau network

**CORS error:**
- Update `CORS_ALLOWED_ORIGINS` di backend configuration
- Restart backend setelah perubahan
- Untuk development: `CORS_ALLOWED_ORIGINS=http://localhost:4200`

**Build failed:**
```bash
# Clear cache dan reinstall
cd frontend
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Authentication Issues

**Registration failed:**
- Cek backend logs untuk error detail
- Pastikan email format valid
- Password minimal 6 karakter

**Login failed:**
- Pastikan user sudah terdaftar
- Cek password (case-sensitive)
- Cek backend logs untuk error BCrypt

**Token expired:**
- Login ulang untuk mendapat token baru
- Atau implement refresh token logic (sudah tersedia di backend)

### Database Issues

**Migrations not applied:**
```bash
# Check migration status di backend logs
# Manual migration (jika perlu):
docker exec -it aspri-backend bash
# Lalu jalankan Flyway commands atau restart app
```

**Data lost after restart:**
```bash
# Pastikan volume di-mount dengan benar
docker volume ls
docker volume inspect aspri_postgres_data

# Untuk persist data, jangan jalankan:
docker-compose down -v  # flag -v akan hapus volumes!
```

## Development Tips

### Backend Development

1. **Hot Reload**: Spring Boot DevTools untuk auto-restart
2. **Database GUI**: Gunakan DBeaver, pgAdmin, atau DataGrip
3. **API Testing**: Postman, Insomnia, atau curl
4. **Logs**: Lihat di console atau `logs/` folder

### Frontend Development

1. **Hot Reload**: Angular CLI support hot reload otomatis
2. **Component Development**: Gunakan Angular DevTools extension
3. **Theme Testing**: Toggle theme switch di navbar untuk test light/dark
4. **Language Testing**: Toggle bahasa ID/EN di navbar
5. **State Management**: Gunakan Angular Signals (recommended untuk Angular 21+)

### Database Development

1. **Schema Changes**: Buat Flyway migration baru di `backend/src/main/resources/db/migration/`
2. **Migration Naming**: `V{version}__{description}.sql` (contoh: `V3__add_notes_table.sql`)
3. **Test Migration**: Restart backend untuk apply migration otomatis
4. **Rollback**: Flyway tidak support rollback otomatis, buat migration baru untuk undo

### Docker Development

1. **Build Only**: `docker-compose build`
2. **Run Specific Service**: `docker-compose up postgres` (hanya database)
3. **View Logs**: `docker-compose logs -f backend` (follow mode)
4. **Execute Commands**: `docker exec -it aspri-backend bash`
5. **Database Access**: `docker exec -it aspri-postgres psql -U postgres -d aspri`

## Security Best Practices

### Production Checklist

- [ ] Generate strong JWT_SECRET (min 32 chars, random)
- [ ] Use strong database password
- [ ] Enable HTTPS (use reverse proxy like Nginx or Traefik)
- [ ] Set proper CORS origins (specific domains only)
- [ ] Enable rate limiting (can use Spring Security or API Gateway)
- [ ] Set secure cookie flags (HttpOnly, Secure, SameSite)
- [ ] Use environment variables (never commit .env file)
- [ ] Regular security updates (dependencies)
- [ ] Implement request logging and monitoring
- [ ] Backup database regularly

### JWT Configuration

```bash
# Generate secure JWT secret
openssl rand -base64 48

# Or use:
node -e "console.log(require('crypto').randomBytes(48).toString('base64'))"
```

## Performance Tips

1. **Database Indexing**: Tambah index untuk query yang sering digunakan
2. **Connection Pooling**: Sudah dikonfigurasi via HikariCP (default Spring Boot)
3. **Caching**: Implement caching untuk data yang jarang berubah
4. **Lazy Loading**: Gunakan lazy loading di Angular untuk module besar
5. **Asset Optimization**: Compress images, minify CSS/JS (otomatis di production build)

## Next Steps

Setelah setup basic berhasil:

1. **Pahami Arsitektur**: Baca [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
2. **Database Schema**: Lihat [docs/DATABASE.md](docs/DATABASE.md)
3. **Setup Spring AI**: Untuk chat assistant - [docs/AI.md](docs/AI.md)
4. **Implement Modules**:
   - Dashboard dengan chart dan statistik
   - Chat interface dengan AI assistant
   - Note management (advanced blocks)
   - Calendar/Schedule dengan reminders
   - Finance tracking dengan budgeting
5. **Chat Integration**: Setup Telegram bot - [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md)
6. **Testing**: Tulis unit test dan integration test
7. **CI/CD**: Setup automated deployment pipeline

## Useful Commands

### Docker Commands
```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# View logs
docker-compose logs -f

# Rebuild and restart
docker-compose up -d --build

# Remove all (including volumes)
docker-compose down -v

# Check service status
docker-compose ps
```

### Database Commands
```bash
# Access database
docker exec -it aspri-postgres psql -U postgres -d aspri

# Backup database
docker exec aspri-postgres pg_dump -U postgres aspri > backup.sql

# Restore database
docker exec -i aspri-postgres psql -U postgres aspri < backup.sql
```

### Maven Commands
```bash
# Clean and build
mvn clean install

# Run without tests
mvn spring-boot:run -DskipTests

# Run tests only
mvn test

# Package JAR
mvn package
```

### NPM Commands
```bash
# Install dependencies
npm install

# Start dev server
npm start

# Build for production
npm run build

# Run tests
npm test

# Update dependencies
npm update
```

## Support & Resources

### Documentation
- [Architecture Overview](docs/ARCHITECTURE.md)
- [Database Schema](docs/DATABASE.md)
- [Authentication System](docs/AUTH.md)
- [Spring AI Integration](docs/AI.md)
- [Chat Integrations](docs/INTEGRATIONS.md)

### External Resources
- [Spring Boot Docs](https://docs.spring.io/spring-boot/docs/current/reference/html/)
- [Angular Docs](https://angular.dev/)
- [Spring AI Docs](https://docs.spring.io/spring-ai/reference/)
- [PostgreSQL Docs](https://www.postgresql.org/docs/)
- [Docker Docs](https://docs.docker.com/)

### Getting Help

Jika mengalami masalah:
1. Cek logs (backend console, frontend browser console, Docker logs)
2. Lihat troubleshooting section di atas
3. Baca dokumentasi di folder `docs/`
4. Create issue di repository

---

**Happy coding! 🚀**
