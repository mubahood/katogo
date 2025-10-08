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
        Schema::table('movie_crawler_websites', function (Blueprint $table) {
            // Add foreign key column for current munowatch category being crawled

            if (!Schema::hasColumn('movie_crawler_websites', 'current_munowatch_category_id')) {
                $table->unsignedBigInteger('current_munowatch_category_id')->nullable()->after('slug');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_crawler_websites', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['current_munowatch_category_id']);

            // Drop the column
            $table->dropColumn('current_munowatch_category_id');
        });
    }
};
