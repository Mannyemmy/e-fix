<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\PaymentGateway;

class SafehavenController extends Controller
{
    protected function getPadipayGatewayConfig(): array
    {
        $paymentGateway = PaymentGateway::where('type', 'padipay')->first();
        if (!$paymentGateway) {
            return [];
        }

        $json = $paymentGateway->is_test ? $paymentGateway->value : $paymentGateway->live_value;
        $config = json_decode($json, true);

        return is_array($config) ? $config : [];
    }

    protected function baseUrl(): string
    {
        $url = config('services.safehaven.external_api_url') ?: env('SAFEHAVEN_EXTERNAL_API_URL', '');

        if (!$url) {
            $config = $this->getPadipayGatewayConfig();
            $url = $config['external_api_url'] ?? '';
        }

        if (!$url) {
            throw new \RuntimeException('SafeHaven external API URL is not configured.');
        }

        return rtrim($url, '/');
    }

    protected function apiKey(): string
    {
        $key = config('services.safehaven.external_api_key') ?: env('SAFEHAVEN_EXTERNAL_API_KEY', '');

        if (!$key) {
            $config = $this->getPadipayGatewayConfig();
            $key = $config['external_api_key'] ?? '';
        }

        if (!$key) {
            throw new \RuntimeException('SafeHaven external API key is not configured.');
        }

        return trim($key);
    }

    protected function buildUrl(string $path): string
    {
        $base = $this->baseUrl();
        $path = ltrim($path, '/');

        if (Str::contains($base, '/api/v1')) {
            $path = preg_replace('#^api/v1/#', '', $path);
        }

        return $base . '/' . $path;
    }

    protected function externalRequest(string $method, string $path, array $payload = [])
    {
        try {
            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30);

            $url = $this->buildUrl($path);

            if (strtoupper($method) === 'GET') {
                $response = $client->get($url);
            } elseif (strtoupper($method) === 'POST') {
                $response = $client->post($url, $payload);
            } elseif (strtoupper($method) === 'PUT') {
                $response = $client->put($url, $payload);
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
                'error' => 'SafeHaven proxy error: ' . $e->getMessage(),
            ], 502);
        }
    }

    public function verifyBvn(Request $request)
    {
        $data = $request->validate([
            'bvn' => 'required|string|size:11',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
        ]);

        return $this->externalRequest('POST', '/api/v1/verify/bvn', [
            'bvn' => trim($data['bvn']),
            'firstName' => trim($data['firstName']),
            'lastName' => trim($data['lastName']),
        ]);
    }

    public function createAccount(Request $request)
    {
        $data = $request->validate([
            'externalRef' => 'required|string',
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'email' => 'required|email',
            'phoneNumber' => 'required|string',
            'bvn' => 'required|string|size:11',
            'type' => 'sometimes|string|in:individual',
            'businessName' => 'sometimes|string|nullable',
            'companyRegistrationNumber' => 'sometimes|string|nullable',
        ]);

        if (isset($data['type']) && $data['type'] !== 'individual') {
            return comman_custom_response([
                'error' => 'Only individual SafeHaven accounts are supported at this time.',
            ], 422);
        }

        $payload = [
            'externalRef' => trim($data['externalRef']),
            'firstName' => trim($data['firstName']),
            'lastName' => trim($data['lastName']),
            'email' => trim($data['email']),
            'phoneNumber' => trim($data['phoneNumber']),
            'bvn' => trim($data['bvn']),
            'type' => 'individual',
        ];

        return $this->externalRequest('POST', '/api/v1/accounts', $payload);
    }

    public function getAccount(Request $request, string $accountNumber)
    {
        return $this->externalRequest('GET', '/api/v1/accounts/' . trim($accountNumber));
    }

    public function nipTransfer(Request $request)
    {
        $data = $request->validate([
            'fromAccountNumber' => 'required|string',
            'beneficiaryAccountNumber' => 'required|string',
            'beneficiaryBankCode' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'narration' => 'nullable|string',
            'reference' => 'required|string',
        ]);

        return $this->externalRequest('POST', '/api/v1/transfers/nip', [
            'fromAccountNumber' => trim($data['fromAccountNumber']),
            'beneficiaryAccountNumber' => trim($data['beneficiaryAccountNumber']),
            'beneficiaryBankCode' => trim($data['beneficiaryBankCode']),
            'amount' => $data['amount'],
            'narration' => trim($data['narration'] ?? ''),
            'reference' => trim($data['reference']),
        ]);
    }

    public function intraTransfer(Request $request)
    {
        $data = $request->validate([
            'fromAccountNumber' => 'required|string',
            'toAccountNumber' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'narration' => 'nullable|string',
            'reference' => 'required|string',
        ]);

        return $this->externalRequest('POST', '/api/v1/transfers/intra', [
            'fromAccountNumber' => trim($data['fromAccountNumber']),
            'toAccountNumber' => trim($data['toAccountNumber']),
            'amount' => $data['amount'],
            'narration' => trim($data['narration'] ?? ''),
            'reference' => trim($data['reference']),
        ]);
    }

    public function listBanks()
    {
        return $this->externalRequest('GET', '/api/v1/banks');
    }

    /**
     * Handle SafeHaven webhooks forwarded from padipay.
     * Processes identity verification, account.debit, and account.credit events.
     */
    public function handleSafehavenWebhook(Request $request)
    {
        try {
            $rawBody = $request->getContent();
            $payload = $request->all();
            $eventType = strtolower($payload['type'] ?? $payload['eventType'] ?? '');
            $data = $payload['data'] ?? $payload;

            \Log::info('[efix SafeHaven webhook] received', [
                'headers' => $request->headers->all(),
                'body' => $payload,
                'rawBody' => $rawBody,
            ]);

            if (!$this->verifyEfixSignature($request, $rawBody)) {
                \Log::warning('[efix SafeHaven webhook] signature verification failed');
                return response()->json(['status' => 'unauthorized'], 401);
            }

            $this->saveEfixWebhookLog($payload, $rawBody, $request->headers->all());

            if (!$eventType) {
                \Log::warning('[efix SafeHaven webhook] No event type found');
                return response()->json(['status' => 'ok'], 200);
            }

            if ($eventType === 'identitycreditcheck') {
                $this->handleIdentityCreditCheck($data);
            } elseif ($eventType === 'account.credit' || $eventType === 'transfer') {
                $this->handleAccountCredit($data);
            } elseif ($eventType === 'account.debit') {
                $this->handleAccountDebit($data);
            } else {
                \Log::info("[efix SafeHaven webhook] Unknown event type: {$eventType}");
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (\Exception $e) {
            \Log::error('[efix SafeHaven webhook] Error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'ok'], 200);
        }
    }

    private function verifyEfixSignature(Request $request, string $rawBody): bool
    {
        $secret = trim(env('EFIX_SAFEHAVEN_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            \Log::warning('[efix SafeHaven webhook] no EFIX_SAFEHAVEN_WEBHOOK_SECRET configured; skipping signature check');
            return true;
        }

        $signature = $request->header('X-Efix-Signature') ?? $request->header('X-Hub-Signature') ?? '';
        if (!$signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    private function saveEfixWebhookLog(array $payload, string $rawBody, array $headers)
    {
        try {
            DB::table('efix_safehaven_webhook_logs')->insert([
                'event_type' => strtolower($payload['type'] ?? $payload['eventType'] ?? ''),
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

    private function updateIdentityVerification(
        $firestore,
        $collection,
        $docId,
        $identityId,
        $identityNumber,
        $status,
        $otpVerified,
        $data
    ) {
        $updateData = [
            'identityId' => $identityId ?: null,
            'identityCheckStatus' => $status,
            'identityCheckMessage' => $data['debitMessage'] ?? null,
            'identityCheckUpdatedAt' => new \Google\Cloud\Firestore\FieldValue\ServerTimestamp(),
            'identityVerification' => [
                'verified' => $status === 'SUCCESS' && $otpVerified,
                'verifiedAt' => ($status === 'SUCCESS' && $otpVerified) ? new \Google\Cloud\Firestore\FieldValue\ServerTimestamp() : null,
                'identityId' => $identityId ?: null,
                'number' => $identityNumber ?: null,
            ],
        ];

        $firestore->collection($collection)->document($docId)->update($updateData);
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
