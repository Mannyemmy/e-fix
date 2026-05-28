<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'safehaven_account_number')) {
                $table->string('safehaven_account_number', 32)
                    ->nullable()
                    ->after('contact_number');
                $table->index('safehaven_account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'safehaven_account_number')) {
                $table->dropIndex(['safehaven_account_number']);
                $table->dropColumn('safehaven_account_number');
            }
        });
    }
};
