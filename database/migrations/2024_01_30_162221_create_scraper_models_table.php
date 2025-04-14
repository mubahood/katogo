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
        Schema::create('scraper_models', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('type')->nullable();
            $table->text('url')->nullable();
            $table->text('title')->nullable();
            $table->longText('datae')->nullable();
            $table->string('status')->nullable();
            $table->string('error')->nullable();
            $table->string('error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraper_models');
    }
};
