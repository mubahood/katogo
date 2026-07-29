<?php

namespace App\Console\Commands;

use App\Models\MovieModel;
use App\Services\MunowatchAuthService;
use App\Models\Utils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Populates old_video_url for Hetzner-hosted movies that don't have one.
 *
 * During Hetzner StorageShare maintenance, MovieModel::applyStorageFallback()
 * uses old_video_url as the working URL. This command fetches the original
 * CDN URL from the MunoWatch API (preview/v2/{munowatch_id}) and stores it
 * in old_video_url so the fallback has something to serve.
 *
 * Run once, then re-run until 0 remain:
 *   php artisan storage:backfill-fallback
 *   php artisan storage:backfill-fallback --limit=2000
 *   php artisan storage:backfill-fallback --dry-run
 */
class StorageBackfillFallback extends Command
{
    protected $signature = 'storage:backfill-fallback
                            {--host=nx100800.your-storageshare.de : Affected storage host}
                            {--limit=500 : Max movies to process per run}
                            {--dry-run : Show what would be updated without saving}';

    protected $description = 'Backfill old_video_url from MunoWatch API for Hetzner-hosted movies missing a fallback URL';

    private int $filled  = 0;
    private int $skipped = 0;
    private int $failed  = 0;

    public function handle(): int
    {
        $host   = $this->option('host');
        $limit  = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        // Movies on the affected host that have NO usable old_video_url
        $movies = MovieModel::where('url', 'like', "%{$host}%")
            ->where('status', 'Active')
            ->where(function ($q) use ($host) {
                $q->whereNull('old_video_url')
                  ->orWhere('old_video_url', '')
                  ->orWhere('old_video_url', 'like', "%{$host}%");
            })
            ->whereNotNull('munowatch_id')
            ->where('munowatch_id', '!=', '')
            ->limit($limit)
            ->get(['id', 'title', 'url', 'old_video_url', 'munowatch_id']);

        $total = count($movies);

        if ($total === 0) {
            $this->info('Nothing to backfill — all Hetzner movies already have a fallback URL.');
            return 0;
        }

        $remaining = DB::table('movie_models')
            ->where('url', 'like', "%{$host}%")
            ->where('status', 'Active')
            ->where(function ($q) use ($host) {
                $q->whereNull('old_video_url')
                  ->orWhere('old_video_url', '')
                  ->orWhere('old_video_url', 'like', "%{$host}%");
            })
            ->whereNotNull('munowatch_id')
            ->where('munowatch_id', '!=', '')
            ->count();

        $this->info("Hetzner movies missing fallback URL: {$remaining} total");
        $this->info("Processing up to {$limit} in this run" . ($dryRun ? ' [DRY RUN]' : '') . "\n");

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — filled: %filled% | skipped: %skipped% | failed: %failed%');
        $bar->setMessage('0', 'filled');
        $bar->setMessage('0', 'skipped');
        $bar->setMessage('0', 'failed');
        $bar->start();

        foreach ($movies as $movie) {
            $this->processOne($movie, $dryRun);
            $bar->setMessage((string) $this->filled,  'filled');
            $bar->setMessage((string) $this->skipped, 'skipped');
            $bar->setMessage((string) $this->failed,  'failed');
            $bar->advance();
            usleep(100_000); // 100ms — be gentle on the MunoWatch API
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done — Filled: {$this->filled}  Skipped: {$this->skipped}  Failed: {$this->failed}");

        if ($remaining > $limit) {
            $leftOver = $remaining - $this->filled - $this->skipped - $this->failed;
            $this->comment("Still ~{$leftOver} movies without a fallback. Run again to continue.");
        }

        return 0;
    }

    private function processOne(MovieModel $movie, bool $dryRun): void
    {
        $munowatchId = $movie->munowatch_id;

        try {
            $session = MunowatchAuthService::getActiveSession();
            $apiUrl  = "https://munowatch.org/api/preview/v2/{$munowatchId}/{$session['user_id']}";
            $headers = [
                'Authorization' => 'Bearer ' . $session['app_jwt'],
                'X-Api-Key'     => $session['app_jwt'],
                'X-Session-Id'  => $session['session_id'],
                'User-Agent'    => 'okhttp/4.9.0',
            ];

            $raw = Utils::get_url_with_auth($apiUrl, $headers);

            // Retry on auth failure
            if (!empty($raw) && MunowatchAuthService::isAuthFailure($raw)) {
                MunowatchAuthService::invalidateSession();
                $session = MunowatchAuthService::refreshSession();
                $headers['Authorization'] = 'Bearer ' . $session['app_jwt'];
                $headers['X-Api-Key']     = $session['app_jwt'];
                $headers['X-Session-Id']  = $session['session_id'];
                $apiUrl = "https://munowatch.org/api/preview/v2/{$munowatchId}/{$session['user_id']}";
                $raw = Utils::get_url_with_auth($apiUrl, $headers);
            }

            if (empty($raw)) {
                $this->failed++;
                Log::warning("[StorageBackfill] Empty response for munowatch_id={$munowatchId} movie_id={$movie->id}");
                return;
            }

            $json = json_decode(trim($raw), true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($json['preview'])) {
                $this->failed++;
                return;
            }

            $preview    = $json['preview'];
            $fallbackUrl = $preview['playingUrl'] ?? '';

            // Secondary fallback from embedurl
            if (empty($fallbackUrl) || !$this->isUsableUrl($fallbackUrl)) {
                $fallbackUrl = $preview['embedurl'] ?? '';
            }

            if (empty($fallbackUrl) || !$this->isUsableUrl($fallbackUrl)) {
                $this->skipped++;
                return;
            }

            if ($dryRun) {
                $this->line("\n  [DRY] #{$movie->id} \"{$movie->title}\" → {$fallbackUrl}");
                $this->filled++;
                return;
            }

            // Store in old_video_url — does NOT touch url (keeps Hetzner URL for later)
            DB::table('movie_models')
                ->where('id', $movie->id)
                ->update(['old_video_url' => $fallbackUrl, 'updated_at' => now()]);

            $this->filled++;
            Log::info("[StorageBackfill] Backfilled #{$movie->id} \"{$movie->title}\" → {$fallbackUrl}");

        } catch (\Throwable $e) {
            $this->failed++;
            Log::error("[StorageBackfill] Exception for movie #{$movie->id}: " . $e->getMessage());
        }
    }

    private function isUsableUrl(string $url): bool
    {
        if (strlen(trim($url)) < 10) return false;
        if (stripos($url, 'eli.mp4')          !== false) return false;
        if (stripos($url, 'googleapis')        !== false) return false;
        if (stripos($url, 'firebasestorage')   !== false) return false;
        return true;
    }
}
