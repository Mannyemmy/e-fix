<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
     * This logs in with mock data instead of real user credentials
     */
    public function store(Request $request)
    {
        // Validate form submission
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Create mock session
        MockDataService::createMockSession();

        // Regenerate session for security
        $request->session()->regenerate();

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
