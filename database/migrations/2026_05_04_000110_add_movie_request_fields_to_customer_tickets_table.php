<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_tickets', 'is_movie_request')) {
                $table->boolean('is_movie_request')->default(false)->after('customer_has_responded');
            }

            if (!Schema::hasColumn('customer_tickets', 'movie_request_payload')) {
                $table->longText('movie_request_payload')->nullable()->after('is_movie_request')
                    ->comment('JSON payload snapshot for movie request context on this ticket');
            }
        });

        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->index('is_movie_request');
        });
    }

    public function down(): void
    {
        Schema::table('customer_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('customer_tickets', 'is_movie_request')) {
                $table->dropColumn('is_movie_request');
            }
            if (Schema::hasColumn('customer_tickets', 'movie_request_payload')) {
                $table->dropColumn('movie_request_payload');
            }
        });

        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->dropIndex(['is_movie_request']);
        });
    }
};
