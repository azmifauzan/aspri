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
        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('days_added'); // Berapa hari yang ditambahkan
            $table->timestamp('previous_ends_at')->nullable(); // Tanggal berakhir sebelum redeem
            $table->timestamp('new_ends_at')->nullable(); // Tanggal berakhir setelah redeem
            $table->timestamps();

            $table->unique(['promo_code_id', 'user_id']); // 1 user = 1x per promo code
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');
    }
};
