<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatPriceOfferResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'provider_id' => $this->provider_id,
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'amount' => $this->amount,
            'note' => $this->note,
            'status' => $this->status,
            'previous_total_amount' => $this->previous_total_amount,
            'responded_at' => $this->responded_at,
            'created_at' => $this->created_at,
        ];
    }
}
