from typing import Any, Dict


class ContactsHandler:
    def __init__(self, svc: Any):
        self.svc = svc

    async def search_contact(self, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        try:
            contact_name = data.get("contact_name") if data else None
            if not contact_name:
                return "Who would you like to search for in your contacts?"

            # Get user object to pass to the service
            user_service = self.svc.UserService(self.svc.db)
            user = await user_service.get_user_by_id(user_id)
            if not user or not getattr(user, 'google_access_token', None):
                return "It seems your Google account isn't linked for contact access. Please link it in the dashboard."

            # Use the GoogleContactService
            contact_service = self.svc.GoogleContactService(user, self.svc.db)
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
            self.svc.print(f"Error handling contact search: {e}")
            return "I'm sorry, I encountered an error while searching your contacts."
