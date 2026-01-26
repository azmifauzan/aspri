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
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('account_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('tx_type'); // income, expense, transfer
            $table->decimal('amount', 18, 2);
            $table->timestamp('occurred_at');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('finance_accounts')
                ->nullOnDelete();

            $table->foreign('category_id')
                ->references('id')
                ->on('finance_categories')
                ->nullOnDelete();

            $table->index(['user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
    }
};
