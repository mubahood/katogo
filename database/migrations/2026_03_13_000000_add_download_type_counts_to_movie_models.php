<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_models', function (Blueprint $table) {
            $table->unsignedInteger('in_app_downloads_count')->default(0)->after('downloads_count');
            $table->unsignedInteger('gallery_downloads_count')->default(0)->after('in_app_downloads_count');
        });
    }

    public function down(): void
    {
        Schema::table('movie_models', function (Blueprint $table) {
            $table->dropColumn(['in_app_downloads_count', 'gallery_downloads_count']);
        });
    }
};
