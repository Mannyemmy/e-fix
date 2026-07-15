<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitiatedByToChatPriceOffersTable extends Migration
{
    public function up()
    {
        Schema::table('chat_price_offers', function (Blueprint $table) {
            // Which side proposed this amount — the *other* side is the one
            // allowed to accept/decline it. 'provider' covers all offers
            // created before this column existed.
            $table->string('initiated_by')->default('provider')->after('customer_id');
        });
    }

    public function down()
    {
        Schema::table('chat_price_offers', function (Blueprint $table) {
            $table->dropColumn('initiated_by');
        });
    }
}
