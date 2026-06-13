<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movie_video_url_changes', function (Blueprint $table) {
            $table->id();

            // Movie reference
            $table->unsignedBigInteger('movie_model_id')->index();
            $table->string('movie_title', 500)->nullable();

            // URL transition
            $table->text('old_url')->nullable();
            $table->text('new_url');

            // Origin of this change
            $table->string('source', 50)->default('hetzner_transfer');
            // hetzner_transfer | manual | api_push | revert | series_sync

            // Overall status
            $table->string('status', 30)->default('pending');
            // pending | applying | applied | failed | reverted

            // Local DB update (on whichever server creates this record)
            $table->string('local_status', 30)->default('pending');
            // pending | applied | failed | skipped

            // Push to origin server (movies.mruodel.com)
            $table->string('remote_status', 30)->default('pending');
            // pending | pushing | applied | failed | skipped

            // Link back to the file transfer that triggered this
            $table->unsignedBigInteger('transfer_id')->nullable()->index();

            // Which remote endpoint was called
            $table->string('remote_endpoint', 500)->nullable();

            // Raw response from remote server
            $table->text('remote_response')->nullable();

            // Error info
            $table->text('error_message')->nullable();

            // Retry tracking
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);

            // Timestamps of key events
            $table->timestamp('local_applied_at')->nullable();
            $table->timestamp('remote_applied_at')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['status']);
            $table->index(['remote_status']);
            $table->index(['movie_model_id', 'status']);
            $table->index(['transfer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_video_url_changes');
    }
};
