<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_file_transfers', function (Blueprint $table) {
            // Higher number = higher priority. Calculated from views + downloads + likes.
            $table->unsignedBigInteger('priority')->default(0)->after('status')
                ->comment('Higher = dispatch sooner. views*3 + downloads*5 + likes*2');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::table('movie_file_transfers', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
        });
    }
};
