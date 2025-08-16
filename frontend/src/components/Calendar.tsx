import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Calendar as BigCalendar, momentLocalizer } from 'react-big-calendar';
import type { View } from 'react-big-calendar';
import moment from 'moment';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { getEvents, createEvent, updateEvent, deleteEvent } from '../services/calendarService';
import type { Event } from '../schemas/calendar';

// Local event shape used by react-big-calendar
type CalendarEvent = {
    id: string;
    title?: string;
    start: Date;
    end: Date;
    summary?: string;
    description?: string;
    attendees?: string[];
};

const Calendar: React.FC = () => {
    const [events, setEvents] = useState<Event[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const { t, i18n } = useTranslation();
    // controlled date/view to ensure navigation buttons have handlers and don't cause runtime errors
    const [currentDate, setCurrentDate] = useState<Date>(new Date());
    const [currentView, setCurrentView] = useState<View | undefined>(undefined);

    // Set moment locale when language changes so calendar displays localized month/day names
    useEffect(() => {
        const lang = i18n.language || 'en';
        try {
            moment.locale(lang);
        } catch (e) {
            moment.locale('en');
        }
    }, [i18n.language]);

    // Create localizer after locale is set
    const localizer = momentLocalizer(moment);

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

    // t and i18n already derived above

    const handleSelectSlot = async ({ start, end }: { start: Date; end: Date }) => {
        const title = window.prompt(t('dashboard.menu.calendar') + ' - ' + t('calendar.new_event_prompt')) || window.prompt(t('calendar.new_event_prompt'));
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
        const newTitle = window.prompt(t('calendar.edit_event_prompt'), event.summary);
        if (newTitle) {
            const updatedEventData = { ...event, summary: newTitle };
            const updatedEvent = await updateEvent(event.id, updatedEventData);
            setEvents(events.map(e => e.id === event.id ? updatedEvent : e));
        } else {
            if (window.confirm(t('calendar.delete_confirm'))) {
                await deleteEvent(event.id);
                setEvents(events.filter(e => e.id !== event.id));
            }
        }
    };

    if (loading) {
        return <div>{t('calendar.loading')}</div>;
    }

    // navigation handlers (safe wrappers)
    const handleNavigate = (date: Date) => {
        try {
            setCurrentDate(date);
        } catch (err) {
            console.error('Error in calendar navigation handler:', err);
        }
    };

    const handleView = (view: View) => {
        try {
            setCurrentView(view);
        } catch (err) {
            console.error('Error in calendar view handler:', err);
        }
    };

    const calendarEvents: CalendarEvent[] = events.map(e => ({
        id: e.id,
        title: e.summary,
        start: new Date(e.start.dateTime),
        end: new Date(e.end.dateTime),
        summary: e.summary,
        description: e.description,
        attendees: e.attendees,
    }));

    const messages = {
        today: t('calendar.today'),
        previous: t('calendar.previous'),
        next: t('calendar.next'),
        month: t('calendar.month'),
        week: t('calendar.week'),
        day: t('calendar.day'),
        agenda: t('calendar.agenda'),
        date: t('calendar.date'),
        time: t('calendar.time'),
        event: t('calendar.event'),
        allDay: t('calendar.all_day'),
        noEventsInRange: t('calendar.no_events'),
        showMore: (total: number) => `+${total} ${t('calendar.more')}`,
    };

    return (
        <div style={{ height: '500px' }}>
            <BigCalendar
                localizer={localizer}
                messages={messages}
                events={calendarEvents}
                startAccessor="start"
                endAccessor="end"
                style={{ height: 500 }}
                selectable
                date={currentDate}
                view={currentView}
                onNavigate={handleNavigate}
                onView={handleView}
                onSelectSlot={handleSelectSlot}
                onSelectEvent={(ev: CalendarEvent) => {
                    // map back to our Event shape for edit/delete
                    const matched = events.find(e => e.id === ev.id);
                    if (matched) handleSelectEvent(matched);
                }}
            />
        </div>
    );
};

export default Calendar;
