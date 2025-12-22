# app/schemas/document.py
from pydantic import BaseModel, Field
from typing import Optional, List, Dict, Any
from datetime import datetime
import base64

# Request Schemas
class DocumentUploadBase(BaseModel):
    filename: str = Field(..., description="Original filename of the document")
    file_type: str = Field(..., description="File type (e.g., 'pdf', 'docx')")
    
class DocumentUpload(DocumentUploadBase):
    file_content: str = Field(..., description="Base64 encoded file content")

class DocumentUpdate(BaseModel):
    filename: Optional[str] = Field(None, description="New filename for the document")

# Response Schemas
class DocumentChunkResponse(BaseModel):
    id: int
    chunk_index: int
    chunk_text: str
    created_at: datetime
    
    class Config:
        from_attributes = True

class DocumentResponse(BaseModel):
    id: int
    user_id: int
    filename: str
    file_type: str
    file_size: int
    created_at: datetime
    updated_at: datetime
    
    class Config:
        from_attributes = True

class DocumentDetailResponse(DocumentResponse):
    chunks: List[DocumentChunkResponse] = []
    
    class Config:
        from_attributes = True

class DocumentListResponse(BaseModel):
    total: int
    documents: List[DocumentResponse]

# Search Schemas
class DocumentSearchQuery(BaseModel):
    query: str = Field(..., description="Search query text")
    limit: int = Field(5, description="Maximum number of results to return")

class SearchResultChunk(BaseModel):
    document_id: int
    chunk_id: int
    chunk_text: str
    similarity_score: float
    document_filename: str
    document_file_type: str

class DocumentSearchResponse(BaseModel):
    results: List[SearchResultChunk]
    query: str