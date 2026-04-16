<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('firestore_doc_id')->nullable()->index();
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            $table->text('message')->nullable();
            $table->string('message_type')->default('TEXT');
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->string('flag_reason')->nullable();
            $table->unsignedBigInteger('flagged_by')->nullable();
            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('flagged_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['sender_id', 'receiver_id']);
            $table->index('is_flagged');
            $table->index('is_blocked');
        });

        Schema::create('blocked_chat_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('pattern');
            $table->string('pattern_type')->default('keyword');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_regex')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('blocked_chat_patterns');
        Schema::dropIfExists('chat_messages');
    }
}
