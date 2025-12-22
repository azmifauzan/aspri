# tests/test_auth.py
import pytest
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)

def test_root_endpoint():
    """Test the root endpoint"""
    response = client.get("/")
    assert response.status_code == 200
    data = response.json()
    assert "message" in data
    assert "ASPRI Backend API is running" in data["message"]

def test_health_endpoint():
    """Test the health check endpoint"""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "healthy"

def test_login_endpoint_without_token():
    """Test login endpoint without token (should fail)"""
    response = client.post("/auth/login", json={})
    assert response.status_code == 422  # Validation error

def test_protected_endpoint_without_auth():
    """Test protected endpoint without authentication"""
    response = client.get("/auth/me")
    assert response.status_code == 403  # Forbidden

def test_register_endpoint_without_auth():
    """Test register endpoint without authentication"""
    response = client.post("/auth/register", json={
        "name": "Test User",
        "birth_date": 15,
        "birth_month": 6,
        "call_preference": "Test",
        "aspri_name": "Assistant",
        "aspri_persona": "Helpful"
    })
    assert response.status_code == 403  # Forbidden

# Note: For full testing, you would need to mock the Google OAuth verification
# and set up a test database. This is a basic structure for testing.