# Environment Variables Setup

## Overview

We've removed all `.env` files from the git history to resolve GitHub's secret scanning violations. This document explains how to set up your environment variables for both the frontend and backend.

## Frontend Environment Variables

Create a `frontend/.env` file with the following content:

```
VITE_GOOGLE_CLIENT_ID=your-google-client-id
VITE_API_BASE_URL=http://localhost:8000
```

### Variable Descriptions

- `VITE_GOOGLE_CLIENT_ID`: Your Google OAuth client ID for authentication
- `VITE_API_BASE_URL`: The base URL for the backend API (http://localhost:8000 for local development)

## Backend Environment Variables

Create a `backend/.env` file with the following content:

```
# Database Configuration
DATABASE_URL=mysql+aiomysql://username:password@localhost:3306/aspri_db

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

### Variable Descriptions

- `DATABASE_URL`: Connection string for your MySQL database
- `SECRET_KEY`: Secret key for JWT token generation (use a strong random string in production)
- `ACCESS_TOKEN_EXPIRE_MINUTES`: Expiration time for access tokens in minutes
- `GOOGLE_CLIENT_ID`: Your Google OAuth client ID for authentication
- `GOOGLE_CLIENT_SECRET`: Your Google OAuth client secret for authentication
- `DEBUG`: Enable/disable debug mode (set to False in production)
- `ENVIRONMENT`: Current environment (development, staging, production)

## Security Notes

- Never commit `.env` files to the repository
- Use strong, random values for secrets in production
- Rotate secrets regularly
- Use different secrets for different environments