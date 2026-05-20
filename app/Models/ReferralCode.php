<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralCode extends Model
{
    use HasFactory;

    protected $table = 'referral_codes';

    protected $fillable = [
        'user_id', 'code', 'total_referred', 'total_earned', 'status'
    ];

    protected $casts = [
        'user_id'       => 'integer',
        'total_referred' => 'integer',
        'total_earned'  => 'double',
        'status'        => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
