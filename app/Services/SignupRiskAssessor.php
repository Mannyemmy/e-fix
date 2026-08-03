<?php

namespace App\Services;

use App\Models\IpGeolocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether a fresh signup looks real enough to send mail to.
 *
 * The account is still created either way - this is not an access control, it
 * only gates the outbound verification email. That matters because the abuse we
 * saw was a spam relay: bots posting harvested third-party addresses so the
 * server would mail real strangers, which burns the domain's sending
 * reputation. Refusing to send is what actually stops that damage.
 *
 * Everything here must be cheap. No live geolocation lookups on the signup
 * path - only the already-cached result, if one happens to exist.
 */
class SignupRiskAssessor
{
    /**
     * Throwaway inbox providers. Not exhaustive by design - it covers the
     * common ones without pretending to be a complete blocklist.
     */
    protected static $disposableDomains = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.info', 'sharklasers.com',
        '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'throwawaymail.com',
        'yopmail.com', 'trashmail.com', 'getnada.com', 'dispostable.com',
        'maildrop.cc', 'fakeinbox.com', 'mytemp.email', 'moakt.com',
        'emailondeck.com', 'tempr.email', 'spamgourmet.com', 'mailnesia.com',
    ];

    protected $geo;

    public function __construct(IpGeolocationService $geo)
    {
        $this->geo = $geo;
    }

    /**
     * @return array{safe: bool, reasons: array}
     */
    public function assess($email, Request $request = null)
    {
        $reasons = [];

        $domain = $this->domainOf($email);

        if ($domain === null) {
            $reasons[] = 'invalid_email';
        } elseif (config('services.signup_risk.block_disposable', true)
            && in_array($domain, static::$disposableDomains, true)) {
            $reasons[] = 'disposable_domain';
        } elseif (config('services.signup_risk.check_mx', true) && ! $this->domainAcceptsMail($domain)) {
            $reasons[] = 'no_mx_record';
        }

        foreach ($this->ipReasons($request) as $reason) {
            $reasons[] = $reason;
        }

        return [
            'safe'    => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    protected function domainOf($email)
    {
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $at = strrchr($email, '@');

        return $at === false ? null : strtolower(substr($at, 1));
    }

    /**
     * A domain with no MX and no A record cannot receive mail, so sending to it
     * only produces a bounce. Falls back to A because plenty of small domains
     * accept mail on the host record alone.
     */
    protected function domainAcceptsMail($domain)
    {
        try {
            return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        } catch (\Throwable $th) {
            // DNS trouble on our side is not the signup's fault - fail open.
            Log::warning('[SignupRisk] DNS check failed', [
                'domain' => $domain,
                'error'  => $th->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * Resolves the address live when we have not seen it before.
     *
     * A cache-only check was near useless against the observed attack: the bot
     * farm rotated 16 addresses across 22 signups, so almost every request came
     * from an address with no cached row, produced no signal, and got its mail
     * sent. The first request from a new address is precisely the one that
     * matters, so it is worth waiting on a lookup here.
     *
     * The cost lands only on the branch that is about to make an SMTP call
     * anyway, and the result is cached, so each address is paid for once.
     */
    protected function ipReasons(Request $request = null)
    {
        if (! $request || ! $request->ip()) {
            return [];
        }

        try {
            $ip  = $request->ip();
            $geo = IpGeolocation::where('ip_address', $ip)->first();

            $unresolved = ! $geo || $geo->lookup_status !== IpGeolocation::STATUS_SUCCESS;

            if ($unresolved && config('services.signup_risk.live_ip_lookup', true)) {
                // Tighter deadline than the admin pages use - somebody is waiting.
                $geo = $this->geo->resolve($ip, (int) config('services.signup_risk.lookup_timeout', 3));
            }

            if (! $geo || $geo->lookup_status !== IpGeolocation::STATUS_SUCCESS) {
                return [];
            }

            if ($geo->is_hosting) {
                return ['datacentre_ip'];
            }

            if ($geo->is_proxy) {
                return ['proxy_ip'];
            }
        } catch (\Throwable $th) {
            // A lookup problem must never stop a signup - fall through as "no signal".
            Log::warning('[SignupRisk] IP check failed', [
                'error' => $th->getMessage(),
            ]);
        }

        return [];
    }
}
