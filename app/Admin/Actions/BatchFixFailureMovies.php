<?php

namespace App\Admin\Actions;

use App\Services\MovieFixerService;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Batch Fix Movies from Failures — re-fetches movie data from the original source
 * for the movies referenced by selected failure records.
 *
 * Deduplicates movie IDs (multiple failures may reference the same movie).
 * Max 200 unique movies per batch to avoid timeouts.
 * Updates video_playback_failures for each movie individually (no deadlocks).
 */
class BatchFixFailureMovies extends BatchAction
{
    public $name = 'Fix Selected Movies';

    /**
     * Process the batch fix.
     *
     * Collects unique movie_ids from selected failures, fixes each movie
     * one-by-one. The MovieFixerService handles updating all related
     * failure records for each movie internally.
     */
    public function handle(Collection $collection, Request $request)
    {
        set_time_limit(1800);
        ini_set('memory_limit', '512M');

        // Collect unique movie IDs from the selected failures
        $movieIds = $collection
            ->pluck('movie_id')
            ->filter()             // remove nulls
            ->unique()
            ->values()
            ->toArray();

        if (empty($movieIds)) {
            return $this->response()->error('None of the selected failures have a linked movie ID.')->refresh();
        }

        $maxMovies = 200;
        if (count($movieIds) > $maxMovies) {
            return $this->response()
                ->error("Too many unique movies (" . count($movieIds) . "). Please select failures for at most {$maxMovies} unique movies at a time.")
                ->refresh();
        }

        $totalFailures = $collection->count();
        $totalMovies   = count($movieIds);
        Log::info("[BatchFixFailureMovies] Starting fix for {$totalMovies} unique movies (from {$totalFailures} selected failures).");

        $fixer  = new MovieFixerService();
        $fixed  = 0;
        $failed = 0;
        $errors = [];

        foreach ($movieIds as $movieId) {
            try {
                $result = $fixer->fix((int) $movieId);

                if ($result['success']) {
                    $fixed++;
                } else {
                    $failed++;
                    $errors[] = "Movie #{$movieId}: " . ($result['message'] ?? 'Unknown error');
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Movie #{$movieId}: Exception — " . $e->getMessage();
                Log::error("[BatchFixFailureMovies] Exception fixing movie #{$movieId}: " . $e->getMessage());
            }

            // Small pause between movies to avoid overwhelming the external API
            if ($totalMovies > 5) {
                usleep(200_000); // 200ms
            }
        }

        Log::info("[BatchFixFailureMovies] Batch complete: {$fixed} fixed, {$failed} failed out of {$totalMovies} unique movies.");

        $msg = "Batch fix complete: {$fixed} movies fixed, {$failed} failed ({$totalMovies} unique movies from {$totalFailures} selected failures).";

        if (!empty($errors)) {
            $shownErrors = array_slice($errors, 0, 5);
            $msg .= "\n\nErrors:\n• " . implode("\n• ", $shownErrors);
            if (count($errors) > 5) {
                $msg .= "\n... and " . (count($errors) - 5) . " more (check logs).";
            }
        }

        if ($failed === 0) {
            return $this->response()->success($msg)->refresh();
        } elseif ($fixed > 0) {
            return $this->response()->success($msg)->refresh();
        } else {
            return $this->response()->error($msg)->refresh();
        }
    }

    /**
     * Confirmation dialog.
     */
    public function dialog()
    {
        $this->confirm('Fix movies for selected failures? This will re-fetch data from external servers and update each unique movie. Related failure records will be marked as FIXED on success. Max 200 unique movies per batch.');
    }
}
