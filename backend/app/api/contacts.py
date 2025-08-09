# app/api/contacts.py
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from typing import List
from app.db.database import get_db
from app.services.user_service import UserService
from app.services.google_contact_service import GoogleContactService
from app.schemas.contact import ContactCreate, ContactUpdate, ContactResponse
from app.core.auth import get_current_user_id

router = APIRouter(prefix="/contacts", tags=["Contacts"])

async def get_contact_service(
    current_user_id: int = Depends(get_current_user_id),
    db: AsyncSession = Depends(get_db)
) -> GoogleContactService:
    """Dependency to get GoogleContactService."""
    user_service = UserService(db)
    user = await user_service.get_user_by_id(current_user_id)
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    if not user.google_access_token:
        raise HTTPException(status_code=400, detail="User has not linked Google account for contacts.")

    return GoogleContactService(user, db)

@router.get("/", response_model=List[ContactResponse])
async def list_contacts(
    contact_service: GoogleContactService = Depends(get_contact_service)
):
    """List all of a user's Google Contacts."""
    try:
        contacts = await contact_service.list_contacts()
        return contacts
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/", response_model=dict)
async def create_contact(
    contact_data: ContactCreate,
    contact_service: GoogleContactService = Depends(get_contact_service)
):
    """Create a new Google Contact."""
    try:
        created_contact = await contact_service.create_contact(
            name=contact_data.name,
            email=contact_data.email,
            phone=contact_data.phone
        )
        return created_contact
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.put("/{resource_name:path}", response_model=dict)
async def update_contact(
    resource_name: str,
    contact_data: ContactUpdate,
    contact_service: GoogleContactService = Depends(get_contact_service)
):
    """Update an existing Google Contact."""
    try:
        updated_contact = await contact_service.update_contact(
            resource_name=resource_name,
            etag=contact_data.etag,
            name=contact_data.name,
            email=contact_data.email,
            phone=contact_data.phone
        )
        return updated_contact
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.delete("/{resource_name:path}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_contact(
    resource_name: str,
    contact_service: GoogleContactService = Depends(get_contact_service)
):
    """Delete a Google Contact."""
    try:
        # The resource_name needs to be URL decoded, FastAPI does this automatically
        await contact_service.delete_contact(resource_name)
        return None
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
