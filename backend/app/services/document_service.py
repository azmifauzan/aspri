# app/services/document_service.py
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, delete, func
from sqlalchemy.orm import selectinload
import base64
import io
import os
import tempfile
import numpy as np
import mimetypes
from typing import List, Optional, Tuple, Dict, Any
from datetime import datetime
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Models
from app.db.models.document import Document, DocumentChunk
from app.schemas.document import DocumentUpload, DocumentUpdate, DocumentSearchQuery

# Services
from app.services.config_service import ConfigService
from app.services.minio_service import MinIOService
from app.services.chromadb_service import ChromaDBService

# Langchain imports
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.document_loaders import PyPDFLoader, Docx2txtLoader, TextLoader

# LangChain GenAI for embeddings
from langchain_google_genai import GoogleGenerativeAIEmbeddings

class DocumentService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.config_service = ConfigService(db)
        self.minio_service = MinIOService()
        self.chromadb_service = ChromaDBService()
        
        # Initialize LangChain GenAI embeddings
        self.embedding_model = GoogleGenerativeAIEmbeddings(
            model="models/gemini-embedding-001",
            google_api_key=os.getenv("GOOGLE_API_KEY", "your-google-api-key-here")
        )
        
    async def _get_embedding(self, text: str) -> List[float]:
        """Get embedding for text using LangChain GenAI"""
        try:
            embeddings = await self.embedding_model.aembed_query(text)
            return embeddings
        except Exception as e:
            print(f"Error getting embedding: {e}")
            # Fallback to zeros if embedding fails
            return [0.0] * 3072  # Google embeddings are 3072-dimensional
    
    async def _check_limits(self, user_id: int, file_size: int) -> Dict[str, Any]:
        """Check if file size and document count limits are exceeded"""
        limits = await self.config_service.get_document_limits()
        
        # Check file size limit
        if file_size > limits["max_file_size_bytes"]:
            return {
                "valid": False,
                "error": f"File size ({file_size} bytes) exceeds maximum allowed size ({limits['max_file_size_bytes']} bytes)"
            }
        
        # Check document count limit
        current_count = await self._get_user_document_count(user_id)
        if current_count >= limits["max_documents_per_user"]:
            return {
                "valid": False,
                "error": f"Document count ({current_count}) exceeds maximum allowed documents ({limits['max_documents_per_user']})"
            }
        
        return {"valid": True}
    
    async def _get_user_document_count(self, user_id: int) -> int:
        """Get current document count for user"""
        query = select(func.count()).select_from(Document).where(Document.user_id == user_id)
        result = await self.db.execute(query)
        return result.scalar_one()
    
    async def create_document(self, user_id: int, document_data: DocumentUpload) -> Document:
        """Create a new document with chunks and embeddings"""
        # Decode base64 file content
        file_content = base64.b64decode(document_data.file_content)
        file_size = len(file_content)
        
        # Check limits
        limit_check = await self._check_limits(user_id, file_size)
        if not limit_check["valid"]:
            raise ValueError(limit_check["error"])
        
        # Create document record
        document = Document(
            user_id=user_id,
            filename=document_data.filename,
            file_type=document_data.file_type,
            file_size=file_size,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow()
        )
        
        self.db.add(document)
        await self.db.flush()  # Get the document ID
        
        try:
            # Upload to MinIO
            minio_object_name = await self.minio_service.upload_document(
                user_id, document.id, document_data.filename, file_content
            )
            document.minio_object_name = minio_object_name
            
            # Process document content, create chunks and embeddings
            chunks_data = await self._process_document(document.id, file_content, document_data.file_type, user_id)
            
            # Store embeddings in ChromaDB
            await self.chromadb_service.add_document_embeddings(document.id, chunks_data)
            
            # Create document chunks in database (without embeddings)
            chunks = []
            for chunk_data in chunks_data:
                chunk = DocumentChunk(
                    document_id=document.id,
                    chunk_index=chunk_data['chunk_index'],
                    chunk_text=chunk_data['chunk_text'],
                    created_at=datetime.utcnow()
                )
                chunks.append(chunk)
            
            # Add all chunks to the database
            self.db.add_all(chunks)
            await self.db.commit()
            await self.db.refresh(document)
            
            return document
            
        except Exception as e:
            # Rollback on error
            await self.db.rollback()
            # Clean up MinIO if upload was successful
            if hasattr(document, 'minio_object_name') and document.minio_object_name:
                await self.minio_service.delete_document(document.minio_object_name)
            raise e
    
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
        """Update document metadata and re-process if content changed"""
        document = await self.get_document(document_id, user_id)
        if not document:
            return None
        
        # Check if file content is being updated
        if hasattr(document_data, 'file_content') and document_data.file_content:
            # Decode new file content
            file_content = base64.b64decode(document_data.file_content)
            file_size = len(file_content)
            
            # Check limits
            limit_check = await self._check_limits(user_id, file_size)
            if not limit_check["valid"]:
                raise ValueError(limit_check["error"])
            
            try:
                # Delete old embeddings from ChromaDB
                await self.chromadb_service.delete_document_embeddings(document_id)
                
                # Delete old document chunks from database
                await self.db.execute(
                    delete(DocumentChunk).where(DocumentChunk.document_id == document_id)
                )
                
                # Upload new content to MinIO
                if document.minio_object_name:
                    await self.minio_service.delete_document(document.minio_object_name)
                
                minio_object_name = await self.minio_service.upload_document(
                    user_id, document_id, document_data.filename or document.filename, file_content
                )
                document.minio_object_name = minio_object_name
                document.file_size = file_size
                
                # Process new document content
                chunks_data = await self._process_document(
                    document_id, file_content, 
                    document_data.file_type or document.file_type, 
                    user_id
                )
                
                # Store new embeddings in ChromaDB
                await self.chromadb_service.add_document_embeddings(document_id, chunks_data)
                
                # Create new document chunks in database
                chunks = []
                for chunk_data in chunks_data:
                    chunk = DocumentChunk(
                        document_id=document_id,
                        chunk_index=chunk_data['chunk_index'],
                        chunk_text=chunk_data['chunk_text'],
                        created_at=datetime.utcnow()
                    )
                    chunks.append(chunk)
                
                self.db.add_all(chunks)
                
            except Exception as e:
                await self.db.rollback()
                raise e
        
        # Update metadata fields if provided
        if document_data.filename is not None:
            document.filename = document_data.filename
            
        document.updated_at = datetime.utcnow()
        await self.db.commit()
        await self.db.refresh(document)
        
        return document
    
    async def delete_document(self, document_id: int, user_id: int) -> bool:
        """Delete a document and its chunks from all storage systems"""
        document = await self.get_document(document_id, user_id)
        if not document:
            return False
        
        try:
            # Delete embeddings from ChromaDB
            await self.chromadb_service.delete_document_embeddings(document_id)
            
            # Delete document from MinIO
            if document.minio_object_name:
                await self.minio_service.delete_document(document.minio_object_name)
            
            # Delete from database (cascades to chunks)
            await self.db.delete(document)
            await self.db.commit()
            
            return True
            
        except Exception as e:
            await self.db.rollback()
            print(f"Error deleting document: {e}")
            return False
    
    async def search_documents(self, user_id: int, search_query: DocumentSearchQuery) -> List[Dict[str, Any]]:
        """Search for documents using vector similarity with ChromaDB"""
        # Get query embedding
        #query_embedding = await self._get_embedding(search_query.query)
        
        # Search in ChromaDB
        search_results = await self.chromadb_service.search_similar_chunks(
            search_query.query, user_id, search_query.limit
        )
        
        return search_results
    
    async def _process_document(self, document_id: int, file_content: bytes, file_type: str, user_id: int) -> List[Dict[str, Any]]:
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
        chunks_data = []
        for i, chunk_text in enumerate(chunks):
            # Create embedding for chunk
            # embedding = await self._get_embedding(chunk_text)
            
            chunk_data = {
                "chunk_index": i,
                "chunk_text": chunk_text,
                # "embedding": embedding,
                "user_id": user_id,
                "document_id": document_id
            }
            chunks_data.append(chunk_data)
            
        return chunks_data
    
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