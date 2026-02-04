// Wayfinder helper types and utilities

export interface RouteQueryOptions {
    query?: Record<string, string | number | boolean | null | undefined>;
    mergeQuery?: Record<string, string | number | boolean | null | undefined>;
}

export interface RouteDefinition<Method extends string = string> {
    url: string;
    method: Method;
    methods?: readonly Method[];
}

export interface RouteFormDefinition extends RouteDefinition<'post'> {
    action: string;
}

/**
 * Apply URL defaults (for routes with optional parameters)
 */
export function applyUrlDefaults(url: string, defaults?: Record<string, string | number>): string {
    if (!defaults) return url;
    
    let finalUrl = url;
    Object.entries(defaults).forEach(([key, value]) => {
        finalUrl = finalUrl.replace(`{${key}?}`, String(value));
    });
    
    return finalUrl;
}

/**
 * Convert query options to query string
 */
export function queryParams(options?: RouteQueryOptions): string {
    if (!options) return '';
    
    const query = options.query || {};
    const params = new URLSearchParams();
    
    // Handle mergeQuery - merge with window location search
    if (options.mergeQuery && typeof window !== 'undefined') {
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.forEach((value, key) => {
            params.set(key, value);
        });
        
        // Apply mergeQuery - null values remove the param
        Object.entries(options.mergeQuery).forEach(([key, value]) => {
            if (value === null) {
                params.delete(key);
            } else if (value !== undefined) {
                params.set(key, String(value));
            }
        });
    }
    
    // Apply regular query params
    Object.entries(query).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
            params.set(key, String(value));
        }
    });
    
    const queryString = params.toString();
    return queryString ? `?${queryString}` : '';
}
