# tests/test_google_auth.py
import pytest
from unittest.mock import patch, MagicMock
from fastapi import HTTPException
from app.core.auth import verify_google_token

class TestGoogleAuth:
    """Test cases for Google OAuth token verification"""
    
    @patch('google.oauth2.id_token.verify_oauth2_token')
    @patch('app.core.auth.os.getenv')
    def test_verify_google_token_success(self, mock_getenv, mock_verify_token):
        """Test successful Google token verification"""
        # Setup mocks
        mock_getenv.return_value = "test-client-id"
        mock_verify_token.return_value = {
            "iss": "accounts.google.com",
            "sub": "123456789",
            "email": "test@example.com",
            "name": "Test User",
            "picture": "https://example.com/photo.jpg",
            "email_verified": True
        }
        
        # Test the function
        result = verify_google_token("valid-token")
        
        # Assertions
        assert result["sub"] == "123456789"
        assert result["email"] == "test@example.com"
        assert result["name"] == "Test User"
        assert result["picture"] == "https://example.com/photo.jpg"
        assert result["email_verified"] == True
        
        # Verify mocks were called correctly
        mock_getenv.assert_called_with("GOOGLE_CLIENT_ID")
        mock_verify_token.assert_called_once()
    
    @patch('app.core.auth.os.getenv')
    def test_verify_google_token_missing_client_id(self, mock_getenv):
        """Test token verification when GOOGLE_CLIENT_ID is not set"""
        # Setup mock to return None (environment variable not set)
        mock_getenv.return_value = None
        
        # Test the function and expect HTTPException
        with pytest.raises(HTTPException) as exc_info:
            verify_google_token("some-token")
        
        assert exc_info.value.status_code == 401
        assert "GOOGLE_CLIENT_ID environment variable is not set" in str(exc_info.value.detail)
    
    @patch('google.oauth2.id_token.verify_oauth2_token')
    @patch('app.core.auth.os.getenv')
    def test_verify_google_token_invalid_issuer(self, mock_getenv, mock_verify_token):
        """Test token verification with invalid issuer"""
        # Setup mocks
        mock_getenv.return_value = "test-client-id"
        mock_verify_token.return_value = {
            "iss": "malicious.com",  # Invalid issuer
            "sub": "123456789",
            "email": "test@example.com"
        }
        
        # Test the function and expect HTTPException
        with pytest.raises(HTTPException) as exc_info:
            verify_google_token("invalid-issuer-token")
        
        assert exc_info.value.status_code == 401
        assert "Wrong issuer" in str(exc_info.value.detail)
    
    @patch('google.oauth2.id_token.verify_oauth2_token')
    @patch('app.core.auth.os.getenv')
    def test_verify_google_token_verification_error(self, mock_getenv, mock_verify_token):
        """Test token verification when Google verification fails"""
        # Setup mocks
        mock_getenv.return_value = "test-client-id"
        mock_verify_token.side_effect = ValueError("Invalid token")
        
        # Test the function and expect HTTPException
        with pytest.raises(HTTPException) as exc_info:
            verify_google_token("invalid-token")
        
        assert exc_info.value.status_code == 401
        assert "Invalid Google token: Invalid token" in str(exc_info.value.detail)
    
    @patch('google.oauth2.id_token.verify_oauth2_token')
    @patch('app.core.auth.os.getenv')
    def test_verify_google_token_with_minimal_data(self, mock_getenv, mock_verify_token):
        """Test token verification with minimal user data"""
        # Setup mocks
        mock_getenv.return_value = "test-client-id"
        mock_verify_token.return_value = {
            "iss": "https://accounts.google.com",
            "sub": "987654321",
            "email": "minimal@example.com"
            # Missing optional fields like name, picture, email_verified
        }
        
        # Test the function
        result = verify_google_token("minimal-token")
        
        # Assertions
        assert result["sub"] == "987654321"
        assert result["email"] == "minimal@example.com"
        assert result["name"] == ""  # Default value for missing field
        assert result["picture"] == ""  # Default value for missing field
        assert result["email_verified"] == False  # Default value for missing field