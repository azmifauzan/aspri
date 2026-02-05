export interface Plugin {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    version: string;
    author: string | null;
    icon: string | null;
    class_name: string;
    is_system: boolean;
    config_schema: ConfigSchema | null;
    default_config: Record<string, unknown> | null;
    installed_at: string | null;
    created_at: string;
    updated_at: string;
    // Computed properties
    user_is_active?: boolean;
    user_plugin_id?: number | null;
    active_users_count?: number;
    // Rating properties
    average_rating?: number;
    total_ratings?: number;
    user_rating?: number | null;
}

export interface UserPlugin {
    id: number;
    user_id: number;
    plugin_id: number;
    is_active: boolean;
    activated_at: string | null;
    created_at: string;
    updated_at: string;
    plugin?: Plugin;
}

export interface PluginConfiguration {
    id: number;
    user_plugin_id: number;
    config_key: string;
    config_value: unknown;
    created_at: string;
    updated_at: string;
}

export interface PluginSchedule {
    id: number;
    user_plugin_id: number;
    schedule_type: 'cron' | 'interval' | 'daily' | 'weekly';
    schedule_value: string;
    last_run_at: string | null;
    next_run_at: string | null;
    is_active: boolean;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
}

export interface PluginLog {
    id: number;
    plugin_id: number;
    user_id: number | null;
    level: 'info' | 'warning' | 'error' | 'debug';
    message: string;
    context: Record<string, unknown> | null;
    created_at: string;
}

export interface PluginRating {
    id: number;
    user_id: number;
    plugin_id: number;
    rating: number;
    review: string | null;
    created_at: string;
    updated_at: string;
    user: {
        id: number;
        name: string;
    };
}

export interface ConfigField {
    key: string;
    type: 'text' | 'textarea' | 'number' | 'integer' | 'boolean' | 'select' | 'multiselect' | 'time' | 'email';
    label: string;
    description?: string | null;
    required?: boolean;
    default?: unknown;
    options?: string[] | null;
    multiple?: boolean;
    condition?: string | null;
    min?: number | null;
    max?: number | null;
}

export interface ConfigSchema {
    [key: string]: {
        type: string;
        label: string;
        description?: string;
        required?: boolean;
        default?: unknown;
        options?: string[];
        multiple?: boolean;
        condition?: string;
        min?: number;
        max?: number;
    };
}

export interface PluginIndexProps {
    plugins: Plugin[];
    filters?: {
        sort_by?: string;
        min_rating?: number | string;
    };
}

export interface PluginShowProps {
    plugin: Plugin & {
        average_rating: number;
        total_ratings: number;
    };
    userPlugin: UserPlugin | null;
    config: Record<string, unknown>;
    formFields: ConfigField[];
    supportsScheduling: boolean;
    schedule: PluginSchedule | null;
    executionHistory: PluginLog[];
    ratings: {
        data: PluginRating[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    userRating: PluginRating | null;
}
