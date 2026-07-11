<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaystackReconciler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paystack calls this directly from its own servers whenever a charge
 * succeeds, independent of whatever happens to the customer's phone
 * afterwards (dropped connection, app killed, etc.). This is the safety
 * net behind the app's own save-payment call: even if the app never
 * confirms a successful charge back to us, Paystack still will.
 *
 * Requires the checkout to have been started with metadata containing
 * purpose=booking_payment and booking_id (see paystack_service.dart) —
 * events without that metadata (e.g. wallet top-ups) are ignored here.
 *
 * Set this URL in the Paystack dashboard: POST /api/paystack/webhook
 */
class PaystackController extends Controller
{
    public function webhook(Request $request)
    {
        $secret = PaystackReconciler::liveSecretKey();
        $signature = $request->header('x-paystack-signature');

        if (!$secret || !$signature || !hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) {
            Log::warning('PaystackController::webhook: invalid or missing signature');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();

        if (($payload['event'] ?? null) !== 'charge.success') {
            return response()->json(['status' => 'ignored']);
        }

        $data = $payload['data'] ?? [];
        $metadata = $data['metadata'] ?? [];

        if (($metadata['purpose'] ?? null) !== 'booking_payment' || empty($metadata['booking_id'])) {
            return response()->json(['status' => 'ignored']);
        }

        $booking = Booking::find($metadata['booking_id']);
        if (!$booking) {
            Log::error("PaystackController::webhook: booking #{$metadata['booking_id']} not found for reference {$data['reference']}");
            return response()->json(['status' => 'booking not found'], 200);
        }

        try {
            $verified = PaystackReconciler::verify($data['reference']);
            if (!$verified) {
                Log::error("PaystackController::webhook: could not independently verify {$data['reference']}");
                return response()->json(['status' => 'verify failed'], 200);
            }

            (new PaystackReconciler())->reconcile($booking, $verified);
        } catch (\Throwable $e) {
            Log::error('PaystackController::webhook: ' . $e->getMessage());
        }

        return response()->json(['status' => 'ok']);
    }
}
