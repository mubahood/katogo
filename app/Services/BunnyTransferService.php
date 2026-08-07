<?php

namespace App\Services;

use App\Models\MovieFileTransfer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Transfers movie files to Bunny Storage, recorded on movie_file_transfers
 * (bunny_* columns) so one row tracks a movie's full storage journey:
 * source (original CDN) → dest (Hetzner) → bunny (Bunny CDN).
 *
 * Source fallback when copying: Hetzner (dest_url) first — if unreachable or
 * the stream dies, the movie's main URL, then the original source_url.
 * bunny_source_used records which one succeeded.
 *
 * Filename convention: identical to Hetzner (dest_path) so the public URL is
 * https://{pull_zone}/movies/....  Files are streamed (never touch local
 * disk), verified by size on Bunny (ranged-GET Content-Range) before a row
 * is marked done. movie_models.url is NEVER mutated — serving priority is
 * decided at read time by config('bunny.url_priority').
 */
class BunnyTransferService
{
    private string $zone;
    private string $host;
    private string $accessKey;
    private string $pullHost;

    public function __construct()
    {
        $this->zone      = config('bunny.storage_zone');
        $this->host      = config('bunny.storage_host');
        $this->accessKey = config('bunny.storage_password');
        $this->pullHost  = config('bunny.pull_zone_host');
    }

    public function isConfigured(): bool
    {
        return $this->zone !== '' && $this->accessKey !== '' && $this->pullHost !== '';
    }

    /**
     * Transfer one movie's file to Bunny, trying each source in order.
     * Returns ['success' => bool, 'message' => string, 'bunny_url' => ?string, 'source' => ?string]
     */
    public function transfer(MovieFileTransfer $t): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Bunny credentials not configured (BUNNY_STORAGE_PASSWORD missing).'];
        }

        // Idempotency: done and verified — nothing to do.
        if ($t->bunny_status === 'done' && !empty($t->bunny_url)) {
            return ['success' => true, 'message' => 'Already on Bunny.', 'bunny_url' => $t->bunny_url, 'source' => $t->bunny_source_used];
        }

        // Concurrency guard: another worker is actively uploading this row.
        if ($t->bunny_status === 'uploading'
            && $t->updated_at && $t->updated_at->gt(now()->subMinutes(10))) {
            return ['success' => false, 'message' => 'Upload already in progress by another worker.'];
        }

        $path = ltrim($t->bunny_storage_path ?: $t->dest_path, '/');
        if (empty($path)) {
            // No Hetzner path recorded — build one with the same convention:
            // movies/{transfer}_{Y_m}_movie_{movieId}_{slug}.{ext}
            $srcForExt = $t->dest_url ?: $t->source_url ?: '';
            $ext  = strtolower(pathinfo(parse_url($srcForExt, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'mp4';
            if (!in_array($ext, ['mp4', 'mkv', 'avi', 'webm', 'mov'], true)) {
                $ext = 'mp4';
            }
            $slug = substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($t->movie_title ?? 'movie')), 0, 60);
            $path = sprintf('movies/%d_%s_movie_%d_%s.%s', $t->id, now()->format('Y_m'), $t->movie_id, trim($slug, '_'), $ext);
        }

        // Already sitting on Bunny from an earlier interrupted run? Verify & adopt.
        $existing = $this->remoteSize($path);
        if ($existing !== null && $existing >= 1024 * 100) {
            return $this->markDone($t, $path, $existing, $t->bunny_source_used ?: 'resumed');
        }

        $t->bunny_status       = 'uploading';
        $t->bunny_storage_path = $path;
        $t->bunny_attempts     = ($t->bunny_attempts ?? 0) + 1;
        $t->bunny_error        = null;
        $t->bunny_progress_pct = 0;
        $t->save();

        $errors = [];
        foreach ($this->sourceCandidates($t) as $label => $sourceUrl) {
            $result = $this->uploadFromSource($t, $sourceUrl, $path, $label);
            if ($result === true) {
                $size = $this->remoteSize($path);
                if ($size !== null && $size >= 1024 * 100) {
                    return $this->markDone($t, $path, $size, $label);
                }
                $errors[] = "{$label}: uploaded but verification failed (size=" . var_export($size, true) . ")";
                continue;
            }
            $errors[] = "{$label}: {$result}";
        }

        return $this->fail($t, 'All sources failed → ' . implode(' | ', $errors));
    }

    /**
     * Ordered source URLs to copy from:
     *   hetzner → the movie's main url (if different) → original source_url.
     */
    public function sourceCandidates(MovieFileTransfer $t): array
    {
        $out = [];

        if (!empty($t->dest_url)) {
            $out['hetzner'] = $t->dest_url;
        }

        $movie = DB::table('movie_models')->where('id', $t->movie_id)->first(['url']);
        $main  = $movie->url ?? null;
        if (!empty($main)
            && strlen($main) > 10
            && stripos($main, 'b-cdn.net') === false             // never copy Bunny→Bunny
            && $main !== ($t->dest_url ?? '')) {
            $out['main'] = $main;
        }

        if (!empty($t->source_url) && $t->source_url !== ($out['main'] ?? '') && $t->source_url !== ($out['hetzner'] ?? '')) {
            $out['source'] = $t->source_url;
        }

        return $out;
    }

    /**
     * Stream one source into Bunny storage with live progress on the record.
     * Returns true on HTTP 201, or an error string.
     */
    private function uploadFromSource(MovieFileTransfer $t, string $sourceUrl, string $path, string $label): bool|string
    {
        // Probe the source first: reachable? total size for progress tracking?
        [$srcOk, $srcSize] = $this->probeSource($sourceUrl);
        if (!$srcOk) {
            return 'source unreachable';
        }

        // Hetzner share links often hide the size behind the WebDAV redirect —
        // fall back to the sizes the transfer pipeline already recorded so the
        // progress bar has a denominator instead of sitting at 0%.
        if ($srcSize <= 0) {
            $srcSize = (int) ($t->dest_size_bytes ?: $t->source_size_bytes ?: 0);
        }

        $src = @fopen($sourceUrl, 'rb', false, stream_context_create([
            'http' => ['follow_location' => 1, 'timeout' => 90, 'user_agent' => 'LugaFlix-BunnySync/2.0'],
            'ssl'  => ['verify_peer' => true],
        ]));
        if ($src === false) {
            return 'could not open source stream';
        }

        $t->bunny_source_used = $label;
        $t->save();

        $putUrl       = "https://{$this->host}/{$this->zone}/" . str_replace(' ', '%20', $path);
        $lastDbUpdate = 0;

        $ch = curl_init($putUrl);
        curl_setopt_array($ch, [
            CURLOPT_UPLOAD         => true,
            CURLOPT_INFILE         => $src,
            CURLOPT_HTTPHEADER     => [
                'AccessKey: ' . $this->accessKey,
                'Content-Type: application/octet-stream',
                'Transfer-Encoding: chunked',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 7200,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => function ($res, $dlT, $dl, $upTotal, $up) use ($t, $srcSize, &$lastDbUpdate) {
                if ($srcSize > 0 && $up > 0 && (time() - $lastDbUpdate) >= 4) {
                    $lastDbUpdate = time();
                    // Direct query — avoids model events/observers during the hot loop
                    DB::table('movie_file_transfers')->where('id', $t->id)->update([
                        'bunny_progress_pct' => min(99, (int) round($up / $srcSize * 100)),
                        'updated_at'         => now(),
                    ]);
                }
                return 0;
            },
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        if (is_resource($src)) {
            fclose($src);
        }

        if ($curlErr) {
            return 'cURL: ' . $curlErr;
        }
        if ($httpCode !== 201) {
            return "Bunny PUT HTTP {$httpCode}: " . substr((string) $body, 0, 150);
        }
        return true;
    }

    /** [reachable, totalBytes] via a 1-byte ranged GET against the source. */
    private function probeSource(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RANGE          => '0-0',
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'LugaFlix-BunnySync/2.0',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code < 200 || $code >= 400 || !is_string($resp)) {
            return [false, 0];
        }
        if (preg_match('/Content-Range:\s*bytes\s+0-0\/(\d+)/i', $resp, $m)) {
            return [true, (int) $m[1]];
        }
        if (preg_match('/Content-Length:\s*(\d+)/i', $resp, $m) && (int) $m[1] > 1) {
            return [true, (int) $m[1]];
        }
        return [true, 0]; // reachable, size unknown — progress will just stay at 0
    }

    /**
     * Size of an object in the storage zone, or null if absent.
     * Ranged GET + Content-Range — Bunny returns no usable Content-Length on HEAD.
     */
    public function remoteSize(string $path): ?int
    {
        $url = "https://{$this->host}/{$this->zone}/" . str_replace(' ', '%20', ltrim($path, '/'));
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RANGE          => '0-0',
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => ['AccessKey: ' . $this->accessKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 206 && is_string($resp)
            && preg_match('/Content-Range:\s*bytes\s+0-0\/(\d+)/i', $resp, $m)) {
            return (int) $m[1];
        }
        if ($code === 200 && is_string($resp)
            && preg_match('/Content-Length:\s*(\d+)/i', $resp, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function markDone(MovieFileTransfer $t, string $path, int $size, ?string $source): array
    {
        $t->bunny_status         = 'done';
        $t->bunny_storage_path   = $path;
        $t->bunny_size_bytes     = $size;
        $t->bunny_url            = "https://{$this->pullHost}/" . str_replace(' ', '%20', $path);
        $t->bunny_source_used    = $source;
        $t->bunny_progress_pct   = 100;
        $t->bunny_transferred_at = now();
        $t->bunny_error          = null;
        $t->save();

        Cache::forget('bunny_url_map');

        Log::info("[BunnyTransfer] movie #{$t->movie_id} done via {$source} → {$t->bunny_url} ({$size} bytes)");

        return ['success' => true, 'message' => "Transferred via {$source} and verified.", 'bunny_url' => $t->bunny_url, 'source' => $source];
    }

    private function fail(MovieFileTransfer $t, string $message): array
    {
        $t->bunny_status       = 'failed';
        $t->bunny_error        = mb_substr($message, 0, 1000);
        $t->bunny_progress_pct = 0;
        $t->save();
        Log::warning("[BunnyTransfer] movie #{$t->movie_id} FAILED: {$message}");
        return ['success' => false, 'message' => $message];
    }
}
