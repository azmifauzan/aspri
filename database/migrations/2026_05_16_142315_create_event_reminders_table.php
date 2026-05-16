<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('minutes_before')->default(30);
            // 'app'      — In-app notification only
            // 'telegram' — Send via Telegram bot
            // 'both'     — Both channels
            $table->string('channel')->default('app');
            $table->timestamp('scheduled_for'); // When to send
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['is_sent', 'scheduled_for']);
            $table->index(['user_id', 'is_sent']);
            $table->index('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminders');
    }
};
