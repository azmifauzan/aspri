# Personal Assistant (Aspri)

## Application Concept

**Main Objective**: ASPRI is an AI-based personal assistant that helps users manage their daily lives through an intuitive chat interface. The application uses Google's Gemini models via the LangChain framework to process natural language requests, with access to users' personal data for contextual and accurate responses. The core concept is "chat-first" where all features are accessed through conversation.

**Key Features**:
1. **User Authentication**: Secure login with Google OAuth and JWT token-based authentication.
2. **User Registration**: Complete profile setup with personal preferences.
3. **Document Management**: Upload documents to a secure MinIO object store.
4. **Intelligent Document Analysis**: Process and analyze uploaded documents, with vector embeddings stored in ChromaDB for semantic search.
5. **Contact Management**: Full CRUD functionality for Google Contacts, synchronized directly with the user's Google account.
6. **Chat Interface**: A central chat interface powered by Google Gemini for conversation, document interaction, and contact searching.
7. **Configuration Management**: System for managing application settings like file limits.

## Application Architecture

The architecture follows a client-server pattern with a decoupled frontend and backend. The backend is designed with a service-oriented approach to handle business logic, data storage, and AI integration.

**Main Components**:
1.  **Frontend**:
    -   **Framework**: React.js with TypeScript and Vite for a modern, fast, and type-safe development experience.
    -   **UI**: Responsive design using Tailwind CSS.
    -   **Integration**: Communicates with the backend via a RESTful API, handles Google OAuth flow, and uses i18next for internationalization.

2.  **Backend**:
    -   **Framework**: Python with FastAPI for building high-performance APIs.
    -   **API**: RESTful endpoints for authentication, chat, document management, and configuration.
    -   **LLM Integration**: Uses LangChain to integrate with Google's Generative AI models (Gemini) for chat responses and generating embeddings.
    -   **Services**: Separate services for managing users, chat, documents, and configuration.

3.  **Data Storage**:
    -   **Relational Database**: MariaDB for storing structured data like user profiles, chat history, and document metadata.
    -   **Object Storage**: MinIO for storing uploaded documents securely and scalably.
    -   **Vector Database**: ChromaDB for storing vector embeddings of documents, enabling efficient semantic search (RAG).

4.  **External Integrations**:
    -   **Google Cloud**: For Google OAuth 2.0 authentication.
    -   **Google AI**: For accessing the Gemini language models.
    -   **Google People API**: For reading and managing user contacts.

**High-Level Architecture Diagram** (Text Description):
-   **User** ↔ **Frontend (React)** ↔ **Backend (FastAPI)**
    -   The **Backend** interacts with several components:
        -   **MariaDB**: For user data, chat logs, Google API tokens, etc.
        -   **MinIO**: For storing and retrieving documents.
        -   **LLM Engine (LangChain + Google Gemini)**: For processing language and generating embeddings.
            -   The LLM Engine uses **ChromaDB** for document retrieval (RAG).
        -   **Google People API**: For managing user contacts.

**Recommended Tech Stack**:
-   **Frontend**: React, TypeScript, Vite, Tailwind CSS.
-   **Backend**: FastAPI (Python), LangChain, Google Generative AI.
-   **Database**: MariaDB + ChromaDB.
-   **Object Storage**: MinIO.
-   **Deployment**: Docker for containerization, with orchestration via Docker Compose. The root `docker-compose.yml` file orchestrates the full-stack deployment including frontend, backend, and all supporting services (MariaDB, MinIO, ChromaDB).
-   **Security**: HTTPS, JWT token authentication, secure handling of secrets.