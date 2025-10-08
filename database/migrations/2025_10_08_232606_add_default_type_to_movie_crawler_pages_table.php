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
        Schema::table('movie_crawler_pages', function (Blueprint $table) {
            // Add default value for type field to fix NOT NULL constraint
            $table->string('type')->default('movie')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_crawler_pages', function (Blueprint $table) {
            // Remove default value
            $table->string('type')->nullable(false)->change();
        });
    }
};
