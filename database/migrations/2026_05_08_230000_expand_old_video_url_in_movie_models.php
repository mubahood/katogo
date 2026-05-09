<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Root fix: old signed URLs can exceed 255 chars.
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN old_video_url TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movie_models MODIFY COLUMN old_video_url VARCHAR(255) NULL');
    }
};
