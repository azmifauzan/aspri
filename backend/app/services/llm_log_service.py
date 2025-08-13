# app/services/llm_log_service.py
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.models.llm_log import LLMLog
from typing import Optional, Dict, Any

class LLMLogService:
    def __init__(self, db_session: AsyncSession):
        self.db_session = db_session

    async def create_log(
        self,
        prompt_type: str,
        prompt_data: Dict[str, Any],
        llm_response: str,
        user_id: Optional[int] = None,
        chat_session_id: Optional[int] = None,
    ) -> LLMLog:
        log_entry = LLMLog(
            user_id=user_id,
            chat_session_id=chat_session_id,
            prompt_type=prompt_type,
            prompt_data=prompt_data,
            llm_response=llm_response,
        )
        self.db_session.add(log_entry)
        await self.db_session.commit()
        await self.db_session.refresh(log_entry)
        return log_entry
