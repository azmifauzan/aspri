<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('finance_categories')->cascadeOnDelete();
            // null category_id = budget umum (semua expense)
            $table->integer('period_year');
            $table->integer('period_month'); // 1-12
            $table->decimal('amount', 15, 2);
            $table->integer('alert_threshold_pct')->default(80); // alert ketika usage >= threshold
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'period_year', 'period_month'], 'finance_budgets_unique_period');
            $table->index(['user_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_budgets');
    }
};
