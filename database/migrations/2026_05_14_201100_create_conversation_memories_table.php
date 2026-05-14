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
        Schema::create('conversation_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('memory_type');
            // 'fact'       — Fakta tentang user (preferensi, kebiasaan, data penting)
            // 'event'      — Kejadian/keputusan signifikan dari percakapan lalu
            // 'summary'    — Ringkasan percakapan (hasil compaction)
            // 'preference' — Preferensi yang user nyatakan secara eksplisit
            // 'pattern'    — Pola perilaku user
            $table->text('content');           // Isi memory dalam natural language
            $table->string('source_thread_id')->nullable(); // Thread asal memory
            $table->integer('importance')->default(3); // 1 (low) - 5 (high)
            $table->integer('access_count')->default(0); // Berapa kali memory ini diakses
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('valid_until')->nullable(); // Null = permanent
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Module terkait, tags, dsb.
            $table->timestamps();

            $table->index(['user_id', 'is_active', 'importance']);
            $table->index(['user_id', 'memory_type']);
            $table->index(['user_id', 'last_accessed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_memories');
    }
};
