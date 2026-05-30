<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * QoreID liveness verification: drives a face-with-ID check that must
 * pass before the user can create their eFix bank (RootFi sub) account.
 *
 * Endpoints:
 *   GET  /qoreid-verify             — public HTML page that hosts the
 *                                     QoreID SDK button. The mobile
 *                                     WebView opens this URL.
 *   POST /api/qoreid/initiate        — Sanctum: mint a customerReference
 *                                     and seed a pending row.
 *   POST /api/qoreid/webhook         — public: QoreID posts the result
 *                                     here. We verify the secret and
 *                                     upsert by customerReference.
 *   GET  /api/qoreid/status          — Sanctum: poll the latest status.
 */
class QoreIdController extends Controller
{
    /** Allowed QoreID product codes from the mobile side. */
    private const ALLOWED_PRODUCT_CODES = ['liveness_nin'];

    /**
     * Normalise a Nigerian phone number to E.164 (`+234XXXXXXXXXX`).
     * QoreID's identity products require the +234 prefix; clients that
     * pass a 0-prefixed or unprefixed number get a less-helpful error
     * from QoreID otherwise. Accepts most realistic input shapes.
     */
    private function normaliseNigerianMsisdn(?string $input): string
    {
        $raw = (string) $input;
        if (trim($raw) === '') return '';

        // Strip everything that isn't a digit.
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || $digits === null) return '';

        // 234XXXXXXXXXX  → already country-prefixed
        if (str_starts_with($digits, '234') && strlen($digits) >= 13) {
            return '+' . $digits;
        }
        // 0XXXXXXXXXX (NG local) → drop leading 0, prepend 234
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+234' . substr($digits, 1);
        }
        // XXXXXXXXXX (10-digit national) → prepend 234
        if (strlen($digits) === 10) {
            return '+234' . $digits;
        }
        // Anything else: best effort — prepend + if it looks like an
        // international number, otherwise return as-is so QoreID can
        // surface its own validation error.
        return strlen($digits) >= 11 ? ('+' . $digits) : $digits;
    }

    /**
     * Resolve a QoreID config value. Tries, in order:
     *   1. The `payment_gateways` row with type=qoreid (test or live blob,
     *      based on its is_test flag) — managed via the admin Payment
     *      Configuration tab.
     *   2. `config('services.qoreid.<key>')` — loaded from services.php.
     *   3. `env('QOREID_<KEY>')` — last-resort raw env read for cases
     *      where the config cache is stale.
     *
     * This mirrors what SafehavenController does for RootFi and lets the
     * operator manage credentials through the admin UI without SSH.
     */
    private function qoreidConfig(string $key, string $default = ''): string
    {
        // 1. PaymentGateway row
        try {
            $row = PaymentGateway::where('type', 'qoreid')->first();
            if ($row) {
                $json = $row->is_test ? $row->value : $row->live_value;
                $decoded = $json ? json_decode($json, true) : null;
                if (is_array($decoded) && !empty($decoded[$key])) {
                    return (string) $decoded[$key];
                }
            }
        } catch (\Throwable $e) {
            // Table may not exist yet on a fresh install; fall through.
        }

        // 2. Cached config
        $fromConfig = config('services.qoreid.' . $key);
        if (!empty($fromConfig)) {
            return (string) $fromConfig;
        }

        // 3. Raw env
        $envKey = 'QOREID_' . strtoupper($key);
        $fromEnv = env($envKey, $default);
        return (string) ($fromEnv ?: $default);
    }

    /**
     * Start a verification session. Returns the customerReference and the
     * URL to load in a WebView. The URL embeds the customerReference,
     * productCode, and applicant data so the SDK can be auto-launched.
     */
    public function initiate(Request $request)
    {
        $data = $request->validate([
            'productCode' => 'required|string',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'email' => 'required|email',
            'phone' => 'sometimes|string|nullable',
        ]);

        if (!in_array($data['productCode'], self::ALLOWED_PRODUCT_CODES, true)) {
            return response()->json([
                'error' => 'Unsupported productCode. Only liveness_nin is supported.',
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $customerReference = 'EFIX-QI-' . $user->id . '-' . Str::random(12);
        $idType = $data['productCode'] === 'liveness_nin' ? 'NIN' : 'BVN';

        DB::table('efix_qoreid_verifications')->insert([
            'user_id' => $user->id,
            'customer_reference' => $customerReference,
            'product_code' => $data['productCode'],
            'id_type' => $idType,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // QoreID expects E.164 — for NG that's `+234…`. Mobile clients
        // may pass any of: "07084531952", "7084531952", "234-7084531952",
        // "+2347084531952", "2347084531952". Normalise here once so both
        // apps and the verify page consume the same canonical form.
        $data['phone'] = $this->normaliseNigerianMsisdn($data['phone'] ?? '');

        // The Flutter native SDK needs clientId at launch time. We mint it
        // here (server-side) so the mobile binary never has to ship the
        // value — matches the same resolution order the verify page uses.
        $clientId = $this->qoreidConfig('client_id');

        $params = http_build_query([
            'ref' => $customerReference,
            'product' => $data['productCode'],
            'firstName' => $data['firstName'],
            'lastName' => $data['lastName'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
        ]);
        $verifyUrl = url('/qoreid-verify') . '?' . $params;

        return response()->json([
            'customerReference' => $customerReference,
            'productCode' => $data['productCode'],
            'verifyUrl' => $verifyUrl,
            // Used by the native Flutter SDK. Empty when admin hasn't
            // configured QoreID — the mobile side surfaces an error.
            'clientId' => $clientId,
            'applicantData' => [
                'firstname' => $data['firstName'],
                'lastname' => $data['lastName'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? '',
            ],
        ]);
    }

    /**
     * Public QoreID webhook receiver. Signature header is validated against
     * QOREID_WEBHOOK_SECRET. Empty secret → signature check is skipped
     * (dev only — warn).
     */
    public function webhook(Request $request)
    {
        try {
            $rawBody = $request->getContent();
            $payload = $request->all();
            Log::info('[efix QoreID webhook] received', [
                'body' => $payload,
                'headers' => $request->headers->all(),
            ]);

            if (!$this->verifySignature($request, $rawBody)) {
                Log::warning('[efix QoreID webhook] signature mismatch');
                return response()->json(['status' => 'unauthorized'], 401);
            }

            $customerReference = $payload['customerReference']
                ?? $payload['customer_reference']
                ?? ($payload['data']['customerReference'] ?? null);

            if (!$customerReference) {
                Log::warning('[efix QoreID webhook] no customerReference');
                return response()->json(['status' => 'ok'], 200);
            }

            $row = DB::table('efix_qoreid_verifications')
                ->where('customer_reference', $customerReference)
                ->first();
            if (!$row) {
                Log::warning('[efix QoreID webhook] unknown customerReference', [
                    'customerReference' => $customerReference,
                ]);
                return response()->json(['status' => 'ok'], 200);
            }

            $details = $this->extractDetails($payload);
            $verifiedAt = ($details['status'] === 'verified') ? now() : null;

            DB::table('efix_qoreid_verifications')
                ->where('customer_reference', $customerReference)
                ->update([
                    'status' => $details['status'],
                    'verification_id' => $details['verificationId'] ?? null,
                    'id_number' => $details['idNumber'] ?? $row->id_number,
                    'full_name' => $details['fullName'] ?? null,
                    'first_name' => $details['firstName'] ?? null,
                    'middle_name' => $details['middleName'] ?? null,
                    'last_name' => $details['lastName'] ?? null,
                    'date_of_birth' => $details['dateOfBirth'] ?? null,
                    'gender' => $details['gender'] ?? null,
                    'liveness_score' => $details['livenessScore'] ?? null,
                    'face_match_score' => $details['faceMatchScore'] ?? null,
                    'raw_webhook' => json_encode($payload),
                    'error_message' => $details['errorMessage'] ?? null,
                    'verified_at' => $verifiedAt,
                    'updated_at' => now(),
                ]);

            // On successful BVN/NIN check, overwrite the authenticated
            // user's name/dob with the verified values so downstream
            // anti-fraud (name-match on withdraw) is anchored to the
            // QoreID-attested identity.
            if ($details['status'] === 'verified' && $details['firstName']) {
                try {
                    $userClass = config('auth.providers.users.model', \App\Models\User::class);
                    /** @var \Illuminate\Database\Eloquent\Model|null $user */
                    $user = $userClass::find($row->user_id);
                    if ($user) {
                        if (isset($user->first_name) || $user->offsetExists('first_name')) {
                            $user->first_name = $details['firstName'];
                        }
                        if (!empty($details['lastName']) && (isset($user->last_name) || $user->offsetExists('last_name'))) {
                            $user->last_name = $details['lastName'];
                        }
                        $user->save();
                    }
                } catch (\Throwable $e) {
                    Log::warning('[efix QoreID webhook] could not sync user name', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // ── Notify the user (FCM + email) about the result ──────────
            try {
                $targetUser = User::find($row->user_id);
                if ($targetUser) {
                    $isVerified = $details['status'] === 'verified';
                    $subject = $isVerified
                        ? 'Identity Verification Successful'
                        : 'Identity Verification Failed';
                    $message = $isVerified
                        ? 'Your identity has been verified successfully. You can now access all features.'
                        : 'Your identity verification did not pass. Reason: '
                            . ($details['errorMessage'] ?? 'NIN details did not match the information you provided.')
                            . ' Please try again or contact support.';

                    // FCM push + in-app notification via global helper
                    if (function_exists('sendNotification')) {
                        sendNotification('identity_verification', $targetUser, [
                            'id'               => $row->id,
                            'type'             => 'identity_verification',
                            'subject'          => $subject,
                            'message'          => $message,
                            'notification-type' => 'identity_verification',
                        ]);
                    }

                    // Email notification
                    try {
                        $appName = config('app.name', 'Handyman');
                        Mail::send([], [], function ($m) use ($targetUser, $subject, $message, $appName) {
                            $m->from(config('mail.from.address'), config('mail.from.name'));
                            $m->to($targetUser->email, $targetUser->display_name ?? '');
                            $m->subject("[$appName] $subject");
                            $m->setBody(
                                "<h3>$subject</h3><p>$message</p><p>Regards,<br>$appName</p>",
                                'text/html'
                            );
                        });
                    } catch (\Throwable $mailErr) {
                        Log::warning('[efix QoreID webhook] email send failed', [
                            'error' => $mailErr->getMessage(),
                            'user'  => $targetUser->id,
                        ]);
                    }
                }
            } catch (\Throwable $notifErr) {
                Log::warning('[efix QoreID webhook] notification dispatch failed', [
                    'error' => $notifErr->getMessage(),
                ]);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Throwable $e) {
            Log::error('[efix QoreID webhook] Error', ['error' => $e->getMessage()]);
            // Always 200 so QoreID doesn't retry forever on our bugs.
            return response()->json(['status' => 'ok'], 200);
        }
    }

    /**
     * Mobile polls this. Returns the latest verification status for the
     * authenticated user.
     */
    public function status(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        $ref = $request->query('customerReference');
        $query = DB::table('efix_qoreid_verifications')->where('user_id', $user->id);
        if ($ref) {
            $query->where('customer_reference', $ref);
        } else {
            $query->where('status', 'verified');
        }
        $row = $query->orderByDesc('id')->first();
        if (!$row) {
            return response()->json(['status' => 'not_started']);
        }
        return response()->json([
            'status' => $row->status,
            'customerReference' => $row->customer_reference,
            'productCode' => $row->product_code,
            'idType' => $row->id_type,
            'idNumber' => $row->id_number,
            'fullName' => $row->full_name,
            'firstName' => $row->first_name,
            'middleName' => $row->middle_name,
            'lastName' => $row->last_name,
            'dateOfBirth' => $row->date_of_birth,
            'livenessScore' => $row->liveness_score,
            'faceMatchScore' => $row->face_match_score,
            'verifiedAt' => $row->verified_at,
            'errorMessage' => $row->error_message,
            // Full QoreID webhook payload — useful when debugging "why
            // didn't the user pass?" or auditing what QoreID actually
            // returned. Only the row owner can read it (controller is
            // sanctum-gated).
            'rawWebhook' => $row->raw_webhook
                ? json_decode($row->raw_webhook, true)
                : null,
        ]);
    }

    /**
     * Public landing page that hosts the QoreID SDK button. The mobile
     * WebView loads this URL. Returns a simple HTML view; the actual
     * Blade template lives at resources/views/qoreid/verify.blade.php.
     */
    public function verifyPage(Request $request)
    {
        // Resolution order: admin Payment Configuration row -> services.php
        // config -> raw env. This lets the operator manage credentials from
        // the admin UI; .env stays as the bootstrap default.
        $clientId = $this->qoreidConfig('client_id');
        $sdkUrl = $this->qoreidConfig('sdk_url',
            'https://dashboard.qoreid.com/qoreid-sdk/qoreid.js');

        // Log the full resolution outcome so we can answer "did the
        // server actually have the keys?" without guessing.
        Log::info('[efix QoreID] verifyPage render', [
            'ref' => $request->query('ref'),
            'product' => $request->query('product'),
            'clientId_len' => strlen($clientId),
            'clientId_first6' => $clientId ? substr($clientId, 0, 6) . '…' : '(empty)',
            'sdkUrl' => $sdkUrl,
            'from_db_row' => (bool) PaymentGateway::where('type', 'qoreid')->first(),
            'from_config' => !empty(config('services.qoreid.client_id')),
            'from_env' => !empty(env('QOREID_CLIENT_ID')),
        ]);

        if (!$clientId) {
            Log::error('[efix QoreID] verifyPage called but client_id is empty. Set it in Admin → Settings → Payment Configuration → QoreID, or add QOREID_CLIENT_ID to .env and run `php artisan config:clear`.');
        }

        return view('qoreid.verify', [
            'clientId' => $clientId,
            'sdkUrl' => $sdkUrl,
            'productCode' => $request->query('product', 'liveness_nin'),
            'customerReference' => $request->query('ref', ''),
            'firstName' => $request->query('firstName', ''),
            'lastName' => $request->query('lastName', ''),
            'email' => $request->query('email', ''),
            'phone' => $request->query('phone', ''),
        ]);
    }

    private function verifySignature(Request $request, string $rawBody): bool
    {
        $secret = trim($this->qoreidConfig('webhook_secret'));
        if ($secret === '') {
            Log::warning('[efix QoreID webhook] QOREID_WEBHOOK_SECRET unset; skipping signature check');
            return true;
        }
        // QoreID sends either x-qoreid-signature or x-hub-signature-256 on
        // legacy accounts. Accept both and compare HMAC-SHA256(body, secret).
        $headerSig = $request->header('X-QoreID-Signature')
            ?? $request->header('X-Qoreid-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? '';
        if (!$headerSig) return false;
        $headerSig = preg_replace('/^sha256=/', '', $headerSig);
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $headerSig);
    }

    /**
     * QoreID webhook bodies vary by product. This pulls the fields we
     * care about regardless of nesting depth.
     */
    private function extractDetails(array $payload): array
    {
        $verification = $payload['verification']
            ?? ($payload['data']['verification'] ?? null);
        $applicant = $payload['applicant']
            ?? ($payload['data']['applicant'] ?? null);
        $metadata = is_array($verification) ? ($verification['metadata'] ?? null) : null;

        // $payload['status'] may be the raw status string OR an object
        // like {"state":"complete","status":"unverified"} — extract the
        // inner string when it's an array.
        $rawStatusValue = null;
        if ($verification && isset($verification['status'])) {
            $rawStatusValue = $verification['status'];
        } elseif (isset($payload['status'])) {
            $s = $payload['status'];
            if (is_string($s)) {
                $rawStatusValue = $s;
            } elseif (is_array($s) && isset($s['status'])) {
                $rawStatusValue = $s['status'];
            }
        }
        $statusRaw = strtolower(($rawStatusValue ?? ''));

        $status = 'failed';
        if (in_array($statusRaw, ['verified', 'success', 'approved', 'completed', 'complete'], true)) {
            $status = 'verified';
        } elseif (in_array($statusRaw, ['pending', 'processing', 'in_progress'], true)) {
            $status = 'pending';
        }

        return [
            'status' => $status,
            'verificationId' => $verification['id'] ?? ($payload['id'] ?? null),
            'idNumber' => $metadata['idNumber']
                ?? $metadata['bvnNumber']
                ?? $metadata['ninNumber']
                ?? null,
            'fullName' => $metadata['fullName']
                ?? ($applicant['fullName'] ?? null),
            'firstName' => $metadata['firstName']
                ?? ($applicant['firstName'] ?? null),
            'middleName' => $metadata['middleName']
                ?? ($applicant['middleName'] ?? null),
            'lastName' => $metadata['lastName']
                ?? ($applicant['lastName'] ?? null),
            'dateOfBirth' => $metadata['dateOfBirth']
                ?? $metadata['birthdate']
                ?? null,
            'gender' => $metadata['gender'] ?? null,
            'livenessScore' => isset($metadata['livenessConfidence'])
                ? (float) $metadata['livenessConfidence']
                : (isset($verification['livenessScore'])
                    ? (float) $verification['livenessScore']
                    : null),
            'faceMatchScore' => isset($metadata['faceMatchConfidence'])
                ? (float) $metadata['faceMatchConfidence']
                : (isset($verification['faceMatchScore'])
                    ? (float) $verification['faceMatchScore']
                    : null),
            'errorMessage' => $verification['reason']
                ?? ($payload['message'] ?? null),
        ];
    }
}
