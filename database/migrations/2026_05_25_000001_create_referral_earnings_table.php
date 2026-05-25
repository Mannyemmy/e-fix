<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferralEarningsTable extends Migration
{
    public function up()
    {
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_user_id');
            $table->unsignedBigInteger('booking_id');
            $table->decimal('admin_commission', 10, 2)->default(0);
            $table->decimal('referral_percentage', 5, 2)->default(0);
            $table->decimal('earned_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('referral_earnings');
    }
}
