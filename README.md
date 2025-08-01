# ASPRI - AI Personal Assistant

ASPRI is an AI-based personal assistant that helps users manage their daily lives through an intuitive chat interface. The application uses Large Language Models (LLM) to process natural requests, with access to users' personal data for contextual and accurate responses.

## Features

- **Google OAuth Authentication**: Secure login using Google accounts
- **JWT Token-based Security**: Protected API endpoints with JWT tokens
- **User Registration System**: Complete profile setup with personal preferences
- **PostgreSQL Database**: Reliable data storage with async operations
- **RESTful API**: Well-structured API endpoints for frontend integration
- **Chat-first Interface**: All features accessible through conversation
- **Personal Data Management**: Schedules, finances, and documents

### Frontend
- **React.js**: Modern JavaScript library for building user interfaces
- **TypeScript**: Strongly typed programming language that builds on JavaScript
- **Vite**: Next generation frontend tooling
- **Tailwind CSS**: Utility-first CSS framework
- **React Router**: Declarative routing for React
- **React OAuth**: Google OAuth integration
- **i18next**: Internationalization framework

## Tech Stack

### Backend
- **FastAPI**: Modern, fast web framework for building APIs
- **PostgreSQL**: Robust relational database
- **SQLAlchemy**: Python SQL toolkit and ORM with async support
- **Alembic**: Database migration tool
- **JWT**: JSON Web Tokens for authentication
- **Google OAuth 2.0**: Secure authentication with Google

### Dependencies
- `fastapi`: Web framework
- `uvicorn`: ASGI server
- `sqlalchemy`: ORM with async support
- `asyncpg`: PostgreSQL adapter
- `python-jose`: JWT handling
- `passlib`: Password hashing
- `python-multipart`: Form data parsing
- `alembic`: Database migrations
- `google-auth`: Google OAuth verification

## Project Structure

```
backend/
├── app/
│   ├── api/
│   │   └── auth.py              # Authentication endpoints
│   ├── core/
│   │   └── auth.py              # JWT and OAuth utilities
│   ├── db/
│   │   ├── base.py              # SQLAlchemy base class
│   │   ├── database.py          # Database configuration
│   │   └── models/
│   │       └── user.py          # User model
│   ├── schemas/
│   │   └── user.py              # Pydantic models
│   ├── services/
│   │   └── user_service.py      # User business logic
│   └── main.py                  # FastAPI application
├── alembic/                     # Database migrations
├── requirements.txt             # Python dependencies
├── .env.example                 # Environment variables template
└── alembic.ini                  # Alembic configuration
```

### Frontend
```
frontend/
├── public/                      # Static assets
├── src/
│   ├── components/              # Reusable UI components
│   ├── contexts/                # React contexts (AuthContext)
│   ├── hooks/                   # Custom React hooks
│   ├── locales/                 # Translation files
│   ├── pages/                  # Page components
│   ├── App.tsx                 # Main application component
│   ├── main.tsx                # Entry point
│   └── index.css               # Global styles
├── index.html                  # HTML entry point
├── package.json                # Frontend dependencies
├── tsconfig.json              # TypeScript configuration
└── vite.config.ts             # Vite configuration
```

## Installation & Setup

### Prerequisites
- Python 3.11+
- PostgreSQL 12+
- Google Cloud Console project (for OAuth)

### 1. Clone the Repository
```bash
git clone <repository-url>
cd aspri/backend
```

### 2. Create Virtual Environment
```bash
python -m venv venv
# On Windows
venv\Scripts\activate
# On macOS/Linux
source venv/bin/activate
```

### 3. Install Dependencies
```bash
pip install -r requirements.txt
```

### 4. Database Setup
```bash
# Create PostgreSQL database
createdb aspri_db

# Set up environment variables
cp .env.example .env
# Edit .env with your database credentials
```

**Important Security Note**: We've removed all `.env` files from the git history to resolve GitHub's secret scanning violations. Please see [Environment Variables Setup](docs/ENVIRONMENT_VARIABLES.md) for detailed instructions on setting up your environment variables.

### 5. Configure Environment Variables
Edit `.env` file with your configuration:
```env
DATABASE_URL=postgresql+asyncpg://username:password@localhost:5432/aspri_db
SECRET_KEY=your-super-secret-jwt-key-here
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
```

### 6. Run Database Migrations
```bash
# Initialize Alembic (if not already done)
alembic init alembic

# Create initial migration
alembic revision --autogenerate -m "Initial migration"

# Run migrations
alembic upgrade head
```

### 7. Start the Server
```bash
# Development server
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000

# Production server
uvicorn app.main:app --host 0.0.0.0 --port 8000
```

## API Endpoints

### Authentication Endpoints

#### POST `/auth/login`
Login with Google OAuth token
```json
{
  "google_token": "google_oauth_token_here"
}
```

Response:
```json
{
  "access_token": "jwt_token_here",
  "token_type": "bearer",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "is_registered": false
  },
  "is_registered": false
}
```

#### POST `/auth/register` (Protected)
Complete user registration
```json
{
  "name": "John Doe",
  "birth_date": 15,
  "birth_month": 6,
  "call_preference": "John",
  "aspri_name": "Alex",
  "aspri_persona": "Friendly and helpful assistant"
}
```

#### GET `/auth/me` (Protected)
Get current user information

Response:
```json
{
  "id": 1,
  "email": "user@example.com",
  "google_id": "google_oauth_id",
  "name": "John Doe",
  "birth_date": 15,
  "birth_month": 6,
  "call_preference": "John",
  "aspri_name": "Alex",
  "aspri_persona": "Friendly and helpful assistant",
  "is_registered": true,
  "created_at": "2023-01-01",
  "updated_at": "2023-01-02"
}
```

### Other Endpoints

#### GET `/`
API root endpoint with basic information

#### GET `/health`
Health check endpoint

## Google OAuth Setup

**Important Security Note**: Never commit your Google OAuth credentials to the repository. These should be stored in `.env` files which are ignored by git. See [Environment Variables Setup](docs/ENVIRONMENT_VARIABLES.md) for more details.

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable Google+ API
4. Create OAuth 2.0 credentials
5. Add authorized redirect URIs
6. Copy Client ID and Client Secret to `.env`

## Database Schema

### Users Table
- `id`: Primary key
- `email`: User email (unique)
- `google_id`: Google OAuth ID (unique)
- `name`: Full name
- `birth_date`: Day of birth (1-31)
- `birth_month`: Month of birth (1-12)
- `call_preference`: Preferred name
- `aspri_name`: AI assistant name
- `aspri_persona`: AI assistant personality
- `is_registered`: Registration completion status
- `created_at`: Account creation date
- `updated_at`: Last update date

## Frontend Implementation

### Authentication Flow
1. User clicks "Login" button on Navbar
2. Redirected to LoginPage (/login)
3. User clicks "Sign in with Google" button
4. Google OAuth flow opens popup for authentication
5. After successful Google login, frontend receives credential token
6. Frontend sends token to backend `/auth/login` endpoint
7. Backend verifies token and creates user if needed
8. Backend returns JWT token and user information
9. If user.is_registered is false, redirect to RegistrationPage (/register)
10. If user.is_registered is true, redirect to UserDashboard (/dashboard)

### Registration Flow
1. User fills registration form with required information
2. Form validation ensures all fields are properly filled
3. On submit, data is sent to backend `/auth/register` endpoint
4. Backend updates user information and sets is_registered to true
5. User is redirected to UserDashboard (/dashboard)

### Dashboard Features
1. Responsive sidebar navigation with menu items
2. User profile information display
3. Quick action buttons for main features
4. Statistics overview
5. Logout functionality that clears JWT token

## Development

### Running Tests
```bash
# Install test dependencies
pip install pytest pytest-asyncio httpx

# Run tests
pytest
```

### Database Migrations
```bash
# Create new migration
alembic revision --autogenerate -m "Description of changes"

# Apply migrations
alembic upgrade head

# Rollback migration
alembic downgrade -1
```

### Code Formatting
```bash
# Install formatting tools
pip install black isort

# Format code
black .
isort .
```

## Security Considerations

- JWT tokens expire after 30 minutes
- All API endpoints (except login) require authentication
- Google OAuth tokens are verified server-side
- Database credentials should be kept secure
- Use HTTPS in production
- Configure CORS properly for production
- Environment variables with secrets are ignored by git and removed from git history
- See [Environment Variables Setup](docs/ENVIRONMENT_VARIABLES.md) for detailed security guidelines

## Deployment

### Docker Deployment
```dockerfile
FROM python:3.11-slim

WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt

COPY . .
EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
```

### Environment Variables for Production
- Set strong `SECRET_KEY`
- Configure proper `DATABASE_URL`
- Set up Google OAuth credentials
- Configure CORS origins
- Enable HTTPS

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For support and questions, please create an issue in the repository.