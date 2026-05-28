<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\PaymentGateway;

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
        ]);

        return $this->externalRequest('POST', '/v1/identity/validate', [
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

        $payload = [
            'externalRef' => trim($data['externalRef']),
            'accountType' => 'individual',
            'firstName' => trim($data['firstName']),
            'lastName' => trim($data['lastName']),
            'emailAddress' => trim($data['email']),
            'phoneNumber' => trim($data['phoneNumber']),
            'bvn' => trim($data['bvn']),
        ];
        if (!empty($data['identityId'])) {
            $payload['identityId'] = trim($data['identityId']);
        }

        return $this->externalRequest('POST', '/v1/accounts/subaccount', $payload);
    }

    public function getAccount(Request $request, string $accountNumber)
    {
        return $this->externalRequest('GET', '/v1/accounts/' . trim($accountNumber));
    }

    public function getAccountBalance(Request $request, string $accountNumber)
    {
        return $this->externalRequest('GET', '/v1/accounts/' . trim($accountNumber) . '/balance');
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
            'paymentReference' => 'required|string',
            'nameEnquiryReference' => 'sometimes|string|nullable',
        ]);

        $payload = [
            'debitAccountNumber' => trim($data['debitAccountNumber']),
            'beneficiaryAccountNumber' => trim($data['beneficiaryAccountNumber']),
            'beneficiaryBankCode' => trim($data['beneficiaryBankCode']),
            'amount' => (float) $data['amount'],
            'narration' => trim($data['narration'] ?? ''),
            'paymentReference' => trim($data['paymentReference']),
        ];
        if (!empty($data['nameEnquiryReference'])) {
            $payload['nameEnquiryReference'] = trim($data['nameEnquiryReference']);
        }

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

        $payload = [
            'debitAccountNumber' => trim($data['fromAccountNumber']),
            'beneficiaryAccountNumber' => trim($data['beneficiaryAccountNumber']),
            'beneficiaryBankCode' => trim($data['beneficiaryBankCode']),
            'amount' => (float) $data['amount'],
            'narration' => trim($data['narration'] ?? ''),
            'paymentReference' => trim($data['reference']),
        ];
        if (!empty($data['nameEnquiryReference'])) {
            $payload['nameEnquiryReference'] = trim($data['nameEnquiryReference']);
        }

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
            'reference' => 'required|string',
        ]);

        $payload = [
            'debitAccountNumber' => trim($data['fromAccountNumber']),
            'beneficiaryAccountNumber' => trim($data['toAccountNumber']),
            'beneficiaryBankCode' => trim($data['beneficiaryBankCode'] ?? '090286'),
            'amount' => (float) $data['amount'],
            'narration' => trim($data['narration'] ?? ''),
            'paymentReference' => trim($data['reference']),
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

    private function handleAccountCredit($data)
    {
        try {
            $creditAccountNumber = $data['creditAccountNumber'] ?? $data['creditAccount'] ?? $data['accountNumber'] ?? null;
            $sessionId = $data['sessionId'] ?? null;
            $amount = (int)($data['amount'] ?? 0);
            $senderName = $data['debitAccountName'] ?? $data['senderName'] ?? 'Bank transfer';

            if (!$creditAccountNumber) {
                \Log::warning('[efix webhook] account.credit: missing creditAccountNumber');
                return;
            }

            \Log::info('[efix webhook] account.credit event received', [
                'creditAccountNumber' => $creditAccountNumber,
                'sessionId' => $sessionId,
                'amount' => $amount,
                'senderName' => $senderName,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error('[efix webhook] handleAccountCredit error', ['error' => $e->getMessage()]);
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
