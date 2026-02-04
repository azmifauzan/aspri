<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->string('action_type'); // create_transaction, create_schedule, create_note, etc.
            $table->string('module'); // finance, schedule, notes
            $table->json('payload'); // Data to be saved
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, expired
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['thread_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_actions');
    }
};
