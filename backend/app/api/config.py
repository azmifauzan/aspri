# app/api/config.py
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.database import get_db
from app.services.config_service import ConfigService
from app.core.auth import get_current_user_id
from typing import Dict, Any
from pydantic import BaseModel

router = APIRouter(prefix="/config", tags=["Configuration"])

class ConfigResponse(BaseModel):
    config_key: str
    config_value: Any
    description: str
    data_type: str
    is_active: bool

class ConfigUpdate(BaseModel):
    config_value: Any
    description: str = None

@router.get("/limits", response_model=Dict[str, Any])
async def get_document_limits(
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Get document upload limits.
    Requires JWT authentication.
    """
    config_service = ConfigService(db)
    limits = await config_service.get_document_limits()
    return limits

@router.get("/{config_key}")
async def get_config(
    config_key: str,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Get configuration value by key.
    Requires JWT authentication.
    """
    config_service = ConfigService(db)
    config_value = await config_service.get_config(config_key)
    
    if config_value is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Configuration '{config_key}' not found"
        )
    
    return {"config_key": config_key, "config_value": config_value}

@router.put("/{config_key}")
async def update_config(
    config_key: str,
    config_data: ConfigUpdate,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Update configuration value.
    Requires JWT authentication.
    Note: This endpoint might be restricted to admin users in production.
    """
    try:
        config_service = ConfigService(db)
        
        # Determine data type based on value
        data_type = "string"
        if isinstance(config_data.config_value, int):
            data_type = "integer"
        elif isinstance(config_data.config_value, float):
            data_type = "float"
        elif isinstance(config_data.config_value, bool):
            data_type = "boolean"
        elif isinstance(config_data.config_value, (dict, list)):
            data_type = "json"
        
        config = await config_service.set_config(
            config_key=config_key,
            config_value=config_data.config_value,
            description=config_data.description,
            data_type=data_type
        )
        
        return {
            "config_key": config.config_key,
            "config_value": config_data.config_value,
            "description": config.description,
            "data_type": config.data_type,
            "is_active": config.is_active
        }
        
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to update configuration: {str(e)}"
        )