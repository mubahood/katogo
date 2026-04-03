<?php

namespace App\Observers;

use App\Models\MovieDownload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MovieDownloadObserver — keeps `movie_models.downloads_count` accurate (P10-08).
 *
 * Increments the denormalized downloads_count on the linked movie whenever
 * a new MovieDownload record is created.  This avoids a full COUNT(*) query for
 * every display and keeps the counter in near-real-time sync.
 *
 * The weekly sync-movie-counts scheduler job acts as a correction pass in case
 * any increments are missed.
 */
class MovieDownloadObserver
{
    public function created(MovieDownload $download): void
    {
        if (!$download->movie_model_id) {
            return;
        }

        try {
            DB::table('movie_models')
                ->where('id', $download->movie_model_id)
                ->increment('downloads_count');
        } catch (\Throwable $e) {
            Log::warning("[MovieDownloadObserver] Increment failed for movie #{$download->movie_model_id}: " . $e->getMessage());
        }
    }
}
