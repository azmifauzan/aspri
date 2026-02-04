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
        Schema::create('plugin_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_plugin_id')->constrained('user_plugins')->cascadeOnDelete();
            $table->string('schedule_type', 50); // 'cron', 'interval', 'daily', 'weekly'
            $table->string('schedule_value'); // cron expression or interval value
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['next_run_at', 'is_active']);
            $table->index('user_plugin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_schedules');
    }
};
