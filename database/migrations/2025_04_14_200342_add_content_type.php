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
        Schema::table('movie_models', function (Blueprint $table) {
            $table->string('content_type')->nullable();
            $table->string('content_is_video')->nullable()->default('No');
            $table->string('content_type_processed')->nullable()->default('No');
            $table->dateTime('content_type_processed_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_models', function (Blueprint $table) {
            //
        });
    }
};
