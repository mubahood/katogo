<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkers_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code', 10)->unique();
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'expired'])
                  ->default('pending');

            // Players
            $table->unsignedBigInteger('player1_id');
            $table->string('player1_name')->default('');
            $table->unsignedBigInteger('player2_id')->nullable();
            $table->string('player2_name')->default('');

            // Board state — 32-element JSON array (null or {color, isKing})
            $table->json('board_state')->nullable();
            $table->enum('current_turn', ['red', 'black'])->default('red');
            $table->unsignedBigInteger('current_turn_user_id')->nullable();

            // Last move info (for UI highlighting)
            $table->integer('last_move_from')->nullable();
            $table->integer('last_move_to')->nullable();
            $table->json('last_captured')->nullable();
            $table->boolean('last_crowned')->default(false);

            // Result
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->string('winner_name')->nullable();
            $table->integer('move_count')->default(0);

            // Abandonment detection
            $table->timestamp('player1_last_poll')->nullable();
            $table->timestamp('player2_last_poll')->nullable();

            // Chat
            $table->unsignedBigInteger('chat_head_id')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('player1_id');
            $table->index('player2_id');
            $table->index('current_turn_user_id');
        });

        // Chat messages table for game sessions
        Schema::create('checkers_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('user_id');
            $table->string('user_name');
            $table->text('message');
            $table->timestamps();

            $table->index('session_id');
            $table->foreign('session_id')
                  ->references('id')
                  ->on('checkers_sessions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkers_chat_messages');
        Schema::dropIfExists('checkers_sessions');
    }
};
