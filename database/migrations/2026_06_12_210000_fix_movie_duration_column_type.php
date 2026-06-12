<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_file_transfers', function (Blueprint $table) {
            // duration stored as formatted string e.g. "01h 32m", "95 min"
            $table->string('movie_duration', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('movie_file_transfers', function (Blueprint $table) {
            $table->unsignedInteger('movie_duration')->nullable()->change();
        });
    }
};
