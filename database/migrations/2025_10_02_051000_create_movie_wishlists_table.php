<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates movie_wishlists table without foreign key constraints
     * and makes all columns nullable except the primary key.
     */
    public function up(): void
    {
        Schema::create('movie_wishlists', function (Blueprint $table) {
            // Primary key with auto-increment
            $table->id();
            
            // Timestamps
            $table->timestamps();
            
            // User and movie references (no foreign key constraints, nullable)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('movie_model_id')->nullable();
            
            // Device tracking information (all nullable)
            $table->string('ip_address', 50)->nullable();
            $table->string('device', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('city', 50)->nullable();
            
            // Status field (nullable with default)
            $table->string('status', 50)->nullable()->default('Active');
            
            // Add indexes for better performance (without foreign key constraints)
            $table->index('user_id');
            $table->index('movie_model_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_wishlists');
    }
};
