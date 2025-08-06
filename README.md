# ASPRI - AI Personal Assistant

ASPRI is an AI-based personal assistant that helps users manage their daily lives through an intuitive chat interface. The application uses Large Language Models (LLM) to process natural requests, with access to users' personal data for contextual and accurate responses.

## Features

- **Google OAuth Authentication**: Secure login using Google accounts.
- **JWT Token-based Security**: Protected API endpoints with JWT tokens.
- **User Registration System**: Complete profile setup with personal preferences.
- **Document Management**: Upload, process, and search documents.
- **Vector Search**: Semantic search through documents using ChromaDB.
- **Object Storage**: Scalable document storage with MinIO.
- **Configuration Management**: Configurable file size and document limits.
- **MariaDB Database**: Reliable data storage with async operations using MariaDB.
- **RESTful API**: Well-structured API endpoints for frontend integration.
- **Chat-first Interface**: All features accessible through conversation, powered by Google Gemini.
- **Financial Tracking**: Manage income and expenses, categorize transactions, and view financial summaries.

## Tech Stack

### Backend
- **FastAPI**: Modern, fast web framework for building APIs.
- **MariaDB**: Robust relational database.
- **SQLAlchemy**: Python SQL toolkit and ORM with async support.
- **Alembic**: Database migration tool.
- **MinIO**: High-performance, S3 compatible object storage.
- **ChromaDB**: AI-native open-source embedding database.
- **LangChain**: Framework for developing applications powered by language models.
- **Google Generative AI**: For AI-powered chat and embeddings.
- **JWT**: JSON Web Tokens for authentication.
- **Google OAuth 2.0**: Secure authentication with Google.

### Frontend
- **React**: A JavaScript library for building user interfaces.
- **Vite**: Next-generation frontend tooling.
- **TypeScript**: A typed superset of JavaScript.
- **Tailwind CSS**: A utility-first CSS framework.
- **React Router**: Declarative routing for React.
- **@react-oauth/google**: Google OAuth integration.
- **i18next**: Internationalization framework.
- **axios**: Promise-based HTTP client.
- **Lucide React**: Simply beautiful open-source icons.

## Project Structure

```
aspri/
├── backend/
│   ├── app/
│   │   ├── api/
│   │   │   ├── auth.py          # Authentication endpoints
│   │   │   ├── chat.py          # Chat endpoints
│   │   │   ├── config.py        # Configuration endpoints
│   │   │   └── document.py      # Document management endpoints
│   │   ├── core/
│   │   │   └── auth.py          # JWT and OAuth utilities
│   │   ├── db/
│   │   │   ├── models/
│   │   │   │   ├── user.py      # User model
│   │   │   │   ├── chat.py      # Chat models
│   │   │   │   ├── document.py  # Document model
│   │   │   │   └── config.py    # Configuration model
│   │   │   └── ...
│   │   ├── schemas/
│   │   │   └── ...
│   │   ├── services/
│   │   │   └── ...
│   │   └── main.py              # FastAPI application
│   ├── alembic/                 # Database migrations
│   ├── requirements.txt         # Python dependencies
│   ├── Dockerfile               # Backend container configuration
│   └── .env.template            # Environment variables template
├── frontend/
│   ├── src/
│   │   └── ...                  # React application source
│   ├── public/
│   │   └── ...                  # Static assets
│   ├── package.json             # Node.js dependencies
│   ├── Dockerfile               # Frontend container configuration
│   ├── nginx.conf               # Nginx configuration
│   └── .env.example             # Frontend environment variables
├── docs/
│   ├── ENVIRONMENT_VARIABLES.md # Environment setup guide
│   ├── CHAT_FEATURE.md          # Chat feature documentation
│   ├── RUNNING_BACKEND.md       # Backend setup guide
│   ├── ARCHITECTURE.md          # System architecture documentation
│   ├── CHROMA_DB_COLLECTIONS.md # ChromaDB collections guide
│   └── TODO.md                  # Development roadmap
├── docker-compose.yml           # Full-stack Docker orchestration
└── README.md                    # This file
```

## Installation & Setup

We recommend using Docker Compose for the easiest setup.

### Option 1: Docker Compose (Recommended)

**Prerequisites:**
- Docker
- Docker Compose

**Steps:**
1.  **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd aspri
    ```
2.  **Configure Environment Variables:**
    - Navigate to the `backend` directory: `cd backend`
    - Copy the example environment file: `cp .env.template .env`
    - Edit the `.env` file with your credentials (Google OAuth, Google AI API Key, etc.).
    - Navigate to the `frontend` directory: `cd ../frontend`
    - Copy the example environment file: `cp .env.example .env`
    - Edit the `.env` file with your Google Client ID.

3.  **Run Docker Compose:**
    From the root directory, run:
    ```bash
    docker-compose up --build
    ```
    This will build and start all services:
    - **Frontend**: Accessible at `http://localhost:3000`
    - **Backend API**: Accessible at `http://localhost:8888`
    - **MariaDB**: Database on port 3306
    - **MinIO**: Object storage on ports 9000 (API) and 9001 (Console)
    - **ChromaDB**: Vector database on port 8000

### Option 2: Manual Setup

**Prerequisites:**
- Python 3.11+
- Node.js (v18+)
- MariaDB (or MySQL)
- MinIO Server
- ChromaDB Server

For detailed instructions on setting up the backend manually, please refer to `docs/RUNNING_BACKEND.md`.

## API Endpoints

### Authentication
- `POST /auth/login`: Login with Google OAuth token.
- `POST /auth/register`: Complete user registration.
- `GET /auth/me`: Get current user information.

### Chat
- `POST /chat/sessions`: Create a new chat session.
- `GET /chat/sessions`: Get all chat sessions for the current user.
- `POST /chat/sessions/{session_id}/messages`: Send a message in a chat session.

### Documents
- `POST /documents/upload`: Upload a document.
- `GET /documents`: Get a list of uploaded documents.
- `GET /documents/{document_id}`: Download a document.
- `DELETE /documents/{document_id}`: Delete a document.

### Configuration
- `GET /config/limits`: Get file and document limits.
- `PUT /config/{config_key}`: Update a configuration value.

### Finance
- `GET /finance/categories`: Get all financial categories.
- `POST /finance/categories`: Create a new financial category.
- `PUT /finance/categories/{category_id}`: Update a financial category.
- `DELETE /finance/categories/{category_id}`: Delete a financial category.
- `GET /finance/transactions`: Get all financial transactions.
- `POST /finance/transactions`: Create a new financial transaction.
- `PUT /finance/transactions/{transaction_id}`: Update a financial transaction.
- `DELETE /finance/transactions/{transaction_id}`: Delete a financial transaction.
- `GET /finance/summary`: Get a financial summary.

## Database Schema

### `users` table
- `id`, `email`, `google_id`, `name`, `birth_date`, `birth_month`, `call_preference`, `aspri_name`, `aspri_persona`, `is_registered`, `created_at`, `updated_at`

### `documents` table
- `id`, `user_id`, `filename`, `s3_path`, `file_size`, `file_type`, `created_at`

### `chat_sessions` table
- `id`, `user_id`, `session_name`, `created_at`

### `chat_messages` table
- `id`, `session_id`, `message`, `is_from_user`, `created_at`

### `configurations` table
- `id`, `key`, `value`, `description`

## Deployment

This application is designed to be deployed using Docker Compose. The root `docker-compose.yml` file provides a complete orchestration setup for all services including frontend, backend, database, and supporting services.

### Production Considerations
- Use strong, unique secrets for all services
- Configure proper networking and volumes for your environment
- Set up reverse proxy (nginx/traefik) for SSL termination
- Configure backup strategies for persistent data
- Monitor service health and logs

For detailed deployment guides, see:
- `docs/RUNNING_BACKEND.md` - Backend setup and configuration
- `docs/CHAT_FEATURE.md` - Chat feature implementation details
- `docs/ENVIRONMENT_VARIABLES.md` - Environment configuration guide
- `docs/ARCHITECTURE.md` - System architecture overview

## Contributing

Contributions are welcome! Please fork the repository and create a pull request with your changes.

## License

This project is licensed under the MIT License.