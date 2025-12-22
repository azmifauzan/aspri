# Finance Feature

The finance feature allows users to track their income and expenses, manage categories, and view financial summaries. The feature is accessible through both a graphical user interface (GUI) and a chat-based interface.

## Flow and Process

The finance feature is designed to be intuitive and easy to use. The general flow for managing financial data is as follows:

1.  **Add a transaction:** The user can add a new income or expense transaction by providing the amount, description, date, and category. This can be done through the GUI or by sending a message to the chat assistant (e.g., "add expense 50000 for lunch").
2.  **Confirmation:** When adding, editing, or deleting a transaction or category through the chat interface, the assistant will first present a summary of the requested action and ask for confirmation. This helps to prevent accidental data modification.
3.  **View transactions:** The user can view a list of all their transactions in the "Mutasi" (Transactions) tab of the finance page. The list can be filtered by category.
4.  **View summary:** The user can view a summary of their finances in the "Ringkasan" (Summary) tab of the finance page. The summary includes charts that show income vs. expense and spending by category.
5.  **Manage categories:** The user can add, edit, and delete financial categories. This helps to organize transactions and provide more detailed financial insights.

## API Usage

The finance feature is powered by a set of RESTful API endpoints. These endpoints are used by the frontend to manage financial data.

-   `GET /finance/categories`: Get all financial categories.
-   `POST /finance/categories`: Create a new financial category.
-   `PUT /finance/categories/{category_id}`: Update a financial category.
-   `DELETE /finance/categories/{category_id}`: Delete a financial category.
-   `GET /finance/transactions`: Get all financial transactions.
-   `POST /finance/transactions`: Create a new financial transaction.
-   `PUT /finance/transactions/{transaction_id}`: Update a financial transaction.
-   `DELETE /finance/transactions/{transaction_id}`: Delete a financial transaction.
-   `GET /finance/summary`: Get a financial summary.

## Chat Commands

The finance feature can also be controlled through the chat interface. The following intents are supported:

-   `add_transaction`: Add a new transaction.
-   `edit_transaction`: Edit an existing transaction.
-   `delete_transaction`: Delete an existing transaction.
-   `manage_category`: Add or edit a category.
-   `list_transaction`: List transactions, with optional filtering by category.
-   `financial_tips`: Provide a summary or tips from the LLM based on current financial data.
-   `show_summary`: Show a financial summary.
