# app/db/models/user.py
from sqlalchemy import String, Integer, Boolean, Date
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.db.base import Base
from datetime import date
from typing import Optional, List

class User(Base):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(primary_key=True)
    email: Mapped[str] = mapped_column(String(255), unique=True, nullable=False)
    google_id: Mapped[str] = mapped_column(String(255), unique=True, nullable=False)
    
    # Registration fields
    name: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    birth_date: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)  # Day of birth (1-31)
    birth_month: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)  # Month of birth (1-12)
    call_preference: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)  # How they want to be called
    aspri_name: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)  # Name for their ASPRI assistant
    aspri_persona: Mapped[Optional[str]] = mapped_column(String(500), nullable=True)  # Personality for ASPRI
    
    # Registration completion status
    is_registered: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    
    # Timestamps
    created_at: Mapped[date] = mapped_column(Date, nullable=False)
    updated_at: Mapped[Optional[date]] = mapped_column(Date, nullable=True)
    
    # Relationships
    documents: Mapped[List["Document"]] = relationship(
        "Document", back_populates="user", cascade="all, delete-orphan"
    )