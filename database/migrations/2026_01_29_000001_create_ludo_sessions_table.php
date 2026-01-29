<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the ludo_sessions table for online Ludo multiplayer games
     */
    public function up(): void
    {
        Schema::create('ludo_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code', 20)->unique();
            $table->enum('status', ['pending', 'waiting', 'playing', 'completed', 'cancelled', 'expired'])->default('pending');
            $table->enum('game_type', ['2_player', '4_player'])->default('2_player');
            
            // Player 1 (Red - Host)
            $table->unsignedBigInteger('player1_id')->nullable();
            $table->string('player1_name')->nullable();
            $table->string('player1_avatar')->nullable();
            $table->json('player1_pieces')->nullable(); // Array of 4 piece positions
            $table->unsignedTinyInteger('player1_finished_count')->default(0);
            
            // Player 2 (Green)
            $table->unsignedBigInteger('player2_id')->nullable();
            $table->string('player2_name')->nullable();
            $table->string('player2_avatar')->nullable();
            $table->json('player2_pieces')->nullable();
            $table->unsignedTinyInteger('player2_finished_count')->default(0);
            
            // Player 3 (Yellow) - For 4-player games
            $table->unsignedBigInteger('player3_id')->nullable();
            $table->string('player3_name')->nullable();
            $table->string('player3_avatar')->nullable();
            $table->json('player3_pieces')->nullable();
            $table->unsignedTinyInteger('player3_finished_count')->default(0);
            
            // Player 4 (Blue) - For 4-player games
            $table->unsignedBigInteger('player4_id')->nullable();
            $table->string('player4_name')->nullable();
            $table->string('player4_avatar')->nullable();
            $table->json('player4_pieces')->nullable();
            $table->unsignedTinyInteger('player4_finished_count')->default(0);
            
            // Turn tracking
            $table->unsignedTinyInteger('current_turn_player')->default(1); // 1, 2, 3, or 4
            $table->unsignedBigInteger('current_turn_user_id')->nullable();
            $table->unsignedTinyInteger('last_dice_roll')->default(0);
            $table->unsignedTinyInteger('consecutive_sixes')->default(0);
            $table->boolean('can_roll_again')->default(false);
            $table->boolean('must_move_piece')->default(false);
            
            // Last action tracking
            $table->string('last_action')->nullable();
            $table->unsignedTinyInteger('last_action_player')->nullable();
            $table->json('last_captured_piece')->nullable();
            
            // Game result
            $table->unsignedTinyInteger('winner_player')->nullable();
            $table->unsignedBigInteger('winner_user_id')->nullable();
            $table->json('rankings')->nullable(); // Final rankings for 4-player
            
            // Polling timestamps for abandonment detection
            $table->timestamp('player1_last_poll')->nullable();
            $table->timestamp('player2_last_poll')->nullable();
            $table->timestamp('player3_last_poll')->nullable();
            $table->timestamp('player4_last_poll')->nullable();
            
            // Timing
            $table->timestamp('turn_started_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('player1_id');
            $table->index('player2_id');
            $table->index('current_turn_user_id');
            
            // Note: Foreign keys removed to support cross-database scenarios
            // Player IDs reference users table but we don't enforce FK constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ludo_sessions');
    }
};
