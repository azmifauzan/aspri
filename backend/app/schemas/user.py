# app/schemas/user.py
from pydantic import BaseModel, EmailStr
from typing import Optional
from datetime import date

class UserBase(BaseModel):
    email: EmailStr

class UserCreate(BaseModel):
    google_token: str
    google_access_token: Optional[str] = None
    google_refresh_token: Optional[str] = None

class UserRegistration(BaseModel):
    name: str
    birth_date: int  # Day of birth (1-31)
    birth_month: int  # Month of birth (1-12)
    call_preference: str  # How they want to be called
    aspri_name: str  # Name for their ASPRI assistant
    aspri_persona: str  # Personality for ASPRI

class UserUpdate(BaseModel):
    name: Optional[str] = None
    birth_date: Optional[int] = None
    birth_month: Optional[int] = None
    call_preference: Optional[str] = None
    aspri_name: Optional[str] = None
    aspri_persona: Optional[str] = None

class UpdateUserRequest(UserUpdate):
    """
    Schema for updating user details.
    Inherits from UserUpdate.
    """
    pass

class UserResponse(BaseModel):
    id: int
    email: str
    google_id: str
    name: Optional[str] = None
    birth_date: Optional[int] = None
    birth_month: Optional[int] = None
    call_preference: Optional[str] = None
    aspri_name: Optional[str] = None
    aspri_persona: Optional[str] = None
    is_registered: bool
    created_at: date
    updated_at: Optional[date] = None

    class Config:
        from_attributes = True

class LoginResponse(BaseModel):
    access_token: str
    token_type: str
    user: UserResponse
    is_registered: bool

class TokenResponse(BaseModel):
    access_token: str
    token_type: str