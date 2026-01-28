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
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            
            // Players
            $table->unsignedBigInteger('player1_id');
            $table->unsignedBigInteger('player2_id');
            
            // Game state stored as JSON
            $table->text('player1_hand')->nullable(); // JSON array of cards
            $table->text('player2_hand')->nullable(); // JSON array of cards
            $table->text('discard_pile')->nullable(); // JSON array of cards
            $table->text('draw_pile')->nullable(); // JSON array of remaining deck
            
            // Game progress
            $table->unsignedBigInteger('current_turn_user_id')->nullable();
            $table->string('current_suit', 20)->nullable(); // Current suit to match (after Ace)
            $table->integer('draw_stack')->default(0); // Stacked 2s penalty
            
            // Scoring
            $table->integer('player1_score')->default(0);
            $table->integer('player2_score')->default(0);
            $table->integer('player1_rounds_won')->default(0);
            $table->integer('player2_rounds_won')->default(0);
            $table->integer('current_round')->default(1);
            $table->integer('target_score')->default(100);
            
            // Game status: waiting, active, completed, abandoned
            $table->string('status', 20)->default('waiting');
            $table->unsignedBigInteger('winner_id')->nullable();
            
            // Chat integration
            $table->unsignedBigInteger('chat_head_id')->nullable();
            
            // Timestamps
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('player1_id');
            $table->index('player2_id');
            $table->index('status');
            $table->index('current_turn_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
