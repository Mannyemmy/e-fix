<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWithdrawRequestsTable extends Migration
{
    /**
     * Customer wallet withdrawal requests. The wallet balance is debited
     * immediately when the request is created (status Pending) and is
     * refunded automatically if the request is later Rejected by admin.
     */
    public function up()
    {
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->double('amount');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_name');
            $table->tinyInteger('status')->default(0)->comment('0-Pending,1-Approved,2-Rejected');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('withdraw_requests');
    }
}
