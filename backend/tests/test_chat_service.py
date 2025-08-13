import pytest
import asyncio
from unittest.mock import AsyncMock, MagicMock, patch

from sqlalchemy.ext.asyncio import AsyncSession

from app.services.chat_service import ChatService
from app.db.models.document import Document

# Since we are in a testing environment that might not have all dependencies,
# we will mock them.
@pytest.fixture
def mock_db_session():
    """Fixture for a mock SQLAlchemy async session."""
    return AsyncMock(spec=AsyncSession)

@pytest.fixture
def chat_service(mock_db_session):
    """Fixture for a ChatService instance."""
    with patch('app.services.chat_service.ChatGoogleGenerativeAI') as mock_llm, \
         patch('app.services.chat_service.ChromaDBService') as mock_chromadb:
        # Mock the language model initialization
        mock_llm.return_value = MagicMock()
        mock_chromadb.return_value = MagicMock()

        # Configure the mock DB session to handle the execute call chain
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_db_session.execute.return_value = mock_result

        service = ChatService(db=mock_db_session)
        # We can also mock the invoke method directly on the instance if needed
        service.chat_model.invoke = MagicMock()
        return service

@pytest.mark.asyncio
@pytest.mark.parametrize("user_message, mock_llm_response, expected_intent, expected_data", [
    # Test for summarizing a specific document
    ("Tolong ringkas dokumen 'laporan_keuangan.pdf'", '{"intent": "summarize_specific_document", "data": {"document_name": "laporan_keuangan.pdf"}}', "summarize_specific_document", {"document_name": "laporan_keuangan.pdf"}),
    # Test for comparing documents
    ("Bandingkan 'proposal_a.docx' dan 'proposal_b.docx'", '{"intent": "compare_document", "data": {"document_names": ["proposal_a.docx", "proposal_b.docx"]}}', "compare_document", {"document_names": ["proposal_a.docx", "proposal_b.docx"]}),
    # Test for semantic search
    ("Cari informasi tentang 'machine learning' di dokumen saya", '{"intent": "search_by_semantic", "data": {"query": "machine learning"}}', "search_by_semantic", {"query": "machine learning"}),
    # Test for adding a transaction
    ("Tambah pengeluaran 50000 untuk makan siang", '{"intent": "add_transaction", "data": {"amount": 50000, "description": "makan siang", "type": "expense"}}', "add_transaction", {"amount": 50000, "description": "makan siang", "type": "expense"}),
    # Test for general chat
    ("Halo, apa kabar?", '{"intent": "chat", "data": null}', "chat", None),
])
async def test_classify_user_intent(chat_service, user_message, mock_llm_response, expected_intent, expected_data):
    """Test the classify_user_intent method for various user messages."""
    # Mock the response from the language model
    mock_response = MagicMock()
    mock_response.content = mock_llm_response
    chat_service.chat_model.invoke.return_value = mock_response

    # Call the method
    result = await chat_service.classify_user_intent(1, 1, user_message)

    # Assertions
    assert result["intent"] == expected_intent
    assert result["data"] == expected_data
    chat_service.chat_model.invoke.assert_called_once()

@pytest.mark.asyncio
@patch('app.services.chat_service.DocumentService')
@patch('app.services.chat_service.MinIOService')
async def test_handle_summarize_specific_document_success(mock_minio_service, mock_doc_service, chat_service):
    """Test _handle_summarize_specific_document successfully."""
    # Setup mocks
    mock_doc_service.return_value.get_document_by_filename_and_user_id = AsyncMock(
        return_value=Document(id=1, filename='test.pdf', minio_object_name='test-obj')
    )
    mock_minio_service.return_value.get_file = AsyncMock(return_value=b"This is the content.")

    mock_llm_response = MagicMock()
    mock_llm_response.content = "This is the summary."
    chat_service.chat_model.invoke.return_value = mock_llm_response

    # Call the method
    data = {"document_name": "test.pdf"}
    user_info = {}
    result = await chat_service._handle_summarize_specific_document(1, data, user_info)

    # Assertions
    assert result == "This is the summary."
    mock_doc_service.return_value.get_document_by_filename_and_user_id.assert_called_once_with("test.pdf", 1)
    mock_minio_service.return_value.get_file.assert_called_once_with('test-obj')
    chat_service.chat_model.invoke.assert_called_once()

@pytest.mark.asyncio
async def test_handle_summarize_specific_document_no_doc_name(chat_service):
    """Test _handle_summarize_specific_document with missing document name."""
    result = await chat_service._handle_summarize_specific_document(1, {}, {})
    assert "Please specify the name of the document" in result

@pytest.mark.asyncio
@patch('app.services.chat_service.DocumentService')
async def test_handle_summarize_specific_document_not_found(mock_doc_service, chat_service):
    """Test _handle_summarize_specific_document when the document is not found."""
    mock_doc_service.return_value.get_document_by_filename_and_user_id = AsyncMock(return_value=None)

    data = {"document_name": "nonexistent.pdf"}
    result = await chat_service._handle_summarize_specific_document(1, data, {})
    assert "couldn't find a document named 'nonexistent.pdf'" in result

@pytest.mark.asyncio
@patch('app.services.chat_service.DocumentService')
@patch('app.services.chat_service.MinIOService')
async def test_handle_compare_document_success(mock_minio_service, mock_doc_service, chat_service):
    """Test _handle_compare_document successfully."""
    # Mock the document service to return two different documents
    doc1 = Document(id=1, filename='doc1.pdf', minio_object_name='obj1')
    doc2 = Document(id=2, filename='doc2.pdf', minio_object_name='obj2')

    async def side_effect(filename, user_id):
        if filename == 'doc1.pdf':
            return doc1
        elif filename == 'doc2.pdf':
            return doc2
        return None

    mock_doc_service.return_value.get_document_by_filename_and_user_id.side_effect = side_effect

    # Mock the minio service to return different content for each document
    async def get_file_side_effect(object_name):
        if object_name == 'obj1':
            return b"Content of doc1."
        elif object_name == 'obj2':
            return b"Content of doc2."
        return None

    mock_minio_service.return_value.get_file.side_effect = get_file_side_effect

    mock_llm_response = MagicMock()
    mock_llm_response.content = "This is the comparison."
    chat_service.chat_model.invoke.return_value = mock_llm_response

    data = {"document_names": ["doc1.pdf", "doc2.pdf"]}
    result = await chat_service._handle_compare_document(1, data, {})

    assert result == "This is the comparison."
    assert mock_doc_service.return_value.get_document_by_filename_and_user_id.call_count == 2
    assert mock_minio_service.return_value.get_file.call_count == 2

@pytest.mark.asyncio
async def test_handle_compare_document_not_enough_docs(chat_service):
    """Test _handle_compare_document with less than two documents."""
    data = {"document_names": ["doc1.pdf"]}
    result = await chat_service._handle_compare_document(1, data, {})
    assert "Please specify at least two documents to compare" in result

@pytest.mark.asyncio
@patch('app.services.chat_service.ChatService._handle_document_search', new_callable=AsyncMock)
async def test_handle_search_by_semantic(mock_search, chat_service):
    """Test that _handle_search_by_semantic calls _handle_document_search."""
    mock_search.return_value = "Search results"
    data = {"query": "test query"}
    result = await chat_service._handle_search_by_semantic(1, data, {})

    assert result == "Search results"
    mock_search.assert_called_once_with(1, "test query", {})

@pytest.mark.asyncio
async def test_handle_add_transaction(chat_service):
    """Test the _handle_add_transaction method."""
    data = {
        "type": "expense",
        "amount": 100,
        "description": "test",
        "date": "2024-01-01",
        "category": "testing"
    }

    # Mock the LLM response for this test
    expected_response = "Confirmation message about adding a transaction."
    chat_service.chat_model.invoke.return_value.content = expected_response

    result = await chat_service._handle_add_transaction(1, 1, data, {})

    assert result == expected_response
    # Verify that the LLM was called, which implies the confirmation prompt was generated
    chat_service.chat_model.invoke.assert_called_once()
