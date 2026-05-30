<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('efix_qoreid_verifications', function (Blueprint $table) {
            $table->text('image_url')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('efix_qoreid_verifications', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
