# scripts/init_config.py
"""
Initialize default configuration values for the application.
Run this script after database migration to set up default configurations.
"""
import asyncio
import sys
import os

# Add the parent directory to the path so we can import from app
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.db.database import get_db
from app.services.config_service import ConfigService

async def init_default_configs():
    """Initialize default configuration values"""
    print("Initializing default configurations...")
    
    # Get database session
    async for db in get_db():
        config_service = ConfigService(db)
        
        try:
            await config_service.initialize_default_configs()
            print("✅ Default configurations initialized successfully!")
            
            # Print current limits
            limits = await config_service.get_document_limits()
            print(f"📄 Max file size: {limits['max_file_size_bytes']} bytes ({limits['max_file_size_bytes'] / (1024*1024):.1f} MB)")
            print(f"📚 Max documents per user: {limits['max_documents_per_user']}")
            
        except Exception as e:
            print(f"❌ Error initializing configurations: {e}")
        
        break  # Only use the first session

if __name__ == "__main__":
    asyncio.run(init_default_configs())