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
        Schema::create('game_invitations', function (Blueprint $table) {
            $table->id();
            
            // Sender and receiver
            $table->unsignedBigInteger('sender_id');
            $table->unsignedBigInteger('receiver_id');
            
            // Game type (for future games)
            $table->string('game_type', 50)->default('matatu');
            
            // Status: pending, accepted, declined, expired, cancelled
            $table->string('status', 20)->default('pending');
            
            // Optional message from sender
            $table->string('message', 255)->nullable();
            
            // Expiration (60 seconds from creation)
            $table->timestamp('expires_at');
            
            // Link to game session if accepted
            $table->unsignedBigInteger('game_session_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('sender_id');
            $table->index('receiver_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_invitations');
    }
};
