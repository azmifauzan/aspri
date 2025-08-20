# app/main.py
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.api.auth import router as auth_router
from app.api.document import router as document_router
from app.api.config import router as config_router
from app.api.chat import router as chat_router
from app.api.finance import router as finance_router
from app.api.contacts import router as contacts_router
from app.api.calendar import router as calendar_router
import os
from dotenv import load_dotenv
load_dotenv()

# Create FastAPI app
app = FastAPI(
    title="ASPRI Backend API",
    description="Personal Assistant API with Google OAuth and JWT authentication",
    version="1.0.0"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure this properly for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
app.include_router(auth_router)
app.include_router(document_router)
app.include_router(config_router)
app.include_router(chat_router)
app.include_router(finance_router)
app.include_router(contacts_router)
app.include_router(calendar_router, prefix="/calendar", tags=["calendar"])

@app.get("/")
def read_root():
    return {
        "message": "ASPRI Backend API is running 🚀",
        "version": "1.0.0",
        "docs": "/docs"
    }

@app.get("/health")
def health_check():
    return {"status": "healthy"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)