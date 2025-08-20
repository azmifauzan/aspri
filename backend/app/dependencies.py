from fastapi import Depends, HTTPException, status
from app.core.auth import get_current_user_id
from app.db.database import get_db
from app.services.user_service import UserService
from app.db.models.user import User
from app.services.supabase_db_service import SupabaseDBService

async def get_current_user(
    user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db),
) -> User:
    user_service = UserService(db)
    user = await user_service.get_user_by_id(user_id)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Could not validate credentials",
            headers={"WWW-Authenticate": "Bearer"},
        )
    return user
