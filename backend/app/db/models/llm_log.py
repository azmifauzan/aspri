# app/db/models/llm_log.py
from sqlalchemy import String, Integer, ForeignKey, Text, DateTime, JSON, func
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.db.base import Base
from datetime import datetime
from typing import Optional

class LLMLog(Base):
    __tablename__ = "llm_logs"

    id: Mapped[int] = mapped_column(primary_key=True)
    user_id: Mapped[Optional[int]] = mapped_column(ForeignKey("users.id", ondelete="SET NULL"), nullable=True)
    chat_session_id: Mapped[Optional[int]] = mapped_column(ForeignKey("chat_sessions.id", ondelete="SET NULL"), nullable=True)

    prompt_type: Mapped[str] = mapped_column(String(100), nullable=False)
    prompt_data: Mapped[dict] = mapped_column(JSON, nullable=False)
    llm_response: Mapped[str] = mapped_column(Text, nullable=False)

    created_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), server_default=func.now(), nullable=False
    )
