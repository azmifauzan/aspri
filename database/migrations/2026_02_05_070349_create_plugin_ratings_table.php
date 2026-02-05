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
        Schema::create('plugin_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plugin_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating'); // 1-5 stars
            $table->text('review')->nullable();
            $table->timestamps();

            // Ensure one rating per user per plugin
            $table->unique(['user_id', 'plugin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_ratings');
    }
};
