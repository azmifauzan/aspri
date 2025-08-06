from pydantic import BaseModel
from datetime import date
from enum import Enum

class TransactionType(str, Enum):
    INCOME = "income"
    EXPENSE = "expense"

class FinancialCategoryBase(BaseModel):
    name: str
    type: TransactionType

class FinancialCategoryCreate(FinancialCategoryBase):
    pass

class FinancialCategoryUpdate(FinancialCategoryBase):
    pass

class FinancialCategory(FinancialCategoryBase):
    id: int
    user_id: int

    class Config:
        orm_mode = True

class FinancialTransactionBase(BaseModel):
    amount: float
    description: str | None = None
    date: date
    type: TransactionType
    category_id: int | None = None

class FinancialTransactionCreate(FinancialTransactionBase):
    pass

class FinancialTransactionUpdate(FinancialTransactionBase):
    pass

class FinancialTransaction(FinancialTransactionBase):
    id: int
    user_id: int

    class Config:
        orm_mode = True
