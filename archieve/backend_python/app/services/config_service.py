# app/services/config_service.py
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from app.db.models.config import Configuration
from typing import Optional, Dict, Any
import json

class ConfigService:
    def __init__(self, db: AsyncSession):
        self.db = db
    
    async def get_config(self, config_key: str) -> Optional[Any]:
        """Get configuration value by key"""
        query = select(Configuration).where(
            Configuration.config_key == config_key,
            Configuration.is_active == True
        )
        result = await self.db.execute(query)
        config = result.scalar_one_or_none()
        
        if not config:
            return None
        
        # Parse value based on data type
        return self._parse_config_value(config.config_value, config.data_type)
    
    async def set_config(self, config_key: str, config_value: Any, description: str = None, data_type: str = "string") -> Configuration:
        """Set or update configuration value"""
        # Check if config exists
        query = select(Configuration).where(Configuration.config_key == config_key)
        result = await self.db.execute(query)
        config = result.scalar_one_or_none()
        
        # Convert value to string for storage
        value_str = self._serialize_config_value(config_value, data_type)
        
        if config:
            # Update existing config
            config.config_value = value_str
            config.data_type = data_type
            if description:
                config.description = description
        else:
            # Create new config
            config = Configuration(
                config_key=config_key,
                config_value=value_str,
                description=description,
                data_type=data_type
            )
            self.db.add(config)
        
        await self.db.commit()
        await self.db.refresh(config)
        return config
    
    async def get_document_limits(self) -> Dict[str, Any]:
        """Get document-related configuration limits"""
        max_file_size = await self.get_config("max_file_size_bytes") or 50 * 1024 * 1024  # 50MB default
        max_documents_per_user = await self.get_config("max_documents_per_user") or 100  # 100 docs default
        
        return {
            "max_file_size_bytes": max_file_size,
            "max_documents_per_user": max_documents_per_user
        }
    
    async def initialize_default_configs(self):
        """Initialize default configuration values"""
        default_configs = [
            {
                "config_key": "max_file_size_bytes",
                "config_value": 50 * 1024 * 1024,  # 50MB
                "description": "Maximum file size allowed for document upload in bytes",
                "data_type": "integer"
            },
            {
                "config_key": "max_documents_per_user",
                "config_value": 100,
                "description": "Maximum number of documents a user can store",
                "data_type": "integer"
            },
            {
                "config_key": "minio_bucket_name",
                "config_value": "documents",
                "description": "MinIO bucket name for document storage",
                "data_type": "string"
            },
            {
                "config_key": "chromadb_collection_name",
                "config_value": "document_embeddings",
                "description": "ChromaDB collection name for document embeddings",
                "data_type": "string"
            }
        ]
        
        for config_data in default_configs:
            await self.set_config(**config_data)
    
    def _parse_config_value(self, value: str, data_type: str) -> Any:
        """Parse configuration value based on data type"""
        try:
            if data_type == "integer":
                return int(value)
            elif data_type == "float":
                return float(value)
            elif data_type == "boolean":
                return value.lower() in ("true", "1", "yes", "on")
            elif data_type == "json":
                return json.loads(value)
            else:  # string
                return value
        except (ValueError, json.JSONDecodeError):
            return value  # Return as string if parsing fails
    
    def _serialize_config_value(self, value: Any, data_type: str) -> str:
        """Serialize configuration value to string for storage"""
        if data_type == "json":
            return json.dumps(value)
        else:
            return str(value)