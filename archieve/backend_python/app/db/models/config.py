# app/db/models/config.py
from sqlalchemy import String, Integer, Text, DateTime, func, Boolean
from sqlalchemy.orm import Mapped, mapped_column
from app.db.base import Base
from datetime import datetime
from typing import Optional

class Configuration(Base):
    __tablename__ = "configurations"

    id: Mapped[int] = mapped_column(primary_key=True)
    
    # Configuration key (unique identifier)
    config_key: Mapped[str] = mapped_column(String(255), unique=True, nullable=False)
    
    # Configuration value (stored as string, can be parsed as needed)
    config_value: Mapped[str] = mapped_column(Text, nullable=False)
    
    # Configuration description
    description: Mapped[Optional[str]] = mapped_column(Text, nullable=True)
    
    # Data type for validation (string, integer, float, boolean, json)
    data_type: Mapped[str] = mapped_column(String(50), nullable=False, default="string")
    
    # Whether this config is active
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    
    # Timestamps
    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
    updated_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False
    )