import api from './api';
import type { Event, EventCreate, EventUpdate } from '../schemas/calendar';

export const getEvents = async (calendarId: string = 'primary'): Promise<Event[]> => {
    const response = await api.get(`/calendar/?calendar_id=${calendarId}`);
    return response.data;
};

export const createEvent = async (event: EventCreate, calendarId: string = 'primary'): Promise<Event> => {
    const response = await api.post(`/calendar/?calendar_id=${calendarId}`, event);
    return response.data;
};

export const updateEvent = async (eventId: string, event: EventUpdate, calendarId: string = 'primary'): Promise<Event> => {
    const response = await api.put(`/calendar/${eventId}?calendar_id=${calendarId}`, event);
    return response.data;
};

export const deleteEvent = async (eventId: string, calendarId: string = 'primary'): Promise<void> => {
    await api.delete(`/calendar/${eventId}?calendar_id=${calendarId}`);
};
