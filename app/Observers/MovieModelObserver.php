<?php

namespace App\Observers;

use App\Models\MovieModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MovieModelObserver — keeps `series_movies.total_episodes` accurate (P10-09 / P4-18).
 *
 * Replaces the COUNT(*) subquery that was fired from MovieModel::boot() `created` event.
 * On new episode creation we use a simple INCREMENT (O(1)) instead of a full re-count.
 * On deletion we decrement to keep the count consistent.
 *
 * The `updated` event re-count (in SeriesMovie::boot()) still guards against
 * category_id changes on update, so accuracy is maintained.
 */
class MovieModelObserver
{
    public function created(MovieModel $movie): void
    {
        if ($movie->type !== 'Series' || !$movie->category_id) {
            return;
        }

        try {
            DB::table('series_movies')
                ->where('id', $movie->category_id)
                ->increment('total_episodes');
        } catch (\Throwable $e) {
            Log::warning("[MovieModelObserver] Episode increment failed for series #{$movie->category_id}: " . $e->getMessage());
        }
    }

    public function deleted(MovieModel $movie): void
    {
        if ($movie->type !== 'Series' || !$movie->category_id) {
            return;
        }

        try {
            DB::table('series_movies')
                ->where('id', $movie->category_id)
                ->where('total_episodes', '>', 0)
                ->decrement('total_episodes');
        } catch (\Throwable $e) {
            Log::warning("[MovieModelObserver] Episode decrement failed for series #{$movie->category_id}: " . $e->getMessage());
        }
    }
}
