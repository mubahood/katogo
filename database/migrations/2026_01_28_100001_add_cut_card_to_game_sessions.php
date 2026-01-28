<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add cut_card column to store the card that determines the cutter suit
     */
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->text('cut_card')->nullable()->after('draw_pile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn('cut_card');
        });
    }
};
