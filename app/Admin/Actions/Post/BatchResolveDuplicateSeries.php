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
 * SAFE duplicate detection (never merges different shows):
 *  TIER 1 — Same munowatch_id (strongest signal, always safe)
 *  TIER 2 — Same cleaned title + one/both have empty munowatch_id (orphan cleanup)
 *
 * NEVER merged:
 *  - Two series with DIFFERENT non-empty munowatch_ids (even if same title)
 *    These are different shows/seasons that happen to share a name.
 *
 * Merge strategy (for each duplicate group):
 *  1. Pick the WINNER: most episodes, then oldest ID as tiebreaker
 *  2. For each LOSER: reassign episodes → winner, copy metadata, delete loser
 *  3. Refresh winner (episode count, title cleaning, activation)
 *
 * Safety:
 *  - Max 50 series per batch
 *  - All moves are logged
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
        $skippedDiffShows = 0;
        $errors = [];

        // Build duplicate groups with SAFE logic
        $groups = $this->buildSafeGroups($collection, $fixer);

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
     * Build safe duplicate groups using a two-tier approach:
     *
     * TIER 1: Group by munowatch_id (exact match, non-empty).
     *         Same munowatch_id = definitely the same show.
     *
     * TIER 2: For series NOT yet grouped (no munowatch_id or unique munowatch_id),
     *         group by cleaned title ONLY if they can be safely merged:
     *         - Both have the SAME non-empty munowatch_id (already handled by tier 1)
     *         - One or both have EMPTY munowatch_id (orphan that belongs to this title)
     *
     *         NEVER group two series with DIFFERENT non-empty munowatch_ids.
     */
    protected function buildSafeGroups(Collection $collection, SeriesFixerService $fixer): array
    {
        $groups = [];
        $assignedIds = []; // series ID → group key (prevent double-assignment)

        // Index all series with their metadata
        $seriesData = [];
        foreach ($collection as $series) {
            $cleanTitle = strtolower(trim($fixer->cleanSeriesTitle($series->title)));
            if (strlen($cleanTitle) < 2) {
                $cleanTitle = strtolower(trim($series->title));
            }
            $seriesData[$series->id] = [
                'model' => $series,
                'clean_title' => $cleanTitle,
                'muno_id' => trim($series->munowatch_id ?? ''),
                'series_code' => trim($series->series_code ?? ''),
            ];
        }

        // TIER 1: Group by munowatch_id (strongest signal)
        $byMunoId = [];
        foreach ($seriesData as $sid => $data) {
            if ($data['muno_id'] !== '') {
                $byMunoId[$data['muno_id']][] = $sid;
            }
        }

        foreach ($byMunoId as $munoId => $sids) {
            if (count($sids) >= 2) {
                $groupKey = 'muno_' . $munoId;
                $groups[$groupKey] = [];
                foreach ($sids as $sid) {
                    $groups[$groupKey][] = $seriesData[$sid]['model'];
                    $assignedIds[$sid] = $groupKey;
                }
                Log::info("[ResolveDuplicates] TIER1 group '{$groupKey}': " . count($sids) . " series with same munowatch_id={$munoId}");
            }
        }

        // TIER 2: Group remaining by cleaned title, but ONLY merge if safe
        // Safe = one or both have empty munowatch_id (orphan cleanup)
        $unassigned = array_filter($seriesData, fn($d, $sid) => !isset($assignedIds[$sid]), ARRAY_FILTER_USE_BOTH);

        $byTitle = [];
        foreach ($unassigned as $sid => $data) {
            $byTitle[$data['clean_title']][] = $sid;
        }

        foreach ($byTitle as $title => $sids) {
            if (count($sids) < 2) continue;

            // Check safety: collect all non-empty munowatch_ids in this title group
            $munoIds = [];
            foreach ($sids as $sid) {
                $mid = $seriesData[$sid]['muno_id'];
                if ($mid !== '') $munoIds[$mid] = true;
            }

            if (count($munoIds) > 1) {
                // DIFFERENT non-empty munowatch_ids → NOT duplicates, skip
                Log::info("[ResolveDuplicates] SKIP title '{$title}': " . count($munoIds) . " different munowatch_ids — these are different shows");
                continue;
            }

            if (count($munoIds) === 1) {
                // All have the same munowatch_id (or empty). Safe to merge.
                // But only include those with matching munowatch_id or empty munowatch_id
                $targetMunoId = array_key_first($munoIds);
                $safeSids = [];
                foreach ($sids as $sid) {
                    $mid = $seriesData[$sid]['muno_id'];
                    if ($mid === '' || $mid === $targetMunoId) {
                        $safeSids[] = $sid;
                    }
                }
                if (count($safeSids) >= 2) {
                    $groupKey = 'title_' . $title;
                    $groups[$groupKey] = [];
                    foreach ($safeSids as $sid) {
                        if (!isset($assignedIds[$sid])) {
                            $groups[$groupKey][] = $seriesData[$sid]['model'];
                            $assignedIds[$sid] = $groupKey;
                        }
                    }
                    Log::info("[ResolveDuplicates] TIER2 group '{$groupKey}': " . count($groups[$groupKey]) . " series (same title + compatible muno_id)");
                }
            } else {
                // count($munoIds) === 0: ALL have empty munowatch_id. Safe to merge by title.
                $groupKey = 'title_' . $title;
                $groups[$groupKey] = [];
                foreach ($sids as $sid) {
                    if (!isset($assignedIds[$sid])) {
                        $groups[$groupKey][] = $seriesData[$sid]['model'];
                        $assignedIds[$sid] = $groupKey;
                    }
                }
                Log::info("[ResolveDuplicates] TIER2 group '{$groupKey}': " . count($groups[$groupKey]) . " series (same title, all empty muno_id)");
            }
        }

        // Also check: can any TIER 1 group absorb orphans from unassigned by title match?
        $stillUnassigned = array_filter($seriesData, fn($d, $sid) => !isset($assignedIds[$sid]), ARRAY_FILTER_USE_BOTH);
        foreach ($stillUnassigned as $sid => $data) {
            if ($data['muno_id'] !== '') continue; // Has a unique munowatch_id, leave alone

            // This orphan has no munowatch_id — check if any existing group has the same clean title
            foreach ($groups as $groupKey => $members) {
                $groupTitle = null;
                foreach ($members as $member) {
                    $memberData = $seriesData[$member->id] ?? null;
                    if ($memberData) {
                        $groupTitle = $memberData['clean_title'];
                        break;
                    }
                }
                if ($groupTitle === $data['clean_title']) {
                    $groups[$groupKey][] = $data['model'];
                    $assignedIds[$sid] = $groupKey;
                    Log::info("[ResolveDuplicates] Absorbed orphan #{$sid} '{$data['model']->title}' into group '{$groupKey}'");
                    break;
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

        // Deduplicate models by ID (prevent self-merge)
        $unique = [];
        foreach ($members as $m) {
            $unique[$m->id] = $m;
        }
        $members = array_values($unique);

        if (count($members) < 2) {
            return ['episodes_moved' => 0, 'losers_removed' => 0, 'errors' => []];
        }

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
            // SAFETY: Never merge a series with itself
            if ($loser->id === $winnerId) {
                Log::warning("[ResolveDuplicates] Skipping self-merge for #{$winnerId}");
                continue;
            }

            try {
                $loserEpCount = MovieModel::where('category_id', $loser->id)->count();
                Log::info("[ResolveDuplicates] Processing loser #{$loser->id} '{$loser->title}' ({$loserEpCount} eps)");

                // Build a lookup of what episodes the winner already has
                $winnerEpisodes = MovieModel::where('category_id', $winnerId)->get();
                $winnerMunoIds = [];
                $winnerExtUrls = [];
                foreach ($winnerEpisodes as $we) {
                    if (!empty($we->munowatch_id)) $winnerMunoIds[$we->munowatch_id] = true;
                    if (!empty($we->external_url)) $winnerExtUrls[trim($we->external_url)] = true;
                }

                // Reassign episodes from loser → winner
                $loserEpisodes = MovieModel::where('category_id', $loser->id)->get();
                $movedCount = 0;
                $dupCount = 0;

                foreach ($loserEpisodes as $ep) {
                    // Check if winner already has this episode
                    $existsInWinner = false;

                    // Check by munowatch_id
                    if (!empty($ep->munowatch_id) && isset($winnerMunoIds[$ep->munowatch_id])) {
                        $existsInWinner = true;
                    }

                    // Check by external_url
                    if (!$existsInWinner && !empty($ep->external_url) && isset($winnerExtUrls[trim($ep->external_url)])) {
                        $existsInWinner = true;
                    }

                    if ($existsInWinner) {
                        // Winner already has this episode — delete the duplicate
                        Log::info("[ResolveDuplicates] Duplicate ep #{$ep->id} (muno:{$ep->munowatch_id}) exists in winner, removing");
                        $ep->delete();
                        $dupCount++;
                    } else {
                        // Move episode to winner
                        $ep->category_id = $winnerId;
                        $ep->category = $winnerTitle;
                        $ep->series_title = $winnerTitle;
                        $ep->save();
                        $movedCount++;

                        // Update lookup so next episodes can check against newly moved ones
                        if (!empty($ep->munowatch_id)) $winnerMunoIds[$ep->munowatch_id] = true;
                        if (!empty($ep->external_url)) $winnerExtUrls[trim($ep->external_url)] = true;
                    }
                }

                $episodesMoved += $movedCount;
                Log::info("[ResolveDuplicates] Moved {$movedCount} episodes, removed {$dupCount} dups from #{$loser->id} → #{$winnerId}");

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
