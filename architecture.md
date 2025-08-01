# Personal Assistant (Aspri)

## Application Concept

**Main Objective**: ASPRI is an AI-based personal assistant that helps users manage their daily lives through an intuitive chat interface. The application uses LLM (such as GPT-series or open-source models like Llama) to process natural requests, with access to users' personal data for contextual and accurate responses. The core concept is "chat-first" where all features are accessed through conversation, similar to frontier LLMs like ChatGPT, but enhanced with specialized tools for internal data (schedules, finances, documents).

**Key Features**:
1. **Schedule Management**: Users can add, edit, or delete schedules through chat (e.g., "Add meeting tomorrow at 10 AM"). Two-way synchronization with Google Calendar using API for real-time updates.
2. **Financial Tracking**: Track income and expenses (e.g., "Record expense of $100 for food"). Monthly summary features, predictions, or alerts through LLM.
3. **Personal Document Management**: Upload and embed documents (PDF, text) to VectorDB for semantic search. LLM can summarize or answer questions based on documents (e.g., "What's in my contract?").
4. **Main Chat**: Central interface like LLM chatbot, with additional tools:
   - Schedule Tool: Access/mutate schedule data.
   - Finance Tool: Query/financial transactions.
   - Document Tool: Retrieval and analysis of documents from VectorDB.
   All features supported by LLM for natural language processing, with fallback to manual UI if needed.

**User Flow**:
- Secure login (OAuth or email).
- Main menu: Chat window with initial prompt.
- User asks/commands via text (e.g., "Schedule vacation next week and record today's expenses").
- LLM parses request, calls relevant tools, and responds with results.
- Side dashboard for manual view (calendar schedule, financial charts, document list).

**Design Principles**: User-centric, privacy-first (data stored locally/encrypted), and extensible (easy to add new tools).

## Application Architecture

Architecture adopts client-server pattern with microservices for modulation. Frontend focuses on interactive UI, backend handles business logic, integration, and LLM. Data flow: User → Frontend → Backend API → LLM/Tools → Response.

**Main Components**:
1. **Frontend**:
   - Framework: React.js (or Vue.js) with state management (Redux) for real-time chat.
   - UI: Chat interface (similar to WhatsApp), dashboard for manual features, document upload.
   - Integration: WebSocket for live chat, API calls to backend.

2. **Backend**:
   - Framework: Node.js with Express.
   - API: RESTful endpoints for authentication, features (e.g., /schedule, /finance, /documents).
   - LLM Integration: LangChain or LlamaIndex for chaining LLM with custom tools (e.g., tool for querying VectorDB or Google API).
   - LLM Tools: 
     - Schedule: Google Calendar API integration (OAuth2 for auth).
     - Finance: CRUD operations to database.
     - Documents: Embedding using models like Sentence Transformers, store to VectorDB.

3. **Database**:
   - Relational/NoSQL: MongoDB or PostgreSQL for structural data (schedules, finances).
   - VectorDB: ChromaDB (open-source) or Pinecone for document embedding (text vectorization for RAG - Retrieval-Augmented Generation).

4. **External Integrations**:
   - Google Calendar API: For schedule sync (use Google SDK).
   - LLM Provider: OpenAI API or Hugging Face for local models (for privacy).
   - Authentication: JWT or Firebase Auth for security.
- **Messaging Apps**: Integration with Telegram Bot API and WhatsApp Business API to enable chat interaction through external platforms. Backend will handle incoming webhooks from both APIs, route messages to LLM engine for processing, and send responses back.

**High-Level Architecture Diagram** (Text Description):
- **User** ↔ **Frontend (React)** ↔ **Backend (Node.js/Express)** 
  - Backend → **LLM Engine (LangChain)** → **Tools**:
    - **Schedule Tool** → Google Calendar API
    - **Finance Tool** → MongoDB
    - **Document Tool** → VectorDB (Chroma)
- Data Flow: Chat requests can originate from frontend app, or through messaging integrations like Telegram/WhatsApp via webhook, processed by LLM, which calls tools if needed, then results are returned to original source.

**Recommended Tech Stack**:
- Frontend: React, Tailwind CSS for modern UI.
- Backend: Node.js, LangChain.
- Database: MongoDB + ChromaDB.
- Deployment: Docker for containerization, hosting on Vercel/Heroku for start.
- Security: HTTPS, sensitive data encryption, API rate limiting.

**Additional Considerations**:
- **Scalability**: Use cloud services if users increase.
- **Security**: All personal data encrypted; Google access via temporary tokens.
- **Development**: Start with MVP (chat + one feature), then iterate.