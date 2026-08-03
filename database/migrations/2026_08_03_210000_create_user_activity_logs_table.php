<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserActivityLogsTable extends Migration
{
    public function up()
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();

            // Nullable on purpose: a failed login has no user, and we still want the
            // attempt recorded. Deliberately no foreign key either - when bot accounts
            // are purged the forensic trail must survive them.
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('event', 40)->index();
            $table->string('email')->nullable()->index();
            $table->string('user_type', 30)->nullable();

            // 45 chars covers IPv6.
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();

            // 'web' or 'app', derived from the request rather than trusted from it.
            $table->string('source', 20)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_activity_logs');
    }
}
