<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// One-time data fix: ChatPriceOfferController::accept() used to update
// bookings.amount/total_amount but not final_total_service_price/
// final_sub_total, so any booking whose price was set by accepting a chat
// price offer shows a stale/zero "Price x Qty" and "Subtotal" on the price
// detail screen even though "Total Amount" is correct. This backfills those
// already-accepted offers to match what the fixed accept() now does going
// forward.
return new class extends Migration
{
    public function up(): void
    {
        $offers = DB::table('chat_price_offers')->where('status', 'accepted')->get();

        foreach ($offers as $offer) {
            DB::table('bookings')
                ->where('id', $offer->booking_id)
                ->where('total_amount', $offer->amount)
                ->update([
                    'final_total_service_price' => $offer->amount,
                    'final_sub_total' => $offer->amount,
                    'final_total_tax' => 0,
                    'final_discount_amount' => 0,
                    'final_coupon_discount_amount' => 0,
                ]);
        }
    }

    public function down(): void
    {
        // Not reversible — the pre-fix values were stale/wrong, nothing
        // meaningful to restore them to.
    }
};
