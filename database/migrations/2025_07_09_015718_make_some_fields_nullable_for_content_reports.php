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
        Schema::table('content_reports', function (Blueprint $table) {
            $table->string('reported_content_type')->nullable()->change();
            $table->unsignedBigInteger('reported_content_id')->nullable()->change();
            $table->string('report_type')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->string('status')->nullable()->change(); 
            $table->string('moderation_action')->nullable()->change();
            $table->text('moderation_notes')->nullable()->change();
            $table->string('priority')->nullable()->change(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_reports', function (Blueprint $table) {
            //
        });
    }
};
