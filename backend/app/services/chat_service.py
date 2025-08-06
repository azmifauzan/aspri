# app/services/chat_service.py
import json
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
from app.services.minio_service import MinIOService

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
        
        # Intent and data extraction prompt
        self.intent_and_data_extraction_prompt = PromptTemplate.from_template(
            "Analyze the user's message and identify the primary intent and any relevant data. "
            "Return the response as a JSON object with two keys: 'intent' and 'data'.\n\n"
            "Possible intents are: 'chat', 'document_search', 'add_transaction', 'edit_transaction', "
            "'delete_transaction', 'manage_category', 'list_transaction', 'financial_tips', 'show_summary', "
            "'summarize_specific_document', 'search_by_semantic', 'compare_document'.\n\n"
            "For 'add_transaction', extract: amount, description, date, type, category.\n"
            "For 'edit_transaction', extract: original (to find it) and new (the updates).\n"
            "For 'delete_transaction', extract: details to identify the transaction.\n"
            "For 'manage_category', extract: action (add, edit, delete) and name.\n"
            "For 'summarize_specific_document', extract: document_name.\n"
            "For 'search_by_semantic', extract: query.\n"
            "For 'compare_document', extract: document_names (as a list).\n"
            "For all other intents, the 'data' field can be null.\n\n"
            "User message: {message}\n\n"
            "JSON output:"
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

        self.summarize_document_prompt = PromptTemplate.from_template(
            "You are an AI personal assistant named {assistant_name} with a {assistant_persona} personality. "
            "Please provide a concise summary of the following document:\n\n"
            "Document: {document_name}\n"
            "Content:\n{document_content}\n\n"
            "Summary:"
        )

        self.compare_documents_prompt = PromptTemplate.from_template(
            "You are an AI personal assistant named {assistant_name} with a {assistant_persona} personality. "
            "Please compare the following documents and provide a summary of their key similarities and differences.\n\n"
            "{document_comparisons}\n\n"
            "Comparison:"
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
        
        # Classify user intent and extract data
        intent_data = await self.classify_user_intent(message_data.content)
        intent = intent_data.get("intent", "chat")
        data = intent_data.get("data")
        user_message.intent = intent
        
        # Get user info for personalization
        user_info = await self._get_user_info(user_id)
        
        # Generate AI response based on intent
        if intent == "document_search":
            ai_response = await self._handle_document_search(user_id, data.get("query") if data else message_data.content, user_info)
        elif intent == "add_transaction":
            ai_response = await self._handle_add_transaction(user_id, data, user_info)
        elif intent == "edit_transaction":
            ai_response = await self._handle_edit_transaction(user_id, data, user_info)
        elif intent == "delete_transaction":
            ai_response = await self._handle_delete_transaction(user_id, data, user_info)
        elif intent == "manage_category":
            ai_response = await self._handle_manage_category(user_id, data, user_info)
        elif intent == "list_transaction":
            ai_response = await self._handle_list_transaction(user_id, data, user_info)
        elif intent == "financial_tips":
            ai_response = await self._handle_financial_tips(user_id, data, user_info)
        elif intent == "show_summary":
            ai_response = await self._handle_show_summary(user_id, data, user_info)
        elif intent == "summarize_specific_document":
            ai_response = await self._handle_summarize_specific_document(user_id, data, user_info)
        elif intent == "search_by_semantic":
            ai_response = await self._handle_search_by_semantic(user_id, data, user_info)
        elif intent == "compare_document":
            ai_response = await self._handle_compare_document(user_id, data, user_info)
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

    async def classify_user_intent(self, message: str) -> Dict[str, Any]:
        """Classify user intent and extract data using Gemini"""
        try:
            # Create the prompt
            prompt = self.intent_and_data_extraction_prompt.format(message=message)
            
            # Get response from Gemini
            response = await self.chat_model.invoke([HumanMessage(content=prompt)])

            # Extract and parse the JSON response
            json_response_str = response.content.strip()
            # It may have ```json  and ``` at the end, so we need to remove it
            if json_response_str.startswith("```json"):
                json_response_str = json_response_str[7:]
            if json_response_str.endswith("```"):
                json_response_str = json_response_str[:-3]
            
            intent_data = json.loads(json_response_str)
            
            # Validate intent
            valid_intents = [
                "chat", "document_search", "add_transaction", "edit_transaction",
                "delete_transaction", "manage_category", "list_transaction",
                "financial_tips", "show_summary", "summarize_specific_document",
                "search_by_semantic", "compare_document"
            ]
            if intent_data.get("intent") in valid_intents:
                return intent_data
            else:
                return {"intent": "chat", "data": None}  # Default to chat if uncertain
        except Exception as e:
            print(f"Error classifying intent or extracting data: {e}")
            return {"intent": "chat", "data": None}  # Default to chat on error

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
            
            response = await self.chat_model.invoke([HumanMessage(content=prompt)])
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
            response = await self.chat_model.invoke([HumanMessage(content=prompt)])
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

    async def _handle_add_transaction(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data:
                return "I need more information to add a transaction. Please provide details like amount, description, type, and date."
            # Create a summary for confirmation
            summary = (
                f"I'm about to add a new transaction:\n"
                f"- Type: {data.get('type')}\n"
                f"- Amount: {data.get('amount')}\n"
                f"- Description: {data.get('description')}\n"
                f"- Date: {data.get('date')}\n"
                f"- Category: {data.get('category')}\n\n"
                f"Is this correct? (yes/no)"
            )
            return summary
        except Exception as e:
            print(f"Error handling add transaction: {e}")
            return "I'm sorry, I had trouble understanding the transaction details. Please try again."

    async def _handle_edit_transaction(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data:
                return "I need more information to edit a transaction. Please specify which transaction to edit and the new details."

            original = data.get('original', {})
            new = data.get('new', {})

            # Create a summary for confirmation
            summary = (
                f"I'm about to edit a transaction.\n"
                f"Original: {original}\n"
                f"New: {new}\n\n"
                f"Is this correct? (yes/no)"
            )
            return summary
        except Exception as e:
            print(f"Error handling edit transaction: {e}")
            return "I'm sorry, I had trouble understanding the transaction details. Please try again."

    async def _handle_delete_transaction(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data:
                return "I need more information to delete a transaction. Please specify which transaction to delete."
            # Create a summary for confirmation
            summary = (
                f"I'm about to delete the following transaction:\n"
                f"{data}\n\n"
                f"Is this correct? (yes/no)"
            )
            return summary
        except Exception as e:
            print(f"Error handling delete transaction: {e}")
            return "I'm sorry, I had trouble understanding which transaction to delete. Please try again."

    async def _handle_manage_category(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data:
                return "I need more information to manage categories. Please specify the action (add, edit, delete) and the category name."

            action = data.get('action')
            name = data.get('name')

            # Create a summary for confirmation
            summary = (
                f"I'm about to {action} the category '{name}'.\n\n"
                f"Is this correct? (yes/no)"
            )
            return summary
        except Exception as e:
            print(f"Error handling manage category: {e}")
            return "I'm sorry, I had trouble understanding the category management request. Please try again."

    async def _handle_list_transaction(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        return "Sorry, I can't list transactions yet."

    async def _handle_financial_tips(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        return "Sorry, I can't provide financial tips yet."

    async def _handle_show_summary(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        return "Sorry, I can't show a summary yet."

    async def _handle_summarize_specific_document(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data or 'document_name' not in data:
                return "Please specify the name of the document you want to summarize."

            document_name = data['document_name']
            document_service = DocumentService(self.db)

            # Fetch the document from the database
            document = await document_service.get_document_by_filename_and_user_id(document_name, user_id)
            if not document:
                return f"I couldn't find a document named '{document_name}'."

            # Get the document content from MinIO
            minio_service = MinIOService()
            try:
                document_content_bytes = await minio_service.get_file(document.minio_object_name)
                # The document content is in bytes, we need to decode it to string
                document_content = document_content_bytes.decode('utf-8')
            except Exception as e:
                print(f"Error fetching document content from MinIO: {e}")
                return "I'm sorry, I encountered an error while retrieving the document content."

            # Generate the summary
            prompt = self.summarize_document_prompt.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                document_name=document_name,
                document_content=document_content
            )
            response = await self.chat_model.invoke([HumanMessage(content=prompt)])
            return response.content

        except Exception as e:
            print(f"Error handling summarize specific document: {e}")
            return "I'm sorry, I encountered an error while summarizing the document."

    async def _handle_search_by_semantic(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data or 'query' not in data:
                return "Please provide a search query."

            query = data['query']
            return await self._handle_document_search(user_id, query, user_info)
        except Exception as e:
            print(f"Error handling search by semantic: {e}")
            return "I'm sorry, I encountered an error while performing the semantic search."

    async def _handle_compare_document(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if not data or 'document_names' not in data or len(data['document_names']) < 2:
                return "Please specify at least two documents to compare."

            doc_names = data['document_names']
            document_service = DocumentService(self.db)
            minio_service = MinIOService()

            document_contents = []
            for name in doc_names:
                document = await document_service.get_document_by_filename_and_user_id(name, user_id)
                if not document:
                    return f"I couldn't find a document named '{name}'."
                try:
                    content_bytes = await minio_service.get_file(document.minio_object_name)
                    document_contents.append({
                        "name": name,
                        "content": content_bytes.decode('utf-8')
                    })
                except Exception as e:
                    print(f"Error fetching document content for '{name}' from MinIO: {e}")
                    return f"I'm sorry, I encountered an error while retrieving the content of '{name}'."

            # Create the comparison string
            document_comparisons = "\n\n---\n\n".join(
                f"Document: {item['name']}\nContent:\n{item['content']}"
                for item in document_contents
            )

            # Generate the comparison
            prompt = self.compare_documents_prompt.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                document_comparisons=document_comparisons
            )
            response = await self.chat_model.invoke([HumanMessage(content=prompt)])
            return response.content

        except Exception as e:
            print(f"Error handling compare document: {e}")
            return "I'm sorry, I encountered an error while comparing the documents."