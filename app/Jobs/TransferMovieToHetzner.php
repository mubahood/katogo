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
 * Concurrency: controlled by the 'transfers' queue worker (numprocs=2 in Supervisor)
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
    const MAX_CONCURRENT = 2;

    /** Cache key prefix for the global slot counter. */
    const SLOT_KEY = 'movie_transfer_active_slots';

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
            $hetzner   = new HetznerStorageService();
            $destPath  = $this->buildDestPath($transfer);
            $hetzner->mkdir('movies/' . date('Y') . '/' . date('m'));
            $uploaded  = $hetzner->upload($destPath, $tmpFile);

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
        $url = $this->sanitizeUrl($transfer->source_url);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KatogoTransfer/1.0)',
        ] + $this->curlSslOptions($url));

        curl_exec($ch);
        $httpCode      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $effectiveUrl  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $curlError     = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode < 200 || $httpCode >= 400) {
            throw new \RuntimeException(
                "Source URL unreachable — HTTP {$httpCode}" . ($curlError ? ": {$curlError}" : '')
            );
        }

        $updates = ['source_verified_at' => now()];
        if ($contentLength > 0) {
            $updates['source_size_bytes'] = $contentLength;
        }
        // If the effective URL after redirects differs, store the sanitized version
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

        $lastProgressUpdate = 0;
        $startTime          = microtime(true);

        $url = $this->sanitizeUrl($transfer->source_url);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 7200,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; KatogoTransfer/1.0)',
            CURLOPT_BUFFERSIZE     => 128 * 1024,
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => function (
                $_handle,
                int $downloadTotal,
                int $downloadedBytes,
                int $_uploadTotal,
                int $_uploadedBytes
            ) use ($transfer, &$lastProgressUpdate, $startTime) {
                // Update progress every 5 MB to avoid excessive DB writes
                if ($downloadedBytes - $lastProgressUpdate < 5 * 1024 * 1024) {
                    return 0; // returning non-zero aborts the transfer
                }
                $lastProgressUpdate = $downloadedBytes;

                $elapsed  = max(0.1, microtime(true) - $startTime);
                $speedBps = $downloadedBytes / $elapsed;
                $speedMbps = round($speedBps / 1_048_576, 2);

                $pct = ($downloadTotal > 0)
                    ? min(95, (int)(($downloadedBytes / $downloadTotal) * 95))
                    : 0;

                $eta = ($speedBps > 0 && $downloadTotal > $downloadedBytes)
                    ? (int)(($downloadTotal - $downloadedBytes) / $speedBps)
                    : null;

                $transfer->update([
                    'bytes_transferred'   => $downloadedBytes,
                    'progress_pct'        => $pct,
                    'transfer_speed_mbps' => $speedMbps,
                    'eta_seconds'         => $eta,
                ]);

                return 0; // must return 0 to continue
            },
        ] + $this->curlSslOptions($url));

        $result    = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if ($result === false || $curlError) {
            @unlink($tmpFile);
            throw new \RuntimeException("cURL download failed: {$curlError}");
        }

        if ($httpCode < 200 || $httpCode >= 400) {
            @unlink($tmpFile);
            throw new \RuntimeException("Source returned HTTP {$httpCode} during download");
        }

        $fileSize = filesize($tmpFile);
        if (!$fileSize || $fileSize < 1024) {
            @unlink($tmpFile);
            throw new \RuntimeException("Downloaded file is too small ({$fileSize} bytes) — likely an error page");
        }

        Log::info("[TransferMovieToHetzner] #{$this->transferId} download complete.", [
            'bytes' => $fileSize,
            'path'  => $tmpFile,
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

        Log::error("[TransferMovieToHetzner] #{$this->transferId} failed (attempt {$attempt}/{$maxAttempts}).", [
            'movie_id'   => $transfer->movie_id,
            'error'      => $e->getMessage(),
            'retry_in'   => $willRetry ? "{$delay} minutes" : 'no more retries',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildDestPath(MovieFileTransfer $transfer): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '_', $transfer->movie_title ?? 'movie');
        $safe = strtolower(substr($safe, 0, 50));
        $safe = trim($safe, '_');

        return sprintf(
            'movies/%s/%s/movie_%d_%s_%d.mp4',
            date('Y'),
            date('m'),
            $transfer->movie_id ?? 0,
            $safe,
            $transfer->id
        );
        // e.g. movies/2026/06/movie_12345_akaboozi_mu_kibuga_99.mp4
    }

    /** Temp directory: prefer the attached Hetzner volume to avoid filling root disk. */
    private function getTmpDir(): string
    {
        $volumeDir = '/mnt/HC_Volume_105999006/transfer_tmp';
        if (is_dir('/mnt/HC_Volume_105999006') && is_writable('/mnt/HC_Volume_105999006')) {
            return $volumeDir;
        }
        // Fallback to system temp (local dev / other environments)
        return storage_path('app/transfer_tmp');
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
