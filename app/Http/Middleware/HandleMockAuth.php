<?php

namespace App\Http\Middleware;

use App\Services\MockDataService;
use Closure;
use Illuminate\Http\Request;

class HandleMockAuth
{
    /**
     * Handle an incoming request.
     * This middleware allows mock users to access protected routes
     * by simulating authentication without a real database user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Mock mode requires a real authenticated session.
        if (MockDataService::isMockMode() && !auth()->check()) {
            MockDataService::clearMockSession();
        }

        return $next($request);
    }
}
