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
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->timestamp('player1_last_poll')->nullable()->after('player2_rounds_won');
            $table->timestamp('player2_last_poll')->nullable()->after('player1_last_poll');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['player1_last_poll', 'player2_last_poll']);
        });
    }
};
