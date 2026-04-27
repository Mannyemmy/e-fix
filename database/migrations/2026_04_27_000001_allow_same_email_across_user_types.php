<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AllowSameEmailAcrossUserTypes extends Migration
{
    /**
     * Run the migrations.
     * Drops the global unique constraints on email and username,
     * replacing them with composite unique constraints scoped to user_type.
     * This allows the same person to register as both a customer (user)
     * and a provider using the same email address.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_username_unique');
            $table->unique(['email', 'user_type'], 'users_email_user_type_unique');
            $table->unique(['username', 'user_type'], 'users_username_user_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_user_type_unique');
            $table->dropUnique('users_username_user_type_unique');
            $table->unique('email', 'users_email_unique');
            $table->unique('username', 'users_username_unique');
        });
    }
}
