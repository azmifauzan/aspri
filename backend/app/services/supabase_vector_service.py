# app/services/supabase_vector_service.py
from supabase import Client
from app.services.supabase_service import get_supabase_client
from typing import List, Dict, Any, Optional
import uuid
import os
from langchain_google_genai import GoogleGenerativeAIEmbeddings

class SupabaseVectorService:
    def __init__(self):
        self.client: Client = get_supabase_client()
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
            return [0.0] * 768

    async def add_document_embeddings(self, user_id: int, document_id: int, chunks: List[Dict[str, Any]]) -> List[str]:
        """Add document chunk embeddings to Supabase."""
        embeddings_to_insert = []
        for chunk in chunks:
            embedding = await self._get_embedding(chunk['chunk_text'])
            embeddings_to_insert.append({
                "document_id": document_id,
                "user_id": user_id,
                "chunk_index": chunk['chunk_index'],
                "chunk_text": chunk['chunk_text'],
                "embedding": embedding,
            })

        response = await self.client.from_('document_embeddings').insert(embeddings_to_insert).execute()
        return [item['id'] for item in response.data]

    async def search_similar_chunks(self, user_id: int, query_text: str, limit: int = 10) -> List[Dict[str, Any]]:
        """Search for similar document chunks in a user's collection."""
        embedding = await self._get_embedding(query_text)

        results = await self.client.rpc(
            'match_document_embeddings',
            {
                'query_embedding': embedding,
                'match_threshold': 0.7,
                'match_count': limit,
                'p_user_id': user_id,
            }
        ).execute()

        return results.data

    async def update_document_embeddings(self, user_id: int, document_id: int, chunks: List[Dict[str, Any]]) -> List[str]:
        """Update document chunk embeddings in a user's collection."""
        await self.delete_document_embeddings(user_id, document_id)
        return await self.add_document_embeddings(user_id, document_id, chunks)

    async def delete_document_embeddings(self, user_id: int, document_id: int) -> bool:
        """Delete all embeddings for a document from a user's collection."""
        await self.client.from_('document_embeddings').delete().match({'document_id': document_id, 'user_id': user_id}).execute()
        return True

    async def get_document_chunk_count(self, user_id: int, document_id: int) -> int:
        """Get the number of chunks for a document in a user's collection."""
        response = await self.client.from_('document_embeddings').select('id', count='exact').match({'document_id': document_id, 'user_id': user_id}).execute()
        return response.count

    async def get_user_document_count(self, user_id: int) -> int:
        """Get the number of unique documents in a user's collection."""
        response = await self.client.from_('documents').select('id', count='exact').eq('user_id', user_id).execute()
        return response.count
