<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\Wallet;
use App\Traits\NotificationTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for "a Paystack charge really happened and was
 * recorded". Both the webhook (Paystack calling us directly) and the
 * app's own save-payment call funnel through reconcile() so a dropped
 * connection on the customer's phone after a successful charge can no
 * longer leave money debited with nothing recorded server-side.
 */
class PaystackReconciler
{
    use NotificationTrait;

    public static function liveSecretKey(): ?string
    {
        $gateway = PaymentGateway::where('type', 'paystack')->first();
        if (!$gateway) return null;

        $json = $gateway->is_test == 1 ? $gateway->value : $gateway->live_value;
        $decoded = json_decode($json ?? '', true);

        return $decoded['paystack_secret'] ?? null;
    }

    /**
     * Ask Paystack itself whether a reference was actually paid.
     * Returns the verified transaction `data` object, or null if it
     * couldn't be confirmed.
     */
    public static function verify(string $reference): ?array
    {
        $secret = self::liveSecretKey();
        if (!$secret) {
            Log::error("PaystackReconciler: no Paystack secret key configured");
            return null;
        }

        $response = Http::withToken($secret)
            ->get('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));

        if (!$response->successful()) {
            Log::error("PaystackReconciler: verify HTTP failure for $reference: " . $response->body());
            return null;
        }

        $body = $response->json();
        if (!($body['status'] ?? false)) {
            Log::error("PaystackReconciler: verify returned status=false for $reference: " . $response->body());
            return null;
        }

        $data = $body['data'] ?? null;
        if (($data['status'] ?? null) !== 'success') {
            Log::warning("PaystackReconciler: $reference is not a successful charge (status=" . ($data['status'] ?? 'null') . ')');
            return null;
        }

        return $data;
    }

    /**
     * Record a verified Paystack charge against a booking: creates the
     * Payment row, updates the booking's paid amount, credits the
     * provider's wallet, and fires the same notifications a normal
     * successful payment would. Idempotent on txn_id.
     *
     * @return array{payment: Payment, alreadyExisted: bool}
     */
    public function reconcile(Booking $booking, array $verifiedData): array
    {
        $reference = $verifiedData['reference'] ?? null;
        if (!$reference) {
            throw new \InvalidArgumentException('Verified Paystack data has no reference.');
        }

        $existing = Payment::where('txn_id', $reference)->where('payment_type', 'paystack')->first();
        if ($existing) {
            return ['payment' => $existing, 'alreadyExisted' => true];
        }

        $amount = ($verifiedData['amount'] ?? 0) / 100;

        $payment = DB::transaction(function () use ($booking, $reference, $amount, $verifiedData) {
            $remaining = (float) $booking->total_amount - (float) ($booking->advance_paid_amount ?? 0);
            $isFullyPaid = $amount >= ($remaining - 0.01);

            $payment = Payment::create([
                'customer_id' => $booking->customer_id,
                'booking_id' => $booking->id,
                'datetime' => isset($verifiedData['paid_at']) ? date('Y-m-d H:i:s', strtotime($verifiedData['paid_at'])) : now(),
                'discount' => 0,
                'total_amount' => $amount,
                'payment_type' => 'paystack',
                'txn_id' => $reference,
                'payment_status' => $isFullyPaid ? 'paid' : 'advanced_paid',
                'other_transaction_detail' => json_encode($verifiedData),
            ]);

            $booking->advance_paid_amount = (float) ($booking->advance_paid_amount ?? 0) + $amount;
            $booking->payment_id = $payment->id;
            if (!$isFullyPaid) {
                $booking->status = 'pending';
            }
            $booking->save();

            if ($booking->provider_id) {
                $wallet = Wallet::where('user_id', $booking->provider_id)->first();
                if (!$wallet) {
                    $provider = User::find($booking->provider_id);
                    $wallet = Wallet::create([
                        'title' => $provider->display_name ?? 'Provider',
                        'user_id' => $booking->provider_id,
                        'amount' => 0,
                    ]);
                }
                $wallet->amount = (float) $wallet->amount + $amount;
                $wallet->save();

                $this->sendNotification([
                    'activity_type' => 'wallet_top_up',
                    'wallet' => $wallet,
                    'top_up_amount' => $amount,
                    'transaction_id' => $reference,
                    'transaction_type' => 'credit',
                ]);
            }

            $this->sendNotification([
                'activity_type' => 'payment_message_status',
                'payment_status' => $payment->payment_status,
                'booking_id' => $booking->id,
                'booking' => $booking,
            ]);

            return $payment;
        });

        return ['payment' => $payment, 'alreadyExisted' => false];
    }
}
