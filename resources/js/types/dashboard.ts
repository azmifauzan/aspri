// Dashboard Types

export interface MonthlySummary {
    income: number;
    expense: number;
    balance: number;
    incomeChange: number;
    expenseChange: number;
}

export interface TodayEvent {
    id: string;
    title: string;
    time: string;
    endTime?: string;
    type: 'meeting' | 'work' | 'personal' | 'reminder';
}

export interface WeeklyExpense {
    day: string;
    amount: number;
}

export interface RecentActivity {
    id: string;
    type: 'expense' | 'income' | 'event' | 'note';
    title: string;
    description: string;
    time: string;
    icon: string;
}

export interface SubscriptionInfo {
    status: string;
    plan: string | null;
    ends_at: string | null;
    days_remaining: number;
    is_paid: boolean;
}

export interface ChatLimit {
    used: number;
    limit: number;
    remaining: number;
}

export interface DashboardProps {
    monthlySummary: MonthlySummary;
    todayEvents: TodayEvent[];
    weeklyExpenses: WeeklyExpense[];
    recentActivities: RecentActivity[];
    subscriptionInfo: SubscriptionInfo;
    chatLimit: ChatLimit;
}
