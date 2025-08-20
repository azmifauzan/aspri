# app/services/document_service.py
from supabase import Client
import base64
import io
import os
import tempfile
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
from app.services.supabase_storage_service import SupabaseStorageService
from app.services.supabase_vector_service import SupabaseVectorService
from app.services.supabase_db_service import SupabaseDBService

# Langchain imports
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.document_loaders import PyPDFLoader, Docx2txtLoader, TextLoader

# LangChain GenAI for embeddings
from langchain_google_genai import GoogleGenerativeAIEmbeddings

class DocumentService:
    def __init__(self, db: SupabaseDBService):
        self.db = db
        self.config_service = ConfigService(db)
        self.storage_service = SupabaseStorageService()
        self.vector_service = SupabaseVectorService()
        
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
    
    async def create_document(self, user_id: int, document_data: DocumentUpload) -> Dict[str, Any]:
        """Create a new document with chunks and embeddings in Supabase"""
        print("Creating document...")
        file_content = base64.b64decode(document_data.file_content)
        file_size = len(file_content)
        print(f"File size: {file_size}")

        limit_check = await self._check_limits(user_id, file_size)
        if not limit_check["valid"]:
            raise ValueError(limit_check["error"])
        print("Limits checked.")

        # Create document record in Supabase
        document_insert = {
            "user_id": user_id,
            "filename": document_data.filename,
            "file_type": document_data.file_type,
            "file_size": file_size,
        }
        print(f"Inserting document: {document_insert}")
        document = await self.db.insert('documents', document_insert)
        print(f"Inserted document: {document}")
        document_id = document[0]['id']

        try:
            # Upload to Supabase Storage
            print("Uploading to storage...")
            storage_object_name = await self.storage_service.upload_document(
                user_id, document_id, document_data.filename, file_content
            )
            print(f"Uploaded to storage: {storage_object_name}")
            await self.db.update('documents', {'id': document_id}, {'storage_object_name': storage_object_name})
            print("Updated document with storage object name.")

            # Process document and create embeddings
            print("Processing document...")
            chunks_data = await self._process_document(document_id, file_content, document_data.file_type, user_id)
            print(f"Processed {len(chunks_data)} chunks.")
            
            # Add embeddings to Supabase Vector
            print("Adding embeddings...")
            await self.vector_service.add_document_embeddings(user_id, document_id, chunks_data)
            print("Embeddings added.")

            # Create document chunks in Supabase
            print("Inserting chunks...")
            for chunk_data in chunks_data:
                chunk_insert = {
                    "document_id": document_id,
                    "chunk_index": chunk_data['chunk_index'],
                    "chunk_text": chunk_data['chunk_text'],
                }
                await self.db.insert('document_chunks', chunk_insert)
            print("Chunks inserted.")

            return document[0]

        except Exception as e:
            # Rollback by deleting the document record
            print(f"Error creating document: {e}")
            await self.db.delete('documents', {'id': document_id})
            if 'storage_object_name' in locals():
                await self.storage_service.delete_document(storage_object_name)
            raise e
    
    async def get_document(self, document_id: int, user_id: int) -> Optional[Dict[str, Any]]:
        """Get a document by ID for a specific user from Supabase"""
        return await self.db.select('documents', {'id': document_id, 'user_id': user_id})

    async def get_document_by_filename_and_user_id(self, filename: str, user_id: int) -> Optional[Dict[str, Any]]:
        """Get a document by filename for a specific user from Supabase"""
        return await self.db.select('documents', {'filename': filename, 'user_id': user_id})

    async def get_document_with_chunks(self, document_id: int, user_id: int) -> Optional[Dict[str, Any]]:
        """Get a document with its chunks by ID for a specific user from Supabase"""
        document = await self.get_document(document_id, user_id)
        if document:
            chunks = await self.db.select('document_chunks', {'document_id': document_id})
            document[0]['chunks'] = chunks
        return document

    async def get_user_documents(self, user_id: int, skip: int = 0, limit: int = 100) -> Tuple[List[Dict[str, Any]], int]:
        """Get all documents for a specific user with pagination from Supabase"""
        documents = await self.db.select('documents', {'user_id': user_id}, order_by='created_at', ascending=False, limit=limit, offset=skip)
        total_count = await self._get_user_document_count(user_id)
        return documents, total_count
    
    async def update_document(self, document_id: int, user_id: int, document_data: DocumentUpdate) -> Optional[Dict[str, Any]]:
        """Update document metadata and re-process if content changed in Supabase"""
        document = await self.get_document(document_id, user_id)
        if not document:
            return None

        if hasattr(document_data, 'file_content') and document_data.file_content:
            file_content = base64.b64decode(document_data.file_content)
            file_size = len(file_content)

            limit_check = await self._check_limits(user_id, file_size)
            if not limit_check["valid"]:
                raise ValueError(limit_check["error"])

            try:
                # Delete old embeddings and chunks
                await self.vector_service.delete_document_embeddings(user_id, document_id)
                await self.db.delete('document_chunks', {'document_id': document_id})

                # Upload new content
                if document[0]['storage_object_name']:
                    await self.storage_service.delete_document(document[0]['storage_object_name'])
                
                storage_object_name = await self.storage_service.upload_document(
                    user_id, document_id, document_data.filename or document[0]['filename'], file_content
                )

                # Process new content and add embeddings
                chunks_data = await self._process_document(
                    document_id, file_content, 
                    document_data.file_type or document[0]['file_type'],
                    user_id
                )
                await self.vector_service.add_document_embeddings(user_id, document_id, chunks_data)

                # Create new chunks
                for chunk_data in chunks_data:
                    await self.db.insert('document_chunks', {
                        "document_id": document_id,
                        "chunk_index": chunk_data['chunk_index'],
                        "chunk_text": chunk_data['chunk_text'],
                    })

                # Update document record
                update_data = {
                    "file_size": file_size,
                    "storage_object_name": storage_object_name,
                    "updated_at": datetime.utcnow().isoformat()
                }
                if document_data.filename:
                    update_data['filename'] = document_data.filename
                
                await self.db.update('documents', {'id': document_id}, update_data)

            except Exception as e:
                # This part needs careful handling of rollback in Supabase
                raise e
        else:
            # Update metadata only
            update_data = {
                "updated_at": datetime.utcnow().isoformat()
            }
            if document_data.filename:
                update_data['filename'] = document_data.filename
            await self.db.update('documents', {'id': document_id}, update_data)

        return await self.get_document(document_id, user_id)
    
    async def delete_document(self, document_id: int, user_id: int) -> bool:
        """Delete a document and its chunks from all Supabase storage systems"""
        document = await self.get_document(document_id, user_id)
        if not document:
            return False

        try:
            # Delete embeddings from Supabase Vector
            await self.vector_service.delete_document_embeddings(user_id, document_id)

            # Delete document from Supabase Storage
            if document[0]['storage_object_name']:
                await self.storage_service.delete_document(document[0]['storage_object_name'])

            # Delete from Supabase database
            await self.db.delete('document_chunks', {'document_id': document_id})
            await self.db.delete('documents', {'id': document_id})

            return True

        except Exception as e:
            print(f"Error deleting document: {e}")
            return False
    
    async def search_documents(self, user_id: int, search_query: DocumentSearchQuery) -> List[Dict[str, Any]]:
        """Search for documents using vector similarity with Supabase"""
        query_embedding = await self._get_embedding(search_query.query)

        search_results = await self.vector_service.search_similar_chunks(
            user_id, query_embedding, search_query.limit
        )
        
        return search_results

    async def _get_user_document_count(self, user_id: int) -> int:
        """Get current document count for user from Supabase"""
        return await self.db.count('documents', {'user_id': user_id})
    
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