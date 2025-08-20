# app/services/supabase_service.py
import os
from supabase import create_client, Client
from dotenv import load_dotenv

load_dotenv()

class SupabaseService:
    def __init__(self):
        self.supabase_url = os.getenv("SUPABASE_URL")
        self.supabase_key = os.getenv("SUPABASE_KEY")
        self.client: Client = create_client(self.supabase_url, self.supabase_key)

    def get_client(self) -> Client:
        return self.client

supabase_service = SupabaseService()

def get_supabase_client() -> Client:
    return supabase_service.get_client()
