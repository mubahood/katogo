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
        //DROP TABLE IF EXISTS movie_pics;
        if (Schema::hasTable('movie_pics')) {
            Schema::dropIfExists('movie_pics');
        }
        Schema::create('movie_pics', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('movie_id')->nullable();
            $table->text('pic_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_pics');
    }
};
