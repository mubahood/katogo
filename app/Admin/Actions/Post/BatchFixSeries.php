<?php

namespace App\Admin\Actions\Post;

use App\Models\MovieCrawlerPage;
use App\Services\SeriesFixerService;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Batch Fix Series — syncs episodes from remote and fixes them.
 *
 * For each selected series:
 *  1. Fetches remote episodes from munowatch API (all ranges, chain traversal)
 *  2. Creates any missing local episode records
 *  3. Fixes up to 20 episodes per series (video URL repair)
 *  4. Updates related crawler page records
 *  5. Cleans series title and activates if ready
 *
 * Max 20 series per batch. Max 20 episode fixes per series to stay within time limits.
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
        set_time_limit(3600);
        ini_set('memory_limit', '512M');

        $maxSeries     = 20;
        $maxEpPerSeries = 20;
        $total         = $collection->count();

        if ($total > $maxSeries) {
            return $this->response()
                ->error("Too many series selected ({$total}). Please select at most {$maxSeries} at a time.")
                ->refresh();
        }

        if ($total === 0) {
            return $this->response()->error('No series selected.')->refresh();
        }

        Log::info("[BatchFixSeries] Starting batch fix for {$total} series (max {$maxEpPerSeries} eps/series).");

        $fixer         = new SeriesFixerService();
        $fixedSeries   = 0;
        $failedSeries  = 0;
        $totalEpFixed  = 0;
        $totalEpFailed = 0;
        $totalSynced   = 0;
        $titlesClean   = 0;
        $errors        = [];

        foreach ($collection as $series) {
            try {
                // Step 1: Sync + Fix (capped at 20 episodes)
                $result = $fixer->fixSeries((int) $series->id, $maxEpPerSeries);

                if (isset($result['error']) && !isset($result['success'])) {
                    $failedSeries++;
                    $errors[] = "#{$series->id} ({$series->title}): {$result['error']}";
                    // Mark fix tracking columns
                    $series->fix_status = 'error';
                    $series->fix_error_message = $result['error'];
                } else {
                    $fixedSeries++;
                    $totalEpFixed  += $result['episodes_fixed'] ?? 0;
                    $totalEpFailed += $result['episodes_failed'] ?? 0;
                    $totalSynced   += ($result['sync']['created'] ?? 0) + ($result['sync']['updated'] ?? 0);
                    // Mark fix tracking columns
                    $series->fix_status = 'fixed';
                    $series->fix_error_message = null;
                }
                $series->fix_date = now();
                $series->fix_counter = ($series->fix_counter ?? 0) + 1;
                $series->save();

                // Step 2: Clean title and activate if ready
                $activation = $fixer->checkAndActivateSeries((int) $series->id);
                if ($activation['title_cleaned'] ?? false) {
                    $titlesClean++;
                }

                // Step 3: Update related crawler pages
                $this->updateCrawlerPages($series);

            } catch (\Throwable $e) {
                $failedSeries++;
                $errors[] = "#{$series->id} ({$series->title}): " . $e->getMessage();
                Log::error("[BatchFixSeries] Exception fixing series #{$series->id}: " . $e->getMessage());
                // Mark fix tracking on exception
                $series->fix_status = 'error';
                $series->fix_error_message = 'Exception: ' . mb_substr($e->getMessage(), 0, 500);
                $series->fix_date = now();
                $series->fix_counter = ($series->fix_counter ?? 0) + 1;
                $series->save();
            }

            if ($total > 3) {
                usleep(500_000); // 500ms between series
            }
        }

        Log::info("[BatchFixSeries] Batch complete: {$fixedSeries}/{$total} series, {$totalSynced} synced, {$totalEpFixed} fixed, {$totalEpFailed} failed, {$titlesClean} titles cleaned.");

        $msg = "Batch fix: {$fixedSeries}/{$total} series processed.\n"
             . "Synced: {$totalSynced} | Fixed: {$totalEpFixed} | Failed: {$totalEpFailed}"
             . ($titlesClean > 0 ? " | Titles cleaned: {$titlesClean}" : "");

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
     * Update crawler page records to reflect the fix results.
     * Marks series-related crawler pages as processed so the crawler knows they're done.
     */
    protected function updateCrawlerPages($series): void
    {
        try {
            $seriesCode = $series->series_code;
            $munowatchId = $series->munowatch_id;

            if (empty($seriesCode) && empty($munowatchId)) return;

            // Find crawler pages for this series by series_code or munowatch_id
            $query = MovieCrawlerPage::query();
            $query->where(function ($q) use ($seriesCode, $munowatchId, $series) {
                if ($seriesCode) {
                    $q->orWhere('series_code', $seriesCode);
                    $q->orWhere('muno_series_group_id', $seriesCode);
                }
                if ($munowatchId) {
                    $q->orWhere('munowatch_id', $munowatchId);
                }
                $q->orWhere('series_id', $series->id);
            });

            $pages = $query->get();
            $updated = 0;

            foreach ($pages as $page) {
                $changed = false;

                // Link to series if not already linked
                if (empty($page->series_id) || $page->series_id != $series->id) {
                    $page->series_id = $series->id;
                    $changed = true;
                }

                // Mark as series-processed
                if ($page->muno_series_processed !== 'Yes') {
                    $page->muno_series_processed = 'Yes';
                    $page->muno_series_success = 'Yes';
                    $changed = true;
                }

                // Set series_code on the page
                if ($seriesCode && $page->series_code !== $seriesCode) {
                    $page->series_code = $seriesCode;
                    $changed = true;
                }

                if ($changed) {
                    $page->save();
                    $updated++;
                }
            }

            if ($updated > 0) {
                Log::info("[BatchFixSeries] Updated {$updated} crawler pages for series #{$series->id}");
            }
        } catch (\Throwable $e) {
            Log::warning("[BatchFixSeries] Failed to update crawler pages for series #{$series->id}: " . $e->getMessage());
        }
    }

    /**
     * Confirmation dialog before processing.
     */
    public function dialog()
    {
        $this->confirm('Fix selected series? Syncs episodes from remote + fixes up to 20 episodes per series. Max 20 series per batch.');
    }
}
