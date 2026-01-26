// Finance Types

export interface FinanceAccount {
    id: string;
    user_id: number;
    name: string;
    type: 'cash' | 'bank' | 'e-wallet';
    currency: string;
    initial_balance: number;
    current_balance?: number;
    income_total?: number;
    expense_total?: number;
    created_at: string;
    updated_at: string;
}

export interface FinanceCategory {
    id: string;
    user_id: number;
    name: string;
    tx_type: 'income' | 'expense';
    icon: string | null;
    color: string | null;
    transactions_count?: number;
    created_at: string;
    updated_at: string;
}

export interface FinanceTransaction {
    id: string;
    user_id: number;
    account_id: string | null;
    category_id: string | null;
    tx_type: 'income' | 'expense' | 'transfer';
    amount: number;
    occurred_at: string;
    note: string | null;
    metadata: Record<string, unknown> | null;
    account?: FinanceAccount | null;
    category?: FinanceCategory | null;
    created_at: string;
    updated_at: string;
}

export interface FinanceMonthlySummary {
    income: number;
    expense: number;
    balance: number;
    incomeChange: number;
    expenseChange: number;
}

export interface FinanceWeeklyExpense {
    day: string;
    amount: number;
}

export interface FinanceOverviewProps {
    monthlySummary: FinanceMonthlySummary;
    recentTransactions: FinanceTransaction[];
    accounts: FinanceAccount[];
    weeklyExpenses: FinanceWeeklyExpense[];
    categories: FinanceCategory[];
}


export interface FinanceTransactionsProps {
    transactions: {
        data: FinanceTransaction[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    categories: FinanceCategory[];
    accounts: FinanceAccount[];
    filters: {
        type?: string;
        category?: string;
        from?: string;
        to?: string;
        search?: string;
    };
}

export interface FinanceCategoriesProps {
    categories: FinanceCategory[];
}

export interface FinanceAccountsProps {
    accounts: FinanceAccount[];
}
