<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('game_type', 20); // matatu, chess, ludo, draft, trivia
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('high_score')->default(0);
            $table->unsignedInteger('total_play_seconds')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_type']);
            $table->index('user_id');
            $table->index('game_type');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_stats');
    }
};
