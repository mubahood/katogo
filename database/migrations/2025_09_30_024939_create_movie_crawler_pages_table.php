<?php

use App\Models\MovieCrawlerWebsite;
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
        Schema::create('movie_crawler_pages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(MovieCrawlerWebsite::class);
            $table->text('title')->nullable();
            $table->text('slug')->nullable();
            $table->text('url')->nullable();
            $table->text('movie_id')->nullable();
            $table->longText('page_content')->nullable();
            $table->longText('error_message')->nullable();
            $table->string('status')->nullable()->default('Pending');
            $table->dateTime('last_fetched_at')->nullable();
            $table->string('type');
            $table->string('row_id')->nullable();
            $table->text('img_port_muno_file_name')->nullable();
            $table->text('bunny_file_name')->nullable();
            $table->text('tmdb_poster_path')->nullable();
            $table->text('vj')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_crawler_pages');
    }
};
