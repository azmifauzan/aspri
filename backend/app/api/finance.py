from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from typing import List

from app.db.database import get_db
from app.schemas.finance import (
    FinancialCategory,
    FinancialCategoryCreate,
    FinancialCategoryUpdate,
    FinancialTransaction,
    FinancialTransactionCreate,
    FinancialTransactionUpdate,
)
from app.services.finance_service import FinanceService
from app.dependencies import get_current_user
from app.db.models.user import User

router = APIRouter()

@router.get("/finance/categories", response_model=List[FinancialCategory])
async def get_categories(
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    return await finance_service.get_categories(user_id=current_user.id)

@router.post("/finance/categories", response_model=FinancialCategory)
async def create_category(
    category: FinancialCategoryCreate,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    return await finance_service.create_category(user_id=current_user.id, category=category)

@router.put("/finance/categories/{category_id}", response_model=FinancialCategory)
async def update_category(
    category_id: int,
    category_update: FinancialCategoryUpdate,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    # TODO: Add check to ensure user owns the category
    updated_category = await finance_service.update_category(category_id=category_id, category_update=category_update)
    if not updated_category:
        raise HTTPException(status_code=404, detail="Category not found")
    return updated_category

@router.delete("/finance/categories/{category_id}", status_code=204)
async def delete_category(
    category_id: int,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    # TODO: Add check to ensure user owns the category
    if not await finance_service.delete_category(category_id=category_id):
        raise HTTPException(status_code=404, detail="Category not found")

@router.get("/finance/transactions", response_model=List[FinancialTransaction])
async def get_transactions(
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    return await finance_service.get_transactions(user_id=current_user.id)

@router.post("/finance/transactions", response_model=FinancialTransaction)
async def create_transaction(
    transaction: FinancialTransactionCreate,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    return await finance_service.create_transaction(user_id=current_user.id, transaction=transaction)

@router.put("/finance/transactions/{transaction_id}", response_model=FinancialTransaction)
async def update_transaction(
    transaction_id: int,
    transaction_update: FinancialTransactionUpdate,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    # TODO: Add check to ensure user owns the transaction
    updated_transaction = await finance_service.update_transaction(transaction_id=transaction_id, transaction_update=transaction_update)
    if not updated_transaction:
        raise HTTPException(status_code=404, detail="Transaction not found")
    return updated_transaction

@router.delete("/finance/transactions/{transaction_id}", status_code=204)
async def delete_transaction(
    transaction_id: int,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    finance_service = FinanceService(db)
    # TODO: Add check to ensure user owns the transaction
    if not await finance_service.delete_transaction(transaction_id=transaction_id):
        raise HTTPException(status_code=404, detail="Transaction not found")

# TODO: Add summary endpoint
# @router.get("/finance/summary")
# async def get_summary(...):
#    ...
