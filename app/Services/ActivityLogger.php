<?php

namespace App\Services;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes auth events to user_activity_logs.
 *
 * Everything here is best effort and wrapped: a logging failure must never stop
 * somebody signing up or logging in. Geolocation is deliberately NOT resolved
 * here - see IpGeolocationService for why.
 */
class ActivityLogger
{
    public static function record($event, array $attributes = [], Request $request = null)
    {
        try {
            $request = $request ?: request();

            UserActivityLog::create([
                'user_id'    => $attributes['user_id']   ?? null,
                'event'      => $event,
                'email'      => $attributes['email']     ?? null,
                'user_type'  => $attributes['user_type'] ?? null,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
                'source'     => static::detectSource($request),
                'meta'       => $attributes['meta']      ?? null,
            ]);
        } catch (\Throwable $th) {
            Log::error('[ActivityLog] could not record event', [
                'event' => $event,
                'error' => $th->getMessage(),
            ]);
        }
    }

    /**
     * The landing-page forms submit through the web session and carry a CSRF
     * token; the mobile apps never send one. Derived from the request rather
     * than trusted from a client-supplied field.
     */
    protected static function detectSource(Request $request = null)
    {
        if (! $request) {
            return null;
        }

        return $request->filled('_token') ? 'web' : 'app';
    }
}
