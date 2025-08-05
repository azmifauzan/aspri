# ASPRI Backend

This is the backend for the ASPRI personal assistant application. It is a FastAPI application that provides a RESTful API for the frontend.

## Tech Stack

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

## Manual Setup

For instructions on how to set up and run the backend manually (without Docker), please refer to the `docs/RUNNING_BACKEND.md` file in the root of the repository.

## Running Tests

To run the backend tests, navigate to the `backend` directory and run the following commands:

```bash
# Install dependencies
pip install -r requirements.txt
pip install pytest pytest-asyncio httpx

# Create a .env file from the template
cp .env.template .env

# Run the tests
pytest
```
