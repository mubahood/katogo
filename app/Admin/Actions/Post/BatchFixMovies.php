<?php

namespace App\Admin\Actions\Post;

use App\Services\MovieFixerService;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Batch Fix Movies — re-fetches movie data from the original source server
 * (munowatch, myvj) and repairs broken records one by one.
 *
 * Max 200 movies per batch to avoid timeouts.
 * Updates video_playback_failures for each movie individually (no deadlocks).
 */
class BatchFixMovies extends BatchAction
{
    public $name = 'Fix Movies (Re-fetch)';

    protected $selector = '.batch-fix-movies';

    /**
     * Process the batch fix.
     *
     * Movies are fixed one-by-one sequentially. Each movie fix:
     *  - Fetches fresh data from the external server (munowatch API / myvj HTML)
     *  - Updates the movie record with the fetched data
     *  - Updates related video_playback_failures records
     *
     * We avoid deadlocks by:
     *  - Processing movies sequentially (not in parallel)
     *  - Each movie's failure records are updated separately after its fix completes
     *  - No wrapping transaction around the entire batch (each movie is its own unit)
     */
    public function handle(Collection $collection, Request $request)
    {
        // Allocate enough resources — each movie fix does an external HTTP call
        // that can take up to 30s. For 500 movies: ~250 min worst case.
        set_time_limit(1800);              // 30 minutes
        ini_set('memory_limit', '512M');   // 512 MB

        // Enforce max 500 movies per batch to prevent server overload and timeouts.
        $maxMovies = 500;
        $total     = $collection->count();

        if ($total > $maxMovies) {
            return $this->response()
                ->error("Too many movies selected ({$total}). Please select at most {$maxMovies} movies at a time.")
                ->refresh();
        }

        if ($total === 0) {
            return $this->response()->error('No movies selected.')->refresh();
        }

        Log::info("[BatchFixMovies] Starting batch fix for {$total} movies.");

        $fixer  = new MovieFixerService();
        $fixed  = 0;
        $failed = 0;
        $errors = [];

        foreach ($collection as $movie) {
            try {
                $result = $fixer->fix((int) $movie->id);

                if ($result['success']) {
                    $fixed++;
                    // Mark fix tracking columns
                    $movie->fix_status = 'fixed';
                    $movie->fix_error_message = null;
                } else {
                    $failed++;
                    $errors[] = "#{$movie->id} ({$movie->title}): " . ($result['message'] ?? 'Unknown error');
                    // Mark fix tracking columns
                    $movie->fix_status = 'error';
                    $movie->fix_error_message = $result['message'] ?? 'Unknown error';
                }
                $movie->fix_date = now();
                $movie->fix_counter = ($movie->fix_counter ?? 0) + 1;
                $movie->save();
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "#{$movie->id} ({$movie->title}): Exception — " . $e->getMessage();
                Log::error("[BatchFixMovies] Exception fixing movie #{$movie->id}: " . $e->getMessage());
                // Mark fix tracking on exception
                $movie->fix_status = 'error';
                $movie->fix_error_message = 'Exception: ' . mb_substr($e->getMessage(), 0, 500);
                $movie->fix_date = now();
                $movie->fix_counter = ($movie->fix_counter ?? 0) + 1;
                $movie->save();
            }

            // Small pause between movies to avoid overwhelming the external API
            if ($total > 5) {
                usleep(200_000); // 200ms
            }
        }

        Log::info("[BatchFixMovies] Batch complete: {$fixed} fixed, {$failed} failed out of {$total}.");

        // Build response message
        $msg = "Batch fix complete: {$fixed} fixed, {$failed} failed (out of {$total} selected).";

        if (!empty($errors)) {
            // Show first 5 errors max to avoid overflow
            $shownErrors = array_slice($errors, 0, 5);
            $msg .= "\n\nErrors:\n• " . implode("\n• ", $shownErrors);
            if (count($errors) > 5) {
                $msg .= "\n... and " . (count($errors) - 5) . " more errors (check logs).";
            }
        }

        if ($failed === 0) {
            return $this->response()->success($msg)->refresh();
        } elseif ($fixed > 0) {
            // Partial success — use warning-style (still success but with info)
            return $this->response()->success($msg)->refresh();
        } else {
            return $this->response()->error($msg)->refresh();
        }
    }

    /**
     * Confirmation dialog before processing.
     */
    public function dialog()
    {
        $this->confirm('Fix selected movies? This will re-fetch data from the original external servers (munowatch/myvj) and update each movie record. Max 500 movies per batch.');
    }
}
