<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * eFix BaaS proxy — backed by RootFi (https://api.rootfi.co).
 *
 * Class/route names retain the "Safehaven" prefix for backwards compatibility
 * with shipped mobile clients; internally every call is forwarded to RootFi
 * using the tenant's `x-api-key`.
 */
class SafehavenController extends Controller
{
    protected function getRootfiGatewayConfig(): array
    {
        $paymentGateway = PaymentGateway::where('type', 'rootfi')->first();
        if (!$paymentGateway) {
            return [];
        }

        $json = $paymentGateway->is_test ? $paymentGateway->value : $paymentGateway->live_value;
        $config = json_decode($json, true);

        return is_array($config) ? $config : [];
    }

    protected function baseUrl(): string
    {
        $url = config('services.rootfi.base_url') ?: env('ROOTFI_BASE_URL', '');

        if (!$url) {
            $config = $this->getRootfiGatewayConfig();
            $url = $config['base_url'] ?? '';
        }

        if (!$url) {
            $url = 'https://api.rootfi.co';
        }

        return rtrim($url, '/');
    }

    protected function apiKey(): string
    {
        $key = config('services.rootfi.api_key') ?: env('ROOTFI_API_KEY', '');

        if (!$key) {
            $config = $this->getRootfiGatewayConfig();
            $key = $config['api_key'] ?? '';
        }

        if (!$key) {
            throw new \RuntimeException('RootFi API key is not configured.');
        }

        return trim($key);
    }

    protected function masterAccountNumber(): ?string
    {
        $num = config('services.rootfi.master_account_number') ?: env('ROOTFI_MASTER_ACCOUNT_NUMBER', '');
        if (!$num) {
            $config = $this->getRootfiGatewayConfig();
            $num = $config['master_account_number'] ?? '';
        }
        return $num ? trim($num) : null;
    }

    protected function buildUrl(string $path): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    protected function externalRequest(string $method, string $path, array $payload = [], array $query = [])
    {
        try {
            $client = Http::withHeaders([
                'x-api-key' => $this->apiKey(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30);

            $url = $this->buildUrl($path);

            $method = strtoupper($method);
            if ($method === 'GET') {
                $response = $client->get($url, $query);
            } elseif ($method === 'POST') {
                $response = $client->post($url, $payload);
            } elseif ($method === 'PUT') {
                $response = $client->put($url, $payload);
            } elseif ($method === 'DELETE') {
                $response = $client->delete($url, $payload);
            } else {
                $response = $client->send($method, $url, ['json' => $payload]);
            }

            $body = $response->json();
            if ($body === null) {
                $body = ['error' => $response->body()];
            }

            return response()->json($body, $response->status());
        } catch (\Exception $e) {
            return comman_custom_response([
                'error' => 'RootFi proxy error: ' . $e->getMessage(),
            ], 502);
        }
    }

    // ─── Identity (BVN) ─────────────────────────────────────────────────────

    /**
     * Kick off BVN identity verification. RootFi responds with an identityId
     * and (depending on async settings) emits an `identitycreditcheck`
     * webhook once SafeHaven completes the check.
     */
    public function verifyBvn(Request $request)
    {
        $data = $request->validate([
            'bvn' => 'required|string|size:11',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'dateOfBirth' => 'sometimes|string|nullable',
        ]);

        return $this->externalRequest('POST', '/v1/identity/verify', [
            'type' => 'BVN',
            'async' => false,
            'number' => trim($data['bvn']),
            'firstName' => trim($data['firstName']),
            'lastName' => trim($data['lastName']),
            'dateOfBirth' => isset($data['dateOfBirth']) ? trim($data['dateOfBirth']) : null,
        ]);
    }

    /**
     * Validate the OTP returned by SafeHaven's identity flow.
     */
    public function validateBvn(Request $request)
    {
        $data = $request->validate([
            'identityId' => 'required|string',
            'otp' => 'required|string',
            'type' => 'sometimes|string',
        ]);

        return $this->externalRequest('POST', '/v1/identity/validate', [
            'type' => trim($data['type'] ?? 'BVN'),
            'identityId' => trim($data['identityId']),
            'otp' => trim($data['otp']),
        ]);
    }

    // ─── Accounts ───────────────────────────────────────────────────────────

    /**
     * Create a per-user sub-account under the eFix tenant. Mirrors the old
     * SafeHaven createAccount call but routes through RootFi's
     * `/v1/accounts/subaccount`.
     */
    public function createAccount(Request $request)
    {
        $data = $request->validate([
            'externalRef' => 'required|string',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'email' => 'required|email',
            'phoneNumber' => 'required|string',
            'bvn' => 'required|string|size:11',
            'identityId' => 'sometimes|string|nullable',
            'type' => 'sometimes|string|in:individual',
            'businessName' => 'sometimes|string|nullable',
            'companyRegistrationNumber' => 'sometimes|string|nullable',
        ]);

        if (isset($data['type']) && $data['type'] !== 'individual') {
            return comman_custom_response([
                'error' => 'Only individual accounts are supported at this time.',
            ], 422);
        }

        // SafeHaven (via Rootfi) pulls firstName/lastName/dob/etc from the
        // verified identity record on its end, so we only forward the link
        // (identityType=vID + identityId) plus contact + reference fields.
        // Sending bvn/identityNumber/firstName etc here triggers a 400.
        if (empty($data['identityId'])) {
            return comman_custom_response([
                'error' => 'identityId is required. Run BVN verification first.',
            ], 422);
        }

        $payload = [
            'identityType' => 'vID',
            'identityId' => trim($data['identityId']),
            'externalReference' => trim($data['externalRef']),
            'emailAddress' => trim($data['email']),
            'phoneNumber' => trim($data['phoneNumber']),
        ];

        $response = $this->externalRequest('POST', '/v1/accounts/subaccount', $payload);

        // On success, persist the returned accountNumber to the authenticated
        // user so the webhook handler can route inbound credits back to them.
        try {
            $body = json_decode($response->getContent(), true) ?: [];
            $accountNumber = $body['data']['accountNumber']
                ?? $body['accountNumber']
                ?? null;
            if ($accountNumber && auth()->check()) {
                User::where('id', auth()->id())->update([
                    'safehaven_account_number' => $accountNumber,
                ]);
                \Log::info('[efix] safehaven_account_number persisted', [
                    'user_id' => auth()->id(),
                    'account_number' => $accountNumber,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('[efix] failed to persist safehaven_account_number', [
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    public function getAccount(Request $request, string $accountNumber)
    {
        return $this->externalRequest('GET', '/v1/accounts/' . trim($accountNumber));
    }

    /**
     * Balance fetch. Rootfi does not expose a dedicated /balance endpoint —
     * the account record itself carries `accountBalance` / `bookBalance` /
     * `availableBalance` (varies by SafeHaven response shape). We fetch
     * the account and surface a normalised payload that mobile clients
     * can read at response.data.availableBalance.
     */
    public function getAccountBalance(Request $request, string $accountNumber)
    {
        try {
            $client = Http::withHeaders([
                'x-api-key' => $this->apiKey(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30);

            $url = $this->buildUrl('/v1/accounts/' . trim($accountNumber));
            $response = $client->get($url);
            $body = $response->json() ?? ['error' => $response->body()];

            // Pluck whichever balance field SafeHaven returned and surface
            // it uniformly so the mobile client only has to look at one
            // path: response.data.availableBalance.
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];
            $available = $data['availableBalance']
                ?? $data['accountBalance']
                ?? $data['bookBalance']
                ?? $data['balance']
                ?? 0;

            return response()->json([
                'statusCode' => $response->status(),
                'message' => $body['message'] ?? 'ok',
                'data' => array_merge($data, [
                    'availableBalance' => $available,
                ]),
            ], $response->status());
        } catch (\Exception $e) {
            return comman_custom_response([
                'error' => 'RootFi proxy error: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Fetch the statement (debits + credits) for an eFix account. Rootfi
     * exposes `GET /v1/accounts/{accountId}/statement` and proxies SafeHaven's
     * raw transaction list. We pass through as-is; the mobile client
     * normalises the rows.
     *
     * Optional query: `type=Credit|Debit` to filter.
     */
    public function getAccountStatement(Request $request, string $accountNumber)
    {
        $query = [];
        $type = $request->query('type');
        if ($type) {
            $query['type'] = $type;
        }
        return $this->externalRequest(
            'GET',
            '/v1/accounts/' . trim($accountNumber) . '/statement',
            [],
            $query
        );
    }

    /**
     * Create a dynamic virtual account (e.g. for one-off top-ups).
     */
    public function createVirtualAccount(Request $request)
    {
        $data = $request->validate([
            'validFor' => 'required|integer|min:1',
            'settlementAccount' => 'required|string',
            'amount' => 'sometimes|numeric|nullable',
            'amountControl' => 'sometimes|string|nullable',
            'callbackUrl' => 'sometimes|string|nullable',
            'externalRef' => 'sometimes|string|nullable',
        ]);

        $payload = [
            'validFor' => (int) $data['validFor'],
            'settlementAccount' => trim($data['settlementAccount']),
        ];
        if (array_key_exists('amount', $data) && $data['amount'] !== null) {
            $payload['amount'] = (float) $data['amount'];
        }
        if (!empty($data['amountControl'])) {
            $payload['amountControl'] = trim($data['amountControl']);
        }
        if (!empty($data['callbackUrl'])) {
            $payload['callbackUrl'] = trim($data['callbackUrl']);
        }
        if (!empty($data['externalRef'])) {
            $payload['externalRef'] = trim($data['externalRef']);
        }

        return $this->externalRequest('POST', '/v1/virtual-accounts', $payload);
    }

    // ─── Transfers (money out) ──────────────────────────────────────────────

    /**
     * Unified transfer endpoint — handles both inter-bank (NIP) and
     * intra-bank cases. RootFi infers the routing from the bank code.
     */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'debitAccountNumber' => 'required|string',
            'beneficiaryAccountNumber' => 'required|string',
            'beneficiaryBankCode' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'narration' => 'nullable|string',
            'paymentReference' => 'sometimes|string|nullable',
            'nameEnquiryReference' => 'required|string',
        ]);

        // Payload shape matches Rootfi's own working call sites
        // (chargeFee, softpos settlement, admin transfer). SafeHaven
        // returns "Bad Request" if `paymentReference` is sent in an
        // unrecognised format or if `saveBeneficiary` is omitted, so we
        // mirror the canonical shape exactly. Default narration to
        // something non-empty because TransferBody requires min(1).
        $payload = [
            'debitAccountNumber' => trim($data['debitAccountNumber']),
            'beneficiaryBankCode' => trim($data['beneficiaryBankCode']),
            'beneficiaryAccountNumber' => trim($data['beneficiaryAccountNumber']),
            'narration' => trim($data['narration'] ?? '') ?: 'eFix transfer',
            'amount' => (float) $data['amount'],
            'nameEnquiryReference' => trim($data['nameEnquiryReference']),
            'saveBeneficiary' => false,
        ];

        return $this->externalRequest('POST', '/v1/transfers', $payload);
    }

    /**
     * Back-compat shim: existing mobile clients call /transfers/nip with
     * `fromAccountNumber` / `reference`. Map to the unified transfer.
     */
    public function nipTransfer(Request $request)
    {
        $data = $request->validate([
            'fromAccountNumber' => 'required|string',
            'beneficiaryAccountNumber' => 'required|string',
            'beneficiaryBankCode' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'narration' => 'nullable|string',
            'reference' => 'required|string',
            'nameEnquiryReference' => 'sometimes|string|nullable',
        ]);

        if (empty($data['nameEnquiryReference'])) {
            return comman_custom_response([
                'error' => 'nameEnquiryReference is required. Run name enquiry first.',
            ], 422);
        }

        $payload = [
            'debitAccountNumber' => trim($data['fromAccountNumber']),
            'beneficiaryBankCode' => trim($data['beneficiaryBankCode']),
            'beneficiaryAccountNumber' => trim($data['beneficiaryAccountNumber']),
            'narration' => trim($data['narration'] ?? '') ?: 'eFix transfer',
            'amount' => (float) $data['amount'],
            'nameEnquiryReference' => trim($data['nameEnquiryReference']),
            'saveBeneficiary' => false,
        ];

        return $this->externalRequest('POST', '/v1/transfers', $payload);
    }

    /**
     * Back-compat shim for intra-bank transfers. Resolves to the same
     * unified transfer endpoint; clients should pass SafeHaven's bank code
     * (090286 at time of writing) as `beneficiaryBankCode`.
     */
    public function intraTransfer(Request $request)
    {
        $data = $request->validate([
            'fromAccountNumber' => 'required|string',
            'toAccountNumber' => 'required|string',
            'beneficiaryBankCode' => 'sometimes|string',
            'amount' => 'required|numeric|min:1',
            'narration' => 'nullable|string',
            'reference' => 'sometimes|string|nullable',
            'nameEnquiryReference' => 'required|string',
        ]);

        $payload = [
            'debitAccountNumber' => trim($data['fromAccountNumber']),
            'beneficiaryBankCode' => trim($data['beneficiaryBankCode'] ?? '090286'),
            'beneficiaryAccountNumber' => trim($data['toAccountNumber']),
            'narration' => trim($data['narration'] ?? '') ?: 'eFix intra transfer',
            'amount' => (float) $data['amount'],
            'nameEnquiryReference' => trim($data['nameEnquiryReference']),
            'saveBeneficiary' => false,
        ];

        return $this->externalRequest('POST', '/v1/transfers', $payload);
    }

    public function nameEnquiry(Request $request)
    {
        $data = $request->validate([
            'bankCode' => 'required|string',
            'accountNumber' => 'required|string',
        ]);

        return $this->externalRequest('POST', '/v1/transfers/name-enquiry', [
            'bankCode' => trim($data['bankCode']),
            'accountNumber' => trim($data['accountNumber']),
        ]);
    }

    public function listBanks(Request $request)
    {
        $showLogos = $request->query('showLogos');
        $query = [];
        if ($showLogos !== null) {
            $query['showLogos'] = $showLogos;
        }
        return $this->externalRequest('GET', '/v1/transfers/banks', [], $query);
    }

    // ─── Webhook (Rootfi -> eFix) ──────────────────────────────────────────

    /**
     * Receive forwarded SafeHaven events from RootFi. Body shape:
     *   { eventType, data, timestamp, signature: "sha256=..." }
     * The `X-RootFi-Signature` header carries the HMAC of the event JSON
     * *without* the `signature` field (see lib/safehaven-api.ts in rootfi).
     */
    public function handleSafehavenWebhook(Request $request)
    {
        try {
            $rawBody = $request->getContent();
            $payload = $request->all();
            $eventType = strtolower($payload['eventType'] ?? $payload['type'] ?? '');
            $data = $payload['data'] ?? $payload;

            \Log::info('[efix RootFi webhook] received', [
                'headers' => $request->headers->all(),
                'body' => $payload,
                'rawBody' => $rawBody,
            ]);

            if (!$this->verifyRootfiSignature($request, $payload)) {
                \Log::warning('[efix RootFi webhook] signature verification failed');
                return response()->json(['status' => 'unauthorized'], 401);
            }

            $this->saveEfixWebhookLog($payload, $rawBody, $request->headers->all());

            if (!$eventType) {
                \Log::warning('[efix RootFi webhook] No event type found');
                return response()->json(['status' => 'ok'], 200);
            }

            if ($eventType === 'identitycreditcheck') {
                $this->handleIdentityCreditCheck($data);
            } elseif ($eventType === 'account.credit' || $eventType === 'transfer') {
                $this->handleAccountCredit($data);
            } elseif ($eventType === 'account.debit') {
                $this->handleAccountDebit($data);
            } else {
                \Log::info("[efix RootFi webhook] Unknown event type: {$eventType}");
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            \Log::error('[efix RootFi webhook] Error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'ok'], 200);
        }
    }

    /**
     * RootFi signs `JSON.stringify({eventType, data, timestamp})` (the event
     * before the signature field is appended). To verify, drop `signature`
     * from the parsed body, re-encode, and HMAC-SHA256 with our webhook
     * secret. Insertion order is preserved by RootFi (`{...event, signature}`),
     * so the re-encoded string matches the originally signed string.
     */
    private function verifyRootfiSignature(Request $request, array $payload): bool
    {
        $secret = trim(config('services.rootfi.webhook_secret') ?: env('ROOTFI_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            \Log::warning('[efix RootFi webhook] no ROOTFI_WEBHOOK_SECRET configured; skipping signature check');
            return true;
        }

        $headerSig = $request->header('X-RootFi-Signature')
            ?? $request->header('X-Rootfi-Signature')
            ?? $request->header('X-Efix-Signature')
            ?? '';
        $bodySig = $payload['signature'] ?? '';
        $signature = $headerSig ?: $bodySig;
        if (!$signature) {
            return false;
        }

        $eventOnly = $payload;
        unset($eventOnly['signature']);
        $signed = json_encode($eventOnly, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $expected = 'sha256=' . hash_hmac('sha256', $signed, $secret);
        return hash_equals($expected, $signature);
    }

    private function saveEfixWebhookLog(array $payload, string $rawBody, array $headers)
    {
        try {
            DB::table('efix_safehaven_webhook_logs')->insert([
                'event_type' => strtolower($payload['eventType'] ?? $payload['type'] ?? ''),
                'payload' => json_encode($payload),
                'raw_body' => $rawBody,
                'headers' => json_encode($headers),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('[efix webhook] saveEfixWebhookLog error', ['error' => $e->getMessage()]);
        }
    }

    private function handleIdentityCreditCheck($data)
    {
        try {
            $identityId = $data['_id'] ?? $data['id'] ?? $data['identityId'] ?? null;
            $identityNumber = $data['identityNumber'] ?? $data['number'] ?? null;
            $status = strtoupper($data['status'] ?? '');
            $otpVerified = $data['otpVerified'] ?? false;

            if (!$identityId && !$identityNumber) {
                \Log::warning('[efix webhook] identityCreditCheck: missing identityId and identityNumber');
                return;
            }

            \Log::info('[efix webhook] identityCreditCheck event received', [
                'identityId' => $identityId,
                'identityNumber' => $identityNumber,
                'status' => $status,
                'otpVerified' => $otpVerified,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('[efix webhook] handleIdentityCreditCheck error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * SafeHaven owns the balance — when a transfer lands in the user's
     * sub-account, the balance is updated by SafeHaven itself. We don't
     * touch any local wallet table. All we do here is:
     *   1. Find which eFix user owns the credited account.
     *   2. Notify them via FCM + insert an in-app notification row.
     */
    private function handleAccountCredit($data)
    {
        try {
            $creditAccountNumber = $data['creditAccountNumber']
                ?? $data['creditAccount']
                ?? $data['accountNumber']
                ?? null;
            $sessionId = $data['sessionId'] ?? null;
            // SafeHaven returns amount in NGN already (not kobo).
            $amount = (float)($data['amount'] ?? 0);
            $senderName = $data['debitAccountName']
                ?? $data['senderName']
                ?? 'Bank transfer';

            if (!$creditAccountNumber) {
                \Log::warning('[efix webhook] account.credit: missing creditAccountNumber');
                return;
            }
            if ($amount <= 0) {
                \Log::warning('[efix webhook] account.credit: non-positive amount', ['data' => $data]);
                return;
            }

            // Idempotency: skip if we've already processed this sessionId.
            // (The webhook log row for THIS event was inserted before we
            // got here, so we look for any OTHER row with the same id.)
            if ($sessionId) {
                $count = DB::table('efix_safehaven_webhook_logs')
                    ->where('event_type', 'account.credit')
                    ->where('payload', 'like', '%' . $sessionId . '%')
                    ->count();
                if ($count > 1) {
                    \Log::info('[efix webhook] account.credit duplicate ignored', [
                        'sessionId' => $sessionId,
                    ]);
                    return;
                }
            }

            $user = User::where('safehaven_account_number', $creditAccountNumber)->first();
            if (!$user) {
                \Log::warning('[efix webhook] account.credit: no matching user', [
                    'creditAccountNumber' => $creditAccountNumber,
                ]);
                return;
            }

            // Try to auto-flip a matching PENDING booking → PAID.
            // Match by customer + amount (±1 NGN tolerance for rounding),
            // most recently created first.
            $bookingId = null;
            $matchingPayment = Payment::where('customer_id', $user->id)
                ->where('payment_status', 'pending')
                ->whereBetween('total_amount', [$amount - 1, $amount + 1])
                ->orderBy('id', 'desc')
                ->first();

            if ($matchingPayment) {
                $matchingPayment->payment_status = 'paid';
                if ($sessionId) {
                    $matchingPayment->txn_id = $sessionId;
                }
                $matchingPayment->save();

                $bookingId = $matchingPayment->booking_id;
                if ($bookingId) {
                    Booking::where('id', $bookingId)
                        ->update(['payment_status' => 'paid']);
                }

                \Log::info('[efix webhook] auto-flipped booking to PAID', [
                    'user_id' => $user->id,
                    'payment_id' => $matchingPayment->id,
                    'booking_id' => $bookingId,
                    'amount' => $amount,
                ]);
            }

            // Fire the standard notification helper which sends FCM (to the
            // user_<id> topic) and inserts a row into the notifications
            // table the mobile client already polls.
            $notificationId = (string) Str::uuid();
            $message = $bookingId
                ? sprintf(
                    'Your transfer of ₦%s was received and booking #%d is now paid.',
                    number_format($amount, 2),
                    $bookingId
                )
                : sprintf(
                    'Your eFix bank account received ₦%s from %s.',
                    number_format($amount, 2),
                    $senderName
                );

            sendNotification('wallet_credit', $user, [
                'id' => $bookingId ?: $notificationId,
                'type' => 'wallet_credit',
                'subject' => $bookingId ? 'booking_paid' : 'wallet_credited',
                'message' => $message,
                'notification-type' => 'wallet_credit',
            ]);

            \Log::info('[efix webhook] account.credit notified', [
                'user_id' => $user->id,
                'amount' => $amount,
                'sender' => $senderName,
                'sessionId' => $sessionId,
                'booking_id' => $bookingId,
                'notification_id' => $notificationId,
            ]);
        } catch (\Exception $e) {
            \Log::error('[efix webhook] handleAccountCredit error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function handleAccountDebit($data)
    {
        try {
            $debitAccountNumber = $data['debitAccountNumber'] ?? $data['account'] ?? $data['accountNumber'] ?? null;
            $sessionId = $data['sessionId'] ?? $data['debitSessionId'] ?? $data['reference'] ?? null;
            $amount = (int)($data['amount'] ?? 0);
            $recipientName = $data['creditAccountName'] ?? 'Transfer recipient';

            if (!$debitAccountNumber) {
                \Log::warning('[efix webhook] account.debit: missing debitAccountNumber');
                return;
            }

            \Log::info('[efix webhook] account.debit event received', [
                'debitAccountNumber' => $debitAccountNumber,
                'sessionId' => $sessionId,
                'amount' => $amount,
                'recipientName' => $recipientName,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('[efix webhook] handleAccountDebit error', ['error' => $e->getMessage()]);
        }
    }
}
