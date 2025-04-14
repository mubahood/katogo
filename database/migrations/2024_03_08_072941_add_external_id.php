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
        Schema::table('learning_material_categories', function (Blueprint $table) {
            $table->text('external_id')->nullable();
            //$table->text('short_description')->nullable();
            //$table->text('description')->nullable();
            // $table->text('image')->nullable();
            // $table->text('color')->nullable();
            // $table->text('icon')->nullable();
            // $table->text('slug')->nullable();
            // $table->integer('order')->nullable();
            // $table->integer('status')->nullable();
            // $table->text('external_url')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_material_categories', function (Blueprint $table) {
        });
    }
};
