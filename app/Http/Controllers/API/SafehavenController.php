<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
}
