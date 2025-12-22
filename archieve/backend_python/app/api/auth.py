# app/api/auth.py
from fastapi import APIRouter, Depends, HTTPException, status, Request
from fastapi.responses import RedirectResponse
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.database import get_db
from app.schemas.user import UserCreate, LoginResponse, UserRegistration, UserResponse, UpdateUserRequest
from app.services.user_service import UserService
from app.core.auth import (
    create_access_token,
    verify_google_token,
    get_current_user_id,
    get_google_auth_url,
    exchange_code_for_tokens
)
from datetime import timedelta
import os

router = APIRouter(prefix="/auth", tags=["Authentication"])

@router.get("/google/login")
async def google_login():
    """
    Redirects to Google's OAuth 2.0 consent screen.
    """
    return RedirectResponse(url=get_google_auth_url())

@router.get("/google/callback")
async def google_callback(
    request: Request,
    db: AsyncSession = Depends(get_db)
):
    """
    Handles the callback from Google after user authentication.
    """
    try:
        code = request.query_params.get('code')
        if not code:
            raise HTTPException(status_code=400, detail="Authorization code not found in callback.")

        token_data = exchange_code_for_tokens(code)

        user_info = token_data['user_info']
        google_id = user_info['sub']
        email = user_info['email']

        user_service = UserService(db)
        user = await user_service.get_user_by_google_id(google_id)

        if user:
            user = await user_service.update_user_tokens(
                user=user,
                access_token=token_data['access_token'],
                refresh_token=token_data['refresh_token']
            )
        else:
            user = await user_service.create_user(
                email=email,
                google_id=google_id,
                access_token=token_data['access_token'],
                refresh_token=token_data['refresh_token']
            )

        # Create JWT token for frontend
        access_token_expires = timedelta(minutes=30)
        jwt_token = create_access_token(
            data={"sub": str(user.id)}, expires_delta=access_token_expires
        )

        # Redirect to frontend with the token
        frontend_url = os.getenv("FRONTEND_URL", "http://localhost:5173")
        redirect_url = f"{frontend_url}/auth/callback?token={jwt_token}"

        return RedirectResponse(url=redirect_url)

    except HTTPException as e:
        # Re-raise HTTP exceptions
        raise e
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=f"Authentication failed: {str(e)}"
        )

@router.post("/login_with_token", response_model=LoginResponse, deprecated=True)
async def login_with_google_token(
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
            # Create new user if they don't exist
            # Note: access_token and refresh_token are not provided in this flow
            user = await user_service.create_user(
                email=email,
                google_id=google_id,
                access_token=None,
                refresh_token=None
            )
        
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

@router.put("/me", response_model=UserResponse)
async def update_current_user(
    form_data: UpdateUserRequest,
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
):
    """
    Update current user information.
    Requires JWT authentication.
    """
    user_service = UserService(db)
    user = await user_service.get_user_by_id(current_user_id)

    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )

    updated_user = await user_service.update_user(current_user_id, form_data)
    return UserResponse.model_validate(updated_user)