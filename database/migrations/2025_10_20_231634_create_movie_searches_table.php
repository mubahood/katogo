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
        Schema::create('movie_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('search_term', 255);
            $table->string('search_term_normalized', 255)->index(); // Lowercase for comparison
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('platform', 50)->default('web'); // web, mobile, etc.
            $table->integer('search_count')->default(1); // How many times this exact search
            $table->integer('results_count')->default(0); // How many results returned
            $table->boolean('has_results')->default(false);
            $table->text('found_movie_ids')->nullable(); // JSON array of movie IDs found
            $table->integer('click_count')->default(0); // How many times user clicked a result
            $table->timestamp('first_searched_at')->useCurrent();
            $table->timestamp('last_searched_at')->useCurrent();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'last_searched_at']);
            $table->index(['search_term_normalized', 'last_searched_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_searches');
    }
};
