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
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 20);
            $table->string('author')->nullable();
            $table->string('icon')->nullable();
            $table->string('class_name');
            $table->boolean('is_system')->default(false);
            $table->json('config_schema')->nullable();
            $table->json('default_config')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('is_system');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
