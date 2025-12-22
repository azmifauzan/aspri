export interface FinancialCategory {
  id: number;
  user_id: number;
  name: string;
  type: 'income' | 'expense';
}

export interface FinancialTransaction {
  id: number;
  user_id: number;
  category_id?: number;
  amount: number;
  description?: string;
  date: string;
  type: 'income' | 'expense';
}

export type FinancialCategoryCreate = Omit<FinancialCategory, 'id' | 'user_id'>;
export type FinancialCategoryUpdate = Partial<FinancialCategoryCreate>;

export type FinancialTransactionCreate = Omit<FinancialTransaction, 'id' | 'user_id'>;
export type FinancialTransactionUpdate = Partial<FinancialTransactionCreate>;
