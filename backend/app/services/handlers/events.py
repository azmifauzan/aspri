import datetime
from typing import Any, Dict


class EventsHandler:
    def __init__(self, svc: Any):
        self.svc = svc

    async def list_events(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Moved from ChatService._handle_list_events"""
        try:
            user_service = self.svc.UserService(self.svc.db)
            user = await user_service.get_user_by_id(user_id)
            calendar_service = self.svc.GoogleCalendarService(user, self.svc.db)
            # Determine time range filters if provided by the intent extractor
            time_min = None
            time_max = None
            from datetime import datetime, timedelta, time as _time

            time_range = None
            if data and isinstance(data, dict):
                time_range = (data.get('time_range') or data.get('time') or '').strip().lower()

            if time_range == 'today':
                today = datetime.utcnow().date()
                start_dt = datetime.combine(today, _time.min)
                end_dt = start_dt + timedelta(days=1)
                time_min = start_dt.isoformat() + 'Z'
                time_max = end_dt.isoformat() + 'Z'
            elif time_range == 'this week':
                today = datetime.utcnow().date()
                start_of_week = today - timedelta(days=today.weekday())
                start_dt = datetime.combine(start_of_week, _time.min)
                end_dt = start_dt + timedelta(days=7)
                time_min = start_dt.isoformat() + 'Z'
                time_max = end_dt.isoformat() + 'Z'
            elif time_range == 'next week':
                today = datetime.utcnow().date()
                start_of_next_week = (today - timedelta(days=today.weekday())) + timedelta(days=7)
                start_dt = datetime.combine(start_of_next_week, _time.min)
                end_dt = start_dt + timedelta(days=7)
                time_min = start_dt.isoformat() + 'Z'
                time_max = end_dt.isoformat() + 'Z'
            elif time_range == 'this month':
                today = datetime.utcnow().date()
                start_dt = datetime.combine(today.replace(day=1), _time.min)
                # compute first day of next month
                if today.month == 12:
                    next_month_first = today.replace(year=today.year + 1, month=1, day=1)
                else:
                    next_month_first = today.replace(month=today.month + 1, day=1)
                end_dt = datetime.combine(next_month_first, _time.min)
                time_min = start_dt.isoformat() + 'Z'
                time_max = end_dt.isoformat() + 'Z'

            events = await calendar_service.list_events(time_min=time_min, time_max=time_max)

            # Build a human-readable summary to feed to the LLM for persona rendering
            if not events:
                system_message = "The user asked to list calendar events, but there are no events in the requested time range. Reply politely to inform them."
                return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

            formatted_events = []
            for event in events:
                start = event.get('start', {}).get('dateTime', event.get('start', {}).get('date'))
                formatted_events.append(f"- {event.get('summary')} at {start}")

            # Create a system message telling the LLM how to present the events in persona
            events_str = "\n".join(formatted_events)
            system_message = (
                f"The user asked to list calendar events. Present the following events in a friendly, concise manner consistent with the assistant persona:\n\n{events_str}\n\n"
                "If appropriate, add a short suggestion or follow-up question."
            )

            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)
        except Exception as e:
            return f"An error occurred: {e}"

    async def add_event(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data or 'summary' not in data or 'start_time' not in data or 'end_time' not in data:
            return "Please provide event details: summary, start time, and end time."

        system_message = f"You want to add an event: '{data['summary']}' from {data['start_time']} to {data['end_time']}. Correct?"
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def update_event(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data or 'original' not in data or 'new' not in data:
            return "Please specify which event to update and the new details."

        system_message = f"You want to update an event. Find event matching '{data['original']}' and update it with '{data['new']}'. Correct?"
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def delete_event(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data or 'details' not in data:
            return "Please specify which event to delete."

        system_message = f"You want to delete an event matching: {data['details']}. Correct?"
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def execute_add_event(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            user_service = self.svc.UserService(self.svc.db)
            user = await user_service.get_user_by_id(user_id)
            calendar_service = self.svc.GoogleCalendarService(user, self.svc.db)

            event_data = {
                "summary": data.get("summary"),
                "start": {"dateTime": data.get("start_time"), "timeZone": "UTC"},
                "end": {"dateTime": data.get("end_time"), "timeZone": "UTC"},
                "description": data.get("description")
            }

            created_event = await calendar_service.add_event(event_data)
            return f"Event '{created_event.get('summary')}' has been added to your calendar."
        except Exception as e:
            return f"An error occurred while adding the event: {e}"

    async def execute_update_event(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        return "Event update functionality is not fully implemented yet."

    async def execute_delete_event(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        return "Event deletion functionality is not fully implemented yet."
