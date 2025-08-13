from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy import func
from app.db.models.finance import FinancialCategory, FinancialTransaction
from app.schemas.finance import FinancialCategoryCreate, FinancialCategoryUpdate, FinancialTransactionCreate, FinancialTransactionUpdate
from typing import List, Optional
from datetime import date

class FinanceService:
    def __init__(self, db_session: AsyncSession):
        self.db_session = db_session

    async def get_categories(self, user_id: int) -> List[FinancialCategory]:
        result = await self.db_session.execute(select(FinancialCategory).filter(FinancialCategory.user_id == user_id))
        return result.scalars().all()

    async def get_category_by_name(self, user_id: int, name: str) -> Optional[FinancialCategory]:
        result = await self.db_session.execute(
            select(FinancialCategory).filter(
                FinancialCategory.user_id == user_id,
                func.lower(FinancialCategory.name) == name.lower()
            )
        )
        return result.scalars().first()

    async def create_category(self, user_id: int, category: FinancialCategoryCreate) -> FinancialCategory:
        db_category = FinancialCategory(**category.dict(), user_id=user_id)
        self.db_session.add(db_category)
        await self.db_session.commit()
        await self.db_session.refresh(db_category)
        return db_category

    async def update_category(self, category_id: int, category_update: FinancialCategoryUpdate) -> Optional[FinancialCategory]:
        result = await self.db_session.execute(select(FinancialCategory).filter(FinancialCategory.id == category_id))
        db_category = result.scalars().first()
        if db_category:
            for key, value in category_update.dict().items():
                setattr(db_category, key, value)
            await self.db_session.commit()
            await self.db_session.refresh(db_category)
        return db_category

    async def delete_category(self, category_id: int) -> bool:
        result = await self.db_session.execute(select(FinancialCategory).filter(FinancialCategory.id == category_id))
        db_category = result.scalars().first()
        if db_category:
            await self.db_session.delete(db_category)
            await self.db_session.commit()
            return True
        return False

    async def get_transactions(self, user_id: int) -> List[FinancialTransaction]:
        result = await self.db_session.execute(select(FinancialTransaction).filter(FinancialTransaction.user_id == user_id))
        return result.scalars().all()

    async def get_last_transaction(self, user_id: int) -> Optional[FinancialTransaction]:
        result = await self.db_session.execute(
            select(FinancialTransaction)
            .filter(FinancialTransaction.user_id == user_id)
            .order_by(FinancialTransaction.created_at.desc())
            .limit(1)
        )
        return result.scalars().first()

    async def create_transaction(self, user_id: int, transaction: FinancialTransactionCreate) -> FinancialTransaction:
        db_transaction = FinancialTransaction(**transaction.dict(), user_id=user_id)
        self.db_session.add(db_transaction)
        await self.db_session.commit()
        await self.db_session.refresh(db_transaction)
        return db_transaction

    async def get_summary(self, user_id: int, start_date: date, end_date: date, transaction_type: Optional[str] = None) -> dict:
        query = (
            select(
                FinancialTransaction.type,
                func.sum(FinancialTransaction.amount).label("total_amount")
            )
            .filter(
                FinancialTransaction.user_id == user_id,
                FinancialTransaction.date >= start_date,
                FinancialTransaction.date <= end_date
            )
        )

        # We will filter by type in python after fetching, to avoid case-sensitivity issues
        # if transaction_type and transaction_type in ['income', 'expense']:
        #     query = query.filter(func.lower(FinancialTransaction.type) == transaction_type.lower())

        query = query.group_by(FinancialTransaction.type)

        result = await self.db_session.execute(query)
        rows = result.all()

        summary = {
            "total_income": 0.0,
            "total_expense": 0.0,
            "net_income": 0.0
        }

        for row in rows:
            # Handle case-insensitivity in Python
            row_type = row.type.lower() if hasattr(row.type, 'lower') else row.type
            if row_type == 'income':
                summary['total_income'] = row.total_amount or 0.0
            elif row_type == 'expense':
                summary['total_expense'] = row.total_amount or 0.0

        # If a specific type was requested, zero out the other one
        if transaction_type == 'income':
            summary['total_expense'] = 0.0
        elif transaction_type == 'expense':
            summary['total_income'] = 0.0

        summary['net_income'] = summary['total_income'] - summary['total_expense']

        return summary

    async def update_transaction(self, transaction_id: int, transaction_update: FinancialTransactionUpdate) -> Optional[FinancialTransaction]:
        result = await self.db_session.execute(select(FinancialTransaction).filter(FinancialTransaction.id == transaction_id))
        db_transaction = result.scalars().first()
        if db_transaction:
            for key, value in transaction_update.dict().items():
                setattr(db_transaction, key, value)
            await self.db_session.commit()
            await self.db_session.refresh(db_transaction)
        return db_transaction

    async def delete_transaction(self, transaction_id: int) -> bool:
        result = await self.db_session.execute(select(FinancialTransaction).filter(FinancialTransaction.id == transaction_id))
        db_transaction = result.scalars().first()
        if db_transaction:
            await self.db_session.delete(db_transaction)
            await self.db_session.commit()
            return True
        return False
