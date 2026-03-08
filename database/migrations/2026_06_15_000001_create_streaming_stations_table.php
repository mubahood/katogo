<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streaming_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type'); // tv, radio
            $table->string('category')->default('General'); // Entertainment, Religious, Regional, News, Music, etc.
            $table->string('frequency')->nullable(); // e.g. "97.7 FM", "UHF 23"
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('country')->default('Uganda');
            $table->string('language')->default('English');
            $table->string('region')->nullable(); // Kampala, Jinja, Gulu, etc.
            $table->string('website_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('votes')->default(0); // popularity from radio-browser.info
            $table->unsignedInteger('listeners_count')->default(0);
            $table->string('status')->default('Active'); // Active, Inactive
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index('type');
            $table->index('category');
            $table->index('status');
            $table->index('is_featured');
            $table->index(['type', 'status']);
            $table->index('sort_order');
        });

        Schema::create('streaming_urls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('streaming_station_id');
            $table->text('url');
            $table->string('label')->nullable(); // "Main", "Backup", "HD", "SD"
            $table->string('format')->nullable(); // hls, mp3, aac, flv
            $table->string('quality')->nullable(); // HD, SD, Audio
            $table->unsignedInteger('bitrate')->nullable(); // kbps
            $table->string('cdn_provider')->nullable(); // Hyde Innovations, Zeno.fm, CloudRad.io, etc.
            $table->string('referrer_url')->nullable(); // required referrer header if any
            $table->boolean('is_default')->default(false);
            $table->boolean('needs_token_refresh')->default(false);
            $table->string('status')->default('Active'); // Active, Inactive, Intermittent
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('streaming_station_id')->references('id')->on('streaming_stations')->onDelete('cascade');
            $table->index('streaming_station_id');
            $table->index('status');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaming_urls');
        Schema::dropIfExists('streaming_stations');
    }
};
