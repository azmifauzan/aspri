# app/services/supabase_storage_service.py
from supabase import Client
from app.services.supabase_service import get_supabase_client
import io
from typing import Optional
from datetime import timedelta

class SupabaseStorageService:
    def __init__(self, bucket_name: str = "documents"):
        self.client: Client = get_supabase_client()
        self.bucket_name = bucket_name
        self._ensure_bucket_exists()

    def _ensure_bucket_exists(self):
        try:
            self.client.storage.get_bucket(self.bucket_name)
        except Exception:
            self.client.storage.create_bucket(self.bucket_name)

    async def upload_document(self, user_id: int, document_id: int, filename: str, file_content: bytes) -> str:
        object_name = f"users/{user_id}/documents/{document_id}/{filename}"
        try:
            self.client.storage.from_(self.bucket_name).upload(
                path=object_name,
                file=io.BytesIO(file_content),
                file_options={"content-type": self._get_content_type(filename)}
            )
            return object_name
        except Exception as e:
            raise Exception(f"Failed to upload document to Supabase Storage: {e}")

    async def download_document(self, object_name: str) -> bytes:
        try:
            response = self.client.storage.from_(self.bucket_name).download(object_name)
            return response
        except Exception as e:
            raise Exception(f"Failed to download document from Supabase Storage: {e}")

    async def delete_document(self, object_name: str) -> bool:
        try:
            self.client.storage.from_(self.bucket_name).remove([object_name])
            return True
        except Exception as e:
            print(f"Error deleting document from Supabase Storage: {e}")
            return False

    async def get_presigned_url(self, object_name: str, expires: int = 3600) -> str:
        try:
            return self.client.storage.from_(self.bucket_name).create_signed_url(object_name, expires)
        except Exception as e:
            raise Exception(f"Failed to generate presigned URL: {e}")

    def _get_content_type(self, filename: str) -> str:
        extension = filename.split('.')[-1].lower()
        content_types = {
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
        return content_types.get(extension, 'application/octet-stream')
