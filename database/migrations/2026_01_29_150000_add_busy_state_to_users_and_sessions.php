<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds busy state tracking for game sessions to prevent duplicate invitations
     * - is_busy_in_game: Boolean flag indicating if user is in an active game
     * - busy_since: Timestamp when user became busy (for auto-cleanup after 15 minutes)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Track if user is busy in a game
            $table->boolean('is_busy_in_game')->default(false)->after('game_coins_balance');
            $table->timestamp('busy_since')->nullable()->after('is_busy_in_game');
            
            // Index for fast querying
            $table->index('is_busy_in_game');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_busy_in_game']);
            $table->dropColumn(['is_busy_in_game', 'busy_since']);
        });
    }
};
