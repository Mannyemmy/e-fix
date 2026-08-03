<?php

namespace App\Services;

use App\Models\IpGeolocation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reverse geolocation for logged IP addresses, backed by ip-api.com.
 *
 * Lookups never happen on the login/signup path - that would put a third party
 * in front of authentication. Addresses are recorded first and resolved later,
 * either when an admin views the logs or from the activity:resolve-ips command.
 *
 * The free ip-api tier allows ~45 lookups/minute per server IP and is HTTP only.
 * Every result is cached in ip_geolocations, so each address costs one call ever.
 */
class IpGeolocationService
{
    /** Fields requested from ip-api. Keep in sync with fill() below. */
    const FIELDS = 'status,message,country,countryCode,regionName,city,lat,lon,timezone,isp,org,asname,mobile,proxy,hosting,query';

    /**
     * Resolve a single address, returning the cached row when we already have it.
     */
    public function resolve($ip)
    {
        if (empty($ip)) {
            return null;
        }

        $existing = IpGeolocation::where('ip_address', $ip)->first();

        if ($existing && in_array($existing->lookup_status, [IpGeolocation::STATUS_SUCCESS, IpGeolocation::STATUS_SKIPPED], true)) {
            return $existing;
        }

        if (! $this->isPublicIp($ip)) {
            return IpGeolocation::updateOrCreate(
                ['ip_address' => $ip],
                ['lookup_status' => IpGeolocation::STATUS_SKIPPED, 'looked_up_at' => now()]
            );
        }

        if (! config('services.ip_api.enabled', true)) {
            return $existing;
        }

        return $this->lookup($ip, $existing);
    }

    /**
     * Resolve up to $limit not-yet-resolved addresses from the given list.
     * Called with a small limit while rendering the admin log pages, so the
     * table fills in over a few page loads rather than stalling on one.
     */
    public function resolveMany(array $ips, $limit = 15)
    {
        $ips = array_values(array_unique(array_filter($ips)));

        if (empty($ips)) {
            return;
        }

        $known = IpGeolocation::whereIn('ip_address', $ips)
            ->whereIn('lookup_status', [IpGeolocation::STATUS_SUCCESS, IpGeolocation::STATUS_SKIPPED])
            ->pluck('ip_address')
            ->all();

        $pending = array_diff($ips, $known);

        foreach (array_slice($pending, 0, $limit) as $ip) {
            $this->resolve($ip);
        }
    }

    protected function lookup($ip, $existing = null)
    {
        $base = rtrim(config('services.ip_api.base_url', 'http://ip-api.com/json'), '/');
        $key  = config('services.ip_api.key');

        $query = ['fields' => self::FIELDS];

        // A pro key switches the endpoint to HTTPS; the free tier is HTTP only.
        if (! empty($key)) {
            $query['key'] = $key;
        }

        try {
            $response = Http::timeout(5)->get($base.'/'.$ip, $query);

            if (! $response->successful()) {
                return $this->markFailed($ip);
            }

            $body = $response->json();

            if (! is_array($body) || ($body['status'] ?? null) !== 'success') {
                return $this->markFailed($ip);
            }

            return IpGeolocation::updateOrCreate(['ip_address' => $ip], [
                'country'       => $body['country']    ?? null,
                'country_code'  => $body['countryCode'] ?? null,
                'region'        => $body['regionName'] ?? null,
                'city'          => $body['city']       ?? null,
                'latitude'      => $body['lat']        ?? null,
                'longitude'     => $body['lon']        ?? null,
                'timezone'      => $body['timezone']   ?? null,
                'isp'           => $body['isp']        ?? null,
                'org'           => $body['org']        ?? null,
                'as_name'       => $body['asname']     ?? null,
                'is_mobile'     => (bool) ($body['mobile']  ?? false),
                'is_proxy'      => (bool) ($body['proxy']   ?? false),
                'is_hosting'    => (bool) ($body['hosting'] ?? false),
                'lookup_status' => IpGeolocation::STATUS_SUCCESS,
                'looked_up_at'  => now(),
            ]);
        } catch (\Throwable $th) {
            Log::warning('[ActivityLog] IP geolocation lookup failed', [
                'ip'    => $ip,
                'error' => $th->getMessage(),
            ]);

            return $this->markFailed($ip);
        }
    }

    protected function markFailed($ip)
    {
        // Left as 'failed' rather than 'skipped' so a later run retries it -
        // the usual cause is a transient rate limit, not a bad address.
        return IpGeolocation::updateOrCreate(
            ['ip_address' => $ip],
            ['lookup_status' => IpGeolocation::STATUS_FAILED, 'looked_up_at' => now()]
        );
    }

    /**
     * Private, loopback and reserved ranges will never resolve, so do not spend
     * a rate-limited call on them.
     */
    protected function isPublicIp($ip)
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
