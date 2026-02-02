export type ChatThread = {
    id: string;
    title: string;
    lastMessageAt: string | null;
};

export type ChatMessage = {
    id: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    createdAt: string;
};

export type ChatPageProps = {
    threads: ChatThread[];
    currentThread: ChatThread | null;
    messages: ChatMessage[];
};
