# Personal Assistant (Aspri)

## Application Concept

**Main Objective**: ASPRI is an AI-based personal assistant that helps users manage their daily lives through an intuitive chat interface. The application uses LLM (such as GPT-series or open-source models like Llama) to process natural requests, with access to users' personal data for contextual and accurate responses. The core concept is "chat-first" where all features are accessed through conversation, similar to frontier LLMs like ChatGPT, but enhanced with specialized tools for internal data (schedules, finances, documents). Users authenticate with Google OAuth and complete a registration process to personalize their experience with a custom AI assistant.

**Key Features**:
1. **User Authentication**: Secure login with Google OAuth and JWT token-based authentication.
2. **User Registration**: Complete profile setup with personal preferences (name, birth date, call preference, ASPRI assistant name and persona).
3. **Dashboard Interface**: Responsive dashboard with sidebar navigation, user profile display, and quick action buttons.
4. **Main Chat**: Central interface like LLM chatbot, with additional tools:
   - Schedule Tool: Access/mutate schedule data.
   - Finance Tool: Query/financial transactions.
   - Document Tool: Retrieval and analysis of documents from VectorDB.
   All features supported by LLM for natural language processing, with fallback to manual UI if needed.

**User Flow**:
- Secure login with Google OAuth.
- New users complete registration with personal preferences.
- Access main dashboard with sidebar navigation.
- User asks/commands via text (e.g., "Schedule vacation next week and record today's expenses").
- LLM parses request, calls relevant tools, and responds with results.
- Side dashboard for manual view (calendar schedule, financial charts, document list).

**Design Principles**: User-centric, privacy-first (data stored locally/encrypted), extensible (easy to add new tools), and responsive (works on all device sizes).

## Application Architecture

Architecture adopts client-server pattern with microservices for modulation. Frontend focuses on interactive UI, backend handles business logic, integration, and LLM. Data flow: User → Frontend → Backend API → Database/LLM/Tools → Response.

**Main Components**:
1. **Frontend**:
   - Framework: React.js with TypeScript and Vite for fast development.
   - UI: Responsive design with Tailwind CSS, landing page, login page, registration page, dashboard with sidebar navigation.
   - Integration: RESTful API calls to backend, Google OAuth for authentication, i18next for internationalization.

2. **Backend**:
   - Framework: Python with FastAPI.
   - API: RESTful endpoints for authentication, features (e.g., /schedule, /finance, /documents).
   - LLM Integration: LangChain or LlamaIndex for chaining LLM with custom tools (e.g., tool for querying VectorDB or Google API).
   - LLM Tools:
     - Schedule: Google Calendar API integration (OAuth2 for auth).
     - Finance: CRUD operations to database.
     - Documents: Embedding using models like Sentence Transformers, store to VectorDB.

3. **Database**:
   - Relational: PostgreSQL for structural data (users, schedules, finances).
   - VectorDB: ChromaDB (open-source) or Pinecone for document embedding (text vectorization for RAG - Retrieval-Augmented Generation).

4. **External Integrations**:
   - Google Calendar API: For schedule sync (use Google SDK).
   - LLM Provider: OpenAI API or Hugging Face for local models (for privacy).
   - Authentication: JWT or Firebase Auth for security.
- **Messaging Apps**: Integration with Telegram Bot API and WhatsApp Business API to enable chat interaction through external platforms. Backend will handle incoming webhooks from both APIs, route messages to LLM engine for processing, and send responses back.

**High-Level Architecture Diagram** (Text Description):
- **User** ↔ **Frontend (React)** ↔ **Backend (FastAPI)**
  - Backend → **LLM Engine (LangChain)** → **Tools**:
    - **Schedule Tool** → Google Calendar API
    - **Finance Tool** → PostgreSQL
    - **Document Tool** → VectorDB (Chroma)
- Data Flow: Chat requests can originate from frontend app, or through messaging integrations like Telegram/WhatsApp via webhook, processed by LLM, which calls tools if needed, then results are returned to original source.

**Recommended Tech Stack**:
- Frontend: React, TypeScript, Vite, Tailwind CSS for modern UI.
- Backend: FastAPI (Python), LangChain.
- Database: PostgreSQL + ChromaDB.
- Deployment: Docker for containerization, hosting on Vercel/Heroku for start.
- Security: HTTPS, sensitive data encryption, API rate limiting.

**Additional Considerations**:
- **Scalability**: Use cloud services if users increase.
- **Security**: All personal data encrypted; Google access via temporary tokens; JWT tokens with short expiration.
- **Development**: Start with MVP (authentication + dashboard), then iterate.