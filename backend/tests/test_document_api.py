# tests/test_document_api.py
import pytest
import pytest_asyncio
import base64
import os
from httpx import AsyncClient
from app.main import app

# Test data
TEST_PDF_PATH = "tests/data/test_document.pdf"
TEST_DOCX_PATH = "tests/data/test_document.docx"
TEST_TXT_PATH = "tests/data/test_document.txt"

# Ensure test data directory exists
os.makedirs(os.path.dirname(TEST_PDF_PATH), exist_ok=True)

# Create test documents if they don't exist
def create_test_documents():
    # Create a simple text file for testing
    if not os.path.exists(TEST_TXT_PATH):
        with open(TEST_TXT_PATH, "w") as f:
            f.write("This is a test document for ASPRI document API testing.\n")
            f.write("It contains some sample text that will be processed and embedded.\n")
            f.write("The text should be chunked and vector embeddings should be created.\n")
            f.write("Later we can search for specific content in this document.\n")


@pytest.fixture(scope="module", autouse=True)
def setup_test_data():
    create_test_documents()


@pytest.mark.asyncio
async def test_document_crud_flow(authenticated_client: AsyncClient):
    """Test the complete document CRUD flow"""
    # 1. Upload a document
    with open(TEST_TXT_PATH, "rb") as f:
        file_content = f.read()
        
    base64_content = base64.b64encode(file_content).decode("utf-8")
    
    # Create document via JSON endpoint
    upload_response = await authenticated_client.post(
        "/documents",
        json={
            "filename": "test_document.txt",
            "file_type": "txt",
            "file_content": base64_content
        }
    )
    
    assert upload_response.status_code == 200
    document_data = upload_response.json()
    document_id = document_data["id"]
    assert document_data["filename"] == "test_document.txt"
    assert document_data["file_type"] == "txt"
    
    # 2. Get document list
    list_response = await authenticated_client.get("/documents")
    assert list_response.status_code == 200
    list_data = list_response.json()
    assert list_data["total"] >= 1
    assert any(doc["id"] == document_id for doc in list_data["documents"])
    
    # 3. Get document details
    detail_response = await authenticated_client.get(f"/documents/{document_id}")
    assert detail_response.status_code == 200
    detail_data = detail_response.json()
    assert detail_data["id"] == document_id
    assert "chunks" in detail_data
    assert len(detail_data["chunks"]) > 0
    
    # 4. Search documents
    search_response = await authenticated_client.post(
        "/documents/search",
        json={
            "query": "vector embeddings",
            "limit": 5
        }
    )
    assert search_response.status_code == 200
    search_data = search_response.json()
    assert "results" in search_data
    assert len(search_data["results"]) > 0
    
    # 5. Update document
    update_response = await authenticated_client.put(
        f"/documents/{document_id}",
        json={
            "filename": "updated_test_document.txt"
        }
    )
    assert update_response.status_code == 200
    update_data = update_response.json()
    assert update_data["filename"] == "updated_test_document.txt"
    
    # 6. Delete document
    delete_response = await authenticated_client.delete(f"/documents/{document_id}")
    assert delete_response.status_code == 204
    
    # Verify document is deleted
    get_deleted_response = await authenticated_client.get(f"/documents/{document_id}")
    assert get_deleted_response.status_code == 404


@pytest.mark.asyncio
async def test_document_upload_file(authenticated_client: AsyncClient):
    """Test document upload using multipart/form-data"""
    with open(TEST_TXT_PATH, "rb") as f:
        files = {"file": ("test_upload.txt", f, "text/plain")}
        response = await authenticated_client.post("/documents/upload", files=files)
    
    assert response.status_code == 200
    document_data = response.json()
    document_id = document_data["id"]
    
    # Clean up
    delete_response = await authenticated_client.delete(f"/documents/{document_id}")
    assert delete_response.status_code == 204


# Add this to conftest.py or create a fixture here
@pytest_asyncio.fixture
async def authenticated_client():
    """Create an authenticated client for testing"""
    # This would need to be implemented with a valid JWT token
    # For now, this is a placeholder - we'll skip auth for testing
    async with AsyncClient(app=app, base_url="http://test") as client:
        # For testing purposes, we'll mock the authentication
        # In a real scenario, you'd generate a valid JWT token
        yield client