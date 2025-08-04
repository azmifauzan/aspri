# app/db/models/document.py
from sqlalchemy import String, Integer, ForeignKey, Text, LargeBinary, DateTime, func
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.db.base import Base
from datetime import datetime
from typing import Optional, List
import numpy as np

class Document(Base):
    __tablename__ = "documents"

    id: Mapped[int] = mapped_column(primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id", ondelete="CASCADE"), nullable=False)
    
    # Document metadata
    filename: Mapped[str] = mapped_column(String(255), nullable=False)
    file_type: Mapped[str] = mapped_column(String(50), nullable=False)  # PDF, DOCX, etc.
    file_size: Mapped[int] = mapped_column(Integer, nullable=False)  # Size in bytes
    
    # Original content storage (optional, could store in file system instead)
    content_blob: Mapped[Optional[bytes]] = mapped_column(LargeBinary, nullable=True)
    
    # Timestamps
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )
    
    # Relationships
    chunks: Mapped[List["DocumentChunk"]] = relationship(
        "DocumentChunk", back_populates="document", cascade="all, delete-orphan"
    )
    
    # Relationship with User
    user = relationship("User", back_populates="documents")


class DocumentChunk(Base):
    __tablename__ = "document_chunks"
    
    id: Mapped[int] = mapped_column(primary_key=True)
    document_id: Mapped[int] = mapped_column(ForeignKey("documents.id", ondelete="CASCADE"), nullable=False)
    
    # Chunk content and metadata
    chunk_index: Mapped[int] = mapped_column(Integer, nullable=False)
    chunk_text: Mapped[str] = mapped_column(Text, nullable=False)
    
    # Vector embedding storage as JSON string (compatible with all databases)
    # Using 1536 dimensions for OpenAI text-embedding-3-small model
    embedding_vector: Mapped[Optional[str]] = mapped_column(
        Text, nullable=True
    )
    
    # Timestamps
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    
    # Relationships
    document: Mapped["Document"] = relationship("Document", back_populates="chunks")