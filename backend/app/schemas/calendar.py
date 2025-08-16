from pydantic import BaseModel
from typing import Optional, List
from datetime import datetime

class EventDateTime(BaseModel):
    dateTime: datetime
    timeZone: Optional[str] = None

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
