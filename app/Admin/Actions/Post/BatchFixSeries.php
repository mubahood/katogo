<?php

namespace App\Admin\Actions\Post;

use App\Services\SeriesFixerService;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Batch Fix Series — syncs episodes from remote and fixes them.
 *
 * For each selected series:
 *  1. Fetches remote episodes from munowatch API
 *  2. Creates any missing local episode records
 *  3. Fixes each episode (re-fetches video URLs)
 *
 * Max 50 series per batch (each series can have many episodes).
 */
class BatchFixSeries extends BatchAction
{
    public $name = 'Fix Series (Sync + Fix)';

    protected $selector = '.batch-fix-series';

    /**
     * Process the batch fix.
     */
    public function handle(Collection $collection, Request $request)
    {
        set_time_limit(3600);              // 60 minutes — series can be large
        ini_set('memory_limit', '512M');

        $maxSeries = 50;
        $total     = $collection->count();

        if ($total > $maxSeries) {
            return $this->response()
                ->error("Too many series selected ({$total}). Please select at most {$maxSeries} at a time.")
                ->refresh();
        }

        if ($total === 0) {
            return $this->response()->error('No series selected.')->refresh();
        }

        Log::info("[BatchFixSeries] Starting batch fix for {$total} series.");

        $fixer         = new SeriesFixerService();
        $fixedSeries   = 0;
        $failedSeries  = 0;
        $totalEpFixed  = 0;
        $totalEpFailed = 0;
        $errors        = [];

        foreach ($collection as $series) {
            try {
                $result = $fixer->fixSeries((int) $series->id, 200);

                if (isset($result['error'])) {
                    $failedSeries++;
                    $errors[] = "#{$series->id} ({$series->title}): {$result['error']}";
                } else {
                    $fixedSeries++;
                    $totalEpFixed  += $result['episodes_fixed'] ?? 0;
                    $totalEpFailed += $result['episodes_failed'] ?? 0;
                }
            } catch (\Throwable $e) {
                $failedSeries++;
                $errors[] = "#{$series->id} ({$series->title}): " . $e->getMessage();
                Log::error("[BatchFixSeries] Exception fixing series #{$series->id}: " . $e->getMessage());
            }

            // Pause between series to avoid overwhelming the API
            if ($total > 3) {
                usleep(500_000); // 500ms
            }
        }

        Log::info("[BatchFixSeries] Batch complete: {$fixedSeries} series processed, {$failedSeries} failed. Episodes: {$totalEpFixed} fixed, {$totalEpFailed} failed.");

        $msg = "Batch fix complete: {$fixedSeries}/{$total} series processed. Episodes: {$totalEpFixed} fixed, {$totalEpFailed} failed.";

        if (!empty($errors)) {
            $shownErrors = array_slice($errors, 0, 5);
            $msg .= "\n\nErrors:\n• " . implode("\n• ", $shownErrors);
            if (count($errors) > 5) {
                $msg .= "\n... and " . (count($errors) - 5) . " more (check logs).";
            }
        }

        if ($failedSeries === 0) {
            return $this->response()->success($msg)->refresh();
        } elseif ($fixedSeries > 0) {
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
        $this->confirm('Fix selected series? This will sync episodes from remote APIs and fix each episode. Max 50 series per batch.');
    }
}
