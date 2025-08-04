# app/services/minio_service.py
from minio import Minio
from minio.error import S3Error
import os
import io
from typing import Optional
from datetime import timedelta

class MinIOService:
    def __init__(self):
        self.client = Minio(
            endpoint=os.getenv("MINIO_ENDPOINT", "localhost:9000"),
            access_key=os.getenv("MINIO_ACCESS_KEY", "minioadmin"),
            secret_key=os.getenv("MINIO_SECRET_KEY", "minioadmin"),
            secure=os.getenv("MINIO_SECURE", "false").lower() == "true"
        )
        self.bucket_name = os.getenv("MINIO_BUCKET_NAME", "documents")
        self._ensure_bucket_exists()
    
    def _ensure_bucket_exists(self):
        """Ensure the bucket exists, create if it doesn't"""
        try:
            if not self.client.bucket_exists(self.bucket_name):
                self.client.make_bucket(self.bucket_name)
        except S3Error as e:
            print(f"Error creating bucket: {e}")
    
    async def upload_document(self, user_id: int, document_id: int, filename: str, file_content: bytes) -> str:
        """Upload document to MinIO and return object name"""
        # Create object name with user_id and document_id for organization
        object_name = f"users/{user_id}/documents/{document_id}/{filename}"
        
        try:
            # Upload file
            self.client.put_object(
                bucket_name=self.bucket_name,
                object_name=object_name,
                data=io.BytesIO(file_content),
                length=len(file_content),
                content_type=self._get_content_type(filename)
            )
            return object_name
        except S3Error as e:
            raise Exception(f"Failed to upload document to MinIO: {e}")
    
    async def download_document(self, object_name: str) -> bytes:
        """Download document from MinIO"""
        try:
            response = self.client.get_object(self.bucket_name, object_name)
            return response.read()
        except S3Error as e:
            raise Exception(f"Failed to download document from MinIO: {e}")
        finally:
            if 'response' in locals():
                response.close()
                response.release_conn()
    
    async def delete_document(self, object_name: str) -> bool:
        """Delete document from MinIO"""
        try:
            self.client.remove_object(self.bucket_name, object_name)
            return True
        except S3Error as e:
            print(f"Error deleting document from MinIO: {e}")
            return False
    
    async def get_presigned_url(self, object_name: str, expires: timedelta = timedelta(hours=1)) -> str:
        """Get presigned URL for document access"""
        try:
            return self.client.presigned_get_object(
                bucket_name=self.bucket_name,
                object_name=object_name,
                expires=expires
            )
        except S3Error as e:
            raise Exception(f"Failed to generate presigned URL: {e}")
    
    def _get_content_type(self, filename: str) -> str:
        """Get content type based on file extension"""
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