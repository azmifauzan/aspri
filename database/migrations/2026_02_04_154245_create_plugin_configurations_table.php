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
        Schema::create('plugin_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_plugin_id')->constrained('user_plugins')->cascadeOnDelete();
            $table->string('config_key', 100);
            $table->json('config_value');
            $table->timestamps();

            $table->unique(['user_plugin_id', 'config_key']);
            $table->index('user_plugin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_configurations');
    }
};
