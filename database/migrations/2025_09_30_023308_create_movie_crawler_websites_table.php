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
        Schema::create('movie_crawler_websites', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->text('name')->nullable();
            $table->text('url')->nullable();
            $table->text('about')->nullable();
            $table->integer('priority')->nullable()->default(1);
            $table->dateTime('last_fetched_at')->nullable();
            $table->integer('page_number')->nullable();
            $table->integer('total_movies_found')->nullable();
            $table->integer('new_movies_found')->nullable();
            $table->string('status')->nullable()->default('Active');
            $table->string('fetch_status')->nullable();
            $table->text('failed_message')->nullable();
            $table->text('response_data')->nullable();
            $table->string('slug')->nullable();
            $table->string('last_page_url')->nullable();
            $table->string('max_page')->nullable();
            $table->text('error_message')->nullable();
            $table->text('email')->nullable();
            $table->text('password')->nullable();
            $table->text('token')->nullable();
            $table->text('token_expiry')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_crawler_websites');
    }
};
