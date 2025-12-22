# Environment Variables Setup

## Overview

This document explains how to set up environment variables for the full-stack ASPRI application. The Docker Compose setup at the project root orchestrates both frontend and backend services, so proper configuration is essential for all components to work together.

## Project Structure

The application now uses a unified Docker Compose setup:
- **Root directory**: Contains `docker-compose.yml` for full-stack orchestration
- **Frontend directory**: Contains React/Vite application  
- **Backend directory**: Contains FastAPI application
- **Docs directory**: Contains all documentation

## Quick Setup with Docker Compose

From the project root directory:

1. **Configure Backend Environment:**
   ```bash
   cd backend
   cp .env.template .env
   # Edit backend/.env with your configuration
   ```

2. **Configure Frontend Environment:**
   ```bash
   cd ../frontend  
   cp .env.example .env
   # Edit frontend/.env with your Google Client ID
   ```

3. **Start All Services:**
   ```bash
   cd ..
   docker-compose up --build
   ```

This will start:
- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8888
- **MariaDB**: Port 3306
- **MinIO**: Ports 9000 (API) and 9001 (Console)
- **ChromaDB**: Port 8000

## Frontend Environment Variables

Create a `.env` file in the `frontend` directory by copying the `.env.example` file.

```bash
cd frontend
cp .env.example .env
```

Then, edit `frontend/.env` with the following content:

```env
VITE_GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
VITE_API_BASE_URL=http://localhost:8888
```

### Variable Descriptions

-   `VITE_GOOGLE_CLIENT_ID`: Your Google OAuth client ID for authentication.
-   `VITE_API_BASE_URL`: The base URL for the backend API. Defaults to `http://localhost:8888` for local development.

**Note**: When using Docker Compose, the frontend service automatically connects to the backend service through the Docker network, so API requests are properly routed via nginx proxy configuration.

## Backend Environment Variables

Create a `.env` file in the `backend` directory by copying the `.env.template` file.

```bash
cd backend
cp .env.template .env
```

Then, edit `backend/.env` with your specific configuration:

```env
# Database Configuration
DATABASE_URL=mysql+aiomysql://aspri_user:aspri_password@localhost:3306/aspri_db

# JWT Configuration
SECRET_KEY=your-super-secret-jwt-key-here-change-in-production
ACCESS_TOKEN_EXPIRE_MINUTES=30

# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret

# Google AI Configuration
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

### Variable Descriptions

-   `DATABASE_URL`: Connection string for your MariaDB/MySQL database.
-   `SECRET_KEY`: Secret key for JWT token generation. Use a strong random string in production.
-   `ACCESS_TOKEN_EXPIRE_MINUTES`: Expiration time for access tokens in minutes.
-   `GOOGLE_CLIENT_ID`: Your Google OAuth client ID (must match frontend configuration).
-   `GOOGLE_CLIENT_SECRET`: Your Google OAuth client secret.
-   `GOOGLE_API_KEY`: Your API key for Google AI Studio (for Gemini models).
-   `MINIO_ENDPOINT`: The endpoint URL for your MinIO server.
-   `MINIO_ACCESS_KEY`: The access key for MinIO.
-   `MINIO_SECRET_KEY`: The secret key for MinIO.
-   `MINIO_SECURE`: Set to `true` if MinIO is using HTTPS.
-   `MINIO_BUCKET_NAME`: The name of the bucket to use for document storage.
-   `CHROMADB_HOST`: The host for your ChromaDB server.
-   `CHROMADB_PORT`: The port for your ChromaDB server.
-   `CHROMADB_COLLECTION_NAME`: The name of the collection to use in ChromaDB.

## Docker Compose Environment Variables

When using Docker Compose, the services communicate through the Docker network using service names:

- Database: `db:3306`
- MinIO: `minio:9000` 
- ChromaDB: `chromadb:8000`
- Backend: `backend:8888`

The docker-compose.yml automatically configures these internal connections.

## Google OAuth Setup

1. **Go to Google Cloud Console:**
   - Visit https://console.cloud.google.com/
   - Create a new project or select existing one

2. **Enable APIs:**
   - Enable Google+ API
   - Enable Google AI API (for Gemini)

3. **Create OAuth Credentials:**
   - Go to "Credentials" → "Create Credentials" → "OAuth client ID"
   - Application type: Web application
   - Authorized redirect URIs: 
     - `http://localhost:3000` (for frontend)
     - `http://localhost:8888` (for backend)

4. **Get API Keys:**
   - Create API key for Google AI Studio
   - Download OAuth client credentials

## Security Notes

-   **Never commit `.env` files to the repository.**
-   Use strong, random values for secrets in production.
-   Rotate secrets regularly.
-   Use different secrets for different environments.
-   In production, use environment-specific URLs and enable HTTPS.

## Troubleshooting

### Common Issues

1. **CORS Errors**: Ensure GOOGLE_CLIENT_ID matches between frontend and backend
2. **Database Connection**: Check DATABASE_URL format and credentials
3. **MinIO Access**: Verify MINIO_ENDPOINT and credentials
4. **Google Auth**: Ensure redirect URIs are configured correctly in Google Console
5. **Service Communication**: In Docker, services use internal network names (e.g., `minio:9000` not `localhost:9000`)

### Environment Validation

Test your configuration:

```bash
# Test frontend
curl http://localhost:3000

# Test backend health
curl http://localhost:8888/health

# Test MinIO
curl http://localhost:9001 

# Test ChromaDB
curl http://localhost:8000/api/v1/heartbeat
```