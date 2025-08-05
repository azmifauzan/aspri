# app/api/chat.py
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.database import get_db
from app.schemas.chat import (
    ChatSessionCreate, ChatSessionResponse, ChatMessageCreate,
    ChatMessageResponse, ChatSessionListResponse, ChatIntentRequest,
    ChatIntentResponse, ChatSessionCreateResponse, ChatSessionListResponseItem
)
from app.services.chat_service import ChatService
from app.core.auth import get_current_user_id
from typing import List
import logging

router = APIRouter(prefix="/chat", tags=["Chat"])

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@router.post("/sessions", response_model=ChatSessionCreateResponse)
async def create_chat_session(
    session_data: ChatSessionCreate,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Create a new chat session.
    Requires JWT authentication.
    """
    try:
        chat_service = ChatService(db)
        session = await chat_service.create_chat_session(current_user_id, session_data)
        return ChatSessionCreateResponse.model_validate(session)
    except Exception as e:
        logger.error(f"Error creating chat session: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to create chat session: {str(e)}"
        )

@router.get("/sessions", response_model=ChatSessionListResponse)
async def get_chat_sessions(
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Get all chat sessions for the current user.
    Requires JWT authentication.
    """
    try:
        chat_service = ChatService(db)
        sessions = await chat_service.get_user_chat_sessions(current_user_id)
        return ChatSessionListResponse(
            sessions=[ChatSessionListResponseItem.model_validate(session) for session in sessions]
        )
    except Exception as e:
        logger.error(f"Error fetching chat sessions: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to fetch chat sessions: {str(e)}"
        )

@router.get("/sessions/{session_id}", response_model=ChatSessionResponse)
async def get_chat_session(
    session_id: int,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Get a specific chat session with its messages.
    Requires JWT authentication.
    """
    try:
        chat_service = ChatService(db)
        session = await chat_service.get_chat_session(session_id, current_user_id)
        
        if not session:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Chat session not found"
            )
        
        return ChatSessionResponse.model_validate(session)
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error fetching chat session: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to fetch chat session: {str(e)}"
        )

@router.post("/sessions/{session_id}/messages", response_model=ChatMessageResponse)
async def send_chat_message(
    session_id: int,
    message_data: ChatMessageCreate,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Send a message in a chat session and get AI response.
    Requires JWT authentication.
    """
    try:
        chat_service = ChatService(db)
        # Send user message and get AI response
        response_message = await chat_service.send_message(
            session_id, current_user_id, message_data
        )
        return ChatMessageResponse.model_validate(response_message)
    except Exception as e:
        logger.error(f"Error sending chat message: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to send chat message: {str(e)}"
        )

@router.post("/intent", response_model=ChatIntentResponse)
async def classify_user_intent(
    intent_request: ChatIntentRequest,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Classify user intent from their message using Gemini.
    Requires JWT authentication.
    """
    try:
        chat_service = ChatService(db)
        intent = await chat_service.classify_user_intent(intent_request.message)
        return ChatIntentResponse(intent=intent)
    except Exception as e:
        logger.error(f"Error classifying user intent: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to classify user intent: {str(e)}"
        )

@router.put("/sessions/{session_id}/activate", response_model=ChatSessionResponse)
async def activate_chat_session(
    session_id: int,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Activate a chat session (set is_active to True).
    Requires JWT authentication.
    """
    try:
        chat_service = ChatService(db)
        session = await chat_service.activate_chat_session(session_id, current_user_id)
        
        if not session:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Chat session not found"
            )
        
        return ChatSessionResponse.model_validate(session)
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error activating chat session: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to activate chat session: {str(e)}"
        )

@router.delete("/sessions/{session_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_chat_session(
    session_id: int,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Delete a chat session and all its messages.
    Requires JWT authentication.
    """
    try:
        chat_service = ChatService(db)
        success = await chat_service.delete_chat_session(session_id, current_user_id)
        
        if not success:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Chat session not found"
            )
        
        return None
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error deleting chat session: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to delete chat session: {str(e)}"
        )