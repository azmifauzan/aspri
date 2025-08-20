# app/api/document.py
from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File, Form, Query
from app.db.database import get_db
from app.schemas.document import (
    DocumentUpload, DocumentUpdate, DocumentResponse, 
    DocumentDetailResponse, DocumentListResponse,
    DocumentSearchQuery, DocumentSearchResponse, SearchResultChunk
)
from app.services.document_service import DocumentService
from app.services.supabase_db_service import SupabaseDBService
from app.core.auth import get_current_user_id
from typing import List, Optional
import base64

router = APIRouter(prefix="/documents", tags=["Documents"])

@router.post("", response_model=DocumentResponse)
async def upload_document(
    document_data: DocumentUpload,
    current_user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db)
):
    """
    Upload a new document.
    The document will be processed, chunked, and embedded.
    Requires JWT authentication.
    """
    try:
        document_service = DocumentService(db)
        document = await document_service.create_document(current_user_id, document_data)
        return DocumentResponse(**document)
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to upload document: {str(e)}"
        )

@router.post("/upload", response_model=DocumentResponse)
async def upload_document_file(
    file: UploadFile = File(...),
    current_user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db)
):
    """
    Alternative endpoint to upload a document using multipart/form-data.
    The document will be processed, chunked, and embedded.
    Requires JWT authentication.
    """
    try:
        file_content = await file.read()
        
        document_data = DocumentUpload(
            filename=file.filename,
            file_type=file.filename.split(".")[-1].lower(),
            file_content=base64.b64encode(file_content).decode("utf-8")
        )
        
        document_service = DocumentService(db)
        document = await document_service.create_document(current_user_id, document_data)
        
        return DocumentResponse(**document)
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to upload document: {str(e)}"
        )

@router.get("", response_model=DocumentListResponse)
async def get_documents(
    skip: int = Query(0, ge=0),
    limit: int = Query(100, ge=1, le=100),
    current_user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db)
):
    """
    Get all documents for the current user.
    Requires JWT authentication.
    """
    document_service = DocumentService(db)
    documents, total = await document_service.get_user_documents(current_user_id, skip, limit)
    
    return DocumentListResponse(
        total=total,
        documents=[DocumentResponse(**doc) for doc in documents]
    )

@router.get("/{document_id}", response_model=DocumentDetailResponse)
async def get_document(
    document_id: int,
    current_user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db)
):
    """
    Get a document by ID.
    Requires JWT authentication.
    """
    document_service = DocumentService(db)
    document = await document_service.get_document_with_chunks(document_id, current_user_id)
    
    if not document:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    return DocumentDetailResponse(**document[0])

@router.put("/{document_id}", response_model=DocumentResponse)
async def update_document(
    document_id: int,
    document_data: DocumentUpdate,
    current_user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db)
):
    """
    Update a document's metadata.
    Requires JWT authentication.
    """
    document_service = DocumentService(db)
    document = await document_service.update_document(document_id, current_user_id, document_data)
    
    if not document:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    return DocumentResponse(**document[0])

@router.delete("/{document_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_document(
    document_id: int,
    current_user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db)
):
    """
    Delete a document.
    Requires JWT authentication.
    """
    document_service = DocumentService(db)
    success = await document_service.delete_document(document_id, current_user_id)
    
    if not success:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    return None

@router.post("/search", response_model=DocumentSearchResponse)
async def search_documents(
    search_query: DocumentSearchQuery,
    current_user_id: int = Depends(get_current_user_id),
    db: SupabaseDBService = Depends(get_db)
):
    """
    Search documents using vector similarity.
    Requires JWT authentication.
    """
    document_service = DocumentService(db)
    search_results = await document_service.search_documents(current_user_id, search_query)
    
    return DocumentSearchResponse(
        results=[SearchResultChunk(**result) for result in search_results],
        query=search_query.query
    )