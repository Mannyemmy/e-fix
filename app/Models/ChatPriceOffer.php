<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatPriceOffer extends Model
{
    use HasFactory;

    protected $table = 'chat_price_offers';

    protected $fillable = [
        'booking_id',
        'provider_id',
        'customer_id',
        'service_id',
        'amount',
        'note',
        'status',
        'previous_total_amount',
        'responded_at',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'provider_id' => 'integer',
        'customer_id' => 'integer',
        'service_id' => 'integer',
        'amount' => 'double',
        'previous_total_amount' => 'double',
        'responded_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
