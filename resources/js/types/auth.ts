export type User = {
    id: number;
    name: string;
    email: string;
    role: 'user' | 'admin' | 'super_admin';
    is_active: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    subscriptionInfo?: {
        status: string;
        plan: string | null;
        ends_at: string | null;
        days_remaining: number;
        is_paid: boolean;
    };
    chatLimit?: {
        used: number;
        limit: number;
        remaining: number;
    };
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
