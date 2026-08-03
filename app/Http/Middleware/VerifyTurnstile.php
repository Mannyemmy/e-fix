<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bot check for the public signup form.
 *
 * IMPORTANT: the mobile apps post to the same /api/register endpoint as the web
 * forms and cannot solve a captcha, so only browser-originated submissions are
 * challenged (they carry the session CSRF token; the apps never send one).
 * That means a bot can skip the challenge by simply not sending _token, so this
 * is defence in depth for the web path - the rate limiter and the validation
 * rules are what apply to every caller. Closing that gap properly means giving
 * the web its own signup route and locking /api/register to attested app
 * clients; see the note left with this change.
 */
class VerifyTurnstile
{
    /**
     * Hidden field rendered on the web signup forms. A human never fills it in,
     * naive form-filling bots fill in everything they find.
     */
    const HONEYPOT_FIELD = 'company_website';

    public function handle(Request $request, Closure $next)
    {
        if (! $request->filled('_token')) {
            return $next($request);
        }

        if (filled($request->input(self::HONEYPOT_FIELD))) {
            Log::warning('[Signup] honeypot triggered', [
                'ip'    => $request->ip(),
                'email' => $request->input('email'),
            ]);

            return $this->reject($request);
        }

        $secret = config('services.turnstile.secret');

        // Not configured yet - nothing to verify.
        if (empty($secret)) {
            return $next($request);
        }

        if (! $this->passesTurnstile($request, $secret)) {
            return $this->reject($request);
        }

        return $next($request);
    }

    protected function passesTurnstile(Request $request, $secret)
    {
        $token = $request->input('cf-turnstile-response');

        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post(config('services.turnstile.verify_url'), [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            return $response->successful() && $response->json('success') === true;
        } catch (\Throwable $th) {
            // Cloudflare unreachable. Fail open rather than block every signup on a
            // third-party outage - the register throttle still sits in front of this.
            // Flip this to `false` if you would rather fail closed.
            Log::error('[Signup] Turnstile verification error', [
                'error' => $th->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * The landing-page forms post here over AJAX and want JSON, but the Breeze
     * /register form is an ordinary browser POST - handing that one a JSON body
     * would dump raw JSON in the user's browser instead of showing an error.
     */
    protected function reject(Request $request)
    {
        $message = __('auth.captcha_failed');

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'status'  => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->back()
            ->withInput($request->except(['password', 'password_confirmation']))
            ->withErrors(['message' => $message]);
    }
}
