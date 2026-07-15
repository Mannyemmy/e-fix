<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\ChatPriceOffer;
use App\Http\Resources\API\ChatPriceOfferResource;
use App\Traits\NotificationTrait;
use Illuminate\Support\Facades\DB;

class ChatPriceOfferController extends Controller
{
    use NotificationTrait;

    // A completed job can still be unpaid (pay-on-completion / cash-on-delivery
    // flows), so "completed" must stay offerable — only truly dead bookings
    // (cancelled/rejected) are excluded.
    private $terminalStatuses = ['cancelled', 'rejected'];

    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $booking = Booking::find($request->booking_id);
        if (!$booking) {
            return comman_message_response(__('messages.booking_not_found'), 400);
        }

        $userId = auth()->id();
        if ($booking->provider_id === $userId) {
            $initiatedBy = 'provider';
        } elseif ($booking->customer_id === $userId) {
            $initiatedBy = 'customer';
        } else {
            abort(403, 'You are not a party to this booking.');
        }

        abort_if(in_array($booking->status, $this->terminalStatuses), 422, 'This booking is not open for a new price offer.');

        $offer = DB::transaction(function () use ($request, $booking, $initiatedBy) {
            ChatPriceOffer::where('booking_id', $booking->id)->pending()->update(['status' => 'superseded']);

            return ChatPriceOffer::create([
                'booking_id' => $booking->id,
                'provider_id' => $booking->provider_id,
                'customer_id' => $booking->customer_id,
                'service_id' => $booking->service_id,
                'initiated_by' => $initiatedBy,
                'amount' => $request->amount,
                'note' => $request->note,
                'status' => 'pending',
            ]);
        });

        $activity_data = [
            'activity_type' => $initiatedBy === 'provider' ? 'price_offer_sent' : 'price_offer_countered',
            'offer' => $offer,
            'booking' => $booking,
        ];
        $this->sendNotification($activity_data);

        return comman_custom_response(['offer' => new ChatPriceOfferResource($offer)]);
    }

    public function accept(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|integer',
        ]);

        $result = DB::transaction(function () use ($request) {
            $offer = ChatPriceOffer::where('id', $request->offer_id)->lockForUpdate()->first();

            if (!$offer) {
                return ['error' => [__('messages.record_not_found'), 400]];
            }

            // Whoever didn't propose the amount is the one who can accept it.
            $responderRole = $offer->initiated_by === 'provider' ? 'customer' : 'provider';
            $expectedResponderId = $responderRole === 'provider' ? $offer->provider_id : $offer->customer_id;
            if ($expectedResponderId !== auth()->id()) {
                return ['error' => ['You are not authorized to respond to this offer.', 403]];
            }
            if ($offer->status !== 'pending') {
                return ['error' => ['This offer is no longer available.', 422]];
            }

            $booking = Booking::where('id', $offer->booking_id)->lockForUpdate()->first();
            if (!$booking || in_array($booking->status, $this->terminalStatuses)) {
                return ['error' => ['This booking is no longer open for payment.', 422]];
            }

            $offer->previous_total_amount = $booking->total_amount;
            $offer->status = 'accepted';
            $offer->responded_at = now();
            $offer->save();

            $booking->amount = $offer->amount;
            $booking->total_amount = $offer->amount;
            $booking->save();

            return ['offer' => $offer, 'booking' => $booking, 'responder_role' => $responderRole];
        });

        if (isset($result['error'])) {
            return comman_message_response($result['error'][0], $result['error'][1]);
        }

        $activity_data = [
            'activity_type' => $result['responder_role'] === 'customer' ? 'price_offer_accepted_by_customer' : 'price_offer_accepted_by_provider',
            'offer' => $result['offer'],
            'booking' => $result['booking'],
        ];
        $this->sendNotification($activity_data);

        $response = ['message' => __('messages.price_offer_accepted_message', ['name' => auth()->user()->display_name])];
        if ($result['booking']->advance_paid_amount > 0) {
            $response['advance_already_paid'] = $result['booking']->advance_paid_amount;
        }

        return comman_custom_response($response);
    }

    public function decline(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|integer',
        ]);

        $result = DB::transaction(function () use ($request) {
            $offer = ChatPriceOffer::where('id', $request->offer_id)->lockForUpdate()->first();

            if (!$offer) {
                return ['error' => [__('messages.record_not_found'), 400]];
            }

            $responderRole = $offer->initiated_by === 'provider' ? 'customer' : 'provider';
            $expectedResponderId = $responderRole === 'provider' ? $offer->provider_id : $offer->customer_id;
            if ($expectedResponderId !== auth()->id()) {
                return ['error' => ['You are not authorized to respond to this offer.', 403]];
            }
            if ($offer->status !== 'pending') {
                return ['error' => ['This offer is no longer available.', 422]];
            }

            $offer->status = 'declined';
            $offer->responded_at = now();
            $offer->save();

            return ['offer' => $offer, 'responder_role' => $responderRole];
        });

        if (isset($result['error'])) {
            return comman_message_response($result['error'][0], $result['error'][1]);
        }

        $activity_data = [
            'activity_type' => $result['responder_role'] === 'customer' ? 'price_offer_declined_by_customer' : 'price_offer_declined_by_provider',
            'offer' => $result['offer'],
            'booking' => $result['offer']->booking,
        ];
        $this->sendNotification($activity_data);

        return comman_message_response(__('messages.price_offer_declined_message', ['name' => auth()->user()->display_name]));
    }
}
