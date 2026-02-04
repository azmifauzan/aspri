<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Access denied. Super admin privileges required.');
        }

        if (! auth()->user()->is_active) {
            auth()->logout();
            abort(403, 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
