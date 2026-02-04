export interface ServerHealth {
    php_version: string;
    laravel_version: string;
    memory_usage: string;
    memory_peak: string;
    memory_limit: string;
    disk_free: string;
    disk_total: string;
    uptime: string | null;
    load_average: number[] | null;
}

export interface DatabaseStats {
    connection: string;
    tables: Array<{
        name: string;
        rows: number;
    }>;
    total_users: number;
    total_messages: number;
    total_transactions: number;
}

export interface QueueStats {
    pending_jobs: number;
    failed_jobs: number;
    jobs_by_queue: Record<string, number>;
    recent_failed_jobs: Array<{
        id: number;
        queue: string;
        failed_at: string;
        exception: string;
    }>;
    recent_batches: Array<{
        id: string;
        name: string;
        total_jobs: number;
        pending_jobs: number;
        failed_jobs: number;
        created_at: number;
        finished_at: number | null;
    }>;
}

export interface CacheStats {
    driver: string;
    prefix: string;
}

export interface UserStats {
    total: number;
    active: number;
    inactive: number;
    by_role: Record<string, number>;
    recent: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
        is_active: boolean;
        created_at: string;
    }>;
    today: number;
    this_week: number;
    this_month: number;
}

export interface ActivityLog {
    id: number;
    user: string;
    action: string;
    description: string | null;
    model_type: string;
    model_id: number | null;
    ip_address: string | null;
    created_at: string;
}

export interface ActivityStats {
    recent: ActivityLog[];
    by_action: Record<string, number>;
    by_day: Record<string, number>;
    total_today: number;
    total_week: number;
}

export interface AiUsageStats {
    total_messages: number;
    messages_today: number;
    messages_this_week: number;
    messages_per_day: Record<string, number>;
    messages_by_role: Record<string, number>;
    top_users: Array<{
        user: string;
        count: number;
    }>;
}

export interface AdminDashboardProps {
    serverHealth: ServerHealth;
    databaseStats: DatabaseStats;
    queueStats: QueueStats;
    cacheStats: CacheStats;
    userStats: UserStats;
    activityStats: ActivityStats;
    aiUsageStats: AiUsageStats;
}

export interface AiSettings {
    ai_provider: string;
    gemini_model: string;
    openai_model: string;
    anthropic_model: string;
    has_gemini_key: boolean;
    has_openai_key: boolean;
    has_anthropic_key: boolean;
}

export interface TelegramSettings {
    bot_token: string | null;
    has_bot_token: boolean;
    webhook_url: string | null;
    bot_username: string | null;
    admin_chat_ids: string | null;
}

export interface AppSettings {
    app_name: string;
    app_description: string;
    app_locale: string;
    app_timezone: string;
    maintenance_mode: boolean;
}

export interface SubscriptionSettings {
    free_trial_days: number;
    monthly_price: number;
    yearly_price: number;
    free_trial_daily_chat_limit: number;
    full_member_daily_chat_limit: number;
    bank_name: string;
    bank_account_number: string;
    bank_account_name: string;
}

export interface EmailSettings {
    mail_mailer: string;
    mail_host: string;
    mail_port: number;
    mail_encryption: string;
    mail_username: string;
    has_mail_password: boolean;
    mail_from_address: string;
    mail_from_name: string;
}

export interface SettingsPageProps {
    aiSettings: AiSettings;
    telegramSettings: TelegramSettings;
    appSettings: AppSettings;
    subscriptionSettings: SubscriptionSettings;
    emailSettings: EmailSettings;
}

export interface PaymentProof {
    id: number;
    user_id: number;
    subscription_id: number | null;
    plan_type: 'monthly' | 'yearly';
    amount: number;
    transfer_proof_path: string;
    bank_name: string | null;
    account_name: string | null;
    transfer_date: string | null;
    status: 'pending' | 'approved' | 'rejected';
    admin_notes: string | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    created_at: string;
    updated_at: string;
    user?: UserWithProfile;
    subscription?: Subscription;
    reviewer?: UserWithProfile;
}

export interface Subscription {
    id: number;
    user_id: number;
    plan: 'free_trial' | 'monthly' | 'yearly';
    status: 'active' | 'expired' | 'cancelled' | 'pending';
    starts_at: string;
    ends_at: string | null;
    price_paid: number;
    payment_method: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedPaymentProofs {
    data: PaymentProof[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

export interface PaymentsPageProps {
    payments: PaginatedPaymentProofs;
    pendingCount: number;
    filters: {
        status?: string;
        per_page?: number;
    };
}

export interface UserWithProfile {
    id: number;
    name: string;
    email: string;
    role: 'user' | 'admin' | 'super_admin';
    is_active: boolean;
    created_at: string;
    updated_at: string;
    profile?: {
        id: number;
        user_id: number;
        aspri_name: string | null;
        call_preference: string | null;
    };
}

export interface PaginatedUsers {
    data: UserWithProfile[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

export interface UserManagementProps {
    users: PaginatedUsers;
    filters: {
        search?: string;
        role?: string;
        is_active?: string;
    };
}

export interface ActivityLogEntry {
    id: number;
    user_id: number | null;
    action: string;
    model_type: string | null;
    model_id: number | null;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
}

export interface PaginatedActivityLogs {
    data: ActivityLogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

export interface ActivityLogsPageProps {
    logs: PaginatedActivityLogs;
    actions: string[];
    filters: {
        search?: string;
        action?: string;
        start_date?: string;
        end_date?: string;
    };
}

export interface QueueMonitorPageProps {
    stats: QueueStats;
}
