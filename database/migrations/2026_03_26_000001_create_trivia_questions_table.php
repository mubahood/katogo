<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the trivia_questions table for Movie Trivia game module.
     */
    public function up(): void
    {
        Schema::create('trivia_questions', function (Blueprint $table) {
            $table->id();

            // Question content
            $table->text('question');
            $table->string('difficulty', 20); // easy, medium, hard, expert, legendary
            $table->string('category', 50);   // plot, characters, actors, dates, behind_scenes, quotes, soundtrack, awards, directors, general

            // Answer format: "multiple_choice" | "true_false" | "image_guess"
            $table->string('format', 30)->default('multiple_choice');

            // Correct answer (always stored here)
            $table->text('correct_answer');

            // Wrong options stored as JSON array (usually 3 items for 4-choice)
            $table->json('wrong_answers');

            // Optional hint (shown after timer runs out or on "use hint")
            $table->text('hint')->nullable();

            // Optional image URL (for image_guess format or visual questions)
            $table->text('image_url')->nullable();

            // Points per difficulty: easy=10, medium=20, hard=30, expert=50, legendary=100
            $table->integer('points')->default(10);

            // Timer in seconds per difficulty: easy=15, medium=12, hard=10, expert=8, legendary=6
            $table->integer('timer_seconds')->default(15);

            // Versioning for sync
            $table->integer('version')->default(1);

            // Status: active, inactive, draft
            $table->string('status', 20)->default('active');

            $table->timestamps();

            // Indexes for efficient querying
            $table->index('difficulty');
            $table->index('category');
            $table->index('format');
            $table->index('status');
            $table->index('version');
        });

        // Version tracking table — clients check this to know if they need to sync
        Schema::create('trivia_meta', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();     // e.g. 'questions_version', 'total_count'
            $table->text('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trivia_meta');
        Schema::dropIfExists('trivia_questions');
    }
};
