<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('movie_model_id')->nullable();
            $table->enum('status', ['active', 'removed'])->default('active');
            $table->timestamp('added_at')->nullable();
            $table->timestamps();

            // Ensure unique user-movie combination
            // $table->unique(['user_id', 'movie_model_id']);
            
            // Index for faster queries
            // $table->index(['user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('watchlists');
    }
};