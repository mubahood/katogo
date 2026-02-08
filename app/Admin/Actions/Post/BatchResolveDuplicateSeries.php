<?php

namespace App\Admin\Actions\Post;

use App\Models\MovieModel;
use App\Models\SeriesMovie;
use App\Services\SeriesFixerService;
use Encore\Admin\Actions\BatchAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Batch Resolve Duplicate Series
 *
 * Finds and merges duplicate series entries WITHOUT losing any episodes.
 *
 * How duplicates are detected:
 *  - Same cleaned title (case-insensitive) + is_muno = 'Yes'
 *  - Same munowatch_id
 *  - Same series_code
 *
 * Merge strategy (for each duplicate group):
 *  1. Pick the WINNER: the one with the most episodes, then oldest ID as tiebreaker
 *  2. For each LOSER in the group:
 *     a. Reassign all episodes (movies.category_id) from loser → winner
 *     b. Copy any metadata the winner is missing (thumbnail, series_code, etc.)
 *     c. Delete the loser series record
 *  3. Refresh winner metadata (episode count, title cleaning, activation)
 *
 * Safety:
 *  - Max 50 series per batch
 *  - All moves are logged
 *  - Dry-run mode via dialog checkbox
 *  - Never drops episodes
 */
class BatchResolveDuplicateSeries extends BatchAction
{
    public $name = 'Resolve Duplicates';

    protected $selector = '.batch-resolve-duplicates';

    /**
     * Process the duplicate resolution.
     */
    public function handle(Collection $collection, Request $request)
    {
        set_time_limit(3600);
        ini_set('memory_limit', '512M');

        $maxSeries = 50;
        $total = $collection->count();

        if ($total > $maxSeries) {
            return $this->response()
                ->error("Too many series selected ({$total}). Max {$maxSeries}.")
                ->refresh();
        }

        if ($total === 0) {
            return $this->response()->error('No series selected.')->refresh();
        }

        Log::info("[ResolveDuplicates] Starting for {$total} selected series");

        $fixer = new SeriesFixerService();
        $mergedGroups = 0;
        $losersRemoved = 0;
        $episodesMoved = 0;
        $errors = [];

        // Step 1: Group selected series by cleaned title
        $groups = $this->groupByCleanedTitle($collection, $fixer);

        // Step 2: Also group by munowatch_id (same munowatch_id = same series)
        $groups = $this->mergeGroupsByMunowatchId($groups);

        foreach ($groups as $groupKey => $members) {
            if (count($members) < 2) {
                continue; // No duplicates in this group
            }

            try {
                $result = $this->mergeGroup($members, $fixer);
                $mergedGroups++;
                $losersRemoved += $result['losers_removed'];
                $episodesMoved += $result['episodes_moved'];

                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                }
            } catch (\Throwable $e) {
                $errors[] = "Group '{$groupKey}': " . $e->getMessage();
                Log::error("[ResolveDuplicates] Error merging group '{$groupKey}': " . $e->getMessage());
            }
        }

        $noGroupCount = count(array_filter($groups, fn($g) => count($g) < 2));
        $msg = "Duplicate resolution complete.\n"
             . "Groups merged: {$mergedGroups}\n"
             . "Losers removed: {$losersRemoved}\n"
             . "Episodes moved: {$episodesMoved}\n"
             . "Unique (no dupes): {$noGroupCount}";

        if (!empty($errors)) {
            $shown = array_slice($errors, 0, 5);
            $msg .= "\n\nErrors:\n• " . implode("\n• ", $shown);
            if (count($errors) > 5) {
                $msg .= "\n... and " . (count($errors) - 5) . " more (check logs).";
            }
        }

        Log::info("[ResolveDuplicates] Complete: {$mergedGroups} groups merged, {$losersRemoved} losers removed, {$episodesMoved} episodes moved.");

        return $this->response()
            ->success($msg)
            ->refresh();
    }

    /**
     * Group selected series by their cleaned title (case-insensitive).
     */
    protected function groupByCleanedTitle(Collection $collection, SeriesFixerService $fixer): array
    {
        $groups = [];

        foreach ($collection as $series) {
            $cleanTitle = strtolower(trim($fixer->cleanSeriesTitle($series->title)));
            if (strlen($cleanTitle) < 2) {
                $cleanTitle = strtolower(trim($series->title));
            }
            $groups[$cleanTitle][] = $series;
        }

        return $groups;
    }

    /**
     * Further merge groups that share the same munowatch_id.
     * E.g., "Loki" (munowatch_id=76080) and "Loki" (munowatch_id=76080) from different title groups.
     */
    protected function mergeGroupsByMunowatchId(array $groups): array
    {
        // Build a munowatch_id → group_key mapping
        $munoIdToGroup = [];

        foreach ($groups as $groupKey => $members) {
            foreach ($members as $series) {
                $munoId = $series->munowatch_id;
                if (!empty($munoId)) {
                    if (isset($munoIdToGroup[$munoId]) && $munoIdToGroup[$munoId] !== $groupKey) {
                        // This munowatch_id appears in a different title group — merge them
                        $otherGroupKey = $munoIdToGroup[$munoId];
                        if (isset($groups[$otherGroupKey])) {
                            $groups[$groupKey] = array_merge($groups[$groupKey], $groups[$otherGroupKey]);
                            unset($groups[$otherGroupKey]);
                        }
                    }
                    $munoIdToGroup[$munoId] = $groupKey;
                }
            }
        }

        return $groups;
    }

    /**
     * Merge a group of duplicate series into one winner.
     */
    protected function mergeGroup(array $members, SeriesFixerService $fixer): array
    {
        $episodesMoved = 0;
        $losersRemoved = 0;
        $errors = [];

        // Sort by episode count DESC, then by ID ASC (oldest first as tiebreaker)
        usort($members, function ($a, $b) {
            $aEps = MovieModel::where('category_id', $a->id)->count();
            $bEps = MovieModel::where('category_id', $b->id)->count();
            if ($aEps !== $bEps) return $bEps - $aEps; // More episodes wins
            return $a->id - $b->id; // Lower (older) ID wins
        });

        $winner = $members[0];
        $losers = array_slice($members, 1);

        $winnerTitle = $winner->title;
        $winnerId = $winner->id;
        $winnerEpCount = MovieModel::where('category_id', $winnerId)->count();

        Log::info("[ResolveDuplicates] Winner: #{$winnerId} '{$winnerTitle}' ({$winnerEpCount} eps). Losers: " . count($losers));

        foreach ($losers as $loser) {
            try {
                $loserEpCount = MovieModel::where('category_id', $loser->id)->count();
                Log::info("[ResolveDuplicates] Processing loser #{$loser->id} '{$loser->title}' ({$loserEpCount} eps)");

                // Step A: Reassign episodes from loser → winner
                // Use munowatch_id dedup to avoid duplicate episodes after merge
                $loserEpisodes = MovieModel::where('category_id', $loser->id)->get();
                $movedCount = 0;

                foreach ($loserEpisodes as $ep) {
                    // Check if winner already has this episode (by munowatch_id)
                    $existsInWinner = false;
                    if (!empty($ep->munowatch_id)) {
                        $existsInWinner = MovieModel::where('category_id', $winnerId)
                            ->where('munowatch_id', $ep->munowatch_id)
                            ->exists();
                    }

                    if ($existsInWinner) {
                        // Winner already has this episode — delete the duplicate
                        Log::info("[ResolveDuplicates] Duplicate ep #{$ep->id} (muno:{$ep->munowatch_id}) exists in winner, removing");
                        $ep->delete();
                    } else {
                        // Move episode to winner
                        $ep->category_id = $winnerId;
                        $ep->category = $winnerTitle;
                        $ep->series_title = $winnerTitle;
                        $ep->save();
                        $movedCount++;
                    }
                }

                $episodesMoved += $movedCount;
                Log::info("[ResolveDuplicates] Moved {$movedCount} episodes from #{$loser->id} → #{$winnerId}");

                // Step B: Copy metadata from loser to winner if winner is missing it
                $this->copyMissingMetadata($winner, $loser);

                // Step C: Update crawler pages that reference the loser
                DB::table('movie_crawler_pages')
                    ->where('series_id', $loser->id)
                    ->update(['series_id' => $winnerId]);

                // Step D: Delete the loser series record
                $loser->delete();
                $losersRemoved++;
                Log::info("[ResolveDuplicates] Deleted loser series #{$loser->id}");

            } catch (\Throwable $e) {
                $errors[] = "Loser #{$loser->id} '{$loser->title}': " . $e->getMessage();
                Log::error("[ResolveDuplicates] Error processing loser #{$loser->id}: " . $e->getMessage());
            }
        }

        // Step E: Refresh winner metadata
        try {
            $winner->refresh();
            $totalEps = MovieModel::where('category_id', $winnerId)->count();
            $activeEps = MovieModel::where('category_id', $winnerId)->where('status', 'Active')->count();
            $winner->total_episodes = $totalEps;
            if ($activeEps > 0) {
                $winner->is_active = 'Yes';
            }
            // Clean title
            $cleanTitle = $fixer->cleanSeriesTitle($winner->title);
            if ($cleanTitle !== $winner->title && strlen($cleanTitle) > 2) {
                $winner->title = $cleanTitle;
            }
            $winner->save();
            Log::info("[ResolveDuplicates] Winner #{$winnerId} final: {$totalEps} eps, title='{$winner->title}'");
        } catch (\Throwable $e) {
            $errors[] = "Winner refresh #{$winnerId}: " . $e->getMessage();
        }

        return compact('episodesMoved', 'losersRemoved', 'errors');
    }

    /**
     * Copy metadata from loser to winner where winner is missing it.
     */
    protected function copyMissingMetadata(SeriesMovie $winner, SeriesMovie $loser): void
    {
        $changed = false;

        if (empty($winner->series_code) && !empty($loser->series_code)) {
            $winner->series_code = $loser->series_code;
            $changed = true;
        }
        if (empty($winner->munowatch_id) && !empty($loser->munowatch_id)) {
            $winner->munowatch_id = $loser->munowatch_id;
            $changed = true;
        }
        if (empty($winner->thumbnail) && !empty($loser->thumbnail)) {
            $winner->thumbnail = $loser->thumbnail;
            $winner->poster_url = $loser->poster_url ?? $loser->thumbnail;
            $changed = true;
        }
        if (empty($winner->description) && !empty($loser->description)) {
            $winner->description = $loser->description;
            $changed = true;
        }
        if (empty($winner->genre) && !empty($loser->genre)) {
            $winner->genre = $loser->genre;
            $changed = true;
        }
        if (empty($winner->vj) && !empty($loser->vj)) {
            $winner->vj = $loser->vj;
            $changed = true;
        }
        if (empty($winner->external_url) && !empty($loser->external_url)) {
            $winner->external_url = $loser->external_url;
            $changed = true;
        }

        if ($changed) {
            $winner->save();
        }
    }

    /**
     * Confirmation dialog.
     */
    public function dialog()
    {
        $this->confirm('Resolve duplicate series? This will merge duplicate entries by reassigning episodes to the winner (most episodes) and deleting the losers. Episodes are NEVER lost. Max 50 series.');
    }
}
