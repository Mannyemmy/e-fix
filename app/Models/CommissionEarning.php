<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionEarning extends Model
{
    use HasFactory;

    protected $table = 'commission_earnings';

    protected $fillable = ['employee_id','booking_id','commissions','user_type', 'commission_amount','commission_status', 'payment_date'];

    protected $casts = [

        'employee_id' => 'integer',
        'booking_id' => 'integer',
        'commission_amount' => 'double',
    ];
}
