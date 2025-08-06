// src/pages/ChatPage.tsx
import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import ChatBubble from '../components/ChatBubble';
import { Send, Search, FileText, X, Clock } from 'lucide-react';
import axios from 'axios';

// Define types based on backend schemas
type Message = {
  id: number;
  chat_session_id: number;
  content: string;
  role: 'user' | 'assistant';
  message_type: string;
  intent?: string;
  created_at: string; // ISO string
};

type ChatSession = {
  id: number;
  user_id: number;
  title: string;
  created_at: string; // ISO string
  updated_at: string; // ISO string
  is_active: boolean;
  messages?: Message[];
};

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

export default function ChatPage() {
  const { user, token } = useAuth();
  const { t } = useTranslation();
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputText, setInputText] = useState('');
  const [sessions, setSessions] = useState<ChatSession[]>([]);
  const [currentSession, setCurrentSession] = useState<ChatSession | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Scroll to bottom of messages
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Set up axios default headers
  // useEffect(() => {
  //   if (token) {
  //     axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  //   }
  // }, [token]);

  // Load chat sessions on component mount
  useEffect(() => {
    if (user) {
      loadChatSessions();
    }
  }, [user]);

  const loadChatSessions = async () => {
    try {
      const response = await axios.get<{sessions: ChatSession[]}>(`${API_BASE_URL}/chat/sessions`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });
      setSessions(response.data.sessions);
    } catch (error: any) {
      console.error('Error loading chat sessions:', error);
      const errorMessage = error.response?.data?.detail || error.message || 'Failed to load chat sessions';
      setError(errorMessage);
      console.error('Error details:', errorMessage);
    }
  };

  const loadMessagesForSession = async (sessionId: number) => {
    try {
      const response = await axios.get<ChatSession>(`${API_BASE_URL}/chat/sessions/${sessionId}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });
      setMessages(response.data.messages ?? []);
    } catch (error: any) {
      console.error('Error loading messages:', error);
      const errorMessage = error.response?.data?.detail || error.message || 'Failed to load messages';
      setError(errorMessage);
      console.error('Error details:', errorMessage);
      setMessages([]);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setInputText(e.target.value);
    if (!currentSession && e.target.value.length > 0) {
      // Hide history view when user starts typing a new message
      setCurrentSession({} as ChatSession); // Set a dummy session to hide history
    }
  };

  const sendMessage = async () => {
    console.log('Sending message:', { inputText, currentSession, hasToken: !!token });
    
    if (!inputText.trim()) {
      console.log('Message not sent - missing input');
      return;
    }

    // If there's no current session, or it's a dummy session, create one first
    let sessionToSendTo = currentSession;
    if (!sessionToSendTo || !sessionToSendTo.id) {
      try {
        console.log('No current session, creating new session');
        const response = await axios.post<ChatSession>(`${API_BASE_URL}/chat/sessions`, {
          title: inputText.substring(0, 30) // Use first 30 chars of message as title
        }, {
          headers: {
            'Authorization': `Bearer ${token}`,
          }
        });
        
        const newSession = {
          ...response.data,
          messages: []
        };
        setSessions([newSession, ...sessions]);
        setCurrentSession(newSession);
        sessionToSendTo = newSession;
      } catch (error: any) {
        console.error('Error creating new session:', error);
        const errorMessage = error.response?.data?.detail || error.message || 'Failed to create new session';
        setError(errorMessage);
        console.error('Error details:', errorMessage);
        return;
      }
    }

    // Add user message immediately for better UX
    const userMessage: Message = {
      id: Date.now(), // Temporary ID until we get the real one from backend
      chat_session_id: sessionToSendTo.id,
      content: inputText,
      role: 'user',
      message_type: 'text',
      created_at: new Date().toISOString()
    };

    setMessages(prev => [...prev, userMessage]);
    setInputText('');
    setIsLoading(true);

    try {
      console.log('Sending request to:', `${API_BASE_URL}/chat/sessions/${sessionToSendTo.id}/messages`);
      const response = await axios.post<Message>(`${API_BASE_URL}/chat/sessions/${sessionToSendTo.id}/messages`, {
        content: inputText,
        role: 'user',
        message_type: 'text'
      }, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });
      
      const aiMessage = response.data;
      setMessages(prev => [...prev, aiMessage]);
      setIsLoading(false);
      
      // Refresh sessions to update the last updated time
      loadChatSessions();
    } catch (error: any) {
      console.error('Error sending message:', error);
      console.error('Error details:', {
        message: error.message,
        status: error.response?.status,
        data: error.response?.data
      });
      
      const errorMessage = error.response?.data?.detail || error.message || 'Failed to send message';
      setError(errorMessage);
      
      setIsLoading(false);
      
      // Add error message
      const errorMessageResponse: Message = {
        id: Date.now() + 1,
        chat_session_id: sessionToSendTo.id,
        content: 'Sorry, I encountered an error processing your request. Please try again.',
        role: 'assistant',
        message_type: 'text',
        created_at: new Date().toISOString()
      };
      
      setMessages(prev => [...prev, errorMessageResponse]);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };
  
  const handleSendClick = () => {
    sendMessage();
  };

  const activateSession = (session: ChatSession) => {
    setCurrentSession(session);
    loadMessagesForSession(session.id);
  };

  // Redirect to login if user is not authenticated
  if (!user) {
    return null; // The AuthContext should handle redirection
  }

  return (
    <div className="flex flex-col h-full bg-gray-50 dark:bg-zinc-900">
      {/* Main chat area */}
      <div className="flex-1 flex flex-col">
        {/* Chat header */}
        {/* <div className="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700 p-4">
          <h2 className="text-lg font-bold text-zinc-900 dark:text-white">
            {currentSession ? currentSession.title : t('chat.new_chat')}
          </h2>
        </div> */}

        {/* Error message */}
        {error && (
          <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded m-4 flex justify-between items-center">
            <span>{error}</span>
            <button onClick={() => setError('')}>
              <X size={18} />
            </button>
          </div>
        )}

        {/* Messages container */}
        <div className="flex-1 overflow-y-auto p-4">
          {messages.length === 0 && !currentSession ? (
            <div className="flex flex-col items-center justify-center h-full text-center py-12">
              <div className="bg-brand/10 p-4 rounded-full mb-4">
                <FileText className="text-brand" size={32} />
              </div>
              <h3 className="text-xl font-bold text-zinc-900 dark:text-white mb-2">
                {t('chat.how_can_i_help')}
              </h3>
              <p className="text-zinc-600 dark:text-zinc-400 max-w-md">
                {t('chat.ask_anything_about_documents')}
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {messages.map((message) => (
                <div key={message.id} className="mb-4">
                  <ChatBubble
                    side={message.role === 'user' ? 'right' : 'left'}
                    text={message.content}
                  />
                  {message.intent === 'document_search' && (
                    <div className="mt-2 flex items-center text-xs text-brand">
                      <Search size={12} className="mr-1" />
                      <span>{t('chat.searching_documents')}</span>
                    </div>
                  )}
                </div>
              ))}
              {isLoading && (
                <div className="mb-4">
                  <ChatBubble side="left" text="..." />
                </div>
              )}
              <div ref={messagesEndRef} />
            </div>
          )}
        </div>

        {/* Input area */}
        <div className="bg-white dark:bg-zinc-800 border-t border-gray-200 dark:border-zinc-700 p-4">
          <div className="flex items-end gap-2">
            <div className="flex-1 bg-gray-100 dark:bg-zinc-700 rounded-lg p-2">
              <textarea
                value={inputText}
                onChange={handleInputChange}
                onKeyDown={handleKeyPress}
                placeholder={t('chat.type_message') || "Type your message..."}
                className="w-full bg-transparent border-none focus:ring-0 resize-none py-2 px-3 text-zinc-900 dark:text-white placeholder-zinc-500"
                rows={1}
                disabled={isLoading}
              />
            </div>
            <button
              onClick={handleSendClick}
              disabled={!inputText.trim() || isLoading}
              className="bg-brand hover:bg-brand/90 text-white p-3 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Send size={20} />
            </button>
          </div>
          <p className="text-xs text-zinc-500 dark:text-zinc-400 mt-2 text-center">
            {t('chat.enter_to_send')}
          </p>
        </div>

        {/* Chat History */}
        {!currentSession && sessions.length > 0 && (
          <div className="bg-white dark:bg-zinc-800 border-t border-gray-200 dark:border-zinc-700 p-4">
            <h3 className="text-lg font-bold text-zinc-900 dark:text-white mb-2 flex items-center">
              <Clock size={20} className="mr-2" /> {t('chat.recent_chats')}
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {sessions.map((session) => (
                <div
                  key={session.id}
                  onClick={() => activateSession(session)}
                  className="bg-gray-100 dark:bg-zinc-700 p-4 rounded-lg cursor-pointer hover:bg-gray-200 dark:hover:bg-zinc-600"
                >
                  <h4 className="font-medium text-zinc-900 dark:text-white truncate">{session.title}</h4>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    {new Date(session.updated_at).toLocaleDateString()}
                  </p>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}