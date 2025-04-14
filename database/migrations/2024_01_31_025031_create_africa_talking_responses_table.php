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
        Schema::create('africa_talking_responses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('sessionId')->nullable();
            $table->string('status')->nullable();
            $table->text('phoneNumber')->nullable();
            $table->text('errorMessage')->nullable();
            $table->text('post')->nullable();
            $table->text('get')->nullable();
            $table->text('recording_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('africa_talking_responses');
    }
};
