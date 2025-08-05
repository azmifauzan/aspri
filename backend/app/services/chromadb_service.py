# app/services/chromadb_service.py
import chromadb
from chromadb.config import Settings
from chromadb.utils import embedding_functions
import os
from typing import List, Dict, Any, Optional
import uuid

class ChromaDBService:
    def __init__(self):
        # Initialize ChromaDB client to connect to external server
        chromadb_host = os.getenv("CHROMADB_HOST", "localhost")
        chromadb_port = int(os.getenv("CHROMADB_PORT", "8000"))
        google_api_key = os.getenv("GOOGLE_API_KEY")
        self.embedding_function = embedding_functions.GoogleGenerativeAiEmbeddingFunction(
            api_key=google_api_key
        )
        
        self.client = chromadb.HttpClient(
            host=chromadb_host,
            port=chromadb_port,
            settings=Settings(
                anonymized_telemetry=False
            )
        )
        
        self.collection_name = os.getenv("CHROMADB_COLLECTION_NAME", "aspri_collection")
        self.collection = self._get_or_create_collection()
    
    def _get_or_create_collection(self):
        """Get or create ChromaDB collection with correct embedding dimensions"""
        try:
            collection = self.client.get_collection(name=self.collection_name,embedding_function=self.embedding_function)
            # Check if collection exists but has wrong dimensions
            # We'll handle this by catching the dimension error when adding embeddings
            return collection
        except Exception as e:
            if "does not exists" in str(e):
                # Create new collection with proper metadata
                return self.client.create_collection(
                    name=self.collection_name,
                    metadata={"description": "Document embeddings for semantic search"},
                    embedding_function=self.embedding_function
                )
            raise
    
    async def add_document_embeddings(self, document_id: int, chunks: List[Dict[str, Any]]) -> List[str]:
        """Add document chunk embeddings to ChromaDB"""
        ids = []
        #embeddings = []
        documents = []
        metadatas = []
        
        for chunk in chunks:
            chunk_id = f"doc_{document_id}_chunk_{chunk['chunk_index']}"
            ids.append(chunk_id)
            #embeddings.append(chunk['embedding'])
            documents.append(chunk['chunk_text'])
            metadatas.append({
                "document_id": document_id,
                "chunk_index": chunk['chunk_index'],
                "user_id": chunk.get('user_id'),
                "filename": chunk.get('filename', ''),
                "file_type": chunk.get('file_type', '')
            })
        
        try:
            self.collection.add(
                ids=ids,
                #embeddings=embeddings,
                documents=documents,
                metadatas=metadatas
            )
            return ids
        except Exception as e:
            # Check if it's a dimension mismatch error
            if "dimension" in str(e).lower() and ("768" in str(e) or "3072" in str(e)):
                print(f"Dimension mismatch detected: {e}")
                print("Recreating collection with correct dimensions...")
                try:
                    # Delete existing collection
                    self.client.delete_collection(name=self.collection_name)
                    # Create new collection
                    self.collection = self.client.create_collection(
                        name=self.collection_name,
                        metadata={"description": "Document embeddings for semantic search"}
                    )
                    # Retry adding embeddings
                    self.collection.add(
                        ids=ids,
                        #embeddings=embeddings,
                        documents=documents,
                        metadatas=metadatas
                    )
                    return ids
                except Exception as retry_error:
                    raise Exception(f"Failed to recreate collection and add embeddings: {retry_error}")
            else:
                raise Exception(f"Failed to add embeddings to ChromaDB: {e}")
    
    async def search_similar_chunks(self, query_text: List[float], user_id: int, limit: int = 10) -> List[Dict[str, Any]]:
        """Search for similar document chunks"""
        try:
            results = self.collection.query(
                query_texts=[query_text],
                n_results=limit,
                where={"user_id": user_id},
                include=["documents", "metadatas", "distances"]
            )
            
            print(f"Search results: {results}")  # Debugging line
            
            search_results = []
            if results['ids'] and len(results['ids'][0]) > 0:
                for i in range(len(results['ids'][0])):
                    search_results.append({
                        "chunk_id": results['ids'][0][i],
                        "chunk_text": results['documents'][0][i],
                        "similarity_score": 1 - results['distances'][0][i],  # Convert distance to similarity
                        "document_id": results['metadatas'][0][i]['document_id'],
                        "chunk_index": results['metadatas'][0][i]['chunk_index'],
                        "document_filename": results['metadatas'][0][i].get('filename', ''),
                        "document_file_type": results['metadatas'][0][i].get('file_type', '')
                    })
            
            return search_results
        except Exception as e:
            raise Exception(f"Failed to search ChromaDB: {e}")
    
    async def update_document_embeddings(self, document_id: int, chunks: List[Dict[str, Any]]) -> List[str]:
        """Update document chunk embeddings in ChromaDB"""
        # First, delete existing embeddings for this document
        await self.delete_document_embeddings(document_id)
        
        # Then add new embeddings
        return await self.add_document_embeddings(document_id, chunks)
    
    async def delete_document_embeddings(self, document_id: int) -> bool:
        """Delete all embeddings for a document"""
        try:
            # Get all chunk IDs for this document
            results = self.collection.get(
                where={"document_id": document_id},
                include=["metadatas"]
            )
            
            if results['ids']:
                self.collection.delete(ids=results['ids'])
            
            return True
        except Exception as e:
            print(f"Error deleting embeddings from ChromaDB: {e}")
            return False
    
    async def get_document_chunk_count(self, document_id: int) -> int:
        """Get the number of chunks for a document"""
        try:
            results = self.collection.get(
                where={"document_id": document_id},
                include=[]
            )
            return len(results['ids']) if results['ids'] else 0
        except Exception as e:
            print(f"Error getting chunk count from ChromaDB: {e}")
            return 0
    
    async def get_user_document_count(self, user_id: int) -> int:
        """Get the number of unique documents for a user"""
        try:
            results = self.collection.get(
                where={"user_id": user_id},
                include=["metadatas"]
            )
            
            if not results['metadatas']:
                return 0
            
            # Get unique document IDs
            document_ids = set()
            for metadata in results['metadatas']:
                document_ids.add(metadata['document_id'])
            
            return len(document_ids)
        except Exception as e:
            print(f"Error getting user document count from ChromaDB: {e}")
            return 0