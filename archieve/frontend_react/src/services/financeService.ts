import api from './api';
import type {
  FinancialCategory,
  FinancialCategoryCreate,
  FinancialCategoryUpdate,
  FinancialTransaction,
  FinancialTransactionCreate,
  FinancialTransactionUpdate,
} from '../types/finance';

export const getCategories = async (): Promise<FinancialCategory[]> => {
  const response = await api.get('/finance/categories');
  return response.data;
};

export const createCategory = async (category: FinancialCategoryCreate): Promise<FinancialCategory> => {
  const response = await api.post('/finance/categories', category);
  return response.data;
};

export const updateCategory = async (id: number, category: FinancialCategoryUpdate): Promise<FinancialCategory> => {
  const response = await api.put(`/finance/categories/${id}`, category);
  return response.data;
};

export const deleteCategory = async (id: number): Promise<void> => {
  await api.delete(`/finance/categories/${id}`);
};

export const getTransactions = async (): Promise<FinancialTransaction[]> => {
  const response = await api.get('/finance/transactions');
  return response.data;
};

export const createTransaction = async (transaction: FinancialTransactionCreate): Promise<FinancialTransaction> => {
  const response = await api.post('/finance/transactions', transaction);
  return response.data;
};

export const updateTransaction = async (id: number, transaction: FinancialTransactionUpdate): Promise<FinancialTransaction> => {
  const response = await api.put(`/finance/transactions/${id}`, transaction);
  return response.data;
};

export const deleteTransaction = async (id: number): Promise<void> => {
  await api.delete(`/finance/transactions/${id}`);
};

export const getSummary = async (): Promise<any> => {
  const response = await api.get('/finance/summary');
  return response.data;
};
