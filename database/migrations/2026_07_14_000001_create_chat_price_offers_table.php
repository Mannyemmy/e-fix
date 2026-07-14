<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatPriceOffersTable extends Migration
{
    public function up()
    {
        Schema::create('chat_price_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->double('amount');
            $table->string('note')->nullable();
            $table->string('status')->default('pending')->comment('pending, accepted, declined, superseded');
            $table->double('previous_total_amount')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['booking_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_price_offers');
    }
}
