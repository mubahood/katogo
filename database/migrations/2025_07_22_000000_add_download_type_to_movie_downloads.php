<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_downloads', function (Blueprint $table) {
            $table->string('download_type', 20)->default('in_app')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('movie_downloads', function (Blueprint $table) {
            $table->dropColumn('download_type');
        });
    }
};
