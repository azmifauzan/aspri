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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Persona settings
            $table->string('call_preference')->nullable(); // "Kak", "Bapak", custom
            $table->string('aspri_name')->default('ASPRI');
            $table->text('aspri_persona')->nullable();

            // Preferences
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('locale')->default('id');
            $table->string('theme')->default('light'); // light, dark

            // Optional personalization
            $table->integer('birth_day')->nullable();
            $table->integer('birth_month')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
