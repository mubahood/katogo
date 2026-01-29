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
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('amount'); // Positive for credit, negative for debit
            $table->integer('balance_after'); // Balance after this transaction
            $table->string('type'); // game_win_online, game_win_offline, game_forfeit, purchase, reward, admin_adjustment
            $table->string('description')->nullable();
            $table->unsignedBigInteger('game_session_id')->nullable(); // Link to game if applicable
            $table->unsignedBigInteger('related_user_id')->nullable(); // Opponent in game
            $table->json('metadata')->nullable(); // Additional data (game details, etc.)
            $table->timestamps();
            
            // Indexes
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('game_session_id')->references('id')->on('game_sessions')->onDelete('set null');
            $table->foreign('related_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'created_at']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');
    }
};
