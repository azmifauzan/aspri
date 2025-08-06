# ASPRI Backend

The backend API for ASPRI (AI Personal Assistant) built with FastAPI. This service provides authentication, document management, chat functionality, and configuration management with AI-powered features.

## Overview

ASPRI Backend is a modern Python web API that serves as the core service for the AI Personal Assistant application. It integrates with various AI services and databases to provide a seamless user experience.

## Tech Stack

- **FastAPI**: Modern, fast web framework for building APIs
- **MariaDB**: Relational database for data persistence
- **SQLAlchemy**: Async ORM for database operations
- **Alembic**: Database migration management
- **MinIO**: S3-compatible object storage for documents
- **ChromaDB**: Vector database for semantic search
- **LangChain**: Framework for LLM applications
- **Google Generative AI**: AI model integration
- **JWT**: Token-based authentication
- **Google OAuth 2.0**: Secure authentication

## Features

- 🔐 **Google OAuth Authentication** with JWT token management
- 📁 **Document Management** with upload, processing, and semantic search
- 💬 **AI-Powered Chat** using Google Gemini with document context
- ⚙️ **Configuration Management** for dynamic settings
- 🔍 **Vector Search** through documents using embeddings
- 📊 **Database Management** with migrations and async operations
- 🐳 **Docker Support** for easy deployment

## Project Structure

```
aspri/backend/
├── app/
│   ├── api/                 # API route handlers
│   │   ├── auth.py         # Authentication endpoints
│   │   ├── chat.py         # Chat functionality
│   │   ├── config.py       # Configuration management
│   │   └── document.py     # Document operations
│   ├── core/               # Core utilities
│   │   └── auth.py         # JWT and OAuth utilities
│   ├── db/                 # Database related code
│   │   ├── models/         # SQLAlchemy models
│   │   │   ├── user.py     # User model
│   │   │   ├── chat.py     # Chat models
│   │   │   ├── document.py # Document model
│   │   │   └── config.py   # Configuration model
│   │   ├── database.py     # Database configuration
│   │   └── base.py         # Base model class
│   ├── schemas/            # Pydantic models
│   │   ├── user.py         # User schemas
│   │   ├── chat.py         # Chat schemas
│   │   └── document.py     # Document schemas
│   ├── services/           # Business logic services
│   │   ├── chat_service.py     # Chat processing
│   │   ├── chromadb_service.py # Vector database
│   │   ├── config_service.py   # Configuration
│   │   ├── document_service.py # Document processing
│   │   ├── minio_service.py    # Object storage
│   │   └── user_service.py     # User management
│   └── main.py             # FastAPI application
├── alembic/                # Database migrations
├── scripts/                # Utility scripts
├── tests/                  # Test suite
├── Dockerfile              # Container definition
├── requirements.txt        # Python dependencies
└── .env.template          # Environment variables template
```

**Note**: Docker Compose configuration (`docker-compose.yml`) has been moved to the project root directory for full-stack orchestration.

## Quick Start

### Prerequisites

- Python 3.11+
- Docker and Docker Compose (recommended)
- MariaDB/MySQL (if not using Docker)
- MinIO server (if not using Docker)

### Docker Setup (Recommended)

1. **Clone and navigate to project root:**
   ```bash
   git clone <repository-url>
   cd aspri
   ```

2. **Configure environment:**
   ```bash
   # Backend configuration
   cd backend
   cp .env.template .env
   # Edit .env with your configuration
   
   # Frontend configuration  
   cd ../frontend
   cp .env.example .env
   # Edit .env with your Google Client ID
   
   cd ..
   ```

3. **Start all services:**
   ```bash
   docker-compose up --build
   ```

4. **Access the services:**
   - Frontend: http://localhost:3000
   - Backend API: http://localhost:8888
   - API Documentation: http://localhost:8888/docs
   - MinIO Console: http://localhost:9001

### Manual Setup

1. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   ```

2. **Configure environment:**
   ```bash
   cp .env.template .env
   # Edit .env with your database and service configurations
   ```

3. **Initialize database:**
   ```bash
   python scripts/init_db.py
   alembic upgrade head
   ```

4. **Start the server:**
   ```bash
   uvicorn app.main:app --host 0.0.0.0 --port 8888 --reload
   ```

## Environment Configuration

Create a `.env` file from `.env.template` and configure:

```bash
# Database
DATABASE_URL=mysql+aiomysql://user:password@localhost:3306/aspri

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# Google AI
GOOGLE_AI_API_KEY=your_google_ai_api_key

# JWT
SECRET_KEY=your_secret_key
ALGORITHM=HS256
ACCESS_TOKEN_EXPIRE_MINUTES=30

# MinIO
MINIO_ENDPOINT=localhost:9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin

# ChromaDB
CHROMADB_HOST=localhost
CHROMADB_PORT=8000
```

## API Endpoints

### Authentication
- `POST /auth/login` - Login with Google OAuth token
- `POST /auth/register` - Complete user registration
- `GET /auth/me` - Get current user information

### Chat
- `POST /chat/sessions` - Create a new chat session
- `GET /chat/sessions` - Get all chat sessions
- `POST /chat/sessions/{session_id}/messages` - Send a message

### Documents
- `POST /documents/upload` - Upload a document
- `GET /documents` - List user documents
- `GET /documents/{document_id}` - Download a document
- `DELETE /documents/{document_id}` - Delete a document

### Configuration
- `GET /config/limits` - Get system limits
- `PUT /config/{config_key}` - Update configuration

## Database Management

### Migrations

Create a new migration:
```bash
alembic revision --autogenerate -m "Description of changes"
```

Apply migrations:
```bash
alembic upgrade head
```

Rollback migration:
```bash
alembic downgrade -1
```

### Models

The application uses SQLAlchemy async models:
- **User**: User profiles and authentication
- **Document**: File storage and metadata
- **ChatSession**: Chat conversation groups
- **ChatMessage**: Individual chat messages
- **Configuration**: Dynamic system settings

## Development

### Running Tests

```bash
# Install test dependencies
pip install pytest pytest-asyncio httpx

# Run tests
pytest tests/
```

### Code Style

The project follows Python best practices:
- Use type hints
- Follow PEP 8 style guide
- Write docstrings for functions and classes
- Use async/await for I/O operations

### Adding New Features

1. Create models in `app/db/models/`
2. Define schemas in `app/schemas/`
3. Implement services in `app/services/`
4. Create API routes in `app/api/`
5. Add tests in `tests/`
6. Update migrations if needed

## Services Integration

### ChromaDB (Vector Database)
Used for semantic search through documents. Documents are automatically embedded and stored for AI-powered search capabilities.

### MinIO (Object Storage)
Handles file storage with S3-compatible API. Documents are stored securely with proper access controls.

### Google AI
Integrates with Google Generative AI for:
- Text embeddings for semantic search
- Chat completions with context
- Document analysis and summarization

## Deployment

### Production Considerations

1. **Environment Variables**: Use secure, production-specific values
2. **Database**: Configure MariaDB with proper security settings
3. **CORS**: Restrict origins to your frontend domain
4. **HTTPS**: Use SSL/TLS certificates
5. **Monitoring**: Add logging and monitoring solutions
6. **Scaling**: Consider load balancing for high traffic

### Docker Production

```bash
# Build production image
docker build -t aspri-backend .

# Run with production environment
docker run -d -p 8888:8888 --env-file .env.prod aspri-backend
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check DATABASE_URL in .env
   - Ensure MariaDB is running
   - Verify credentials

2. **Google OAuth Issues**
   - Verify GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET
   - Check redirect URIs in Google Console
   - Ensure proper scopes

3. **MinIO Connection Error**
   - Check MinIO endpoint and credentials
   - Verify bucket creation permissions
   - Ensure network connectivity

4. **ChromaDB Issues**
   - Verify ChromaDB server is running
   - Check host and port configuration
   - Ensure proper collection creation

### Logs

View application logs:
```bash
# Docker logs
docker-compose logs backend

# Direct logs (if running manually)
tail -f app.log
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.