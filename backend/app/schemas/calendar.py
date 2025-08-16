from pydantic import BaseModel, root_validator
from typing import Optional, List
from datetime import datetime


class EventDateTime(BaseModel):
    """Represents an event boundary.

    Google Calendar may return either a full datetime under 'dateTime'
    or an all-day date under 'date'. To make our response validation
    robust, accept either and coerce 'date' into a midnight datetime.
    """
    dateTime: Optional[datetime] = None
    timeZone: Optional[str] = None

    @root_validator(pre=True)
    def coerce_date_to_datetime(cls, values):
        # If 'dateTime' is missing but 'date' exists (all-day event),
        # convert 'YYYY-MM-DD' into a midnight datetime.
        if not values.get('dateTime') and values.get('date'):
            d = values.get('date')
            try:
                # Create an ISO-like datetime string and let pydantic parse it
                values['dateTime'] = datetime.fromisoformat(f"{d}T00:00:00")
            except Exception:
                # Fall back to leaving dateTime as None; validation later
                # will catch invalid formats.
                values['dateTime'] = None
        return values

class Event(BaseModel):
    id: str
    summary: str
    description: Optional[str] = None
    start: EventDateTime
    end: EventDateTime
    attendees: Optional[List[str]] = None

class EventCreate(BaseModel):
    summary: str
    description: Optional[str] = None
    start: EventDateTime
    end: EventDateTime
    attendees: Optional[List[str]] = None

class EventUpdate(BaseModel):
    summary: Optional[str] = None
    description: Optional[str] = None
    start: Optional[EventDateTime] = None
    end: Optional[EventDateTime] = None
    attendees: Optional[List[str]] = None
