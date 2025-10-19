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
        Schema::create('video_transfers', function (Blueprint $table) {
            $table->id();
            
            // Source video information
            $table->string('source_url', 1000)->comment('Original video URL to transfer');
            $table->string('source_type', 50)->nullable()->comment('Source type: url, file, etc');
            $table->bigInteger('source_size')->nullable()->comment('Source video size in bytes');
            
            // Google Drive information
            $table->string('drive_file_id', 255)->nullable()->comment('Google Drive file ID');
            $table->string('drive_file_name', 500)->nullable()->comment('File name in Google Drive');
            $table->string('drive_public_url', 1000)->nullable()->comment('Public playable URL from Google Drive');
            $table->string('drive_download_url', 1000)->nullable()->comment('Direct download URL from Google Drive');
            $table->string('drive_folder_id', 255)->nullable()->comment('Google Drive folder ID');
            
            // Transfer status and progress
            $table->enum('status', [
                'pending',
                'downloading',
                'uploading',
                'completed',
                'failed',
                'cancelled'
            ])->default('pending')->comment('Current transfer status');
            
            $table->integer('progress')->default(0)->comment('Progress percentage (0-100)');
            $table->bigInteger('bytes_transferred')->default(0)->comment('Bytes transferred so far');
            $table->bigInteger('total_bytes')->default(0)->comment('Total bytes to transfer');
            
            // Timing information
            $table->timestamp('started_at')->nullable()->comment('When transfer started');
            $table->timestamp('completed_at')->nullable()->comment('When transfer completed');
            $table->integer('duration_seconds')->nullable()->comment('Total transfer duration in seconds');
            
            // Error handling
            $table->text('error_message')->nullable()->comment('Error message if failed');
            $table->text('error_details')->nullable()->comment('Detailed error information');
            $table->integer('retry_count')->default(0)->comment('Number of retry attempts');
            $table->timestamp('last_retry_at')->nullable()->comment('Last retry timestamp');
            
            // Metadata
            $table->string('video_title', 500)->nullable()->comment('Video title/name');
            $table->text('video_description')->nullable()->comment('Video description');
            $table->string('video_duration', 50)->nullable()->comment('Video duration (e.g., 02:15:30)');
            $table->string('video_quality', 50)->nullable()->comment('Video quality (e.g., 1080p, 720p)');
            $table->string('video_format', 50)->nullable()->comment('Video format (e.g., mp4, mkv)');
            
            // Additional information
            $table->json('transfer_metadata')->nullable()->comment('Additional metadata as JSON');
            $table->string('transferred_by', 100)->nullable()->comment('User who initiated transfer');
            $table->text('notes')->nullable()->comment('Admin notes');
            
            // Performance tracking
            $table->decimal('average_speed_mbps', 10, 2)->nullable()->comment('Average transfer speed in Mbps');
            $table->string('server_location', 100)->nullable()->comment('Server location used for transfer');
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index('status');
            $table->index('drive_file_id');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_transfers');
    }
};
