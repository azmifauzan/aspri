import React, { useState, useEffect } from 'react';
import { Calendar as BigCalendar, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { getEvents, createEvent, updateEvent, deleteEvent } from '../services/calendarService';
import { Event } from '../schemas/calendar';

const localizer = momentLocalizer(moment);

const Calendar: React.FC = () => {
    const [events, setEvents] = useState<Event[]>([]);
    const [loading, setLoading] = useState<boolean>(true);

    useEffect(() => {
        const fetchEvents = async () => {
            try {
                const fetchedEvents = await getEvents();
                setEvents(fetchedEvents);
            } catch (error) {
                console.error('Error fetching events:', error);
            } finally {
                setLoading(false);
            }
        };
        fetchEvents();
    }, []);

    const handleSelectSlot = async ({ start, end }: { start: Date; end: Date }) => {
        const title = window.prompt('New Event name');
        if (title) {
            const newEvent = {
                summary: title,
                start: { dateTime: start.toISOString() },
                end: { dateTime: end.toISOString() },
            };
            const savedEvent = await createEvent(newEvent);
            setEvents([...events, savedEvent]);
        }
    };

    const handleSelectEvent = async (event: Event) => {
        const newTitle = window.prompt('Edit Event name', event.summary);
        if (newTitle) {
            const updatedEventData = { ...event, summary: newTitle };
            const updatedEvent = await updateEvent(event.id, updatedEventData);
            setEvents(events.map(e => e.id === event.id ? updatedEvent : e));
        } else {
            if (window.confirm('Are you sure you want to delete this event?')) {
                await deleteEvent(event.id);
                setEvents(events.filter(e => e.id !== event.id));
            }
        }
    };

    if (loading) {
        return <div>Loading...</div>;
    }

    return (
        <div style={{ height: '500px' }}>
            <BigCalendar
                localizer={localizer}
                events={events.map(e => ({ ...e, start: new Date(e.start.dateTime), end: new Date(e.end.dateTime) }))}
                startAccessor="start"
                endAccessor="end"
                style={{ height: 500 }}
                selectable
                onSelectSlot={handleSelectSlot}
                onSelectEvent={handleSelectEvent}
            />
        </div>
    );
};

export default Calendar;
