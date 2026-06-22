<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movie_crawler_websites', function (Blueprint $table) {
            $table->text('session_id')->nullable()->after('token_expiry');
            $table->unsignedBigInteger('munowatch_user_id')->nullable()->after('session_id');
            $table->timestamp('session_refreshed_at')->nullable()->after('munowatch_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('movie_crawler_websites', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'munowatch_user_id', 'session_refreshed_at']);
        });
    }
};
