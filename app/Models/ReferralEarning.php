<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralEarning extends Model
{
    use HasFactory;

    protected $table = 'referral_earnings';

    protected $fillable = [
        'referrer_id', 'referred_user_id', 'booking_id',
        'admin_commission', 'referral_percentage', 'earned_amount',
    ];

    protected $casts = [
        'referrer_id'       => 'integer',
        'referred_user_id'  => 'integer',
        'booking_id'        => 'integer',
        'admin_commission'  => 'double',
        'referral_percentage' => 'double',
        'earned_amount'     => 'double',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
