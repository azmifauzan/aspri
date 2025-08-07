# app/services/google_calendar_service.py
import os
from typing import List, Dict, Any, Optional
from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError
from app.db.models.user import User
from sqlalchemy.ext.asyncio import AsyncSession
from app.services.user_service import UserService

GOOGLE_CLIENT_ID = os.getenv("GOOGLE_CLIENT_ID")
GOOGLE_CLIENT_SECRET = os.getenv("GOOGLE_CLIENT_SECRET")

class GoogleCalendarService:
    def __init__(self, user: User, db: AsyncSession):
        self.user = user
        self.db = db
        self.creds = self._get_credentials()

    def _get_credentials(self) -> Optional[Credentials]:
        """Create Google credentials from user data."""
        if not self.user.google_access_token:
            return None

        # The scopes here should be a subset of what was granted during OAuth
        return Credentials(
            token=self.user.google_access_token,
            refresh_token=self.user.google_refresh_token,
            token_uri="https://oauth2.googleapis.com/token",
            client_id=GOOGLE_CLIENT_ID,
            client_secret=GOOGLE_CLIENT_SECRET,
            scopes=["https://www.googleapis.com/auth/calendar"]
        )

    async def _refresh_credentials_if_needed(self):
        """Refresh token if expired and update user."""
        if self.creds and self.creds.expired and self.creds.refresh_token:
            self.creds.refresh(Request())
            user_service = UserService(self.db)
            await user_service.update_user_tokens(
                user=self.user,
                access_token=self.creds.token,
                refresh_token=self.creds.refresh_token
            )

    def _build_service(self):
        """Build the Google Calendar API service."""
        return build('calendar', 'v3', credentials=self.creds)

    async def list_calendars(self) -> List[Dict[str, Any]]:
        """List all of a user's Google Calendars."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            calendar_list = service.calendarList().list().execute()
            return calendar_list.get('items', [])
        except HttpError as err:
            raise Exception(f"Google Calendar API error: {err}")
