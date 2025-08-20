# app/db/database.py
from app.services.supabase_db_service import SupabaseDBService

def get_db():
    return SupabaseDBService()
