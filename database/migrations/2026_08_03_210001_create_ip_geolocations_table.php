<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIpGeolocationsTable extends Migration
{
    public function up()
    {
        Schema::create('ip_geolocations', function (Blueprint $table) {
            $table->id();

            $table->string('ip_address', 45)->unique();

            $table->string('country')->nullable();
            $table->string('country_code', 5)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->nullable();

            $table->string('isp')->nullable();
            $table->string('org')->nullable();
            $table->string('as_name')->nullable();

            // The reason this table earns its keep for abuse triage: hosting=true
            // means a datacentre address, which almost never belongs to a real
            // customer signing up from a phone.
            $table->boolean('is_mobile')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_hosting')->default(false);

            // 'success' or 'failed'. Stored so a private/bogon/unroutable address is
            // not looked up again on every page render.
            $table->string('lookup_status', 20)->default('pending')->index();
            $table->timestamp('looked_up_at')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ip_geolocations');
    }
}
