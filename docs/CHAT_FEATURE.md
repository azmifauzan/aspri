# Chat Feature Implementation

## Overview

This document describes the implementation of the chat feature for the ASPRI application. The chat feature allows users to have conversations with an AI assistant powered by Google's Gemini 2.5 Flash model and search through their uploaded documents.

## Features

1. **Chat Interface**: A complete chat UI with message bubbles, session management, and input area
2. **Chat History**: Users can view previous conversations and reactivate sessions
3. **Intent Classification**: Backend identifies user intent using Gemini (chat vs document search)
4. **Document Search**: Integrated with ChromaDB for searching uploaded documents
5. **Gemini Integration**: Used Gemini 2.5 Flash for both intent classification and chat responses

## Backend Implementation

### Database Models

The chat feature uses two main database models:

1. **ChatSession**: Represents a chat session with metadata
2. **ChatMessage**: Represents individual messages within a session

### API Endpoints

The following API endpoints are available:

- `POST /chat/sessions` - Create a new chat session
- `GET /chat/sessions` - Get all chat sessions for the current user
- `GET /chat/sessions/{session_id}` - Get a specific chat session with its messages
- `POST /chat/sessions/{session_id}/messages` - Send a message in a chat session and get AI response
- `POST /chat/intent` - Classify user intent from their message using Gemini
- `PUT /chat/sessions/{session_id}/activate` - Activate a chat session
- `DELETE /chat/sessions/{session_id}` - Delete a chat session and all its messages

### Services

The chat service handles:

1. **Chat Session Management**: Creating, retrieving, and deleting chat sessions
2. **Message Handling**: Sending messages and receiving AI responses
3. **Intent Classification**: Using Gemini to classify user intent
4. **Document Search Integration**: Searching documents using ChromaDB when user intent is document search

## Frontend Implementation

### Components

1. **ChatPage**: Main chat interface component
2. **ChatBubble**: Individual message bubble component

### Integration

The frontend integrates with the backend through:

1. **API Calls**: Using axios to communicate with backend endpoints
2. **Authentication**: Using the existing AuthContext for JWT token management
3. **Real-time Updates**: Displaying messages as they are sent and received

### Fixed Issues

1. **API Authorization**: Fixed issues with API calls not including proper Authorization headers
2. **Error Handling**: Added comprehensive error handling with user feedback
3. **Component State**: Improved state management for chat sessions and messages

## Setup Instructions

### Backend Setup

1. Ensure all dependencies are installed:
   ```bash
   pip install -r requirements.txt
   ```

2. Run database migrations to create chat tables:
   ```bash
   alembic upgrade head
   ```

3. Ensure environment variables are set:
   - `GOOGLE_API_KEY`: Your Google AI API key for Gemini access

### Frontend Setup

1. Ensure all dependencies are installed:
   ```bash
   npm install
   ```

2. Start the development server:
   ```bash
   npm run dev
   ```

## Testing

### Backend Tests

Run the backend tests with:
```bash
python -m pytest backend/tests/test_chat_api.py
```

### Frontend Tests

Run the frontend tests with:
```bash
npm test
```

### Manual Testing

To manually test the chat functionality:

1. Ensure the backend is running on port 8888
2. Log in to the application
3. Navigate to the Chat section
4. Try creating a new session and sending a message

You can also run the test script in `frontend/src/components/__tests__/chat-functional-test.js` in the browser console.

## Troubleshooting

### Common Issues

1. **Authentication Errors**: Ensure the JWT token is properly set in the AuthContext
2. **API Connection Issues**: Verify the backend is running and accessible on port 8888
3. **Gemini API Errors**: Check that the GOOGLE_API_KEY is set correctly
4. **Authorization Header Issues**: Ensure all API calls include the Authorization header

### Database Issues

If you encounter database issues:

1. Ensure the database migration has been run
2. Check that the database connection settings are correct
3. Verify that the database user has proper permissions

## Future Enhancements

1. **Enhanced Intent Classification**: Add more categories for user intent
2. **Advanced Document Search**: Improve search algorithms and result ranking
3. **Chat Analytics**: Add analytics for user chat patterns
4. **Multi-language Support**: Extend support for more languages in chat responses