# app/schemas/chat.py
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime

# Request Schemas
class ChatSessionCreate(BaseModel):
    title: str = Field(..., description="Title of the chat session")

class ChatMessageCreate(BaseModel):
    content: str = Field(..., description="Content of the message")
    role: str = Field(default="user", description="Role of the message sender (user or assistant)")
    message_type: str = Field(default="text", description="Type of the message (text, document_search, etc.)")

class ChatIntentRequest(BaseModel):
    message: str = Field(..., description="User message to classify intent")

# Response Schemas
class ChatMessageResponse(BaseModel):
    id: int
    chat_session_id: int
    content: str
    role: str
    message_type: str
    intent: Optional[str] = None
    created_at: datetime
    
    class Config:
        from_attributes = True

class ChatSessionResponse(BaseModel):
    id: int
    user_id: int
    title: str
    created_at: datetime
    updated_at: datetime
    is_active: bool
    messages: List[ChatMessageResponse] = []
    
    class Config:
        from_attributes = True

class ChatSessionCreateResponse(BaseModel):
    id: int
    user_id: int
    title: str
    created_at: datetime
    updated_at: datetime
    is_active: bool
    
    class Config:
        from_attributes = True

class ChatSessionListResponseItem(BaseModel):
    id: int
    user_id: int
    title: str
    created_at: datetime
    updated_at: datetime
    is_active: bool
    
    class Config:
        from_attributes = True

class ChatSessionListResponse(BaseModel):
    sessions: List[ChatSessionListResponseItem]

class ChatIntentResponse(BaseModel):
    intent: str = Field(..., description="Classified intent of the user message")