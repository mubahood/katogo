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
        Schema::table('movie_models', function (Blueprint $table) {
            $table->string('is_trending')->default('No')->nullable();
            $table->dateTime('trending_time')->nullable();
            $table->integer('trending_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_models', function (Blueprint $table) {
            //
        });
    }
};
