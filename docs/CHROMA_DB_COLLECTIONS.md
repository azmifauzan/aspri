# ChromaDB Collection Architecture

This document outlines the architecture for managing ChromaDB collections in this application.

## User-Specific Collections

To ensure strict data isolation and improve query performance, the application uses a multi-collection strategy where **each user has their own dedicated ChromaDB collection**.

### Naming Convention

Collections are named using the following convention:

`user_{user_id}_collection`

Where `{user_id}` is the unique identifier of the user.

### How it Works

1.  **Dynamic Creation**: A new collection is automatically created for a user when they upload their first document.
2.  **Scoped Operations**: All document-related operations—such as creating, updating, deleting, and searching—are performed exclusively within the user's dedicated collection.
3.  **Authentication**: The `user_id` is obtained from the authenticated user's JWT, ensuring that API calls can only interact with the collection belonging to the authenticated user.

This approach prevents data leakage between users and allows for more efficient, targeted queries, as searches are confined to a smaller, user-specific dataset.
