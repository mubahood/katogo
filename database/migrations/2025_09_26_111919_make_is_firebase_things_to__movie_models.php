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
        Schema::table('movie_models', function (Blueprint $table) {
            // Indicates if the current video URL has been tested using a curl command
            $table->string('video_url_tested_by_curl')->default('No')->nullable();
            // Result of curl test on the current video URL
            $table->string('video_url_tested_by_curl_works')->default('No')->nullable();
            // Indicates if the current video URL has been tested by a human
            $table->string('video_url_tested_by_human')->default('No')->nullable();
            // Result of human test on the current video URL
            $table->string('video_url_tested_by_human_works')->default('No')->nullable();
            // Indicates if a Firebase transfer has been attempted
            $table->string('firebase_transfer_attempted')->default('No')->nullable();
            // Indicates if a Firebase transfer is currently in progress
            $table->string('firebase_transfer_transfer_in_progress')->default('No')->nullable();
            // Indicates if the Firebase transfer was successful
            $table->string('firebase_transfer_successful')->default('No')->nullable();
            // Reason for Firebase transfer failure, if any
            $table->text('firebase_transfer_failure_reason')->nullable();
            // Path of the transferred file in Firebase
            $table->text('firebase_transfer_path')->nullable();
            // URL of the video stored in Firebase
            $table->text('firebase_video_url')->nullable();
            // Expiry date and time for the Firebase video URL
            $table->dateTime('firebase_video_url_expires_at')->nullable();
            // Indicates if the Firebase video URL has been tested using a curl command
            $table->string('firebase_video_tested_by_curl')->default('No')->nullable();
            // Result of curl test on the Firebase video URL
            $table->string('firebase_video_tested_by_curl_works')->default('No')->nullable();
            // Indicates if the Firebase video URL has been tested by a human
            $table->string('firebase_video_tested_by_human')->default('No')->nullable();
            // Result of human test on the Firebase video URL
            $table->string('firebase_video_tested_by_human_works')->default('No')->nullable();
            // Stores the previous video URL before Firebase transfer
            $table->string('old_video_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movie_models', function (Blueprint $table) {
            //
        });
    }
};
