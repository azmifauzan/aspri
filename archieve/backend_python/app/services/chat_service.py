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
from app.services.google_calendar_service import GoogleCalendarService
from app.services.finance_service import FinanceService
from app.services.user_service import UserService
from app.services.llm_log_service import LLMLogService


# LangChain GenAI imports
from langchain_google_genai import ChatGoogleGenerativeAI
from langchain_core.prompts import PromptTemplate
from langchain_core.messages import HumanMessage, AIMessage, SystemMessage

# =========================
# 🚦 Policy: Auto / Confirm / Never
# =========================
# Intent yang aman dieksekusi otomatis (read-only / tanpa side-effect)
ALLOW_AUTO_INTENTS = {
    "document_search",
    "search_by_semantic",
    "list_transaction",
    "show_summary",
    "financial_tips",
    "search_contact",
    "compare_document",
    "summarize_specific_document",
    "list_events",
}
# Intent yang WAJIB konfirmasi dulu (ada side-effect tulis/ubah/hapus)
CONFIRM_FIRST_INTENTS = {
    "add_transaction",
    "edit_transaction",
    "delete_transaction",
    "add_event",
    "update_event",
    "delete_event",
}
# Intent yang TIDAK BOLEH auto (kalau di masa depan ada aksi destruktif)
NEVER_AUTO_INTENTS = set()


class ChatService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.chromadb_service = ChromaDBService()
        self.finance_service = FinanceService(db)
        self.llm_log_service = LLMLogService(db)
        
        # Initialize LangChain GenAI Chat model (Gemini 2.5 Flash)
        self.chat_model = ChatGoogleGenerativeAI(
            model="gemini-2.5-flash",
            google_api_key=os.getenv("GOOGLE_API_KEY", "your-google-api-key-here"),
            temperature=0.7,
            max_tokens=1000
        )
        # Attach handler modules and commonly used classes/functions so handlers can access via svc
        try:
            from app.services import handlers as _handlers_pkg
            self.handlers = _handlers_pkg
        except Exception:
            self.handlers = None

        # Export commonly used classes and functions to the instance for handler convenience
        self.UserService = UserService
        self.GoogleCalendarService = GoogleCalendarService
        self.DocumentService = DocumentService
        self.MinIOService = MinIOService
        self.GoogleContactService = GoogleContactService
        self.FinancialTransactionCreate = FinancialTransactionCreate
        self.FinancialCategoryCreate = FinancialCategoryCreate
        self.DocumentSearchQuery = DocumentSearchQuery
        self.HumanMessage = HumanMessage
        self.AIMessage = AIMessage
        self.SystemMessage = SystemMessage
        self.select = select
        self.ChatMessage = ChatMessage
        self.print = print
        # Instantiate handler classes when available (some handlers are class-based)
        try:
            self.events_handler = self.handlers.events.EventsHandler(self)
        except Exception:
            self.events_handler = None

        try:
            self.documents_handler = self.handlers.documents.DocumentsHandler(self)
        except Exception:
            self.documents_handler = None

        try:
            self.finance_handler = self.handlers.finance.FinanceHandler(self)
        except Exception:
            self.finance_handler = None
        try:
            self.contacts_handler = self.handlers.contacts.ContactsHandler(self)
        except Exception:
            self.contacts_handler = None
        try:
            self.confirmations_handler = self.handlers.confirmations.ConfirmationsHandler(self)
        except Exception:
            self.confirmations_handler = None
        
        # Intent and data extraction prompt
        self.intent_and_data_extraction_prompt = PromptTemplate.from_template(
            "Based on the chat history, analyze the last user's message and identify the primary intent and any relevant data. "
            "Return the response as a JSON object with two keys: 'intent' and 'data'.\n\n"
            "Possible intents are: 'chat', 'document_search', 'add_transaction', 'edit_transaction', "
            "'delete_transaction', 'manage_category', 'list_transaction', 'financial_tips', 'show_summary', "
            "'summarize_specific_document', 'search_by_semantic', 'compare_document', 'confirm_action', 'cancel_action', "
            "'search_contact', 'list_events', 'add_event', 'update_event', 'delete_event'.\n\n"
            "For 'add_transaction', extract: amount, description, date, type, category.\n"
            "For 'edit_transaction', extract: original (details to find the transaction, if the user mentions 'it' or 'the last one', set original to 'last') and new (the updates to apply, e.g., new category, new amount).\n"
            "For 'delete_transaction', extract: details to identify the transaction.\n"
            "For 'manage_category', extract: action (add, edit, delete), name, and type.\n"
            "For 'show_summary', extract: time_range (e.g., 'today', 'this month', 'last week') and type ('income', 'expense', or 'all'). If the user doesn't specify a type, assume 'all'.\n"
            "For 'summarize_specific_document', extract: document_name.\n"
            "For 'search_by_semantic', extract: query.\n"
            "For 'compare_document', extract: document_names (as a list).\n"
            "For 'search_contact', extract: contact_name.\n"
            "For 'list_events', extract: time_range (e.g., 'today', 'this week', 'next week').\n"
            "When the intent is 'list_events', ALWAYS include a 'time_range' field inside 'data' in the JSON output. "
            "Acceptable values: 'today', 'this week', 'next week', 'this month', 'upcoming'. If uncertain, prefer the value that best matches the user's phrasing (do not omit this field).\n"
            "For 'add_event', extract: summary, description, start_time, end_time.\n"
            "For 'update_event', extract: original (details to find the event) and new (the updates to apply).\n"
            "For 'delete_event', extract: details to identify the event.\n"
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
        """Return all chat sessions for a user ordered by most recent update."""
        query = select(ChatSession).where(
            ChatSession.user_id == user_id
        ).order_by(ChatSession.updated_at.desc())

        result = await self.db.execute(query)
        return result.scalars().all()

    async def get_chat_session(self, session_id: int, user_id: int) -> Optional[ChatSession]:
        """Get a chat session with its messages for a user."""
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

    # =========================
    # 🔁 Helper: store pending + build confirmation text
    # =========================
    async def _store_pending_action(self, session_id: int, user_id: int, intent: str, data: Dict[str, Any]) -> None:
        chat_session = await self.get_chat_session(session_id, user_id)
        if not chat_session:
            return
        chat_session.pending_action = {"intent": intent, "data": data}
        await self.db.commit()

    async def _build_confirmation_text(
        self,
        session_id: int,
        user_id: int,
        intent: str,
        data: Dict[str, Any],
        user_info: Dict[str, Any],
    ) -> str:
        """Build a human-friendly confirmation message, but render it via LLM
        so the tone stays consistent with the Aspri persona.
        """
        # Raw summary (system-side)
        if intent == "add_transaction":
            amt = data.get("amount")
            dt = data.get("date")
            typ = data.get("type")
            cat = data.get("category")
            desc = data.get("description")
            raw_summary = (
                "Konfirmasi tambah transaksi berikut:\n"
                f"- Jumlah: {amt}\n- Tanggal: {dt}\n- Tipe: {typ}\n- Kategori: {cat}\n- Deskripsi: {desc}\n\n"
                "Instruksi balasan: Minta user untuk balas **ya** untuk konfirmasi atau **batal** untuk membatalkan."
            )
        elif intent == "edit_transaction":
            raw_summary = (
                "Konfirmasi ubah transaksi:\n"
                f"- Target: {data.get('original')}\n- Perubahan: {data.get('new')}\n\n"
                "Instruksi balasan: Minta user untuk balas **ya** untuk konfirmasi atau **batal** untuk membatalkan."
            )
        elif intent == "delete_transaction":
            raw_summary = (
                "Konfirmasi hapus transaksi dengan kriteria berikut:\n"
                f"- Detail: {data}\n\n"
                "Instruksi balasan: Minta user untuk balas **ya** untuk konfirmasi atau **batal** untuk membatalkan."
            )
        else:
            raw_summary = (
                f"Konfirmasi aksi '{intent}' dengan argumen berikut:\n{json.dumps(data or {}, ensure_ascii=False)}\n\n"
                "Instruksi balasan: Minta user untuk balas **ya** untuk konfirmasi atau **batal** untuk membatalkan."
            )

        # Kirim ke LLM agar diformat sesuai persona + bahasa user
        system_message = (
            "Ubah ringkasan teknis di bawah ini menjadi pesan konfirmasi singkat yang ramah, "
            "konsisten dengan persona asisten, dan gunakan bahasa yang sama dengan pesan user. "
            "Akhiri dengan instruksi yang jelas untuk membalas **ya** (konfirmasi) atau **batal**."
        )
        # Gunakan _generate_chat_response agar persona konsisten
        rendered = await self._generate_chat_response(
            session_id=session_id,
            user_id=user_id,
            user_message="placeholder",
            user_info=user_info,
            system_message=f"{system_message}\n\nRingkasan:\n{raw_summary}"
        )
        return rendered

    def _is_data_complete_for_add(self, data: Optional[Dict[str, Any]]) -> bool:
        if not data:
            return False
        required = {"amount", "description", "type"}
        return required.issubset(set(data.keys()))

    # =========================
    # ✉️ Main entry
    # =========================
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
        intent_data = await self.classify_user_intent(session_id, user_id, message_data.content)
        intent = intent_data.get("intent", "chat")
        data = intent_data.get("data")
        user_message.intent = intent
        user_message.structured_data = data
        
        # Get user info for personalization
        user_info = await self._get_user_info(user_id)

        # =========================
        # 🚦 Policy Gate
        # =========================
        if intent in NEVER_AUTO_INTENTS:
            ai_response = "Maaf, aksi ini tidak dapat dijalankan otomatis."
        elif intent in CONFIRM_FIRST_INTENTS:
            if intent == "add_transaction" and not self._is_data_complete_for_add(data):
                ai_response = await self._generate_chat_response(
                    session_id, user_id, "placeholder", user_info,
                    "Kamu ingin menambahkan transaksi. Tolong beri detail minimal: jumlah (amount), deskripsi, tipe (income/expense), dan opsional tanggal/kategori."
                )
            else:
                await self._store_pending_action(session_id, user_id, intent, data or {})
                ai_response = await self._build_confirmation_text(
                    session_id=session_id,
                    user_id=user_id,
                    intent=intent,
                    data=data or {},
                    user_info=user_info
                )
        elif intent in ALLOW_AUTO_INTENTS:
            # langsung eksekusi handler terkait (read-only / aman)
            if intent == "document_search":
                ai_response = await self._handle_document_search(user_id, data.get("query") if data else message_data.content, user_info)
            elif intent == "search_by_semantic":
                ai_response = await self._handle_search_by_semantic(user_id, data, user_info)
            elif intent == "list_transaction":
                ai_response = await self._handle_list_transaction(session_id, user_id, data, user_info)
            elif intent == "show_summary":
                ai_response = await self._handle_show_summary(session_id, user_id, data or {}, user_info)
            elif intent == "financial_tips":
                ai_response = await self._handle_financial_tips(session_id, user_id, data, user_info)
            elif intent == "search_contact":
                ai_response = await self._handle_search_contact(user_id, data, user_info)
            elif intent == "compare_document":
                ai_response = await self._handle_compare_document(user_id, data, user_info)
            elif intent == "summarize_specific_document":
                ai_response = await self._handle_summarize_specific_document(user_id, data, user_info)
            elif intent == "list_events":
                ai_response = await self._handle_list_events(session_id, user_id, data, user_info)
            else:
                # fallback ke chat jika masuk allowlist tapi belum ada handler khusus
                ai_response = await self._generate_chat_response(session_id, user_id, message_data.content, user_info)
        else:
            # Intent lain: pakai handler yang ada / default chat
            if intent == "manage_category":
                ai_response = await self._handle_manage_category(session_id, user_id, data, user_info)
            elif intent == "confirm_action":
                ai_response = await self._handle_confirm_action(session_id, user_id, user_info)
            elif intent == "cancel_action":
                ai_response = await self._handle_cancel_action(session_id, user_id, user_info)
            elif intent == "add_transaction":
                # (fallback lama jika tidak masuk CONFIRM_FIRST_INTENTS — harusnya tidak terjadi)
                ai_response = await self._handle_add_transaction(session_id, user_id, data, user_info)
            elif intent == "edit_transaction":
                ai_response = await self._handle_edit_transaction(session_id, user_id, data, user_info)
            elif intent == "delete_transaction":
                ai_response = await self._handle_delete_transaction(session_id, user_id, data, user_info)
            elif intent == "add_event":
                ai_response = await self._handle_add_event(session_id, user_id, data, user_info)
            elif intent == "update_event":
                ai_response = await self._handle_update_event(session_id, user_id, data, user_info)
            elif intent == "delete_event":
                ai_response = await self._handle_delete_event(session_id, user_id, data, user_info)
            else:
                ai_response = await self._generate_chat_response(session_id, user_id, message_data.content, user_info)
        
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

    async def classify_user_intent(self, session_id: int, user_id: int, message: str) -> Dict[str, Any]:
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

            # Log the interaction
            await self.llm_log_service.create_log(
                prompt_type="intent_extraction",
                prompt_data={"history": history, "message": message},
                llm_response=response.content,
                user_id=user_id,
                chat_session_id=session_id
            )

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
                "search_by_semantic", "compare_document", "confirm_action", "cancel_action", "search_contact",
                "list_events", "add_event", "update_event", "delete_event"
            ]
            if intent_data.get("intent") in valid_intents:
                # If user asked to list events but extractor didn't provide a time_range,
                # ask the LLM a focused follow-up to determine the time range so handlers
                # can call the calendar API with proper filters.
                # No follow-up required: primary extractor prompt now requests time_range for list_events.

                # As a final safety, ensure list_events always has a time_range key
                if intent_data.get('intent') == 'list_events':
                    d = intent_data.get('data') or {}
                    if not d.get('time_range'):
                        d['time_range'] = 'upcoming'
                        intent_data['data'] = d

                return intent_data
            else:
                return {"intent": "chat", "data": None}  # Default to chat if uncertain
        except Exception as e:
            print(f"Error classifying intent or extracting data: {e}")
            return {"intent": "chat", "data": None}  # Default to chat on error

    async def _handle_list_events(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Delegate to events handler (class-based if available)."""
        try:
            if self.events_handler:
                return await self.events_handler.list_events(session_id, user_id, data, user_info)
            raise RuntimeError("EventsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"
    async def _handle_add_event(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.events_handler:
                return await self.events_handler.add_event(session_id, user_id, data, user_info)
            raise RuntimeError("EventsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _handle_update_event(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.events_handler:
                return await self.events_handler.update_event(session_id, user_id, data, user_info)
            raise RuntimeError("EventsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _handle_delete_event(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.events_handler:
                return await self.events_handler.delete_event(session_id, user_id, data, user_info)
            raise RuntimeError("EventsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _handle_document_search(self, user_id: int, query: str, user_info: Dict[str, Any]) -> str:
        try:
            if self.documents_handler:
                return await self.documents_handler.document_search(user_id, query, user_info)
            raise RuntimeError("DocumentsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _generate_chat_response(self, session_id: int, user_id: int, user_message: str, user_info: Dict[str, Any], system_message: str = None) -> str:
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

            await self.llm_log_service.create_log(
                prompt_type="chat_response",
                prompt_data={
                    "user_info": user_info,
                    "history": history,
                    "user_message": user_message,
                    "system_message": system_message
                },
                llm_response=response.content,
                user_id=user_id,
                chat_session_id=session_id
            )

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
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, "The user wants to add a transaction, but didn't provide enough details. Ask them to provide details like amount, description, type, and date.")

        system_message = (
            f"The user wants to add a new transaction with these details: {data}. "
            "Please confirm with the user if the details are correct before proceeding. Ask for a 'yes' or 'no' response."
        )
        return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def _handle_edit_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, "The user wants to edit a transaction but hasn't specified which one or what to change. Ask for more details.")

        system_message = (
            f"The user wants to edit a transaction. They want to find a transaction matching '{data.get('original')}' and update it with '{data.get('new')}'. "
            "Please confirm with the user if this is correct. Ask for a 'yes' or 'no' response."
        )
        return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def _handle_delete_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, "The user wants to delete a transaction but hasn't specified which one. Ask for more details.")

        system_message = (
            f"The user wants to delete a transaction matching these details: {data}. "
            "Please confirm with the user that they want to delete this transaction. Ask for a 'yes' or 'no' response."
        )
        return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def _handle_manage_category(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Handles category management directly without a confirmation step."""
        if not data or not data.get('action') or not data.get('name'):
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, "The user wants to manage a category but hasn't provided enough details. Ask for the action (add, edit, delete) and the category name.")

        action = data.get('action')

        if action == 'add':
            # This logic is moved from the old _execute_add_category
            if 'type' not in data:
                 return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, "Please specify a type ('income' or 'expense') for the new category.")

            type_map = {"pemasukan": "income", "pengeluaran": "expense", "income": "income", "expense": "expense"}
            category_type = data.get('type', '').lower()
            mapped_type = type_map.get(category_type)

            if not mapped_type:
                system_message = f"Invalid category type '{data.get('type')}'. Please use 'income' or 'expense'."
                return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

            category_create = FinancialCategoryCreate(name=data['name'], type=mapped_type)
            new_category = await self.finance_service.create_category(user_id, category_create)

            system_message = f"Action completed. The new category has been successfully added. Details: {new_category}"
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

        # Placeholder for future edit/delete logic
        else:
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, f"Sorry, I can't {action} categories just yet.")


    async def _handle_list_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Handles listing user's financial transactions."""
        try:
            transactions = await self.finance_service.get_transactions(user_id)

            if not transactions:
                system_message = "The user has no transactions recorded. Let them know this and maybe encourage them to add one."
                return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

            # Format the last 10 transactions for display
            formatted_transactions = []
            for t in transactions[-10:]:
                # Assuming category is loaded or accessible. If not, this might need adjustment.
                category_name = t.category.name if t.category else "Uncategorized"
                formatted_transactions.append(
                    f"- Date: {t.date}, Type: {t.type.name}, Amount: {t.amount}, Desc: {t.description}, Category: {category_name}"
                )

            transaction_list_str = "\n".join(formatted_transactions)

            system_message = (
                f"The user has asked for their transaction list. Here are the last 10:\n{transaction_list_str}\n\n"
                "Present this list to the user in a clear and organized way, according to your persona."
            )
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

        except Exception as e:
            print(f"Error in _handle_list_transaction: {e}")
            system_message = "An unexpected error occurred while trying to retrieve the transaction list. Apologize to the user and suggest they try again later."
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def _handle_financial_tips(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        """Generates financial tips based on the user's recent activity."""
        try:
            # Get financial summary for the last 30 days
            end_date = datetime.utcnow().date()
            from datetime import timedelta
            start_date = end_date - timedelta(days=30)

            summary = await self.finance_service.get_summary(user_id, start_date, end_date)

            # Check if there's enough data to provide tips
            if summary['total_income'] == 0 and summary['total_expense'] == 0:
                system_message = "I don't have enough data about your finances to give tips yet. Try adding some transactions first!"
                return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

            # Create a detailed prompt for the LLM
            prompt_data = (
                f"Here is the user's financial summary for the last 30 days:\n"
                f"- Total Income: {summary['total_income']}\n"
                f"- Total Expense: {summary['total_expense']}\n"
                f"- Net Income: {summary['net_income']}\n\n"
                "Based on this data, please provide 2-3 actionable and personalized financial tips for the user. "
                "Consider their income vs. expense ratio. If expenses are high, suggest ways to save. "
                "If income is good, suggest investment or saving strategies. "
                "Keep the tone helpful and aligned with your persona."
            )

            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message=prompt_data)

        except Exception as e:
            print(f"Error in _handle_financial_tips: {e}")
            system_message = "I had trouble analyzing the financial data to generate tips. Please try again later."
            return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def _handle_show_summary(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        from datetime import datetime, date, timedelta

        time_range_str = data.get("time_range", "this month")
        transaction_type = data.get("type", "all")
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

        type_to_fetch = transaction_type if transaction_type in ['income', 'expense'] else None
        summary_data = await self.finance_service.get_summary(user_id, start_date, end_date, transaction_type=type_to_fetch)

        if transaction_type == 'income':
            system_message = (
                f"The user asked for their income summary for '{time_range_str}'.\n"
                f"Data: Total Income = {summary_data['total_income']}.\n"
                "Present this to the user."
            )
        elif transaction_type == 'expense':
            system_message = (
                f"The user asked for their expense summary for '{time_range_str}'.\n"
                f"Data: Total Expense = {summary_data['total_expense']}.\n"
                "Present this to the user."
            )
        else:
            system_message = (
                f"The user asked for a financial summary for '{time_range_str}'.\n"
                f"Data: Total Income = {summary_data['total_income']}, Total Expense = {summary_data['total_expense']}, Net Income = {summary_data['net_income']}.\n"
                "Present this summary to the user."
            )

        return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)


    async def _handle_summarize_specific_document(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.documents_handler:
                return await self.documents_handler.summarize_specific_document(user_id, data, user_info)
            raise RuntimeError("DocumentsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _handle_search_by_semantic(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            # map to document search which expects (user_id, query, user_info)
            query = None
            if data and isinstance(data, dict):
                query = data.get('query')
            if self.documents_handler:
                return await self.documents_handler.document_search(user_id, query or '', user_info)
            raise RuntimeError("DocumentsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _handle_compare_document(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.documents_handler:
                return await self.documents_handler.compare_document(user_id, data, user_info)
            raise RuntimeError("DocumentsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _handle_confirm_action(self, session_id: int, user_id: int, user_info: Dict[str, Any]) -> str:
        try:
            if getattr(self, 'confirmations_handler', None):
                return await self.confirmations_handler.confirm_action(session_id, user_id, user_info)
            raise RuntimeError("ConfirmationsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _execute_add_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any], is_first_attempt: bool = False) -> str:
        try:
            if self.finance_handler:
                return await self.finance_handler.execute_add_transaction(session_id, user_id, data, user_info, is_first_attempt=is_first_attempt)
            raise RuntimeError("FinanceHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _execute_edit_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.finance_handler:
                return await self.finance_handler.execute_edit_transaction(session_id, user_id, data, user_info)
            raise RuntimeError("FinanceHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _execute_add_event(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.events_handler:
                return await self.events_handler.execute_add_event(user_id, data, user_info)
            raise RuntimeError("EventsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _execute_update_event(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.events_handler:
                return await self.events_handler.execute_update_event(user_id, data, user_info)
            raise RuntimeError("EventsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _execute_delete_event(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if self.events_handler:
                return await self.events_handler.execute_delete_event(user_id, data, user_info)
            raise RuntimeError("EventsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"

    async def _handle_cancel_action(self, session_id: int, user_id: int, user_info: Dict[str, Any]) -> str:
        """Handles the user cancelling an action."""
        system_message = "The user has cancelled the previous action. Acknowledge this and ask what they would like to do next."
        return await self._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def _handle_search_contact(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            if getattr(self, 'contacts_handler', None):
                return await self.contacts_handler.search_contact(user_id, data, user_info)
            raise RuntimeError("ContactsHandler not initialized")
        except Exception as e:
            return f"An error occurred: {e}"
