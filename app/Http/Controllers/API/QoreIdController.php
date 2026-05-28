<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    private const ALLOWED_PRODUCT_CODES = ['liveness_bvn', 'liveness_nin'];

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
                'error' => 'Unsupported productCode. Use liveness_bvn or liveness_nin.',
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
        ]);
    }

    /**
     * Public landing page that hosts the QoreID SDK button. The mobile
     * WebView loads this URL. Returns a simple HTML view; the actual
     * Blade template lives at resources/views/qoreid/verify.blade.php.
     */
    public function verifyPage(Request $request)
    {
        return view('qoreid.verify', [
            'clientId' => config('services.qoreid.client_id'),
            'sdkUrl' => config('services.qoreid.sdk_url'),
            'productCode' => $request->query('product', 'liveness_bvn'),
            'customerReference' => $request->query('ref', ''),
            'firstName' => $request->query('firstName', ''),
            'lastName' => $request->query('lastName', ''),
            'email' => $request->query('email', ''),
            'phone' => $request->query('phone', ''),
        ]);
    }

    private function verifySignature(Request $request, string $rawBody): bool
    {
        $secret = trim(config('services.qoreid.webhook_secret') ?: env('QOREID_WEBHOOK_SECRET', ''));
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

        $statusRaw = strtolower(
            $verification['status']
                ?? $payload['status']
                ?? ''
        );
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
