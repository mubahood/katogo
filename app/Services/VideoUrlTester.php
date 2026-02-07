<?php

namespace App\Services;

/**
 * VideoUrlTester — Reusable service for testing video URL accessibility.
 *
 * Provides server-side cURL-based URL testing that mimics mobile app behaviour.
 * Can be used by any controller, command, or job to verify video URLs.
 *
 * Usage:
 *   $tester = new VideoUrlTester();
 *
 *   // Test a single URL
 *   $result = $tester->testUrl('https://example.com/video.mp4');
 *
 *   // Check if a URL serves actual video content
 *   if ($tester->isVideo('https://example.com/video.mp4')) { ... }
 *
 *   // Get CDN fallback URL for dead munoserver hostnames
 *   $cdn = VideoUrlTester::getCdnFallbackUrl('http://munoserver12.club/path/video.mp4');
 *
 *   // Check if a hostname is a dead munoserver domain
 *   if (VideoUrlTester::isDeadHostname('munoserver12.club')) { ... }
 *
 *   // Sanitize a raw URL (trim, HTTPS upgrade for CDN)
 *   $clean = VideoUrlTester::sanitizeUrl($rawUrl);
 *
 * @package App\Services
 */
class VideoUrlTester
{
    /**
     * MIME types that are considered valid video content.
     * Matches the Flutter mobile app's accepted video types.
     *
     * @var string[]
     */
    public const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/x-msvideo',
        'video/mpeg',
        'video/quicktime',
        'video/x-flv',
        'video/x-matroska',
        'video/webm',
        'video/3gpp',
        'video/3gpp2',
        'video/x-ms-wmv',
        'video/ogg',
        'application/vnd.apple.mpegurl',  // HLS
        'application/x-mpegurl',          // HLS
        'application/octet-stream',       // Generic binary (CDNs often use this)
    ];

    /**
     * BunnyCDN pull zone hostname.
     * Dead munoserverXX domains resolve to this CDN.
     *
     * @var string
     */
    public const CDN_FALLBACK_HOST = 'munotek.b-cdn.net';

    /**
     * Regex pattern matching dead/parked munoserver custom hostnames.
     *
     * @var string
     */
    public const DEAD_HOSTNAME_PATTERN = '/^(munoserver\d+\.(club|org|xyz)|munowatch\.co|muno\d*\.club|gumite\.club)$/i';

    /**
     * CDN domains that require HTTPS.
     *
     * @var string[]
     */
    public const HTTPS_REQUIRED_DOMAINS = [
        'b-cdn.net',
        'bunnycdn.com',
        'cloudfront.net',
        'akamaihd.net',
    ];

    /**
     * Default cURL timeout in seconds.
     *
     * @var int
     */
    protected int $timeout = 20;

    /**
     * Default cURL connection timeout in seconds.
     *
     * @var int
     */
    protected int $connectTimeout = 10;

    /**
     * Set the request timeout.
     *
     * @param int $seconds  Timeout in seconds
     * @return $this
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set the connection timeout.
     *
     * @param int $seconds  Connection timeout in seconds
     * @return $this
     */
    public function setConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    // ─────────────────────────────────────────────
    //  STATIC URL UTILITIES
    // ─────────────────────────────────────────────

    /**
     * Check if a hostname matches the dead munoserver pattern.
     *
     * @param  string $hostname  e.g. "munoserver12.club"
     * @return bool
     */
    public static function isDeadHostname(string $hostname): bool
    {
        return (bool) preg_match(self::DEAD_HOSTNAME_PATTERN, $hostname);
    }

    /**
     * Get the CDN fallback URL for a dead munoserver hostname.
     * Returns null if the hostname is not a dead munoserver domain.
     *
     * @param  string $url  Original URL
     * @return string|null  CDN fallback URL or null
     */
    public static function getCdnFallbackUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || !self::isDeadHostname($host)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $query = parse_url($url, PHP_URL_QUERY);

        $fallback = 'https://' . self::CDN_FALLBACK_HOST . $path;
        if ($query) {
            $fallback .= '?' . $query;
        }

        return $fallback;
    }

    /**
     * Sanitize a video URL: trim whitespace, upgrade HTTP to HTTPS for known CDNs.
     *
     * @param  string $url  Raw URL from database
     * @return string       Sanitized URL
     */
    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if (empty($url)) {
            return $url;
        }

        // Upgrade HTTP to HTTPS for known CDN domains
        if (str_starts_with($url, 'http://')) {
            $lower = strtolower($url);
            foreach (self::HTTPS_REQUIRED_DOMAINS as $domain) {
                if (str_contains($lower, $domain)) {
                    $url = 'https://' . substr($url, 7);
                    break;
                }
            }
        }

        return $url;
    }

    /**
     * Check if a content type represents video content.
     *
     * @param  string $contentType  e.g. "video/mp4; charset=utf-8"
     * @return bool
     */
    public static function isVideoContentType(string $contentType): bool
    {
        // Strip charset or other parameters
        $clean = strtolower(trim(explode(';', $contentType)[0]));

        return in_array($clean, self::VIDEO_MIME_TYPES, true);
    }

    /**
     * Detect the CDN provider from a URL.
     *
     * @param  string $url
     * @return string  Provider name (e.g. "BunnyCDN", "CloudFront", "Custom")
     */
    public static function detectCdnProvider(string $url): string
    {
        $lower = strtolower($url);

        if (str_contains($lower, 'b-cdn.net') || str_contains($lower, 'bunnycdn')) {
            return 'BunnyCDN';
        }
        if (str_contains($lower, 'cloudfront')) {
            return 'CloudFront';
        }
        if (str_contains($lower, 'akamaihd')) {
            return 'Akamai';
        }
        if (str_contains($lower, 'googleapis')) {
            return 'Google Cloud';
        }
        if (str_contains($lower, 'firebase')) {
            return 'Firebase';
        }
        if (str_contains($lower, 'munoserver')) {
            return 'MunoServer (Dead)';
        }

        return 'Custom';
    }

    // ─────────────────────────────────────────────
    //  URL TESTING (cURL)
    // ─────────────────────────────────────────────

    /**
     * Test a URL's accessibility using a server-side cURL HEAD request.
     *
     * Returns an associative array with:
     *  - success      (bool)   Whether the URL returned a 2xx/3xx status
     *  - http_code    (int)    HTTP status code
     *  - content_type (string) Content-Type header value
     *  - content_length (int|null) Content-Length in bytes
     *  - is_video     (bool)   Whether content type is a known video MIME
     *  - effective_url (string|null) Final URL after redirects (null if same)
     *  - total_time_ms (int)   Request duration in milliseconds
     *  - error        (string|null) cURL error message
     *  - headers      (array)  Parsed response headers
     *
     * @param  string $url      URL to test
     * @param  array  $headers  Optional cURL headers (e.g. User-Agent)
     * @param  string $label    Human label for logging
     * @return array            Test result
     */
    public function testUrl(string $url, array $headers = [], string $label = ''): array
    {
        if (empty($headers)) {
            $headers = [
                'User-Agent: Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            ];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_NOBODY         => true,         // HEAD request
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_REFERER        => '',           // Empty = bypass hotlink protection
        ]);

        $response    = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $contentLen  = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $totalTime   = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $error       = curl_error($ch);
        $errno       = curl_errno($ch);

        // Parse response headers
        $parsedHeaders = [];
        if ($response) {
            foreach (explode("\r\n", $response) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $val] = explode(':', $line, 2);
                    $parsedHeaders[strtolower(trim($key))] = trim($val);
                }
            }
        }

        curl_close($ch);

        $cleanType = $contentType ? explode(';', $contentType)[0] : '';
        $isVideo   = self::isVideoContentType($cleanType ?: '');
        $success   = ($httpCode >= 200 && $httpCode < 400) && $errno === 0;

        return [
            'success'        => $success,
            'label'          => $label,
            'http_code'      => $httpCode,
            'content_type'   => $cleanType ?: null,
            'content_length' => ($contentLen > 0) ? (int) $contentLen : null,
            'is_video'       => $isVideo,
            'effective_url'  => ($effectiveUrl !== $url) ? $effectiveUrl : null,
            'total_time_ms'  => (int) round($totalTime * 1000),
            'error'          => $error ?: null,
            'headers'        => $parsedHeaders,
        ];
    }

    /**
     * Test a URL with the Flutter mobile app's dual-attempt strategy.
     *
     * Attempt 1: Standard browser User-Agent (no Referer).
     * Attempt 2: okhttp User-Agent + MunoWatch Referer (mobile app retry).
     *
     * @param  string $url  URL to test
     * @return array        Test result from the successful attempt (or both failures)
     */
    public function testUrlDualAttempt(string $url): array
    {
        // Attempt 1: Browser UA
        $result = $this->testUrl($url, [
            'User-Agent: Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        ], 'Attempt 1 (Browser UA)');

        if ($result['success']) {
            return $result;
        }

        // Attempt 2: okhttp + MunoWatch headers
        $result2 = $this->testUrl($url, [
            'User-Agent: okhttp/4.9.0',
            'Referer: https://munowatch.org/',
        ], 'Attempt 2 (okhttp + MunoWatch)');

        if ($result2['success']) {
            $result2['note'] = 'Succeeded on Attempt 2 (okhttp/munowatch). Attempt 1 (browser UA) failed.';
            return $result2;
        }

        // Both failed
        $result['attempt2_error']     = $result2['error'] ?? 'Also failed';
        $result['attempt2_http_code'] = $result2['http_code'] ?? null;

        return $result;
    }

    /**
     * Quick boolean check: does this URL serve video content?
     *
     * Performs a HEAD request and checks both HTTP status and Content-Type.
     *
     * @param  string $url  URL to check
     * @return bool         True if URL returns 2xx/3xx with a video MIME type
     */
    public function isVideo(string $url): bool
    {
        $result = $this->testUrl($url);

        return $result['success'] && $result['is_video'];
    }

    /**
     * Build a list of candidate URLs for a movie, including CDN fallbacks.
     *
     * @param  string $rawUrl  Raw URL from database
     * @return array           Array of ['url' => ..., 'source' => ...]
     */
    public static function buildUrlQueue(string $rawUrl): array
    {
        $urls = [];
        $seen = [];

        $url = self::sanitizeUrl($rawUrl);
        if (empty($url) || strlen($url) < 5) {
            return $urls;
        }

        $fallback = self::getCdnFallbackUrl($url);

        // CDN fallback first (if applicable)
        if ($fallback && !isset($seen[$fallback])) {
            $seen[$fallback] = true;
            $urls[] = ['url' => $fallback, 'source' => 'url > ' . self::CDN_FALLBACK_HOST];
        }

        // Original URL
        if (!isset($seen[$url])) {
            $seen[$url] = true;
            $urls[] = ['url' => $url, 'source' => 'url (original)'];
        }

        return $urls;
    }
}
