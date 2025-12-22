import enum
from sqlalchemy import Column, Integer, String, Float, Date, ForeignKey, Enum
from sqlalchemy.orm import relationship
from app.db.base import Base

class TransactionType(enum.Enum):
    INCOME = "income"
    EXPENSE = "expense"

class FinancialCategory(Base):
    __tablename__ = "financial_categories"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    name = Column(String(255), nullable=False)
    type = Column(Enum(TransactionType), nullable=False)

    user = relationship("User", back_populates="financial_categories")
    transactions = relationship("FinancialTransaction", back_populates="category")

class FinancialTransaction(Base):
    __tablename__ = "financial_transactions"

    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id"), nullable=False)
    category_id = Column(Integer, ForeignKey("financial_categories.id"), nullable=True)
    amount = Column(Float, nullable=False)
    description = Column(String(255), nullable=True)
    date = Column(Date, nullable=False)
    type = Column(Enum(TransactionType), nullable=False)

    user = relationship("User", back_populates="financial_transactions")
    category = relationship("FinancialCategory", back_populates="transactions")

# Add relationships to User model
from app.db.models.user import User
User.financial_categories = relationship("FinancialCategory", back_populates="user")
User.financial_transactions = relationship("FinancialTransaction", back_populates="user")
