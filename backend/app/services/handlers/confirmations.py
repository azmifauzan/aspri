from typing import Any, Dict


class ConfirmationsHandler:
    def __init__(self, svc: Any):
        self.svc = svc

    async def confirm_action(self, session_id: int, user_id: int, user_info: Dict[str, Any]) -> str:
        try:
            chat_session = await self.svc.get_chat_session(session_id, user_id)
            if not chat_session:
                return "Could not find the chat session."

            # Prioritize pending actions stored on the session
            if chat_session.pending_action:
                original_intent = chat_session.pending_action.get("intent")
                original_data = chat_session.pending_action.get("data")

                # Clear the pending action immediately
                chat_session.pending_action = None
                await self.svc.db.commit()

                # Re-route to the appropriate handler with the stored data
                if original_intent == "add_transaction":
                    if getattr(self.svc, 'finance_handler', None):
                        return await self.svc.finance_handler.execute_add_transaction(session_id, user_id, original_data, user_info)
                    return await self.svc.handlers.finance.execute_add_transaction(self.svc, session_id, user_id, original_data, user_info)
                elif original_intent == "edit_transaction":
                    if getattr(self.svc, 'finance_handler', None):
                        return await self.svc.finance_handler.execute_edit_transaction(session_id, user_id, original_data, user_info)
                    return await self.svc.handlers.finance.execute_edit_transaction(self.svc, session_id, user_id, original_data, user_info)
                elif original_intent == "delete_transaction":
                    return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "Aksi hapus telah dikonfirmasi, namun eksekusi hapus belum diimplementasikan.")
                elif original_intent == "add_event":
                    if getattr(self.svc, 'events_handler', None):
                        return await self.svc.events_handler.execute_add_event(user_id, original_data, user_info)
                    return await self.svc.handlers.events.execute_add_event(self.svc, user_id, original_data, user_info)
                elif original_intent == "update_event":
                    if getattr(self.svc, 'events_handler', None):
                        return await self.svc.events_handler.execute_update_event(user_id, original_data, user_info)
                    return await self.svc.handlers.events.execute_update_event(self.svc, user_id, original_data, user_info)
                elif original_intent == "delete_event":
                    if getattr(self.svc, 'events_handler', None):
                        return await self.svc.events_handler.execute_delete_event(user_id, original_data, user_info)
                    return await self.svc.handlers.events.execute_delete_event(self.svc, user_id, original_data, user_info)
                else:
                    return "I'm not sure how to handle this pending action."

            # If no pending action, get the last user message that required confirmation
            query = self.svc.select(self.svc.ChatMessage).where(
                self.svc.ChatMessage.chat_session_id == session_id,
                self.svc.ChatMessage.role == 'user',
                self.svc.ChatMessage.intent.notin_(['confirm_action', 'cancel_action', 'chat'])
            ).order_by(self.svc.ChatMessage.created_at.desc()).limit(1)
            result = await self.svc.db.execute(query)
            last_message = result.scalar_one_or_none()

            if not last_message:
                return "I'm not sure what you are confirming. Could you please clarify?"

            original_intent = last_message.intent
            original_data = last_message.structured_data

            if original_intent == "add_transaction":
                if getattr(self.svc, 'finance_handler', None):
                    return await self.svc.finance_handler.execute_add_transaction(session_id, user_id, original_data, user_info, is_first_attempt=True)
                return await self.svc.handlers.finance.execute_add_transaction(self.svc, session_id, user_id, original_data, user_info, is_first_attempt=True)

            elif original_intent == "edit_transaction":
                if getattr(self.svc, 'finance_handler', None):
                    return await self.svc.finance_handler.execute_edit_transaction(session_id, user_id, original_data, user_info)
                return await self.svc.handlers.finance.execute_edit_transaction(self.svc, session_id, user_id, original_data, user_info)

            elif original_intent == "add_event":
                if getattr(self.svc, 'events_handler', None):
                    return await self.svc.events_handler.execute_add_event(user_id, original_data, user_info)
                return await self.svc.handlers.events.execute_add_event(self.svc, user_id, original_data, user_info)

            elif original_intent == "update_event":
                if getattr(self.svc, 'events_handler', None):
                    return await self.svc.events_handler.execute_update_event(user_id, original_data, user_info)
                return await self.svc.handlers.events.execute_update_event(self.svc, user_id, original_data, user_info)

            elif original_intent == "delete_event":
                if getattr(self.svc, 'events_handler', None):
                    return await self.svc.events_handler.execute_delete_event(user_id, original_data, user_info)
                return await self.svc.handlers.events.execute_delete_event(self.svc, user_id, original_data, user_info)

            else:
                system_message = "Action confirmed, but no specific database action was taken for this intent yet."
                return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

        except Exception as e:
            self.svc.print(f"Error handling confirm action: {e}")
            return "I'm sorry, I encountered an error while confirming the action."
