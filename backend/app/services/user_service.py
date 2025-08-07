# app/services/user_service.py
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from app.db.models.user import User
from app.schemas.user import UserRegistration
from datetime import datetime, timedelta, date
from typing import Optional

class UserService:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_user_by_email(self, email: str) -> Optional[User]:
        """Get user by email"""
        result = await self.db.execute(select(User).where(User.email == email))
        return result.scalar_one_or_none()

    async def get_user_by_google_id(self, google_id: str) -> Optional[User]:
        """Get user by Google ID"""
        result = await self.db.execute(select(User).where(User.google_id == google_id))
        return result.scalar_one_or_none()

    async def get_user_by_id(self, user_id: int) -> Optional[User]:
        """Get user by ID"""
        result = await self.db.execute(select(User).where(User.id == user_id))
        return result.scalar_one_or_none()

    async def create_user(
        self,
        email: str,
        google_id: str,
        access_token: Optional[str] = None,
        refresh_token: Optional[str] = None
    ) -> User:
        """Create new user from Google OAuth"""
        expiry_time = datetime.utcnow() + timedelta(hours=1) if access_token else None

        user = User(
            email=email,
            google_id=google_id,
            google_access_token=access_token,
            google_refresh_token=refresh_token,
            google_token_expiry=expiry_time,
            is_registered=False,
            created_at=date.today()
        )
        self.db.add(user)
        await self.db.commit()
        await self.db.refresh(user)
        return user

    async def update_user_tokens(
        self,
        user: User,
        access_token: str,
        refresh_token: Optional[str] = None
    ) -> User:
        """Update Google API tokens for an existing user"""
        user.google_access_token = access_token
        user.google_token_expiry = datetime.utcnow() + timedelta(hours=1)
        if refresh_token:
            user.google_refresh_token = refresh_token

        await self.db.commit()
        await self.db.refresh(user)
        return user

    async def complete_registration(self, user_id: int, registration_data: UserRegistration) -> User:
        """Complete user registration with additional data"""
        user = await self.get_user_by_id(user_id)
        if not user:
            raise ValueError("User not found")
        
        # Validate birth date and month
        if not (1 <= registration_data.birth_date <= 31):
            raise ValueError("Birth date must be between 1 and 31")
        if not (1 <= registration_data.birth_month <= 12):
            raise ValueError("Birth month must be between 1 and 12")
        
        # Update user data
        user.name = registration_data.name
        user.birth_date = registration_data.birth_date
        user.birth_month = registration_data.birth_month
        user.call_preference = registration_data.call_preference
        user.aspri_name = registration_data.aspri_name
        user.aspri_persona = registration_data.aspri_persona
        user.is_registered = True
        user.updated_at = date.today()
        
        await self.db.commit()
        await self.db.refresh(user)
        return user