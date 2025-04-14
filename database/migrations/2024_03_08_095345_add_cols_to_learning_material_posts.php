<?php

use App\Models\LearningMaterialCategory;
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
        Schema::table('learning_material_posts', function (Blueprint $table) {
            $table->foreignIdFor(LearningMaterialCategory::class)->nullable();
            $table->text('title')->nullable();
            $table->text('external_id')->nullable();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('image')->nullable();
            $table->text('slug')->nullable();
            $table->text('external_url')->nullable();
            $table->text('external_download_url')->nullable();
            $table->text('download_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_material_posts', function (Blueprint $table) {
            //
        });
    }
};
