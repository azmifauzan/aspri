# app/services/document_service.py
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete, func, text
from sqlalchemy.orm import selectinload
import base64
import io
import os
import tempfile
import numpy as np
import mimetypes
import json
import os
from typing import List, Optional, Tuple, Dict, Any
from datetime import datetime
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Models
from app.db.models.document import Document, DocumentChunk
from app.schemas.document import DocumentUpload, DocumentUpdate, DocumentSearchQuery

# Langchain imports
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.document_loaders import PyPDFLoader, Docx2txtLoader, TextLoader

# OpenAI client
import openai

class DocumentService:
    def __init__(self, db: AsyncSession):
        self.db = db
        # Initialize the OpenAI client
        self.openai_client = openai.OpenAI(
            api_key=os.getenv("OPENAI_API_KEY", "your-openai-api-key-here")
        )
        self.embedding_model = os.getenv("OPENAI_EMBEDDING_MODEL", "text-embedding-3-small")
        
    def _get_embedding(self, text: str) -> List[float]:
        """Get embedding for text using OpenAI"""
        try:
            response = self.openai_client.embeddings.create(
                input=text,
                model=self.embedding_model
            )
            return response.data[0].embedding
        except Exception as e:
            print(f"Error getting embedding: {e}")
            # Fallback to zeros if embedding fails
            return [0.0] * 1536  # OpenAI embeddings are 1536-dimensional
    
    async def create_document(self, user_id: int, document_data: DocumentUpload) -> Document:
        """Create a new document with chunks and embeddings"""
        # Decode base64 file content
        file_content = base64.b64decode(document_data.file_content)
        file_size = len(file_content)
        
        # Create document record
        document = Document(
            user_id=user_id,
            filename=document_data.filename,
            file_type=document_data.file_type,
            file_size=file_size,
            content_blob=file_content,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow()
        )
        
        self.db.add(document)
        await self.db.flush()  # Get the document ID
        
        # Process document content, create chunks and embeddings
        chunks = await self._process_document(document.id, file_content, document_data.file_type)
        
        # Add all chunks to the database
        self.db.add_all(chunks)
        await self.db.commit()
        await self.db.refresh(document)
        
        return document
    
    async def get_document(self, document_id: int, user_id: int) -> Optional[Document]:
        """Get a document by ID for a specific user"""
        query = select(Document).where(
            Document.id == document_id,
            Document.user_id == user_id
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()
    
    async def get_document_with_chunks(self, document_id: int, user_id: int) -> Optional[Document]:
        """Get a document with its chunks by ID for a specific user"""
        query = select(Document).options(
            selectinload(Document.chunks)
        ).where(
            Document.id == document_id,
            Document.user_id == user_id
        )
        result = await self.db.execute(query)
        return result.scalar_one_or_none()
    
    async def get_user_documents(self, user_id: int, skip: int = 0, limit: int = 100) -> Tuple[List[Document], int]:
        """Get all documents for a specific user with pagination"""
        # Count total documents
        count_query = select(func.count()).select_from(Document).where(Document.user_id == user_id)
        total = await self.db.execute(count_query)
        total_count = total.scalar_one()
        
        # Get documents with pagination
        query = select(Document).where(
            Document.user_id == user_id
        ).order_by(Document.created_at.desc()).offset(skip).limit(limit)
        
        result = await self.db.execute(query)
        documents = result.scalars().all()
        
        return documents, total_count
    
    async def update_document(self, document_id: int, user_id: int, document_data: DocumentUpdate) -> Optional[Document]:
        """Update document metadata"""
        document = await self.get_document(document_id, user_id)
        if not document:
            return None
            
        # Update fields if provided
        if document_data.filename is not None:
            document.filename = document_data.filename
            
        document.updated_at = datetime.utcnow()
        await self.db.commit()
        await self.db.refresh(document)
        
        return document
    
    async def delete_document(self, document_id: int, user_id: int) -> bool:
        """Delete a document and its chunks"""
        document = await self.get_document(document_id, user_id)
        if not document:
            return False
            
        await self.db.delete(document)
        await self.db.commit()
        
        return True
    
    async def search_documents(self, user_id: int, search_query: DocumentSearchQuery) -> List[Dict[str, Any]]:
        """Search for documents using vector similarity with in-memory calculation"""
        # Get query embedding
        query_embedding = np.array(self._get_embedding(search_query.query))
        
        # Get all document chunks for the user
        query = select(DocumentChunk).join(Document).where(
            Document.user_id == user_id,
            DocumentChunk.embedding_vector.isnot(None)
        )
        
        result = await self.db.execute(query)
        chunks = result.scalars().all()
        
        # Calculate similarities in memory
        search_results = []
        for chunk in chunks:
            if chunk.embedding_vector:
                # Deserialize the stored embedding
                chunk_embedding = self._deserialize_embedding(chunk.embedding_vector)
                if chunk_embedding is not None:
                    # Calculate cosine similarity
                    similarity = self._calculate_similarity(query_embedding, chunk_embedding)
                    
                    search_results.append({
                        "document_id": chunk.document_id,
                        "chunk_id": chunk.id,
                        "chunk_text": chunk.chunk_text,
                        "similarity_score": float(similarity),
                        "document_filename": chunk.document.filename,
                        "document_file_type": chunk.document.file_type
                    })
        
        # Sort by similarity score (descending) and limit results
        search_results.sort(key=lambda x: x["similarity_score"], reverse=True)
        return search_results[:search_query.limit]
    
    async def _process_document(self, document_id: int, file_content: bytes, file_type: str) -> List[DocumentChunk]:
        """Process document content, split into chunks, and create embeddings"""
        # Extract text from document based on file type
        text = await self._extract_text(file_content, file_type)
        
        # Split text into chunks
        text_splitter = RecursiveCharacterTextSplitter(
            chunk_size=1000,
            chunk_overlap=200,
            length_function=len,
        )
        chunks = text_splitter.split_text(text)
        
        # Create document chunks with embeddings
        document_chunks = []
        for i, chunk_text in enumerate(chunks):
            # Create embedding for chunk
            embedding = self._get_embedding(chunk_text)
            embedding_bytes = self._serialize_embedding(np.array(embedding))
            
            # Create chunk object
            chunk = DocumentChunk(
                document_id=document_id,
                chunk_index=i,
                chunk_text=chunk_text,
                embedding_vector=embedding_bytes,
                created_at=datetime.utcnow()
            )
            document_chunks.append(chunk)
            
        return document_chunks
    
    async def _extract_text(self, file_content: bytes, file_type: str) -> str:
        """Extract text from different file types"""
        # Create a temporary file to work with
        with tempfile.NamedTemporaryFile(delete=False, suffix=f".{file_type}") as temp_file:
            temp_file.write(file_content)
            temp_path = temp_file.name
        
        try:
            # Detect file type using mimetypes (cross-platform)
            mime_type, _ = mimetypes.guess_type(f"file.{file_type}")
            if mime_type is None:
                # Fallback based on file extension
                mime_type = self._get_mime_type_from_extension(file_type)
            
            # Extract text based on file type
            if 'pdf' in mime_type or file_type.lower() == 'pdf':
                loader = PyPDFLoader(temp_path)
                documents = loader.load()
                text = " ".join([doc.page_content for doc in documents])
            elif 'word' in mime_type or file_type.lower() in ['docx', 'doc']:
                loader = Docx2txtLoader(temp_path)
                documents = loader.load()
                text = " ".join([doc.page_content for doc in documents])
            elif 'text' in mime_type or file_type.lower() in ['txt', 'md', 'json']:
                loader = TextLoader(temp_path)
                documents = loader.load()
                text = " ".join([doc.page_content for doc in documents])
            else:
                # Default to treating as plain text
                with open(temp_path, 'rb') as f:
                    text = f.read().decode('utf-8', errors='replace')
            
            return text
        finally:
            # Clean up temporary file
            if os.path.exists(temp_path):
                os.unlink(temp_path)
    
    def _serialize_embedding(self, embedding: np.ndarray) -> str:
        """Serialize numpy array to JSON string for MariaDB vector storage"""
        # Convert to list and then to JSON string
        return json.dumps(embedding.tolist())
    
    def _deserialize_embedding(self, embedding_json: str) -> np.ndarray:
        """Deserialize JSON string back to numpy array"""
        if embedding_json:
            return np.array(json.loads(embedding_json), dtype=np.float32)
        return None
    
    def _calculate_similarity(self, embedding1: np.ndarray, embedding2: np.ndarray) -> float:
        """Calculate cosine similarity between two embeddings"""
        # This is now handled by MariaDB's vector functions, but keeping for reference
        if embedding1 is None or embedding2 is None:
            return 0.0
        return np.dot(embedding1, embedding2) / (np.linalg.norm(embedding1) * np.linalg.norm(embedding2))
    
    def _get_mime_type_from_extension(self, file_type: str) -> str:
        """Get MIME type from file extension as fallback"""
        extension_map = {
            'pdf': 'application/pdf',
            'doc': 'application/msword',
            'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt': 'text/plain',
            'md': 'text/markdown',
            'json': 'application/json',
            'csv': 'text/csv',
            'html': 'text/html',
            'xml': 'application/xml'
        }
        return extension_map.get(file_type.lower(), 'text/plain')