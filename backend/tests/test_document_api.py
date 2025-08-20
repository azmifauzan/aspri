# tests/test_document_api.py
import pytest
import base64
import os
from fastapi.testclient import TestClient
from app.main import app
from app.core.auth import get_current_user_id

# Test data
TEST_TXT_PATH = "tests/data/test_document.txt"

# Ensure test data directory exists
os.makedirs(os.path.dirname(TEST_TXT_PATH), exist_ok=True)

# Create test documents if they don't exist
def create_test_documents():
    if not os.path.exists(TEST_TXT_PATH):
        with open(TEST_TXT_PATH, "w") as f:
            f.write("This is a test document for ASPRI document API testing.\n")

@pytest.fixture(scope="module", autouse=True)
def setup_test_data():
    create_test_documents()

def get_current_user_id_override():
    return 1

app.dependency_overrides[get_current_user_id] = get_current_user_id_override

@pytest.fixture
def client():
    """Create a test client for the application"""
    with TestClient(app) as c:
        yield c

def test_document_crud_flow(client: TestClient):
    """Test the complete document CRUD flow"""
    try:
        # 1. Upload a document
        with open(TEST_TXT_PATH, "rb") as f:
            file_content = f.read()

        base64_content = base64.b64encode(file_content).decode("utf-8")
        
        upload_response = client.post(
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

        # 2. Get document list
        list_response = client.get("/documents")
        assert list_response.status_code == 200
        list_data = list_response.json()
        assert list_data["total"] >= 1

        # 3. Get document details
        detail_response = client.get(f"/documents/{document_id}")
        assert detail_response.status_code == 200
        detail_data = detail_response.json()
        assert detail_data["id"] == document_id

        # 4. Search documents
        search_response = client.post(
            "/documents/search",
            json={"query": "testing", "limit": 5}
        )
        assert search_response.status_code == 200

        # 5. Update document
        update_response = client.put(
            f"/documents/{document_id}",
            json={"filename": "updated_test_document.txt"}
        )
        assert update_response.status_code == 200

        # 6. Delete document
        delete_response = client.delete(f"/documents/{document_id}")
        assert delete_response.status_code == 204

        # Verify document is deleted
        get_deleted_response = client.get(f"/documents/{document_id}")
        assert get_deleted_response.status_code == 404
    except Exception as e:
        print(e)
        if 'upload_response' in locals():
            print(upload_response.json())
        raise e

def test_document_upload_file(client: TestClient):
    """Test document upload using multipart/form-data"""
    with open(TEST_TXT_PATH, "rb") as f:
        files = {"file": ("test_upload.txt", f, "text/plain")}
        response = client.post("/documents/upload", files=files)
    
    assert response.status_code == 200
    document_data = response.json()
    document_id = document_data["id"]
    
    # Clean up
    delete_response = client.delete(f"/documents/{document_id}")
    assert delete_response.status_code == 204