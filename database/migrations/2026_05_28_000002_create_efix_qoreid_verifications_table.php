<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eFix QoreID liveness verifications.
 *
 * The lifecycle:
 *   1. User taps "Verify Identity" → mobile opens the QoreID webview.
 *   2. We seed a row here keyed by `customer_reference` (a hash of
 *      user_id + nonce), status='pending'. The mobile bridge sends a
 *      hint message but does not write here directly.
 *   3. QoreID fires its webhook to /api/qoreid/webhook with the result.
 *      We upsert by `customer_reference`.
 *   4. The mobile app polls /api/qoreid/status until status='verified'
 *      or 'failed', then proceeds with bank-account creation only on
 *      'verified'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efix_qoreid_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('customer_reference')->unique();
            $table->string('product_code'); // liveness_bvn | liveness_nin
            $table->string('id_type'); // BVN | NIN
            $table->string('id_number')->nullable();
            $table->string('status')->default('pending'); // pending | verified | failed
            $table->string('verification_id')->nullable()->index(); // QoreID's own id
            $table->string('full_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->decimal('liveness_score', 5, 2)->nullable();
            $table->decimal('face_match_score', 5, 2)->nullable();
            $table->json('raw_webhook')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efix_qoreid_verifications');
    }
};
