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
        Schema::create('series_movies', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('title')->nullable();
            $table->text('Category')->nullable();
            $table->text('description')->nullable();
            $table->text('thumbnail')->nullable();
            $table->integer('total_seasons')->nullable();
            $table->integer('total_episodes')->nullable();
            $table->integer('total_views')->nullable();
            $table->integer('total_rating')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series_movies');
    }
};
