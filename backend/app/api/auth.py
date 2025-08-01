# app/api/auth.py
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.database import get_db
from app.schemas.user import UserCreate, LoginResponse, UserRegistration, UserResponse
from app.services.user_service import UserService
from app.core.auth import create_access_token, verify_google_token, get_current_user_id
from datetime import timedelta

router = APIRouter(prefix="/auth", tags=["Authentication"])

@router.post("/login", response_model=LoginResponse)
async def login_with_google(
    user_data: UserCreate,
    db: AsyncSession = Depends(get_db)
):
    """
    Login endpoint that accepts Google OAuth token redirect.
    Creates user if doesn't exist, returns JWT token and registration status.
    """
    try:
        # Verify Google token and extract user info
        google_user_info = verify_google_token(user_data.google_token)
        google_id = google_user_info["sub"]
        email = google_user_info["email"]
        
        user_service = UserService(db)
        
        # Check if user exists
        user = await user_service.get_user_by_google_id(google_id)
        
        if not user:
            # Create new user
            user = await user_service.create_user(email=email, google_id=google_id)
        
        # Create JWT token
        access_token_expires = timedelta(minutes=30)
        access_token = create_access_token(
            data={"sub": str(user.id)}, expires_delta=access_token_expires
        )
        
        return LoginResponse(
            access_token=access_token,
            token_type="bearer",
            user=UserResponse.model_validate(user),
            is_registered=user.is_registered
        )
        
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Authentication failed: {str(e)}"
        )

@router.post("/register", response_model=UserResponse)
async def complete_registration(
    registration_data: UserRegistration,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Registration endpoint for saving user data after Google login.
    Requires JWT authentication.
    """
    try:
        user_service = UserService(db)
        user = await user_service.complete_registration(current_user_id, registration_data)
        
        return UserResponse.model_validate(user)
        
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Registration failed: {str(e)}"
        )

@router.get("/me", response_model=UserResponse)
async def get_current_user(
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Get current user information.
    Requires JWT authentication.
    """
    user_service = UserService(db)
    user = await user_service.get_user_by_id(current_user_id)
    
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )
    
    return UserResponse.model_validate(user)