<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferredUser extends Model
{
    use HasFactory;

    protected $table = 'referred_users';

    protected $fillable = [
        'referrer_id', 'referred_user_id', 'referral_code', 'status', 'reward_amount', 'credited_at'
    ];

    protected $casts = [
        'referrer_id'     => 'integer',
        'referred_user_id' => 'integer',
        'reward_amount'   => 'double',
        'credited_at'     => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
