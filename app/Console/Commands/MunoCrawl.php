<?php

namespace App\Console\Commands;

use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use App\Models\MunoCrawlLog;
use App\Models\MunoCrawlSession;
use App\Models\MunowatchMovieCategory;
use App\Services\MunowatchAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * MunoCrawl — Artisan command for crawling the Munowatch API.
 *
 * Two-phase approach matching the Namz crawler:
 *   Phase 1 (Discovery): Fetch movie lists from each active category → create MovieCrawlerPage records
 *   Phase 2 (Processing): For each pending crawler page, fetch the preview API and persist to movie_models
 *
 * Features matching/improving on NamzCrawl:
 *   - Session tracking (muno_crawl_sessions table)
 *   - Per-page logging (muno_crawl_logs table)
 *   - Stop signal file (storage/munowatch_stop)
 *   - Configurable delay between requests
 *   - Pre-auth refresh every N pages (avoids mid-batch 429s)
 *   - Dry-run mode
 *   - Single-movie processing via --munowatch-id=
 *   - --session= to resume a stopped session
 *   - --skip-discovery to jump straight to processing pending pages
 */
class MunoCrawl extends Command
{
    protected $signature = 'munowatch:crawl
                            {--delay=600          : Milliseconds between preview API requests}
                            {--batch=30           : Pages per batch before a longer sleep}
                            {--refresh-every=100  : Re-authenticate every N processed pages}
                            {--category=          : Only crawl this MunowatchMovieCategory.id}
                            {--munowatch-id=      : Process a single munowatch video ID only}
                            {--session=           : Resume an existing session by ID}
                            {--skip-discovery     : Skip Phase 1, only process pending crawler pages}
                            {--limit=0            : Max pages to process in Phase 2 (0 = unlimited)}
                            {--dry-run            : Parse + log but do not write to movie_models}
                            {--force              : Re-process pages that are already status=success}';

    protected $description = 'Crawl the Munowatch API: discover new movies and process their preview data';

    private bool $dryRun      = false;
    private MunoCrawlSession $session;

    // ─────────────────────────────────────────────────────────────────────────
    //  ENTRY POINT
    // ─────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $this->info('Munowatch Crawler');
        $this->info('Dry-run: ' . ($this->dryRun ? 'YES — no DB writes' : 'NO'));

        // ── Authenticate first ───────────────────────────────────────────────
        try {
            $authSession = MunowatchAuthService::getActiveSession();
            $this->info("Auth OK — user_id={$authSession['user_id']} session=" . substr($authSession['session_id'], 0, 8) . '…');
        } catch (\Throwable $e) {
            $this->error('Munowatch authentication failed: ' . $e->getMessage());
            return 1;
        }

        // ── Resolve or create crawl session ──────────────────────────────────
        $sessionId = $this->option('session');
        if ($sessionId && $existing = MunoCrawlSession::find($sessionId)) {
            $this->session = $existing;
            $this->session->update(['status' => MunoCrawlSession::STATUS_RUNNING, 'ended_at' => null]);
            $this->info("Resuming session #{$this->session->id}");
        } else {
            $catId   = $this->option('category') ? (int) $this->option('category') : null;
            $catName = $catId ? (MunowatchMovieCategory::find($catId)?->category_name ?? "Category #{$catId}") : 'All categories';
            $this->session = MunoCrawlSession::create([
                'status'       => MunoCrawlSession::STATUS_RUNNING,
                'category_id'  => $catId,
                'category_name'=> $catName,
                'triggered_by' => PHP_SAPI === 'cli' ? 'manual' : 'scheduler',
                'started_at'   => now(),
            ]);
            $this->info("Session #{$this->session->id} — {$catName}");
        }

        // ── Single-ID mode ────────────────────────────────────────────────────
        if ($mwId = $this->option('munowatch-id')) {
            $result = $this->processOnePage((int) $mwId);
            $this->session->update(['status' => MunoCrawlSession::STATUS_COMPLETED, 'ended_at' => now()]);
            $this->info($result ? "Done: {$result['title']}" : 'Failed — check muno_crawl_logs');
            return 0;
        }

        // ── Phase 1: Discovery ────────────────────────────────────────────────
        if (!$this->option('skip-discovery')) {
            $this->line('');
            $this->info('── Phase 1: Discovery ──────────────────────────────────────────');
            $this->runDiscovery();
        }

        // ── Phase 2: Process pending crawler pages ────────────────────────────
        $this->line('');
        $this->info('── Phase 2: Process pending pages ──────────────────────────────');
        $this->runProcessing();

        // ── Finish ─────────────────────────────────────────────────────────────
        $this->session->update(['status' => MunoCrawlSession::STATUS_COMPLETED, 'ended_at' => now()]);
        $this->printSummary();
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PHASE 1 — DISCOVERY
    // ─────────────────────────────────────────────────────────────────────────

    private function runDiscovery(): void
    {
        $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();
        if (!$website) {
            $this->warn('No MovieCrawlerWebsite with slug=munowatch found — skipping discovery.');
            return;
        }

        $categoryFilter = $this->option('category') ? (int) $this->option('category') : null;

        $categories = MunowatchMovieCategory::active()
            ->when($categoryFilter, fn ($q) => $q->where('id', $categoryFilter))
            ->orderBy('last_movies_fetched_at', 'asc')
            ->get();

        if ($categories->isEmpty()) {
            $this->warn('No active categories found. Run MunowatchMovieCategory::fetchCategoriesFromDashboard() first.');
            return;
        }

        $this->info("Processing {$categories->count()} categories…");

        foreach ($categories as $category) {
            if ($this->stopSignalReceived()) {
                $this->handleStop();
                return;
            }

            $this->line("  → [{$category->munowatch_category_id}] {$category->category_name}");

            try {
                $session  = MunowatchAuthService::getActiveSession();
                $newPages = 0;
                $totalMoviesSeen = 0;
                $page = 1;
                $maxPages = 100; // safety cap: 100 pages × 20 movies = 2,000 movies per category

                do {
                    $movies = $category->fetchMovies($page, $session['user_id']);

                    if (empty($movies)) {
                        break; // no more pages
                    }

                    $totalMoviesSeen += count($movies);

                    foreach ($movies as $movie) {
                        $movieId = $movie['vid'] ?? $movie['id'] ?? null;
                        if (empty($movieId)) continue;

                        // Check by slug (munowatch_id) first — avoids URL-based duplicates
                        $exists = MovieCrawlerPage::where('slug', (string) $movieId)
                            ->where('movie_crawler_website_id', $website->id)
                            ->exists();
                        if ($exists) continue;

                        $url = "https://munowatch.org/api/preview/v2/{$movieId}/{$session['user_id']}";

                        // Secondary URL check
                        if (MovieCrawlerPage::where('url', $url)->exists()) continue;

                        $cp                           = new MovieCrawlerPage();
                        $cp->movie_crawler_website_id = $website->id;
                        $cp->url                      = $url;
                        $cp->title                    = $movie['title'] ?? 'Unknown';
                        $cp->status                   = 'pending';
                        $cp->slug                     = (string) $movieId;
                        $cp->is_muno                  = 'Yes';
                        $cp->muno_processed           = 'No';
                        $cp->munowatch_id             = $movieId;
                        $cp->vj                       = 'Munowatch API';
                        $cp->save();
                        $newPages++;
                    }

                    $page++;

                    // Stop paginating if we got fewer than 20 movies (last page)
                } while (count($movies) >= 20 && $page <= $maxPages);

                $this->line("    ✓ {$newPages} new pages queued (scanned {$totalMoviesSeen} movies across " . ($page - 1) . ' pages)');
                $this->session->increment('movies_discovered', $newPages);
                $this->session->increment('pages_fetched', $page - 1);

                $category->completeFetching($totalMoviesSeen);

            } catch (\Throwable $e) {
                $this->warn("    ✗ Failed: " . $e->getMessage());
                Log::warning("[MunoCrawl] Discovery failed for category {$category->id}: " . $e->getMessage());
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PHASE 2 — PROCESS PENDING PAGES
    // ─────────────────────────────────────────────────────────────────────────

    private function runProcessing(): void
    {
        $delay        = (int) $this->option('delay');
        $batchSize    = (int) $this->option('batch');
        $refreshEvery = (int) $this->option('refresh-every');
        $limit        = (int) $this->option('limit');
        $force        = (bool) $this->option('force');
        $catFilter    = $this->option('category') ? (int) $this->option('category') : null;

        $website = MovieCrawlerWebsite::where('slug', 'munowatch')->first();

        $query = MovieCrawlerPage::where('movie_crawler_website_id', $website?->id)
            ->where('is_muno', 'Yes')
            ->when(!$force, fn ($q) => $q->where('status', 'pending'))
            ->when($catFilter, fn ($q) => $q->where('munowatch_movie_category_id', $catFilter))
            ->orderBy('id', 'asc');

        $total     = $query->count();
        $toProcess = $limit > 0 ? min($total, $limit) : $total;

        $this->info("Pending pages to process: {$toProcess}" . ($limit > 0 ? " (limit={$limit})" : ''));

        if ($toProcess === 0) {
            $this->info('Nothing to process.');
            return;
        }

        $bar = $this->output->createProgressBar($toProcess);
        $bar->start();

        $processed = 0;
        $query->chunk(50, function ($pages) use (&$processed, $toProcess, $limit, $delay, $batchSize, $refreshEvery, $bar) {
            foreach ($pages as $page) {
                if ($this->stopSignalReceived()) {
                    $this->handleStop();
                    return false; // stop chunk iteration
                }

                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                $munowatchId = (int) ($page->slug ?: $page->munowatch_id ?: 0);
                if (!$munowatchId) {
                    $bar->advance();
                    $processed++;
                    continue;
                }

                $this->processOnePage($munowatchId, $page);

                $bar->advance();
                $processed++;

                // Rate limiting
                if ($delay > 0 && $processed < $toProcess) {
                    usleep(($processed % $batchSize === 0 ? $delay * 2 : $delay) * 1000);
                }

                // Proactive session refresh every N pages
                if ($refreshEvery > 0 && $processed % $refreshEvery === 0) {
                    try {
                        MunowatchAuthService::invalidateSession();
                        MunowatchAuthService::refreshSession();
                        Log::info("[MunoCrawl] Session refreshed after {$processed} pages");
                    } catch (\Throwable $e) {
                        $this->warn("\nSession refresh failed at page {$processed}: " . $e->getMessage());
                    }
                }
            }
        });

        $bar->finish();
        $this->newLine();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PROCESS ONE PAGE (preview API → movie_models)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch and process one Munowatch movie by its munowatch video ID.
     * Returns ['title', 'result'] on success, null on failure.
     */
    private function processOnePage(int $munowatchId, ?MovieCrawlerPage $page = null): ?array
    {
        // Resolve the crawler page if not provided
        if (!$page) {
            $page = MovieCrawlerPage::where('slug', (string) $munowatchId)
                ->where('is_muno', 'Yes')
                ->first();
        }

        // Create a log entry
        $log = MunoCrawlLog::create([
            'session_id'   => $this->session->id,
            'munowatch_id' => $munowatchId,
            'url'          => $page?->url,
            'status'       => MunoCrawlLog::STATUS_PENDING,
            'video_status' => MunoCrawlLog::VIDEO_PENDING,
        ]);

        if (!$page) {
            $log->update(['status' => MunoCrawlLog::STATUS_FAILED, 'result' => MunoCrawlLog::RESULT_ERROR, 'error_message' => 'No crawler page found for munowatch_id=' . $munowatchId]);
            $this->session->increment('movies_failed');
            return null;
        }

        if ($this->dryRun) {
            $log->update(['status' => MunoCrawlLog::STATUS_SUCCESS, 'result' => 'dry_run', 'title' => $page->title]);
            $this->session->increment('movies_processed');
            return ['title' => $page->title, 'result' => 'dry_run'];
        }

        try {
            // Delegate to the existing MovieCrawlerPage fetch+process pipeline
            $page->fetch_page_content(true);

            $result   = MunoCrawlLog::RESULT_EXISTING;
            $videoSt  = MunoCrawlLog::VIDEO_PENDING;
            $movieId  = null;
            $seriesId = null;

            // Determine result from page status after processing
            if ($page->status === 'success') {
                $movie = \App\Models\MovieModel::where('munowatch_id', $munowatchId)->latest()->first();
                if ($movie) {
                    $movieId = $movie->id;
                    $result  = MunoCrawlLog::RESULT_UPDATED; // may be new_movie — we can't easily distinguish here
                    $videoSt = !empty($movie->url) ? MunoCrawlLog::VIDEO_FOUND : MunoCrawlLog::VIDEO_NOT_FOUND;
                    $this->session->increment('movies_updated');
                } else {
                    $this->session->increment('movies_skipped');
                }
            } elseif ($page->status === 'error') {
                $log->update([
                    'status'        => MunoCrawlLog::STATUS_FAILED,
                    'result'        => MunoCrawlLog::RESULT_ERROR,
                    'error_message' => $page->error_message,
                ]);
                $this->session->increment('movies_failed');
                return null;
            }

            $log->update([
                'status'       => MunoCrawlLog::STATUS_SUCCESS,
                'result'       => $result,
                'title'        => $page->title,
                'movie_id'     => $movieId,
                'series_id'    => $seriesId,
                'video_status' => $videoSt,
            ]);

            $this->session->increment('movies_processed');
            return ['title' => $page->title, 'result' => $result];

        } catch (\Throwable $e) {
            $log->update([
                'status'        => MunoCrawlLog::STATUS_FAILED,
                'result'        => MunoCrawlLog::RESULT_ERROR,
                'error_message' => $e->getMessage(),
            ]);
            $this->session->increment('movies_failed');
            Log::warning("[MunoCrawl] munowatch_id={$munowatchId} failed: " . $e->getMessage());
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function stopSignalReceived(): bool
    {
        return file_exists(storage_path('munowatch_stop'));
    }

    private function handleStop(): void
    {
        @unlink(storage_path('munowatch_stop'));
        $this->session->update(['status' => MunoCrawlSession::STATUS_STOPPED, 'ended_at' => now()]);
        $this->newLine();
        $this->warn("Stop signal received. Session #{$this->session->id} marked Stopped.");
    }

    private function printSummary(): void
    {
        $this->session->refresh();
        $this->newLine();
        $this->info('═══════════════════════════════════════════════');
        $this->info("Session #{$this->session->id} Complete — {$this->session->category_name}");
        $this->info("Pages fetched   : {$this->session->pages_fetched}");
        $this->info("Movies discovered: {$this->session->movies_discovered}");
        $this->info("Movies processed : {$this->session->movies_processed}");
        $this->info("  Updated/New    : {$this->session->movies_updated}");
        $this->info("  Skipped        : {$this->session->movies_skipped}");
        $this->info("  Failed         : {$this->session->movies_failed}");
        $this->info("Duration         : {$this->session->duration}");
        $this->info('═══════════════════════════════════════════════');
    }
}
