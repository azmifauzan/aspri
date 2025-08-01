# ASPRI Development Plan

Here is the task list for the next development of the ASPRI application.

## Frontend
- [ ] Implement authentication functionality (Login & Register).
- [ ] Connect CTA buttons to appropriate registration/login pages.
- [ ] Build main dashboard page after user login.
- [ ] Develop functional chat interface with WebSocket.
- [ ] Create UI components for each feature (Calendar, Finance, Documents).
- [ ] Add state management (Redux or Zustand) to manage application data.

## Backend
- [ ] Set up Node.js server with Express or FastAPI.
- [ ] Create API endpoints for authentication (JWT).
- [ ] Implement API for Schedule feature (Google Calendar integration).
- [ ] Develop API for Finance feature (CRUD to database).
- [ ] Build API for document upload and management (VectorDB integration).
- [ ] Integrate LangChain/LlamaIndex to connect LLM with tools.

## General
- [ ] Set up database (MongoDB/PostgreSQL and ChromaDB).
- [ ] Configure Docker for containerization.
- [ ] Write unit tests and integration tests for frontend and backend.
- [ ] Perform initial deployment to platforms like Vercel or Heroku.