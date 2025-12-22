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
    
    def _get_user_collection_name(self, user_id: int) -> str:
        """Generate a collection name for a given user ID."""
        return f"user_{user_id}_collection"

    def _get_or_create_collection(self, user_id: int):
        """Get or create ChromaDB collection for a specific user."""
        collection_name = self._get_user_collection_name(user_id)
        try:
            # Check if collection exists
            collection = self.client.get_collection(
                name=collection_name,
                embedding_function=self.embedding_function
            )
            return collection
        except Exception:
            # Create new collection if it doesn't exist
            return self.client.create_collection(
                name=collection_name,
                metadata={"description": f"Document embeddings for user {user_id}"},
                embedding_function=self.embedding_function
            )
    
    async def add_document_embeddings(self, user_id: int, document_id: int, chunks: List[Dict[str, Any]]) -> List[str]:
        """Add document chunk embeddings to a user's ChromaDB collection."""
        collection = self._get_or_create_collection(user_id)
        ids = []
        documents = []
        metadatas = []
        
        for chunk in chunks:
            chunk_id = f"doc_{document_id}_chunk_{chunk['chunk_index']}"
            ids.append(chunk_id)
            documents.append(chunk['chunk_text'])
            metadatas.append({
                "document_id": document_id,
                "chunk_index": chunk['chunk_index'],
                "user_id": user_id,  # Ensure user_id is in metadata
                "filename": chunk.get('filename', ''),
                "file_type": chunk.get('file_type', '')
            })
        
        try:
            collection.add(
                ids=ids,
                documents=documents,
                metadatas=metadatas
            )
            return ids
        except Exception as e:
            raise Exception(f"Failed to add embeddings to ChromaDB: {e}")
    
    async def search_similar_chunks(self, user_id: int, query_text: str, limit: int = 10) -> List[Dict[str, Any]]:
        """Search for similar document chunks in a user's collection."""
        collection = self._get_or_create_collection(user_id)
        try:
            results = collection.query(
                query_texts=[query_text],
                n_results=limit,
                include=["documents", "metadatas", "distances"]
            )
            
            search_results = []
            if results['ids'] and len(results['ids'][0]) > 0:
                for i in range(len(results['ids'][0])):
                    search_results.append({
                        "chunk_id": results['ids'][0][i],
                        "chunk_text": results['documents'][0][i],
                        "similarity_score": 1 - results['distances'][0][i],
                        "document_id": results['metadatas'][0][i]['document_id'],
                        "chunk_index": results['metadatas'][0][i]['chunk_index'],
                        "document_filename": results['metadatas'][0][i].get('filename', ''),
                        "document_file_type": results['metadatas'][0][i].get('file_type', '')
                    })
            
            return search_results
        except Exception as e:
            raise Exception(f"Failed to search ChromaDB: {e}")
    
    async def update_document_embeddings(self, user_id: int, document_id: int, chunks: List[Dict[str, Any]]) -> List[str]:
        """Update document chunk embeddings in a user's collection."""
        await self.delete_document_embeddings(user_id, document_id)
        return await self.add_document_embeddings(user_id, document_id, chunks)
    
    async def delete_document_embeddings(self, user_id: int, document_id: int) -> bool:
        """Delete all embeddings for a document from a user's collection."""
        collection = self._get_or_create_collection(user_id)
        try:
            results = collection.get(
                where={"document_id": document_id},
                include=["metadatas"]
            )
            
            if results['ids']:
                collection.delete(ids=results['ids'])
            
            return True
        except Exception as e:
            print(f"Error deleting embeddings from ChromaDB: {e}")
            return False
    
    async def get_document_chunk_count(self, user_id: int, document_id: int) -> int:
        """Get the number of chunks for a document in a user's collection."""
        collection = self._get_or_create_collection(user_id)
        try:
            results = collection.get(
                where={"document_id": document_id},
                include=[]
            )
            return len(results['ids']) if results['ids'] else 0
        except Exception as e:
            print(f"Error getting chunk count from ChromaDB: {e}")
            return 0
    
    async def get_user_document_count(self, user_id: int) -> int:
        """Get the number of unique documents in a user's collection."""
        collection = self._get_or_create_collection(user_id)
        try:
            results = collection.get(include=["metadatas"])
            
            if not results['metadatas']:
                return 0
            
            document_ids = {metadata['document_id'] for metadata in results['metadatas']}
            return len(document_ids)
        except Exception as e:
            print(f"Error getting user document count from ChromaDB: {e}")
            return 0