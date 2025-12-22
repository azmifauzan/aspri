from typing import Any, Dict
import datetime


class FinanceHandler:
    def __init__(self, svc: Any):
        self.svc = svc

    async def add_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "The user wants to add a transaction, but didn't provide enough details. Ask them to provide details like amount, description, type, and date.")

        system_message = (
            f"The user wants to add a new transaction with these details: {data}. "
            "Please confirm with the user if the details are correct before proceeding. Ask for a 'yes' or 'no' response."
        )
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def edit_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "The user wants to edit a transaction but hasn't specified which one or what to change. Ask for more details.")

        system_message = (
            f"The user wants to edit a transaction. They want to find a transaction matching '{data.get('original')}' and update it with '{data.get('new')}'. "
            "Please confirm with the user if this is correct. Ask for a 'yes' or 'no' response."
        )
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def delete_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data:
            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "The user wants to delete a transaction but hasn't specified which one. Ask for more details.")

        system_message = (
            f"The user wants to delete a transaction matching these details: {data}. "
            "Please confirm with the user that they want to delete this transaction. Ask for a 'yes' or 'no' response."
        )
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def execute_add_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any], is_first_attempt: bool = False) -> str:
        if not data:
            return "I don't have the details for the transaction to add."

        category_name = data.pop('category', None)
        category_id = None

        if category_name:
            category = await self.svc.finance_service.get_category_by_name(user_id, category_name)
            if category:
                category_id = category.id
            elif is_first_attempt:
                # Category not found, store pending action
                chat_session = await self.svc.get_chat_session(session_id, user_id)
                data['category'] = category_name # put it back for when we re-process
                chat_session.pending_action = {"intent": "add_transaction", "data": data}
                await self.svc.db.commit()

                system_message = f"The category '{category_name}' was not found. Please confirm if I should create it first, or you can add it manually."
                return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)
            else:
                # This case happens if it's a pending action and the category is still not found.
                return "The category is still not found. Please add it first."

        if category_id:
            data['category_id'] = category_id

        if 'type' in data:
            type_map = {"pemasukan": "income", "pengeluaran": "expense", "income": "income", "expense": "expense"}
            data['type'] = type_map.get(data['type'].lower(), data['type'])

        if 'date' not in data or not data.get('date'):
            data['date'] = datetime.datetime.utcnow().date()
        elif isinstance(data['date'], str):
            try:
                data['date'] = datetime.datetime.fromisoformat(data['date'].replace('Z', '+00:00')).date()
            except ValueError:
                data['date'] = datetime.datetime.utcnow().date()

        if 'amount' in data:
            try:
                data['amount'] = float(data['amount'])
            except (ValueError, TypeError):
                system_message = "The amount provided is not a valid number. Please try again."
                return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

        transaction_create = self.svc.FinancialTransactionCreate(**data)
        new_transaction = await self.svc.finance_service.create_transaction(user_id, transaction_create)
        system_message = f"Successfully added the transaction. Details: {new_transaction}"
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)

    async def execute_edit_transaction(self, session_id: int, user_id: int, data: Dict[str, Any], user_info: Dict[str, Any]) -> str:
        if not data or 'original' not in data or 'new' not in data:
            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "I'm not sure which transaction to edit or what to change. Please be more specific.")

        if data['original'] != 'last':
            # For now, we only support editing the 'last' transaction
            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "Sorry, I can only edit the most recent transaction for now.")

        last_transaction = await self.svc.finance_service.get_last_transaction(user_id)
        if not last_transaction:
            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "There are no transactions to edit.")

        update_payload = {}
        new_data = data['new']

        # Check for category update
        if 'category' in new_data:
            category_name = new_data['category']
            category = await self.svc.finance_service.get_category_by_name(user_id, category_name)
            if not category:
                return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, f"I couldn't find the category '{category_name}'. Please create it first.")
            update_payload['category_id'] = category.id

        # Add other updatable fields here if necessary (e.g., amount, description)
        if 'amount' in new_data:
            try:
                update_payload['amount'] = float(new_data['amount'])
            except (ValueError, TypeError):
                return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "The new amount provided is not a valid number.")

        if 'description' in new_data:
            update_payload['description'] = new_data['description']


        if not update_payload:
            return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, "You didn't specify any changes. What would you like to update?")

        updated_transaction = await self.svc.finance_service.update_transaction(last_transaction.id, update_payload)

        system_message = f"Successfully updated the last transaction. The new details are: {updated_transaction}"
        return await self.svc._generate_chat_response(session_id, user_id, "placeholder", user_info, system_message)
