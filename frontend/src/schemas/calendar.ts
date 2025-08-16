export interface EventDateTime {
    dateTime: string;
    timeZone?: string;
}

export interface Event {
    id: string;
    summary: string;
    description?: string;
    start: EventDateTime;
    end: EventDateTime;
    attendees?: string[];
}

export interface EventCreate {
    summary: string;
    description?: string;
    start: EventDateTime;
    end: EventDateTime;
    attendees?: string[];
}

export interface EventUpdate {
    summary?: string;
    description?: string;
    start?: EventDateTime;
    end?: EventDateTime;
    attendees?: string[];
}
