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
        Schema::table('admin_users', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_users', 'game_coins_balance')) {
                $table->integer('game_coins_balance')->default(0)->unsigned();
            }
            if (!Schema::hasColumn('admin_users', 'total_games_played')) {
                $table->integer('total_games_played')->default(0)->unsigned();
            }
            if (!Schema::hasColumn('admin_users', 'total_games_won')) {
                $table->integer('total_games_won')->default(0)->unsigned();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['game_coins_balance', 'total_games_played', 'total_games_won']);
        });
    }
};
