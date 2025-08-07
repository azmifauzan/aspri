# app/services/google_contact_service.py
import os
from typing import List, Dict, Any, Optional
from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError
from app.db.models.user import User
from sqlalchemy.ext.asyncio import AsyncSession
from app.services.user_service import UserService

# It's recommended to store these in environment variables
GOOGLE_CLIENT_ID = os.getenv("GOOGLE_CLIENT_ID")
GOOGLE_CLIENT_SECRET = os.getenv("GOOGLE_CLIENT_SECRET")

class GoogleContactService:
    def __init__(self, user: User, db: AsyncSession):
        self.user = user
        self.db = db
        self.creds = self._get_credentials()

    def _get_credentials(self) -> Optional[Credentials]:
        """Create Google credentials from user data."""
        if not self.user.google_access_token:
            return None

        return Credentials(
            token=self.user.google_access_token,
            refresh_token=self.user.google_refresh_token,
            token_uri="https://oauth2.googleapis.com/token",
            client_id=GOOGLE_CLIENT_ID,
            client_secret=GOOGLE_CLIENT_SECRET,
            scopes=["https://www.googleapis.com/auth/contacts.readonly", "https://www.googleapis.com/auth/contacts"]
        )

    async def _refresh_credentials_if_needed(self):
        """Refresh token if expired and update user."""
        if self.creds and self.creds.expired and self.creds.refresh_token:
            self.creds.refresh(Request())
            # Update the user's tokens in the database
            user_service = UserService(self.db)
            await user_service.update_user_tokens(
                user=self.user,
                access_token=self.creds.token,
                refresh_token=self.creds.refresh_token
            )

    def _build_service(self):
        """Build the Google People API service."""
        return build('people', 'v1', credentials=self.creds)

    async def list_contacts(self) -> List[Dict[str, Any]]:
        """List all Google Contacts for the user."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()

            results = service.people().connections().list(
                resourceName='people/me',
                pageSize=1000,
                personFields='names,emailAddresses,phoneNumbers'
            ).execute()

            connections = results.get('connections', [])

            contact_list = []
            for person in connections:
                names = person.get('names', [{}])
                emails = person.get('emailAddresses', [{}])
                phones = person.get('phoneNumbers', [{}])
                contact_list.append({
                    "id": person.get('resourceName'),
                    "name": names[0].get('displayName', 'N/A'),
                    "email": emails[0].get('value'),
                    "phone": phones[0].get('value')
                })
            return contact_list

        except HttpError as err:
            raise Exception(f"Google Contacts API error: {err}")

    async def create_contact(self, name: str, email: str, phone: str) -> Dict[str, Any]:
        """Create a new Google Contact."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            contact_body = {
                "names": [{"givenName": name}],
                "emailAddresses": [{"value": email}],
                "phoneNumbers": [{"value": phone, "type": "mobile"}]
            }
            created_contact = service.people().createContact(body=contact_body).execute()
            return created_contact

        except HttpError as err:
            raise Exception(f"Google Contacts API error: {err}")

    async def update_contact(self, resource_name: str, etag: str, name: str, email: str, phone: str) -> Dict[str, Any]:
        """Update an existing Google Contact."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            contact_body = {
                "resourceName": resource_name,
                "etag": etag,
                "names": [{"givenName": name}],
                "emailAddresses": [{"value": email}],
                "phoneNumbers": [{"value": phone, "type": "mobile"}]
            }

            updated_contact = service.people().updateContact(
                resourceName=resource_name,
                updatePersonFields='names,emailAddresses,phoneNumbers',
                body=contact_body
            ).execute()
            return updated_contact

        except HttpError as err:
            raise Exception(f"Google Contacts API error: {err}")

    async def delete_contact(self, resource_name: str):
        """Delete a Google Contact."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            service.people().deleteContact(resourceName=resource_name).execute()

        except HttpError as err:
            raise Exception(f"Google Contacts API error: {err}")
