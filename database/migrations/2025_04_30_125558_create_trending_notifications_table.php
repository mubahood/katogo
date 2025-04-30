<?php

use App\Models\MovieModel;
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
        Schema::create('trending_notifications', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignIdFor(MovieModel::class, 'movie_model_id')->nullable();
            $table->text('title')->nullable();
            $table->string('type')->nullable();
            $table->text('image_url')->nullable();
            $table->text('description')->nullable();
            $table->integer('views_count')->nullable();
            $table->integer('views_time')->nullable();
            $table->text('url')->nullable();
            $table->dateTime('trending_time')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trending_notifications');
    }
};
