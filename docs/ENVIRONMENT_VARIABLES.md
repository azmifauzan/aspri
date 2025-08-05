# Environment Variables Setup

## Overview

This document explains how to set up your environment variables for both the frontend and backend. It is crucial to configure these correctly for the application to run.

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
-   `GOOGLE_CLIENT_ID`: Your Google OAuth client ID.
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

## Security Notes

-   **Never commit `.env` files to the repository.**
-   Use strong, random values for secrets in production.
-   Rotate secrets regularly.
-   Use different secrets for different environments.