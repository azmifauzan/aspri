from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from typing import List, Any, Optional
from app.dependencies import get_db, get_current_user
from app.db.models.user import User
from app.schemas.calendar import Event, EventCreate, EventUpdate
from app.services.google_calendar_service import GoogleCalendarService

router = APIRouter()

@router.get("/", response_model=List[Event])
async def list_events(
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
    calendar_id: str = 'primary',
    time_min: Optional[str] = None,
    time_max: Optional[str] = None,
    max_results: int = 250
) -> Any:
    """
    List all events from a calendar.
    """
    service = GoogleCalendarService(user=current_user, db=db)
    try:
        events = await service.list_events(calendar_id, time_min, time_max, max_results)
        return events
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/", response_model=Event)
async def create_event(
    event: EventCreate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
    calendar_id: str = 'primary'
) -> Any:
    """
    Create a new event.
    """
    service = GoogleCalendarService(user=current_user, db=db)
    try:
        new_event = await service.add_event(event, calendar_id)
        return new_event
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.put("/{event_id}", response_model=Event)
async def update_event_details(
    event_id: str,
    event: EventUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
    calendar_id: str = 'primary'
) -> Any:
    """
    Update an event.
    """
    service = GoogleCalendarService(user=current_user, db=db)
    try:
        updated_event = await service.update_event(event_id, event, calendar_id)
        return updated_event
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.delete("/{event_id}", status_code=204)
async def delete_event_entry(
    event_id: str,
    db: AsyncSession = Depends(get_db),
    current_user: User = Depends(get_current_user),
    calendar_id: str = 'primary'
):
    """
    Delete an event.
    """
    service = GoogleCalendarService(user=current_user, db=db)
    try:
        await service.delete_event(event_id, calendar_id)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
    return {"ok": True}
