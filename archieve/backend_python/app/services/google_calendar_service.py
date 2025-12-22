# app/services/google_calendar_service.py
import os
import datetime
from typing import List, Dict, Any, Optional
from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError
from app.db.models.user import User
from sqlalchemy.ext.asyncio import AsyncSession
from app.services.user_service import UserService
from app.schemas.calendar import EventCreate, EventUpdate

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
            scopes=["https://www.googleapis.com/auth/calendar.events", "https://www.googleapis.com/auth/calendar"]
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

    async def list_events(self, calendar_id: str = 'primary', time_min: Optional[str] = None, time_max: Optional[str] = None, max_results: int = 250) -> List[Dict[str, Any]]:
        """List events from a specific calendar."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            now = datetime.datetime.utcnow().isoformat() + 'Z'
            time_min = time_min or now

            events_result = service.events().list(
                calendarId=calendar_id,
                timeMin=time_min,
                timeMax=time_max,
                maxResults=max_results,
                singleEvents=True,
                orderBy='startTime'
            ).execute()
            items = events_result.get('items', [])
            # Normalize events so they match our Pydantic schemas
            normalized = [self._normalize_event(e) for e in items]
            return normalized
        except HttpError as err:
            raise Exception(f"Google Calendar API error: {err}")

    async def add_event(self, event_data: EventCreate, calendar_id: str = 'primary') -> Dict[str, Any]:
        """Add an event to a calendar."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            event_body = event_data.dict(exclude_unset=True)
            event = service.events().insert(calendarId=calendar_id, body=event_body).execute()
            return self._normalize_event(event)
        except HttpError as err:
            raise Exception(f"Google Calendar API error: {err}")

    async def update_event(self, event_id: str, event_data: EventUpdate, calendar_id: str = 'primary') -> Dict[str, Any]:
        """Update an event in a calendar."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            event_body = event_data.dict(exclude_unset=True)
            event = service.events().update(calendarId=calendar_id, eventId=event_id, body=event_body).execute()
            return self._normalize_event(event)
        except HttpError as err:
            raise Exception(f"Google Calendar API error: {err}")

    async def delete_event(self, event_id: str, calendar_id: str = 'primary'):
        """Delete an event from a calendar."""
        if not self.creds:
            raise ValueError("User does not have Google credentials.")

        await self._refresh_credentials_if_needed()

        try:
            service = self._build_service()
            service.events().delete(calendarId=calendar_id, eventId=event_id).execute()
        except HttpError as err:
            raise Exception(f"Google Calendar API error: {err}")

    def _normalize_event(self, event: Dict[str, Any]) -> Dict[str, Any]:
        """Normalize a Google Calendar event to match our Pydantic schema.

        - Convert all-day events that use 'date' to 'dateTime' (keep date string).
        - Ensure 'start' and 'end' have 'dateTime' keys when appropriate.
        - Map attendees to a simple list of emails if present, otherwise None.
        """
        ev = dict(event)  # shallow copy

        def norm_dt(dt_obj: Dict[str, Any]) -> Dict[str, Any]:
            if not dt_obj:
                return {"dateTime": None}
            # If it's an all-day event, Google uses 'date' not 'dateTime'
            if 'dateTime' in dt_obj:
                return { 'dateTime': dt_obj.get('dateTime'), 'timeZone': dt_obj.get('timeZone') }
            if 'date' in dt_obj:
                # Convert all-day 'date' (YYYY-MM-DD) to an ISO datetime at midnight so
                # Pydantic can parse it into a datetime field expected by the schema.
                d = dt_obj.get('date')
                if d:
                    date_time_str = f"{d}T00:00:00"
                else:
                    date_time_str = None
                return { 'dateTime': date_time_str, 'timeZone': dt_obj.get('timeZone') }
            return { 'dateTime': None }

        ev['start'] = norm_dt(ev.get('start', {}))
        ev['end'] = norm_dt(ev.get('end', {}))

        # Normalize attendees: our schema expects Optional[List[str]] of emails
        attendees = ev.get('attendees')
        if attendees is None:
            ev['attendees'] = None
        else:
            emails = []
            for a in attendees:
                if isinstance(a, dict):
                    email = a.get('email')
                    if email:
                        emails.append(email)
                elif isinstance(a, str):
                    emails.append(a)
            ev['attendees'] = emails if emails else None

        # Ensure required fields exist for response model
        ev.setdefault('id', ev.get('id', ''))
        ev.setdefault('summary', ev.get('summary', ''))
        ev.setdefault('description', ev.get('description'))

        return ev
