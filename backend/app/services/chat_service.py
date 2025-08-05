# app/services/chat_service.py
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select, func
from sqlalchemy.orm import selectinload
import os
from typing import List, Optional, Dict, Any
from datetime import datetime
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Models
from app.db.models.chat import ChatSession, ChatMessage
from app.db.models.document import Document
from app.schemas.chat import ChatSessionCreate, ChatMessageCreate
from app.schemas.document import DocumentSearchQuery
from app.db.models.user import User

# Services
from app.services.document_service import DocumentService
from app.services.chromadb_service import ChromaDBService

# LangChain GenAI imports
from langchain_google_genai import ChatGoogleGenerativeAI
from langchain_core.prompts import PromptTemplate
from langchain_core.messages import HumanMessage, AIMessage, SystemMessage

class ChatService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.chromadb_service = ChromaDBService()
        
        # Initialize LangChain GenAI Chat model (Gemini 2.5 Flash)
        self.chat_model = ChatGoogleGenerativeAI(
            model="gemini-2.5-flash",
            google_api_key=os.getenv("GOOGLE_API_KEY", "your-google-api-key-here"),
            temperature=0.7,
            max_tokens=1000
        )
        
        # Intent classification prompt
        self.intent_prompt = PromptTemplate.from_template(
            "Classify the following user message into one of these categories: "
            "'chat' for general conversation or 'document_search' for searching in uploaded documents.\n\n"
            "Message: {message}\n\n"
            "Category:"
        )
        
        # Chat prompt template
        self.chat_prompt = PromptTemplate.from_template(
            "You are an AI personal assistant named {assistant_name} with a {assistant_persona} personality. "
            "You are helping {user_name} (who prefers to be called {call_preference}). "
            "Here is the conversation history:\n{history}\n\n"
            "User: {user_message}\n"
            "Assistant:"
        )
        
        self.system_instruction = PromptTemplate.from_template(
            """
            You are an AI assistant named {assistant_name}. You have a distinct personality and communication style described as: "{assistant_persona}".

            Your primary responsibility is to help the user named {user_name} in a way that is aligned with your persona.

            Always refer to the user using their preferred form of address: "{call_preference}". Be consistent in how you call the user in every response.

            Important behavior rules:
            1. Match the language of the user's input. If they ask in Bahasa Indonesia, respond in Bahasa Indonesia. If they ask in English, respond in English.
            2. When giving answers, reflect your assistant persona in tone, word choice, and attitude.
            3. Do not repeat your name unless asked. Speak naturally as if you're a real assistant.
            4. If the user greets you (e.g., "Hi", "Halo"), explain who you are briefly, including your name and persona.

            Always aim to be helpful, respectful, and aligned with the communication preferences of the user.
            
            "Here is the conversation history:\n{history}\n\n"
            "User: {user_message}\n"
            "Assistant:"
            """
        )

        
        # Document search prompt template
        self.document_search_prompt = PromptTemplate.from_template(
            "You are an AI personal assistant named {assistant_name} with a {assistant_persona} personality. "
            "You are helping {user_name} (who prefers to be called {call_preference}). "
            "The user wants to search in their documents. Here are the search results:\n{search_results}\n\n"
            "Please provide a helpful summary of the search results to answer the user's query: {user_query}\n"
            "Assistant:"
        )

    async def create_chat_session(self, user_id: int, session_data: ChatSessionCreate) -> ChatSession:
        """Create a new chat session"""
        chat_session = ChatSession(
            user_id=user_id,
            title=session_data.title,
            created_at=datetime.utcnow(),
            updated_at=datetime.utcnow(),
            is_active=True
        )
        
        self.db.add(chat_session)
        await self.db.commit()
        await self.db.refresh(chat_session)
        
        return chat_session

    async def get_user_chat_sessions(self, user_id: int) -> List[ChatSession]:
        """Get all chat sessions for a user"""
        query = select(ChatSession).where(
            ChatSession.user_id == user_id
        ).order_by(ChatSession.updated_at.desc())
        
        result = await self.db.execute(query)
        return result.scalars().all()

    async def get_chat_session(self, session_id: int, user_id: int) -> Optional[ChatSession]:
        """Get a chat session with its messages"""
        query = select(ChatSession).options(
            selectinload(ChatSession.messages)
        ).where(
            ChatSession.id == session_id,
            ChatSession.user_id == user_id
        )
        
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def activate_chat_session(self, session_id: int, user_id: int) -> Optional[ChatSession]:
        """Activate a chat session"""
        chat_session = await self.get_chat_session(session_id, user_id)
        if not chat_session:
            return None
            
        chat_session.is_active = True
        chat_session.updated_at = datetime.utcnow()
        
        await self.db.commit()
        await self.db.refresh(chat_session)
        
        return chat_session

    async def delete_chat_session(self, session_id: int, user_id: int) -> bool:
        """Delete a chat session and all its messages"""
        chat_session = await self.get_chat_session(session_id, user_id)
        if not chat_session:
            return False
            
        await self.db.delete(chat_session)
        await self.db.commit()
        
        return True

    async def send_message(self, session_id: int, user_id: int, message_data: ChatMessageCreate) -> ChatMessage:
        """Send a message and get AI response"""
        # Save user message
        user_message = ChatMessage(
            chat_session_id=session_id,
            content=message_data.content,
            role="user",
            message_type=message_data.message_type,
            created_at=datetime.utcnow()
        )
        
        self.db.add(user_message)
        await self.db.flush()
        
        # Classify user intent
        intent = await self.classify_user_intent(message_data.content)
        user_message.intent = intent
        
        # Get user info for personalization
        user_info = await self._get_user_info(user_id)
        
        # Generate AI response based on intent
        if intent == "document_search":
            ai_response = await self._handle_document_search(user_id, message_data.content, user_info)
        else:
            # Default to chat response
            ai_response = await self._generate_chat_response2(session_id, message_data.content, user_info)
        
        # Save AI response
        ai_message = ChatMessage(
            chat_session_id=session_id,
            content=ai_response,
            role="assistant",
            message_type="text",
            intent=intent,
            created_at=datetime.utcnow()
        )
        
        self.db.add(ai_message)
        
        # Update session timestamp
        chat_session = await self.get_chat_session(session_id, user_id)
        chat_session.updated_at = datetime.utcnow()
        
        await self.db.commit()
        await self.db.refresh(ai_message)
        
        return ai_message

    async def classify_user_intent(self, message: str) -> str:
        """Classify user intent using Gemini"""
        try:
            # Create the prompt
            prompt = self.intent_prompt.format(message=message)
            
            # Get response from Gemini
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
            
            # Extract intent from response
            intent = response.content.strip().lower()
            
            # Validate intent
            if intent in ["chat", "document_search"]:
                return intent
            else:
                return "chat"  # Default to chat if uncertain
        except Exception as e:
            print(f"Error classifying intent: {e}")
            return "chat"  # Default to chat on error

    async def _handle_document_search(self, user_id: int, query: str, user_info: Dict[str, Any]) -> str:
        """Handle document search intent"""
        try:
            print(f"Handling document search for user {user_id} with query: {query}")
            # Create document service
            document_service = DocumentService(self.db)
            
            # Create search query object
            search_query = DocumentSearchQuery(query=query, limit=5)
            
            # Search documents using vector similarity
            search_results = await document_service.search_documents(
                user_id, 
                search_query
            )
            
            if not search_results:
                return "I couldn't find any relevant information in your documents for that query."
            
            # Format search results
            formatted_results = "\n\n".join([
                f"Document: {result['document_filename']}\n"
                f"Relevance: {result['similarity_score']:.2f}\n"
                f"Content: {result['chunk_text']}"
                for result in search_results
            ])
            
            # Generate response using Gemini
            prompt = self.document_search_prompt.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                user_name=user_info.get("name", "User"),
                call_preference=user_info.get("call_preference", "User"),
                search_results=formatted_results,
                user_query=query
            )
            
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
            return response.content
            
        except Exception as e:
            print(f"Error handling document search: {e}")
            return "I encountered an error while searching your documents. Please try rephrasing your query."

    async def _generate_chat_response(self, session_id: int, user_message: str, user_info: Dict[str, Any]) -> str:
        """Generate chat response using Gemini"""
        try:
            # Get conversation history
            history = await self._get_conversation_history(session_id)
            
            # Create prompt
            prompt = self.chat_prompt.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                user_name=user_info.get("name", "User"),
                call_preference=user_info.get("call_preference", "User"),
                history=history,
                user_message=user_message
            )
            
            # Get response from Gemini
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
            return response.content
            
        except Exception as e:
            print(f"Error generating chat response: {e}")
            return "I'm sorry, I encountered an error while processing your message. Please try again."
    
    async def _generate_chat_response2(self, session_id: int, user_message: str, user_info: Dict[str, Any]) -> str:
        """Generate chat response using Gemini"""
        try:
            # Get conversation history
            history = await self._get_conversation_history(session_id)
            
            # Create prompt
            prompt = self.system_instruction.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                user_name=user_info.get("name", "User"),
                call_preference=user_info.get("call_preference", "User"),
                history=history,
                user_message=user_message
            )
            
            print(f"Generated prompt: {prompt}")  # Debugging line
            # Get response from Gemini
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
            print(f"Response from Gemini: {response.content}")  # Debugging line
            return response.content
            
        except Exception as e:
            print(f"Error generating chat response: {e}")
            return "I'm sorry, I encountered an error while processing your message. Please try again."

    async def _get_conversation_history(self, session_id: int) -> str:
        """Get formatted conversation history"""
        query = select(ChatMessage).where(
            ChatMessage.chat_session_id == session_id
        ).order_by(ChatMessage.created_at)
        
        result = await self.db.execute(query)
        messages = result.scalars().all()
        
        # Format history
        history = "\n".join([
            f"{'User' if msg.role == 'user' else 'Assistant'}: {msg.content}"
            for msg in messages[-10:]  # Last 10 messages
        ])
        
        return history if history else "No previous conversation."

    async def _get_user_info(self, user_id: int) -> Dict[str, Any]:
        """Get user information for personalization"""
        
        query = select(User).where(User.id == user_id)
        result = await self.db.execute(query)
        user = result.scalar_one_or_none()
        if not user:
            return {
                "name": "User",
                "call_preference": "User",
                "aspri_name": "ASPRI",
                "aspri_persona": "helpful"
            }
        else:
            return {
                "name": user.name or "User",
                "call_preference": user.call_preference or "User",
                "aspri_name": user.aspri_name or "ASPRI",
                "aspri_persona": user.aspri_persona or "helpful"
            }