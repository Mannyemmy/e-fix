<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model
{
    use HasFactory;

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    protected $table = 'withdraw_requests';

    protected $fillable = [
        'user_id', 'amount', 'bank_name', 'account_number', 'account_name', 'status', 'admin_note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount'  => 'double',
        'status'  => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getStatusLabelAttribute()
    {
        switch ((int) $this->status) {
            case self::STATUS_APPROVED:
                return 'Approved';
            case self::STATUS_REJECTED:
                return 'Rejected';
            default:
                return 'Pending';
        }
    }
}
