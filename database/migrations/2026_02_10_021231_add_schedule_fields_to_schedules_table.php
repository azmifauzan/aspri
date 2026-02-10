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
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false)->after('location');
            $table->boolean('is_recurring')->default(false)->after('is_completed');
            $table->string('recurrence_rule')->nullable()->after('is_recurring');
            $table->boolean('is_all_day')->default(false)->after('recurrence_rule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['is_completed', 'is_recurring', 'recurrence_rule', 'is_all_day']);
        });
    }
};
