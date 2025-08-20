# app/services/config_service.py
from app.services.supabase_db_service import SupabaseDBService
from typing import Optional, Dict, Any
import json

class ConfigService:
    def __init__(self, db: SupabaseDBService):
        self.db = db
    
    async def get_config(self, config_key: str) -> Optional[Any]:
        """Get configuration value by key from Supabase"""
        config = await self.db.select('configurations', {'config_key': config_key, 'is_active': True})
        
        if not config:
            return None
        
        config = config[0]
        # Parse value based on data type
        return self._parse_config_value(config['config_value'], config['data_type'])
    
    async def set_config(self, config_key: str, config_value: Any, description: str = None, data_type: str = "string") -> Dict[str, Any]:
        """Set or update configuration value in Supabase"""
        config = await self.db.select('configurations', {'config_key': config_key})
        
        value_str = json.dumps(config_value) if data_type == "json" else str(config_value)

        if config:
            update_data = {
                "config_value": value_str,
                "data_type": data_type,
            }
            if description:
                update_data['description'] = description
            return await self.db.update('configurations', {'config_key': config_key}, update_data)
        else:
            insert_data = {
                "config_key": config_key,
                "config_value": value_str,
                "description": description,
                "data_type": data_type,
            }
            return await self.db.insert('configurations', insert_data)

    async def get_document_limits(self) -> Dict[str, Any]:
        """Get document-related configuration limits from Supabase"""
        max_file_size = await self.get_config("max_file_size_bytes") or 50 * 1024 * 1024
        max_documents_per_user = await self.get_config("max_documents_per_user") or 100
        
        return {
            "max_file_size_bytes": max_file_size,
            "max_documents_per_user": max_documents_per_user,
        }

    async def initialize_default_configs(self):
        """Initialize default configuration values in Supabase"""
        default_configs = [
            {"config_key": "max_file_size_bytes", "config_value": 50 * 1024 * 1024, "data_type": "integer"},
            {"config_key": "max_documents_per_user", "config_value": 100, "data_type": "integer"},
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
                return value.lower() in ("true", "1", "yes")
            elif data_type == "json":
                return json.loads(value)
            return value
        except (ValueError, json.JSONDecodeError):
            return value