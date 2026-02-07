<?php

namespace App\Admin\Controllers;

use App\Services\MovieFixerService;
use App\Services\VideoUrlTester;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Debug Player Proxy Controller
 *
 * Thin controller that delegates URL testing to VideoUrlTester service.
 * Provides three endpoints:
 *   - POST proxy()     — test video URLs (single or all for a movie)
 *   - POST fixMovie()  — re-fetch movie data from source & repair broken records
 *   - GET  stream()    — stream video through server (bypasses CDN hotlink protection)
 *
 * @see \App\Services\VideoUrlTester   Reusable URL testing logic
 * @see \App\Services\MovieFixerService Reusable movie fix logic
 */
class DebugPlayerProxyController extends Controller
{
    protected VideoUrlTester $tester;

    public function __construct()
    {
        $this->tester = new VideoUrlTester();
    }

    /**
     * Test a video URL using server-side cURL.
     *
     * Modes:
     *  - test_all + movie_id  →  fetch movie from DB, test its url field
     *  - url only             →  dual-attempt strategy (browser UA then okhttp)
     */
    public function proxy(Request $request)
    {
        $url     = $request->input('url');
        $movieId = $request->input('movie_id');
        $testAll = $request->boolean('test_all', false);

        if (empty($url) && empty($movieId)) {
            return response()->json([
                'success' => false,
                'error'   => 'No URL or movie_id provided',
            ]);
        }

        // ── Test ALL URLs for a movie ────────────────────────
        if ($testAll && $movieId) {
            return $this->proxyTestAll($movieId);
        }

        // ── Single URL — dual-attempt strategy ───────────────
        if (empty($url)) {
            return response()->json(['success' => false, 'error' => 'No URL provided']);
        }

        $result = $this->tester->testUrlDualAttempt($url);

        return response()->json($result);
    }

    /**
     * Test all URL fields for a movie record.
     */
    private function proxyTestAll(int|string $movieId)
    {
        $movie = \App\Models\MovieModel::find($movieId);
        if (!$movie) {
            return response()->json(['success' => false, 'error' => 'Movie not found']);
        }

        $results = [];
        $val = trim($movie->url ?? '');

        if (!empty($val) && strlen($val) > 5) {
            $result = $this->tester->testUrl($val, [], 'url');
            $result['source']       = 'url';
            $result['original_url'] = $val;
            $results[] = $result;
        }

        return response()->json([
            'success'  => true,
            'mode'     => 'test_all',
            'movie_id' => $movieId,
            'results'  => $results,
        ]);
    }

    /**
     * Fix a movie by re-fetching data from its original source.
     *
     * Accepts single movie_id or array of movie_ids for batch processing.
     * Returns updated movie data so the debug player can refresh without page reload.
     */
    public function fixMovie(Request $request)
    {
        $movieId  = $request->input('movie_id');
        $movieIds = $request->input('movie_ids'); // batch mode

        $fixer = new MovieFixerService();

        if (!empty($movieIds) && is_array($movieIds)) {
            $result = $fixer->fixBatch($movieIds);
            return response()->json($result);
        }

        if (empty($movieId)) {
            return response()->json(['success' => false, 'message' => 'No movie_id provided']);
        }

        $result = $fixer->fix((int) $movieId);

        return response()->json($result);
    }

    /**
     * Stream a video through the server.
     *
     * The <video> element loads from this endpoint instead of the CDN directly.
     * This bypasses:
     * - BunnyCDN hotlink protection (no Referer header sent from server)
     * - CORS restrictions (same-origin request from browser)
     * - Mixed content issues (HTTP CDN → HTTPS admin page)
     *
     * Supports Range requests for seeking.
     */
    public function stream(Request $request)
    {
        $url = $request->input('url');
        if (empty($url)) {
            abort(400, 'No URL provided');
        }

        // Simple token check — prevents unauthorized use of the stream proxy
        $token = $request->input('token');
        $expectedToken = substr(sha1('debug-stream-' . config('app.key')), 0, 32);
        if (!$token || !hash_equals($expectedToken, $token)) {
            abort(403, 'Invalid stream token');
        }

        // Build list of URLs to try: original + CDN fallback for dead hostnames
        $urlsToTry = [$url];
        $fallback  = VideoUrlTester::getCdnFallbackUrl($url);
        if ($fallback) {
            $urlsToTry[] = $fallback;
        }

        // HEAD test each URL until one succeeds
        $workingUrl  = null;
        $httpCode    = 0;
        $contentType = 'video/mp4';
        $totalSize   = 0;

        foreach ($urlsToTry as $tryUrl) {
            $result = $this->tester->setTimeout(10)->setConnectTimeout(5)->testUrl($tryUrl);

            $httpCode = $result['http_code'];

            if ($result['success']) {
                $workingUrl  = $result['effective_url'] ?? $tryUrl;
                $contentType = $result['content_type'] ?: 'video/mp4';
                $totalSize   = $result['content_length'] ?? 0;
                break;
            }
        }

        // Restore default timeouts
        $this->tester->setTimeout(20)->setConnectTimeout(10);

        if (!$workingUrl) {
            abort(502, 'Upstream returned HTTP ' . $httpCode . ' for all URLs tried');
        }

        // Check for Range header (browser seeking)
        $rangeHeader = $request->header('Range');
        $start = 0;
        $end   = $totalSize > 0 ? $totalSize - 1 : 0;

        if ($rangeHeader && $totalSize > 0 && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $m)) {
            $start = (int) $m[1];
            $end   = !empty($m[2]) ? (int) $m[2] : $totalSize - 1;
            if ($start > $end || $start >= $totalSize) {
                return response('', 416)->header('Content-Range', 'bytes */' . $totalSize);
            }
        }

        $length    = $end - $start + 1;
        $isPartial = $rangeHeader && $totalSize > 0;

        // Build response headers
        $headers = [
            'Content-Type'                => $contentType,
            'Accept-Ranges'               => 'bytes',
            'Cache-Control'               => 'no-cache',
            'Access-Control-Allow-Origin' => '*',
        ];
        if ($totalSize > 0) {
            $headers['Content-Length'] = $length;
            if ($isPartial) {
                $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $totalSize;
            }
        }

        $statusCode = $isPartial ? 206 : 200;

        // Stream the video body using cURL
        return response()->stream(function () use ($workingUrl, $start, $end, $isPartial) {
            $ch = curl_init();
            $curlHeaders = [
                'User-Agent: Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            ];
            if ($isPartial) {
                $curlHeaders[] = 'Range: bytes=' . $start . '-' . $end;
            }
            curl_setopt_array($ch, [
                CURLOPT_URL            => $workingUrl,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_REFERER        => '',
                CURLOPT_HTTPHEADER     => $curlHeaders,
                CURLOPT_WRITEFUNCTION  => function ($ch, $data) {
                    echo $data;
                    if (ob_get_level()) ob_flush();
                    flush();
                    return strlen($data);
                },
            ]);
            curl_exec($ch);
            curl_close($ch);
        }, $statusCode, $headers);
    }
}
