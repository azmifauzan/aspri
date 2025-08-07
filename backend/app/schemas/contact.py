# app/schemas/contact.py
from pydantic import BaseModel, EmailStr
from typing import Optional

class ContactBase(BaseModel):
    name: str
    email: Optional[EmailStr] = None
    phone: Optional[str] = None

class ContactCreate(ContactBase):
    pass

class ContactUpdate(BaseModel):
    name: Optional[str] = None
    email: Optional[EmailStr] = None
    phone: Optional[str] = None
    etag: str

class ContactResponse(ContactBase):
    id: str # This will be the resourceName from Google

    class Config:
        from_attributes = True
