<?php

namespace App\Http\Middleware;

use App\Services\MockDataService;
use Closure;
use Illuminate\Http\Request;

class HandleMockAuthApi
{
    /**
     * Handle an incoming API request.
     * For API requests with mock mode session, ensure mock user is authenticated
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (MockDataService::isMockMode() && !auth()->check()) {
            MockDataService::clearMockSession();
        }

        return $next($request);
    }
}
