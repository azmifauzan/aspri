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
        Schema::table('chat_threads', function (Blueprint $table) {
            $table->string('source', 20)->default('web')->after('title');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
