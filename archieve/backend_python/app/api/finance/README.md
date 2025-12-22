# Finance API

This API provides endpoints for managing financial data, including categories and transactions.

## Endpoints

### Categories

- `GET /categories`: Retrieve all financial categories for the current user.
- `POST /categories`: Create a new financial category.
- `PUT /categories/{category_id}`: Update an existing financial category.
- `DELETE /categories/{category_id}`: Delete a financial category.

### Transactions

- `GET /transactions`: Retrieve all financial transactions for the current user.
- `POST /transactions`: Create a new financial transaction.
- `PUT /transactions/{transaction_id}`: Update an existing financial transaction.
- `DELETE /transactions/{transaction_id}`: Delete a financial transaction.

### Summary

- `GET /summary`: Retrieve a financial summary for the current user.
