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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description')->nullable();
            $table->integer('duration_days'); // Berapa hari perpanjangan membership
            $table->integer('max_usages')->default(1); // Maksimal berapa kali bisa dipakai
            $table->integer('usage_count')->default(0); // Sudah berapa kali dipakai
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at'); // Kapan promo kadaluarsa
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['code', 'is_active']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
