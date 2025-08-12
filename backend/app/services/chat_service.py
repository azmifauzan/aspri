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
from app.schemas.finance import FinancialTransactionCreate, FinancialCategoryCreate

# Services
from app.services.document_service import DocumentService
from app.services.chromadb_service import ChromaDBService
from app.services.minio_service import MinIOService
from app.services.google_contact_service import GoogleContactService
from app.services.finance_service import FinanceService
from app.services.user_service import UserService


# LangChain GenAI imports
from langchain_google_genai import ChatGoogleGenerativeAI
from langchain_core.prompts import PromptTemplate
from langchain_core.messages import HumanMessage, AIMessage, SystemMessage

class ChatService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.chromadb_service = ChromaDBService()
        self.finance_service = FinanceService(db)
        
        # Initialize LangChain GenAI Chat model (Gemini 2.5 Flash)
        self.chat_model = ChatGoogleGenerativeAI(
            model="gemini-2.5-flash",
            google_api_key=os.getenv("GOOGLE_API_KEY", "your-google-api-key-here"),
            temperature=0.7,
            max_tokens=1000
        )
        
        # Intent and data extraction prompt
        self.intent_and_data_extraction_prompt = PromptTemplate.from_template(
            "Based on the chat history, analyze the last user's message and identify the primary intent and any relevant data. "
            "Return the response as a JSON object with two keys: 'intent' and 'data'.\n\n"
            "Possible intents are: 'chat', 'document_search', 'add_transaction', 'edit_transaction', "
            "'delete_transaction', 'manage_category', 'list_transaction', 'financial_tips', 'show_summary', "
            "'summarize_specific_document', 'search_by_semantic', 'compare_document', 'confirm_action', 'cancel_action', 'search_contact'.\n\n"
            "For 'add_transaction', extract: amount, description, date, type, category.\n"
            "For 'edit_transaction', extract: original (details to find the transaction, if the user mentions 'it' or 'the last one', set original to 'last') and new (the updates to apply, e.g., new category, new amount).\n"
            "For 'delete_transaction', extract: details to identify the transaction.\n"
            "For 'manage_category', extract: action (add, edit, delete), name, and type.\n"
            "For 'show_summary', extract: time_range (e.g., 'today', 'this month', 'last week') and type ('income', 'expense', or 'all'). If the user doesn't specify a type, assume 'all'.\n"
            "For 'summarize_specific_document', extract: document_name.\n"
            "For 'search_by_semantic', extract: query.\n"
            "For 'compare_document', extract: document_names (as a list).\n"
            "For 'search_contact', extract: contact_name.\n"
            "If the user says 'yes', 'yup', 'correct', or similar, classify the intent as 'confirm_action'.\n"
            "If the user says 'no', 'nope', 'cancel', or similar, classify the intent as 'cancel_action'.\n"
            "For all other intents, the 'data' field can be null.\n\n"
            "Chat History:\n{history}\n\n"
            "User message: {message}\n\n"
            "JSON output:"
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
            "Based on the following system message, please formulate a response to the user's last message: {user_message}\n"
            "System message: {system_message}\n"
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
        intent_data = await self.classify_user_intent(session_id, message_data.content)
        intent = intent_data.get("intent", "chat")
        data = intent_data.get("data")
        user_message.intent = intent
        user_message.structured_data = data
        
        # Get user info for personalization
        user_info = await self._get_user_info(user_id)
        
        # Generate AI response based on intent
        if intent == "document_search":
            ai_response = await self._handle_document_search(user_id, data.get("query") if data else message_data.content, user_info)
        elif intent == "add_transaction":
            ai_response = await self._handle_add_transaction(session_id, user_id, data, user_info)
        elif intent == "edit_transaction":
            ai_response = await self._handle_edit_transaction(session_id, user_id, data, user_info)
        elif intent == "delete_transaction":
            ai_response = await self._handle_delete_transaction(session_id, user_id, data, user_info)
        elif intent == "manage_category":
            ai_response = await self._handle_manage_category(session_id, user_id, data, user_info)
        elif intent == "list_transaction":
            ai_response = await self._handle_list_transaction(user_id, data, user_info)
        elif intent == "financial_tips":
            ai_response = await self._handle_financial_tips(user_id, data, user_info)
        elif intent == "show_summary":
            ai_response = await self._handle_show_summary(session_id, user_id, data, user_info)
        elif intent == "summarize_specific_document":
            ai_response = await self._handle_summarize_specific_document(user_id, data, user_info)
        elif intent == "search_by_semantic":
            ai_response = await self._handle_search_by_semantic(user_id, data, user_info)
        elif intent == "compare_document":
            ai_response = await self._handle_compare_document(user_id, data, user_info)
        elif intent == "confirm_action":
            ai_response = await self._handle_confirm_action(session_id, user_id, user_info)
        elif intent == "search_contact":
            ai_response = await self._handle_search_contact(user_id, data, user_info)
        elif intent == "cancel_action":
            ai_response = await self._handle_cancel_action(session_id, user_info)
        else:
            # Default to chat response
            ai_response = await self._generate_chat_response(session_id, message_data.content, user_info)
        
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

    async def classify_user_intent(self, session_id: int, message: str) -> Dict[str, Any]:
        """Classify user intent and extract data using Gemini, with chat history."""
        try:
            history = await self._get_conversation_history(session_id)

            # Create the prompt
            prompt = self.intent_and_data_extraction_prompt.format(
                history=history,
                message=message
            )
            
            # Get response from Gemini
            response = self.chat_model.invoke([HumanMessage(content=prompt)])

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
                "search_by_semantic", "compare_document", "confirm_action", "cancel_action", "search_contact"
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
            
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
            return response.content
            
        except Exception as e:
            print(f"Error handling document search: {e}")
            return "I encountered an error while searching your documents. Please try rephrasing your query."

    async def _generate_chat_response(self, session_id: int, user_message: str, user_info: Dict[str, Any], system_message: str = None) -> str:
        """Generate chat response using Gemini, optionally with a system message."""
        try:
            history = await self._get_conversation_history(session_id)
            
            # If no system message, use a simpler prompt
            if system_message is None:
                system_message = "You are a helpful assistant."

            prompt = self.system_instruction.format(
                assistant_name=user_info.get("aspri_name", "ASPRI"),
                assistant_persona=user_info.get("aspri_persona", "helpful"),
                user_name=user_info.get("name", "User"),
                call_preference=user_info.get("call_preference", "User"),
                history=history,
                user_message=user_message,
                system_message=system_message
            )
            
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
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

    async def _handle_add_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self._generate_chat_response(session_id, "placeholder", user_info, "The user wants to add a transaction, but didn't provide enough details. Ask them to provide details like amount, description, type, and date.")

        system_message = (
            f"The user wants to add a new transaction with these details: {data}. "
            "Please confirm with the user if the details are correct before proceeding. Ask for a 'yes' or 'no' response."
        )
        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

    async def _handle_edit_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self._generate_chat_response(session_id, "placeholder", user_info, "The user wants to edit a transaction but hasn't specified which one or what to change. Ask for more details.")

        system_message = (
            f"The user wants to edit a transaction. They want to find a transaction matching '{data.get('original')}' and update it with '{data.get('new')}'. "
            "Please confirm with the user if this is correct. Ask for a 'yes' or 'no' response."
        )
        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

    async def _handle_delete_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self._generate_chat_response(session_id, "placeholder", user_info, "The user wants to delete a transaction but hasn't specified which one. Ask for more details.")

        system_message = (
            f"The user wants to delete a transaction matching these details: {data}. "
            "Please confirm with the user that they want to delete this transaction. Ask for a 'yes' or 'no' response."
        )
        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

    async def _handle_manage_category(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Handles category management directly without a confirmation step."""
        if not data or not data.get('action') or not data.get('name'):
            return await self._generate_chat_response(session_id, "placeholder", user_info, "The user wants to manage a category but hasn't provided enough details. Ask for the action (add, edit, delete) and the category name.")

        action = data.get('action')

        if action == 'add':
            # This logic is moved from the old _execute_add_category
            if 'type' not in data:
                 return await self._generate_chat_response(session_id, "placeholder", user_info, "Please specify a type ('income' or 'expense') for the new category.")

            type_map = {"pemasukan": "income", "pengeluaran": "expense", "income": "income", "expense": "expense"}
            category_type = data.get('type', '').lower()
            mapped_type = type_map.get(category_type)

            if not mapped_type:
                system_message = f"Invalid category type '{data.get('type')}'. Please use 'income' or 'expense'."
                return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

            category_create = FinancialCategoryCreate(name=data['name'], type=mapped_type)
            new_category = await self.finance_service.create_category(user_id, category_create)

            system_message = f"Action completed. The new category has been successfully added. Details: {new_category}"
            return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

        # Placeholder for future edit/delete logic
        else:
            return await self._generate_chat_response(session_id, "placeholder", user_info, f"Sorry, I can't {action} categories just yet.")


    async def _handle_list_transaction(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        return "Sorry, I can't list transactions yet."

    async def _handle_financial_tips(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        return "Sorry, I can't provide financial tips yet."

    async def _handle_show_summary(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        from datetime import datetime, date, timedelta

        time_range_str = data.get("time_range", "this month")
        today = datetime.utcnow().date()

        if time_range_str == 'today':
            start_date = today
            end_date = today
        elif time_range_str == 'this week':
            start_date = today - timedelta(days=today.weekday())
            end_date = start_date + timedelta(days=6)
        elif time_range_str == 'last week':
            end_date = today - timedelta(days=today.weekday() + 1)
            start_date = end_date - timedelta(days=6)
        elif time_range_str == 'this month':
            start_date = today.replace(day=1)
            end_date = (start_date + timedelta(days=31)).replace(day=1) - timedelta(days=1)
        else: # Default to this month
            start_date = today.replace(day=1)
            end_date = (start_date + timedelta(days=31)).replace(day=1) - timedelta(days=1)

        summary_data = await self.finance_service.get_summary(user_id, start_date, end_date)

        system_message = (
            f"The user asked for a financial summary for the period '{time_range_str}' (from {start_date} to {end_date}).\n"
            f"Here is the data:\n"
            f"- Total Income: {summary_data['total_income']}\n"
            f"- Total Expense: {summary_data['total_expense']}\n"
            f"- Net Income: {summary_data['net_income']}\n"
            "Please present this summary to the user in a clear and friendly way, according to your persona."
        )

        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)


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
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
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
            response = self.chat_model.invoke([HumanMessage(content=prompt)])
            return response.content

        except Exception as e:
            print(f"Error handling compare document: {e}")
            return "I'm sorry, I encountered an error while comparing the documents."

    async def _handle_confirm_action(self, session_id: int, user_id: int, user_info: Dict[str, Any]) -> str:
        """Handle the confirmation of a previous action, prioritizing pending actions."""
        try:
            chat_session = await self.get_chat_session(session_id, user_id)
            if not chat_session:
                return "Could not find the chat session."

            # Prioritize pending actions stored on the session
            if chat_session.pending_action:
                original_intent = chat_session.pending_action.get("intent")
                original_data = chat_session.pending_action.get("data")

                # Clear the pending action immediately
                chat_session.pending_action = None
                await self.db.commit()

                # Re-route to the appropriate handler with the stored data
                if original_intent == "add_transaction":
                     return await self._execute_add_transaction(session_id, user_id, original_data, user_info)
                # Add other pending actions here if needed in the future
                else:
                    return "I'm not sure how to handle this pending action."

            # If no pending action, get the last user message that required confirmation
            query = select(ChatMessage).where(
                ChatMessage.chat_session_id == session_id,
                ChatMessage.role == 'user',
                ChatMessage.intent.notin_(['confirm_action', 'cancel_action', 'chat'])
            ).order_by(ChatMessage.created_at.desc()).limit(1)
            result = await self.db.execute(query)
            last_message = result.scalar_one_or_none()

            if not last_message:
                return "I'm not sure what you are confirming. Could you please clarify?"

            original_intent = last_message.intent
            original_data = last_message.structured_data

            if original_intent == "add_transaction":
                return await self._execute_add_transaction(session_id, user_id, original_data, user_info, is_first_attempt=True)

            elif original_intent == "edit_transaction":
                return await self._execute_edit_transaction(session_id, user_id, original_data, user_info)

            else:
                system_message = "Action confirmed, but no specific database action was taken for this intent yet."
                return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

        except Exception as e:
            print(f"Error handling confirm action: {e}")
            return "I'm sorry, I encountered an error while confirming the action."

    async def _handle_edit_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self._generate_chat_response(session_id, "placeholder", user_info, "The user wants to edit a transaction but hasn't specified which one or what to change. Ask for more details.")

        system_message = (
            f"The user wants to edit a transaction. They want to find a transaction matching '{data.get('original')}' and update it with '{data.get('new')}'. "
            "Please confirm with the user if this is correct. Ask for a 'yes' or 'no' response."
        )
        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

    async def _execute_add_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any], is_first_attempt: bool = False) -> str:
        """Helper method to contain the logic for adding a transaction."""
        if not data:
            return "I don't have the details for the transaction to add."

        category_name = data.pop('category', None)
        category_id = None

        if category_name:
            category = await self.finance_service.get_category_by_name(user_id, category_name)
            if category:
                category_id = category.id
            elif is_first_attempt:
                # Category not found, store pending action
                chat_session = await self.get_chat_session(session_id, user_id)
                data['category'] = category_name # put it back for when we re-process
                chat_session.pending_action = {"intent": "add_transaction", "data": data}
                await self.db.commit()

                system_message = f"The category '{category_name}' was not found. Please confirm if I should create it first, or you can add it manually."
                return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)
            else:
                # This case happens if it's a pending action and the category is still not found.
                return "The category is still not found. Please add it first."

        if category_id:
            data['category_id'] = category_id

        if 'type' in data:
            type_map = {"pemasukan": "income", "pengeluaran": "expense", "income": "income", "expense": "expense"}
            data['type'] = type_map.get(data['type'].lower(), data['type'])

        if 'date' not in data or not data.get('date'):
            data['date'] = datetime.utcnow().date()
        elif isinstance(data['date'], str):
            try:
                data['date'] = datetime.fromisoformat(data['date'].replace('Z', '+00:00')).date()
            except ValueError:
                data['date'] = datetime.utcnow().date()

        if 'amount' in data:
            try:
                data['amount'] = float(data['amount'])
            except (ValueError, TypeError):
                system_message = "The amount provided is not a valid number. Please try again."
                return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

        transaction_create = FinancialTransactionCreate(**data)
        new_transaction = await self.finance_service.create_transaction(user_id, transaction_create)
        system_message = f"Successfully added the transaction. Details: {new_transaction}"
        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

    async def _execute_edit_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Helper method to contain the logic for editing a transaction."""
        if not data or 'original' not in data or 'new' not in data:
            return await self._generate_chat_response(session_id, "placeholder", user_info, "I'm not sure which transaction to edit or what to change. Please be more specific.")

        if data['original'] != 'last':
            # For now, we only support editing the 'last' transaction
            return await self._generate_chat_response(session_id, "placeholder", user_info, "Sorry, I can only edit the most recent transaction for now.")

        last_transaction = await self.finance_service.get_last_transaction(user_id)
        if not last_transaction:
            return await self._generate_chat_response(session_id, "placeholder", user_info, "There are no transactions to edit.")

        update_payload = {}
        new_data = data['new']

        # Check for category update
        if 'category' in new_data:
            category_name = new_data['category']
            category = await self.finance_service.get_category_by_name(user_id, category_name)
            if not category:
                return await self._generate_chat_response(session_id, "placeholder", user_info, f"I couldn't find the category '{category_name}'. Please create it first.")
            update_payload['category_id'] = category.id

        # Add other updatable fields here if necessary (e.g., amount, description)
        if 'amount' in new_data:
            try:
                update_payload['amount'] = float(new_data['amount'])
            except (ValueError, TypeError):
                return await self._generate_chat_response(session_id, "placeholder", user_info, "The new amount provided is not a valid number.")

        if 'description' in new_data:
            update_payload['description'] = new_data['description']


        if not update_payload:
            return await self._generate_chat_response(session_id, "placeholder", user_info, "You didn't specify any changes. What would you like to update?")

        updated_transaction = await self.finance_service.update_transaction(last_transaction.id, update_payload)

        system_message = f"Successfully updated the last transaction. The new details are: {updated_transaction}"
        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

    async def _handle_cancel_action(self, session_id: int, user_info: Dict[str, Any]) -> str:
        """Handles the user cancelling an action."""
        system_message = "The user has cancelled the previous action. Acknowledge this and ask what they would like to do next."
        return await self._generate_chat_response(session_id, "placeholder", user_info, system_message)

    async def _handle_search_contact(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Handle contact search intent."""
        try:
            contact_name = data.get("contact_name") if data else None
            if not contact_name:
                return "Who would you like to search for in your contacts?"

            # Get user object to pass to the service
            user_service = UserService(self.db)
            user = await user_service.get_user_by_id(user_id)
            if not user or not user.google_access_token:
                return "It seems your Google account isn't linked for contact access. Please link it in the dashboard."

            # Use the GoogleContactService
            contact_service = GoogleContactService(user, self.db)
            all_contacts = await contact_service.list_contacts()

            # Filter contacts by name
            found_contacts = [
                c for c in all_contacts
                if contact_name.lower() in c.get("name", "").lower()
            ]

            if not found_contacts:
                return f"I couldn't find anyone named '{contact_name}' in your contacts."

            # Format the response
            response_lines = [f"I found {len(found_contacts)} contact(s) matching '{contact_name}':"]
            for contact in found_contacts:
                contact_info = f"- Name: {contact.get('name')}"
                if contact.get('email'):
                    contact_info += f", Email: {contact.get('email')}"
                if contact.get('phone'):
                    contact_info += f", Phone: {contact.get('phone')}"
                response_lines.append(contact_info)

            return "\n".join(response_lines)

        except Exception as e:
            print(f"Error handling contact search: {e}")
            return "I'm sorry, I encountered an error while searching your contacts."