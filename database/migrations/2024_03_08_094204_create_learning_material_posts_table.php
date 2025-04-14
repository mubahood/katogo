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
        Schema::create('learning_material_posts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('category_id')->nullable();
            $table->text('type')->nullable();   
            
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_material_posts');
    }
};
