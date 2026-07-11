<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\PaystackReconciler;
use Illuminate\Console\Command;

/**
 * One-off / recovery tool: a customer's card was charged by Paystack but
 * the app never confirmed it back to us (dropped connection, backgrounded
 * app, etc.), so no Payment row, no booking credit, no provider payout.
 * This looks the reference up on Paystack directly and, if it really was
 * paid, records it exactly as a normal successful payment would have.
 *
 * Usage: php artisan payment:reconcile-paystack {booking_id} {reference}
 */
class ReconcilePaystackPayment extends Command
{
    protected $signature = 'payment:reconcile-paystack {booking_id} {reference} {--force : proceed even if the Paystack customer email does not match the booking\'s customer}';

    protected $description = 'Verify a Paystack reference against Paystack itself and record it against a booking (payment, booking balance, provider wallet, notifications).';

    public function handle(): int
    {
        $bookingId = $this->argument('booking_id');
        $reference = $this->argument('reference');

        $booking = Booking::find($bookingId);
        if (!$booking) {
            $this->error("Booking #$bookingId not found.");
            return self::FAILURE;
        }

        $this->info("Verifying $reference with Paystack...");
        $data = PaystackReconciler::verify($reference);
        if (!$data) {
            $this->error("Paystack did not confirm $reference as a successful charge. See laravel.log for details.");
            return self::FAILURE;
        }

        $paystackEmail = strtolower(trim($data['customer']['email'] ?? ''));
        $bookingEmail = strtolower(trim($booking->customer->email ?? ''));
        if ($paystackEmail !== '' && $bookingEmail !== '' && $paystackEmail !== $bookingEmail && !$this->option('force')) {
            $this->error("Paystack customer email ($paystackEmail) does not match booking #$bookingId's customer ($bookingEmail).");
            $this->error('Re-run with --force if you are sure this is still the right booking.');
            return self::FAILURE;
        }

        $amount = ($data['amount'] ?? 0) / 100;
        $this->info("Confirmed: {$data['currency']} $amount paid by {$paystackEmail} at {$data['paid_at']}.");

        $result = (new PaystackReconciler())->reconcile($booking, $data);

        if ($result['alreadyExisted']) {
            $this->warn("Reference $reference was already reconciled as Payment #{$result['payment']->id} — nothing more to do.");
            return self::SUCCESS;
        }

        $booking->refresh();
        $wallet = \App\Models\Wallet::where('user_id', $booking->provider_id)->first();

        $this->info("Created Payment #{$result['payment']->id}.");
        $this->info("Booking #{$booking->id}: advance_paid_amount is now {$booking->advance_paid_amount} of {$booking->total_amount}, status={$booking->status}.");
        if ($wallet) {
            $this->info("Provider (user #{$booking->provider_id}) wallet balance is now {$wallet->amount}.");
        }
        $this->info('Notifications dispatched to customer and provider.');

        return self::SUCCESS;
    }
}
