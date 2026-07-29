<?php

namespace App\Console\Commands;

use App\Models\MovieModel;
use App\Models\Utils;
use App\Services\MunowatchAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * MunoFixBroken — Re-fetches video URLs from munowatch for broken/deactivated munowatch movies.
 *
 * Targets two populations:
 *   1. Movies with is_muno=Yes, munowatch_id set, status=Inactive (deactivated — no valid URL)
 *   2. Movies with url LIKE '%eli.mp4%' (dummy placeholder URL)
 *
 * For each, calls the munowatch preview API and updates the URL if a valid one is found,
 * then re-activates the movie.
 *
 * Usage:
 *   php artisan munowatch:fix-broken
 *   php artisan munowatch:fix-broken --type=dummy
 *   php artisan munowatch:fix-broken --type=deactivated --limit=500
 *   php artisan munowatch:fix-broken --dry-run
 */
class MunoFixBroken extends Command
{
    protected $signature = 'munowatch:fix-broken
                            {--type=all          : Which movies to fix: "deactivated", "dummy", or "all"}
                            {--limit=200         : Max movies to process per run}
                            {--delay=400         : Milliseconds between API requests}
                            {--dry-run           : Show what would be done without writing to DB}';

    protected $description = 'Re-fetch video URLs from munowatch for deactivated/dummy-URL movies';

    private bool $dryRun = false;
    private int  $fixed  = 0;
    private int  $failed = 0;
    private int  $skipped = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $type         = $this->option('type');
        $limit        = (int) $this->option('limit');
        $delay        = (int) $this->option('delay');

        $this->info('MunoFixBroken — re-fetch URLs from munowatch');
        if ($this->dryRun) {
            $this->warn('DRY-RUN mode — no writes');
        }

        // ── Authenticate first ──────────────────────────────────────────────
        try {
            $session = MunowatchAuthService::getActiveSession();
            $this->info("Auth OK — user_id={$session['user_id']} session=" . substr($session['session_id'], 0, 8) . '…');
        } catch (\Throwable $e) {
            $this->error('Munowatch auth failed: ' . $e->getMessage());
            return 1;
        }

        // ── Build query ──────────────────────────────────────────────────────
        $query = MovieModel::where('is_muno', 'Yes')
            ->whereNotNull('munowatch_id')
            ->where('munowatch_id', '!=', '');

        if ($type === 'deactivated') {
            $query->where('status', 'Inactive');
        } elseif ($type === 'dummy') {
            $query->where('url', 'like', '%eli.mp4%');
        } else {
            // all: deactivated OR dummy URL
            $query->where(function ($q) {
                $q->where('status', 'Inactive')
                  ->orWhere('url', 'like', '%eli.mp4%');
            });
        }

        $total = $query->count();
        $toProcess = $limit > 0 ? min($total, $limit) : $total;
        $this->info("Found {$total} candidates — processing {$toProcess}");

        if ($toProcess === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        $bar = $this->output->createProgressBar($toProcess);
        $bar->start();

        $processed = 0;

        $query->orderBy('id', 'asc')
            ->limit($toProcess)
            ->chunk(50, function ($movies) use (&$processed, $toProcess, $delay, $bar) {
                foreach ($movies as $movie) {
                    if ($processed >= $toProcess) return false;

                    $this->processOne($movie);
                    $processed++;
                    $bar->advance();

                    if ($delay > 0 && $processed < $toProcess) {
                        usleep($delay * 1000);
                    }
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done — Fixed: {$this->fixed}  Skipped: {$this->skipped}  Failed: {$this->failed}  Total: {$processed}");

        return 0;
    }

    private function processOne(MovieModel $movie): void
    {
        $munowatchId = $movie->munowatch_id;

        try {
            $session = MunowatchAuthService::getActiveSession();
            $url     = "https://munowatch.org/api/preview/v2/{$munowatchId}/{$session['user_id']}";
            $headers = [
                'Authorization' => 'Bearer ' . $session['app_jwt'],
                'X-Api-Key'     => $session['app_jwt'],
                'X-Session-Id'  => $session['session_id'],
                'User-Agent'    => 'okhttp/4.9.0',
            ];

            $raw = Utils::get_url_with_auth($url, $headers);

            // Retry on auth failure
            if (!empty($raw) && MunowatchAuthService::isAuthFailure($raw)) {
                MunowatchAuthService::invalidateSession();
                $session = MunowatchAuthService::refreshSession();
                $headers['Authorization'] = 'Bearer ' . $session['app_jwt'];
                $headers['X-Api-Key']     = $session['app_jwt'];
                $headers['X-Session-Id']  = $session['session_id'];
                $url = "https://munowatch.org/api/preview/v2/{$munowatchId}/{$session['user_id']}";
                $raw = Utils::get_url_with_auth($url, $headers);
            }

            if (empty($raw)) {
                $this->failed++;
                Log::warning("[MunoFixBroken] Empty response for munowatch_id={$munowatchId} movie_id={$movie->id}");
                return;
            }

            $json = json_decode(trim($raw), true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($json['preview'])) {
                $this->failed++;
                return;
            }

            $preview    = $json['preview'];
            $playingUrl = $preview['playingUrl'] ?? '';

            if (empty($playingUrl) || !$this->isHealthyUrl($playingUrl)) {
                // Try embedUrl as fallback
                $playingUrl = $preview['embedurl'] ?? '';
            }

            if (empty($playingUrl) || !$this->isHealthyUrl($playingUrl)) {
                $this->skipped++;
                Log::info("[MunoFixBroken] No valid URL for munowatch_id={$munowatchId} movie_id={$movie->id}");
                return;
            }

            if ($this->dryRun) {
                $this->line("\n  [DRY] #{$movie->id} \"{$movie->title}\" → " . $playingUrl);
                $this->fixed++;
                return;
            }

            $movie->url    = $playingUrl;
            $movie->status = 'Active';
            $movie->muno_message = 'URL restored by munowatch:fix-broken';
            $movie->save();

            $this->fixed++;
            Log::info("[MunoFixBroken] Restored movie #{$movie->id} \"{$movie->title}\" → {$playingUrl}");

        } catch (\Throwable $e) {
            $this->failed++;
            Log::error("[MunoFixBroken] Exception for movie #{$movie->id}: " . $e->getMessage());
        }
    }

    private function isHealthyUrl(?string $url): bool
    {
        if (empty($url) || strlen(trim($url)) < 8) return false;
        if (stripos($url, 'eli.mp4')         !== false) return false;
        if (stripos($url, 'googleapis')      !== false) return false;
        if (stripos($url, 'firebasestorage') !== false) return false;
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        return true;
    }
}
