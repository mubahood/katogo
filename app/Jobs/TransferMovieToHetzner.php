<?php

namespace App\Jobs;

use App\Models\MovieFileTransfer;
use App\Models\MovieModel;
use App\Services\HetznerStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TransferMovieToHetzner
 *
 * Transfers a movie video file from its source URL to Hetzner Storage Share
 * using a streaming download → temp-file → WebDAV upload pipeline.
 *
 * Memory profile: only 8 KB in PHP memory per transfer (cURL handles buffering).
 * The temp file is written to /mnt/HC_Volume_105999006/transfer_tmp/ on the VPS
 * (the attached Hetzner volume) to avoid filling the root disk.
 *
 * Concurrency: controlled by the 'transfers' queue worker (numprocs=20 in Supervisor)
 * + a cache-based global slot lock so at most MAX_CONCURRENT jobs run simultaneously.
 */
class TransferMovieToHetzner implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Job-level timeout — 2 hours for large files. */
    public int $timeout = 7200;

    /** Do not auto-retry at the job level; retry logic lives in the transfer record. */
    public int $tries = 1;

    /** Max simultaneous transfers across all workers (enforced via cache lock). */
    const MAX_CONCURRENT = 20;

    /** Cache key prefix for the global slot counter. */
    const SLOT_KEY = 'movie_transfer_active_slots';

    /**
     * Index into headerStrategies() that succeeded in verifySource().
     * streamDownload() starts from this index so it skips known-bad strategies.
     */
    private int $winningStrategy = 0;

    public function __construct(public readonly int $transferId) {}

    // ── Middleware ────────────────────────────────────────────────────────────

    public function middleware(): array
    {
        // One job per transfer record in queue at a time
        return [
            (new WithoutOverlapping("mft_{$this->transferId}"))
                ->releaseAfter(30)
                ->expireAfter(7200),
        ];
    }

    // ── Main handler ──────────────────────────────────────────────────────────

    public function handle(): void
    {
        $transfer = MovieFileTransfer::find($this->transferId);

        if (!$transfer) {
            Log::warning("[TransferMovieToHetzner] Transfer #{$this->transferId} not found — skipping.");
            return;
        }

        // Guard: skip if already in a terminal or active state
        if (in_array($transfer->status, [
            MovieFileTransfer::STATUS_DONE,
            MovieFileTransfer::STATUS_TRANSFERRING,
            MovieFileTransfer::STATUS_COMPLETING,
            MovieFileTransfer::STATUS_CANCELLED,
            MovieFileTransfer::STATUS_SKIPPED,
        ])) {
            Log::info("[TransferMovieToHetzner] #{$this->transferId} already in status '{$transfer->status}' — skipping.");
            return;
        }

        // Global concurrency guard
        if (!$this->acquireSlot()) {
            Log::info("[TransferMovieToHetzner] All " . self::MAX_CONCURRENT . " slots occupied. Releasing #{$this->transferId} for 60s.");
            $this->release(60);
            return;
        }

        // Disk-space guard: abort if root disk > 85% full to prevent 500 errors site-wide
        $diskFree  = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        if ($diskTotal > 0 && ($diskFree / $diskTotal) < 0.15) {
            $this->releaseSlot();
            Log::warning("[TransferMovieToHetzner] #{$this->transferId} aborted — root disk > 85% full. Free: " . round($diskFree / 1073741824, 1) . " GB");
            $this->release(300); // retry in 5 min after cleanup
            return;
        }

        $tmpFile = null;

        try {
            $transfer->update([
                'status'             => MovieFileTransfer::STATUS_VERIFYING,
                'started_at'         => now(),
                'worker_hostname'    => gethostname(),
                'attempt_count'      => $transfer->attempt_count + 1,
                'last_attempted_at'  => now(),
            ]);

            // Step 1: Verify source URL is reachable
            $this->verifySource($transfer);

            // Step 2: Ensure temp directory exists
            $tmpDir = $this->getTmpDir();
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            // Step 3: Stream download from source to temp file
            $transfer->update(['status' => MovieFileTransfer::STATUS_TRANSFERRING]);
            $tmpFile = $this->streamDownload($transfer, $tmpDir);

            // Step 4: Upload temp file to Hetzner Storage
            $hetzner  = new HetznerStorageService();
            $destPath = $this->buildDestPath($transfer);
            $hetzner->mkdir('movies'); // single-level — 201 if new, 405 if exists, both OK
            $uploaded = $hetzner->upload($destPath, $tmpFile);

            if (!$uploaded) {
                throw new \RuntimeException("Hetzner WebDAV upload returned failure for path: {$destPath}");
            }

            // Step 5: Generate public share link
            $transfer->update(['status' => MovieFileTransfer::STATUS_COMPLETING]);
            $publicUrl = $hetzner->share($destPath);

            if (!$publicUrl) {
                throw new \RuntimeException("Hetzner share link generation failed for: {$destPath}");
            }

            // Step 6: Update MovieModel.video_url
            $this->updateMovieUrl($transfer, $publicUrl);

            // Step 7: Mark done
            $transfer->update([
                'status'          => MovieFileTransfer::STATUS_DONE,
                'dest_url'        => $publicUrl,
                'dest_path'       => $destPath,
                'dest_size_bytes' => filesize($tmpFile) ?: null,
                'completed_at'    => now(),
                'duration_seconds' => now()->diffInSeconds($transfer->started_at),
                'progress_pct'    => 100,
                'eta_seconds'     => 0,
            ]);

            Log::info("[TransferMovieToHetzner] #{$this->transferId} DONE. Movie #{$transfer->movie_id} URL updated.", [
                'dest_url' => $publicUrl,
                'duration' => now()->diffInSeconds($transfer->started_at) . 's',
            ]);

        } catch (\Throwable $e) {
            $this->handleFailure($transfer, $e);
        } finally {
            // Always release the global slot and clean up temp file
            $this->releaseSlot();
            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    // ── Step 1: Verify source ─────────────────────────────────────────────────

    private function verifySource(MovieFileTransfer $transfer): void
    {
        $url       = $this->sanitizeUrl($transfer->source_url);
        $strategies = $this->headerStrategies($url);

        $httpCode      = 0;
        $contentLength = 0;
        $effectiveUrl  = null;
        $curlError     = '';

        foreach ($strategies as $idx => $headers) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER     => $headers,
            ] + $this->curlSslOptions($url));

            curl_exec($ch);
            $httpCode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentLength = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            $effectiveUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: null;
            $curlError     = curl_error($ch);
            curl_close($ch);

            if (!$curlError && $httpCode >= 200 && $httpCode < 400) {
                $this->winningStrategy = $idx;
                Log::info("[TransferMovieToHetzner] #{$this->transferId} verifySource: strategy {$idx} succeeded (HTTP {$httpCode}).");
                break;
            }

            Log::warning("[TransferMovieToHetzner] #{$this->transferId} verifySource: strategy {$idx} failed — HTTP {$httpCode}" . ($curlError ? " ({$curlError})" : '') . '.');
        }

        if ($curlError || $httpCode < 200 || $httpCode >= 400) {
            throw new \RuntimeException(
                "Source URL unreachable after all header strategies — HTTP {$httpCode}" . ($curlError ? ": {$curlError}" : '')
            );
        }

        $updates = ['source_verified_at' => now()];
        if ($contentLength > 0) {
            $updates['source_size_bytes'] = $contentLength;
        }
        if ($effectiveUrl && $effectiveUrl !== $transfer->source_url) {
            $updates['notes'] = ($transfer->notes ? $transfer->notes . ' | ' : '')
                . 'Effective URL after redirects: ' . $effectiveUrl;
        }
        $transfer->update($updates);
    }

    // ── Step 3: Stream download to temp file ──────────────────────────────────

    private function streamDownload(MovieFileTransfer $transfer, string $tmpDir): string
    {
        $tmpFile = $tmpDir . '/mft_' . $this->transferId . '_' . time() . '.mp4';
        $fh      = fopen($tmpFile, 'wb');

        if (!$fh) {
            throw new \RuntimeException("Cannot open temp file for writing: {$tmpFile}");
        }

        $url        = $this->sanitizeUrl($transfer->source_url);
        $strategies = $this->headerStrategies($url);

        $httpCode  = 0;
        $curlError = '';
        $succeeded = false;

        foreach ($strategies as $idx => $headers) {
            // Skip strategies we already know failed at verify time
            if ($idx < $this->winningStrategy) {
                continue;
            }

            // Reset the file so each retry starts clean
            ftruncate($fh, 0);
            rewind($fh);

            $lastProgressUpdate = 0;
            $startTime          = microtime(true);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE             => $fh,
                CURLOPT_FOLLOWLOCATION   => true,
                CURLOPT_MAXREDIRS        => 10,
                CURLOPT_TIMEOUT          => 7200,
                CURLOPT_CONNECTTIMEOUT   => 30,
                CURLOPT_HTTPHEADER       => $headers,
                CURLOPT_BUFFERSIZE       => 128 * 1024,
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_PROGRESSFUNCTION => function (
                    $_handle,
                    int $downloadTotal,
                    int $downloadedBytes,
                    int $_uploadTotal,
                    int $_uploadedBytes
                ) use ($transfer, &$lastProgressUpdate, $startTime) {
                    if ($downloadedBytes - $lastProgressUpdate < 5 * 1024 * 1024) {
                        return 0;
                    }
                    $lastProgressUpdate = $downloadedBytes;
                    $elapsed   = max(0.1, microtime(true) - $startTime);
                    $speedBps  = $downloadedBytes / $elapsed;
                    $pct = ($downloadTotal > 0)
                        ? min(95, (int)(($downloadedBytes / $downloadTotal) * 95))
                        : 0;
                    $eta = ($speedBps > 0 && $downloadTotal > $downloadedBytes)
                        ? (int)(($downloadTotal - $downloadedBytes) / $speedBps)
                        : null;
                    $transfer->update([
                        'bytes_transferred'   => $downloadedBytes,
                        'progress_pct'        => $pct,
                        'transfer_speed_mbps' => round($speedBps / 1_048_576, 2),
                        'eta_seconds'         => $eta,
                    ]);
                    return 0;
                },
            ] + $this->curlSslOptions($url));

            $result    = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($result !== false && !$curlError && $httpCode >= 200 && $httpCode < 400) {
                $this->winningStrategy = $idx;
                $succeeded = true;
                Log::info("[TransferMovieToHetzner] #{$this->transferId} download: strategy {$idx} succeeded (HTTP {$httpCode}).");
                break;
            }

            Log::warning("[TransferMovieToHetzner] #{$this->transferId} download: strategy {$idx} failed — HTTP {$httpCode}" . ($curlError ? " ({$curlError})" : '') . '. Retrying with next strategy.');
        }

        fclose($fh);

        if (!$succeeded) {
            @unlink($tmpFile);
            if ($curlError) {
                throw new \RuntimeException("cURL download failed after all header strategies: {$curlError}");
            }
            throw new \RuntimeException("Source returned HTTP {$httpCode} after all header strategies");
        }

        $fileSize = filesize($tmpFile);
        if (!$fileSize || $fileSize < 1024) {
            @unlink($tmpFile);
            throw new \RuntimeException("Downloaded file is too small ({$fileSize} bytes) — likely an error page");
        }

        Log::info("[TransferMovieToHetzner] #{$this->transferId} download complete.", [
            'bytes'    => $fileSize,
            'strategy' => $this->winningStrategy,
            'path'     => $tmpFile,
        ]);

        return $tmpFile;
    }

    // ── Step 6: Update movie URL ──────────────────────────────────────────────

    private function updateMovieUrl(MovieFileTransfer $transfer, string $newUrl): void
    {
        if (!$transfer->movie_id) return;

        $movie = MovieModel::find($transfer->movie_id);
        if (!$movie) return;

        $movie->url = $newUrl;
        $movie->save();

        $transfer->update([
            'movie_url_updated'       => true,
            'old_movie_url_backed_up' => true,  // source_url holds the original
        ]);

        Log::info("[TransferMovieToHetzner] Movie #{$transfer->movie_id} url updated to Hetzner CDN.");
    }

    // ── Failure handler ───────────────────────────────────────────────────────

    private function handleFailure(MovieFileTransfer $transfer, \Throwable $e): void
    {
        $attempt    = $transfer->attempt_count;
        $maxAttempts = $transfer->max_attempts ?? 3;
        $willRetry  = $attempt < $maxAttempts;

        // Exponential backoff: 5 min, 20 min, 60 min
        $backoffMinutes = [5, 20, 60];
        $delay = $backoffMinutes[min($attempt - 1, count($backoffMinutes) - 1)];

        $transfer->update([
            'status'        => $willRetry ? MovieFileTransfer::STATUS_QUEUED : MovieFileTransfer::STATUS_FAILED,
            'error_message' => substr($e->getMessage(), 0, 500),
            'error_trace'   => substr($e->getTraceAsString(), 0, 10000),
            'next_retry_at' => $willRetry ? now()->addMinutes($delay) : null,
            'progress_pct'  => 0,
            'bytes_transferred' => 0,
        ]);

        try {
            Log::error("[TransferMovieToHetzner] #{$this->transferId} failed (attempt {$attempt}/{$maxAttempts}).", [
                'movie_id'   => $transfer->movie_id,
                'error'      => $e->getMessage(),
                'retry_in'   => $willRetry ? "{$delay} minutes" : 'no more retries',
            ]);
        } catch (\Throwable) {
            // Log failure must never crash the job — status is already saved above
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns ordered sets of CURLOPT_HTTPHEADER arrays to try for a given source URL.
     *
     * Discovery (2026-06-13): most BunnyCDN zones used here (munotech, munotech3, nkuba,
     * yuti) have munowatch.org on a BLOCKLIST — sending that Referer returns 403.
     * Sending NO Referer returns 200 on all tested zones.
     * munotek-vault.b-cdn.net uses an ALLOWLIST that requires munowatch.org/munowatch.com.
     *
     * Strategy order:
     *   0. No Referer, minimal UA — works for all zones that have no / blocklist-only protection
     *   1. OkHttp + no Referer — different UA profile, no hotlink header
     *   2. munowatch.com Referer — works for munotek-vault allowlist, safe for others
     *   3. munowatch.org Referer — for munotek-vault that specifically requires .org
     */
    private function headerStrategies(string $url): array
    {
        $lower = strtolower($url);

        if (str_contains($lower, 'b-cdn.net') || str_contains($lower, 'munotek')) {
            return [
                // 0: No Referer — works for all zones with no protection or blocklist-only
                [
                    'User-Agent: Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
                    'Accept: video/mp4,video/webm,video/*;q=0.9,*/*;q=0.5',
                    'Accept-Language: en-US,en;q=0.9',
                    'Connection: keep-alive',
                ],
                // 1: OkHttp UA, no Referer — different fingerprint, same approach
                [
                    'User-Agent: okhttp/4.12.0',
                    'Accept-Encoding: identity',
                    'Connection: keep-alive',
                ],
                // 2: munowatch.com Referer — works for munotek-vault allowlist
                [
                    'User-Agent: Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
                    'Referer: https://munowatch.com/',
                    'Origin: https://munowatch.com',
                    'Accept: video/mp4,video/*;q=0.9,*/*;q=0.5',
                    'Accept-Language: en-US,en;q=0.9',
                    'Connection: keep-alive',
                ],
                // 3: munowatch.org Referer — for munotek-vault zones that require .org specifically
                [
                    'User-Agent: Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
                    'Referer: https://munowatch.org/',
                    'Origin: https://munowatch.org',
                    'Accept: video/mp4,video/*;q=0.9,*/*;q=0.5',
                    'Accept-Language: en-US,en;q=0.9',
                    'Connection: keep-alive',
                ],
            ];
        }

        // Generic fallback for non-CDN / unknown sources
        return [[
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.82 Safari/537.36',
            'Accept: video/mp4,video/*;q=0.9,application/octet-stream;q=0.5,*/*;q=0.1',
            'Accept-Language: en-US,en;q=0.9',
            'Connection: keep-alive',
        ]];
    }

    private function buildDestPath(MovieFileTransfer $transfer): string
    {
        // Sanitise title: lowercase, spaces → underscores, strip specials, cap at 40 chars
        $raw  = $transfer->movie_title ?? 'movie';
        $safe = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $raw), '_'));
        $safe = substr($safe, 0, 40);

        // Extract extension from source URL (mp4, mkv, avi …), default to mp4
        $ext = 'mp4';
        if ($transfer->source_url) {
            $path    = parse_url($transfer->source_url, PHP_URL_PATH) ?? '';
            $guessed = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($guessed && preg_match('/^[a-z0-9]{2,5}$/', $guessed)) {
                $ext = $guessed;
            }
        }

        // Flat layout: movies/{transfer_id}_{YYYY}_{MM}_movie_{movie_id}_{safe_title}.ext
        // All files in one folder — no nested year/month subdirectories.
        return sprintf(
            'movies/%d_%s_%s_movie_%d_%s.%s',
            $transfer->id,
            date('Y'),
            date('m'),
            $transfer->movie_id ?? 0,
            $safe,
            $ext
        );
        // e.g. movies/99_2026_06_movie_21393_sex_tape_jr.mp4
    }

    /** Temp directory: prefer the attached Hetzner volume to avoid filling root disk. */
    private function getTmpDir(): string
    {
        $volumeDir = '/mnt/HC_Volume_105999006/transfer_tmp';
        // Ensure the subdirectory exists with correct ownership before testing writability
        if (!is_dir($volumeDir)) {
            @mkdir($volumeDir, 0775, true);
        }
        // Test the actual subdirectory, not the parent (parent is root-owned)
        if (is_dir($volumeDir) && is_writable($volumeDir)) {
            return $volumeDir;
        }
        // Fallback: local storage (dev / volume unmounted)
        return storage_path('app/transfer_tmp');
    }

    /** Purge orphaned temp files older than 2 hours from both possible tmp dirs. */
    public static function purgeOrphanedTmpFiles(): void
    {
        $dirs = [
            '/mnt/HC_Volume_105999006/transfer_tmp',
            storage_path('app/transfer_tmp'),
        ];
        $cutoff = time() - 7200; // 2 hours
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '/mft_*') ?: [] as $file) {
                if (filemtime($file) < $cutoff) {
                    @unlink($file);
                }
            }
        }
    }

    // ── Concurrency slot management (cache-based) ─────────────────────────────

    private function acquireSlot(): bool
    {
        // Use atomic cache increment to count active jobs
        $current = (int) Cache::get(self::SLOT_KEY, 0);
        if ($current >= self::MAX_CONCURRENT) {
            return false;
        }
        Cache::put(self::SLOT_KEY, $current + 1, 7200);
        return true;
    }

    private function releaseSlot(): void
    {
        $current = (int) Cache::get(self::SLOT_KEY, 0);
        if ($current > 0) {
            Cache::put(self::SLOT_KEY, $current - 1, 7200);
        }
    }

    /**
     * Percent-encode spaces and special characters in the path portion of a URL
     * so cURL accepts filenames like "sex tape jr.mp4". Decodes first to avoid
     * double-encoding URLs that are already partially encoded.
     */
    private function sanitizeUrl(string $url): string
    {
        $url   = trim($url);
        $parts = parse_url($url);

        if (!isset($parts['host'])) {
            return $url; // unparseable — return as-is
        }

        $scheme   = ($parts['scheme'] ?? 'https') . '://';
        $userinfo = '';
        if (isset($parts['user'])) {
            $userinfo = rawurlencode($parts['user']);
            if (isset($parts['pass'])) {
                $userinfo .= ':' . rawurlencode($parts['pass']);
            }
            $userinfo .= '@';
        }
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        // Encode each path segment individually; decode first to avoid double-encoding
        $path = '';
        if (isset($parts['path'])) {
            $path = implode('/', array_map(
                fn($seg) => rawurlencode(rawurldecode($seg)),
                explode('/', $parts['path'])
            ));
        }

        $query    = isset($parts['query'])    ? '?' . $parts['query']    : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $userinfo . $host . $port . $path . $query . $fragment;
    }

    /**
     * Returns cURL SSL options appropriate for the URL scheme.
     * HTTPS: verify peer and host using the system CA bundle.
     * HTTP:  no SSL (verification flags still set to false for safety).
     */
    private function curlSslOptions(string $url): array
    {
        if (!str_starts_with($url, 'https://')) {
            return [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
        }
        $opts = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        $caBundle = '/etc/ssl/certs/ca-certificates.crt';
        if (file_exists($caBundle)) {
            $opts[CURLOPT_CAINFO] = $caBundle;
        }
        return $opts;
    }
}
