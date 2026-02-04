export * from './admin';
export * from './auth';
export * from './chat';
export * from './dashboard';
export * from './finance';
export * from './navigation';
export * from './plugin';
export * from './ui';

import type { Auth } from './auth';

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
};
