<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Services\MockDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MockAuthController extends Controller
{
    /**
     * Display mock login view
     */
    public function create()
    {
        return view('auth.login-v2');
    }

    /**
     * Handle mock authentication
     * This uses real credentials, then enables mock data mode in session
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        // Regenerate session for security
        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->status == 0) {
            Auth::logout();
            return redirect()->back()->withErrors(['message' => __('auth.account_inactive')]);
        }

        // Enable mock data mode for this authenticated session
        MockDataService::createMockSession();

        // Reset old mock snapshot and generate a fresh dataset for this mock session.
        (new MockDataService())->resetPersistedMockDashboardData();

        // Redirect to admin home
        return redirect(RouteServiceProvider::HOME);
    }

    /**
     * Destroy mock session
     */
    public function destroy(Request $request)
    {
        MockDataService::clearMockSession();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
