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

### Frontend
- **React.js**: Modern JavaScript library for building user interfaces.
- **TypeScript**: Strongly typed programming language that builds on JavaScript.
- **Vite**: Next generation frontend tooling.
- **Tailwind CSS**: Utility-first CSS framework.
- **React Router**: Declarative routing for React.
- **React OAuth**: Google OAuth integration.
- **i18next**: Internationalization framework.

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
backend/
├── app/
│   ├── api/
│   │   ├── auth.py          # Authentication endpoints
│   │   ├── chat.py          # Chat endpoints
│   │   ├── config.py        # Configuration endpoints
│   │   └── document.py      # Document management endpoints
│   ├── core/
│   │   └── auth.py          # JWT and OAuth utilities
│   ├── db/
│   │   ├── models/
│   │   │   ├── user.py      # User model
│   │   │   ├── chat.py      # Chat models
│   │   │   ├── document.py  # Document model
│   │   │   └── config.py    # Configuration model
│   │   └── ...
│   ├── schemas/
│   │   └── ...
│   ├── services/
│   │   └── ...
│   └── main.py              # FastAPI application
├── alembic/                 # Database migrations
├── docker-compose.yml       # Docker orchestration
├── requirements.txt         # Python dependencies
└── .env.template            # Environment variables template
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
    From the `backend` directory, run:
    ```bash
    docker-compose up --build
    ```
    This will build the images and start all services: the backend app, MariaDB, MinIO, and ChromaDB. The backend will be accessible at `http://localhost:8888`.

### Option 2: Manual Setup

For detailed instructions on setting up the frontend and backend manually, please refer to the `frontend/README.md` and `backend/README.md` files respectively.

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

## Documentation

For more detailed documentation, please refer to the files in the `docs` directory:
-   `docs/architecture.md`: High-level architecture of the system.
-   `docs/RUNNING_BACKEND.md`: Detailed instructions for running the backend manually.
-   `docs/CHAT_FEATURE.md`: Information about the chat feature.
-   `docs/ENVIRONMENT_VARIABLES.md`: A complete list of environment variables.

## Deployment
This application is designed to be deployed using Docker. The `docker-compose.yml` file in the root directory provides a starting point for orchestrating the necessary services.

## Contributing

Contributions are welcome! Please fork the repository and create a pull request with your changes.

## License

This project is licensed under the MIT License.