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
        Schema::table('my_counters', function (Blueprint $table) {
            $table->string('status')->nullable()->default('SUCCESS');
            $table->text('status_message')->nullable();
            $table->longText('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('my_counters', function (Blueprint $table) {
            //
        });
    }
};
