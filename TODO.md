# ASPRI Development Plan

This document tracks the development progress and future plans for the ASPRI application.

## Completed Tasks

### Frontend
- [x] Implement authentication functionality (Login & Register).
- [x] Connect CTA buttons to appropriate registration/login pages.
- [x] Build main dashboard page after user login.
- [x] Develop functional chat interface.
- [x] Create UI components for Documents and Chat features.

### Backend
- [x] Set up Python server with FastAPI.
- [x] Create API endpoints for authentication (JWT).
- [x] Build API for document upload and management (MinIO and ChromaDB integration).
- [x] Integrate LangChain with Google Gemini for chat and embeddings.
- [x] Implement configuration management API.

### General
- [x] Set up database (MariaDB and ChromaDB).
- [x] Configure Docker for containerization of all services.
- [x] Update all technical documentation to reflect the current status.

## Ongoing & Future Tasks

### Frontend
- [ ] Refine state management for chat and documents.
- [ ] Create UI components for planned features (e.g., Calendar, Finance).
- [ ] Enhance UI/UX based on user feedback.

### Backend
- [ ] Implement API for Schedule feature (e.g., Google Calendar integration).
- [ ] Develop API for Finance feature (CRUD to database).
- [ ] Enhance chat functionality with more advanced features (e.g., tool use).

### General
- [ ] Expand unit and integration test coverage for frontend and backend.
- [ ] Set up a CI/CD pipeline for automated testing and deployment.
- [ ] Perform initial deployment to a cloud platform.