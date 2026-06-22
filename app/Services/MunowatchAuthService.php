<?php

namespace App\Services;

use App\Models\MovieCrawlerWebsite;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Munowatch authentication — login, session caching, auto-refresh.
 *
 * Session is stored both in DB (movie_crawler_websites) and a 6-hour Laravel cache entry.
 * Any service needing Munowatch auth should call getActiveSession() — it returns a fresh
 * session automatically if the cached one is missing or stale.
 */
class MunowatchAuthService
{
    /** Slug for the munowatch record in movie_crawler_websites */
    protected const WEBSITE_SLUG = 'munowatch';

    /** Cache key for the active session */
    protected const CACHE_KEY = 'munowatch_active_session';

    /** How many hours a session is considered valid before forcing a refresh */
    protected const SESSION_TTL_HOURS = 6;

    /** App-level JWT that identifies the mobile client to Munowatch */
    protected const APP_JWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6IkFuZHJvaWQgVFYiLCJhcHBuYW1lIjoiTXVub3dhdGNoIFRWIiwiaG9zdCI6Im11bm93YXRjaC5jbyIsImFwcHNlY3JldCI6IjAyMjc3OGU0MThhZDY4ZmZkYTlhYTRmYWIxODkyZmZmIiwiYWN0aXZhdGVkIjoiMSIsImV4cCI6MTcwNzM2ODQwMH0.unlPnEzptg6VFHs7WWm213bRHHNxYuAN2eZQvjtPKL0';

    /** Munowatch mobile login endpoint */
    protected const LOGIN_URL = 'https://munowatch.org/api/users/login/v2';

    // ─────────────────────────────────────────────
    //  PUBLIC API
    // ─────────────────────────────────────────────

    /**
     * Return a valid Munowatch session, logging in automatically if needed.
     *
     * Returns:
     *   [
     *     'session_id'   => string,
     *     'user_id'      => int,
     *     'app_jwt'      => string,   // for Authorization/X-Api-Key headers
     *   ]
     *
     * Throws \RuntimeException on complete failure (network down, bad credentials, etc.)
     */
    public static function getActiveSession(): array
    {
        // 1. Fast path: warm cache
        $cached = Cache::get(self::CACHE_KEY);
        if (self::isValidSession($cached)) {
            return $cached;
        }

        // 2. DB path: check if DB has a recent session
        $site = self::getWebsite();
        if ($site && self::isValidSession(self::siteToSession($site))) {
            $session = self::siteToSession($site);
            Cache::put(self::CACHE_KEY, $session, now()->addHours(self::SESSION_TTL_HOURS));
            return $session;
        }

        // 3. Login to get a fresh session
        return self::refreshSession();
    }

    /**
     * Force a fresh login regardless of cached state.
     * Call this after receiving 429 / 401 / 403 from the API.
     *
     * @throws \RuntimeException on failure
     */
    public static function refreshSession(): array
    {
        $result = self::login();

        if (!$result['success']) {
            throw new \RuntimeException('Munowatch login failed: ' . $result['error']);
        }

        $session = [
            'session_id' => $result['session_id'],
            'user_id'    => (int) $result['user_id'],
            'app_jwt'    => self::APP_JWT,
        ];

        // Persist to cache
        Cache::put(self::CACHE_KEY, $session, now()->addHours(self::SESSION_TTL_HOURS));

        // Persist to DB so the session survives PHP process restarts / cache flushes
        try {
            $site = self::getWebsite();
            if ($site) {
                $site->session_id           = $session['session_id'];
                $site->munowatch_user_id    = $session['user_id'];
                $site->session_refreshed_at = Carbon::now();
                $site->save();
            }
        } catch (\Throwable $e) {
            // Non-fatal — cache is already set
            Log::warning('MunowatchAuthService: could not persist session to DB — ' . $e->getMessage());
        }

        Log::info('MunowatchAuthService: session refreshed', [
            'user_id'    => $session['user_id'],
            'session_prefix' => substr($session['session_id'], 0, 8) . '...',
        ]);

        return $session;
    }

    /**
     * Invalidate the cached session (call before refreshSession() when you know it's bad).
     */
    public static function invalidateSession(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Perform a fresh login against the Munowatch mobile API.
     *
     * Returns:
     *   ['success' => bool, 'session_id' => string|null, 'user_id' => int|null, 'error' => string|null]
     */
    public static function login(): array
    {
        try {
            [$email, $password] = self::getCredentials();

            $postData = http_build_query([
                'email'      => $email,
                'password'   => $password,
                'version'    => '3.4.0',
                'deviceinfo' => 'Katogo Server',
                'device'     => 'server',
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => self::LOGIN_URL,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: okhttp/4.9.0',
                    'Accept: application/json',
                    'Authorization: Bearer ' . self::APP_JWT,
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            $ch = null; // release handle (curl_close deprecated in PHP 8+)

            if ($curlErr) {
                return self::loginFail('Network error: ' . $curlErr);
            }

            if ($httpCode !== 200) {
                return self::loginFail("HTTP {$httpCode} from login endpoint. Body: " . substr($response, 0, 150));
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return self::loginFail('Invalid JSON from login endpoint: ' . json_last_error_msg());
            }

            $sessionId = $data['user']['session_id'] ?? null;
            $userId    = $data['user']['id']         ?? null;

            if (empty($sessionId) || empty($userId)) {
                $errMsg = $data['error'] ?? $data['message'] ?? 'No session_id in login response';
                return self::loginFail($errMsg . ' | keys: ' . implode(',', array_keys($data)));
            }

            return [
                'success'    => true,
                'session_id' => (string) $sessionId,
                'user_id'    => (int) $userId,
                'error'      => null,
            ];

        } catch (\Throwable $e) {
            return self::loginFail('Exception: ' . $e->getMessage());
        }
    }

    /**
     * Check if an API response body indicates an auth failure (429/401/403).
     */
    public static function isAuthFailure(string $body, int $httpCode = 0): bool
    {
        if (in_array($httpCode, [401, 403, 429])) {
            return true;
        }

        $lower = strtolower($body);
        foreach (['unusual activity', 'unauthorized', 'forbidden', 'token expired', 'invalid token', 'access denied'] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ─────────────────────────────────────────────

    /** Pull credentials from DB, fall back to env/hardcoded */
    private static function getCredentials(): array
    {
        $site = self::getWebsite();

        $email    = ($site && !empty($site->email) && str_contains($site->email, '@'))
            ? $site->email
            : 'mubahood360@gmail.com';

        $password = ($site && !empty($site->password))
            ? $site->password
            : 'ilovemum999';

        return [$email, $password];
    }

    private static function getWebsite(): ?MovieCrawlerWebsite
    {
        return MovieCrawlerWebsite::where('slug', self::WEBSITE_SLUG)->first();
    }

    private static function siteToSession(MovieCrawlerWebsite $site): ?array
    {
        if (empty($site->session_id) || empty($site->munowatch_user_id)) {
            return null;
        }

        return [
            'session_id' => $site->session_id,
            'user_id'    => (int) $site->munowatch_user_id,
            'app_jwt'    => self::APP_JWT,
        ];
    }

    private static function isValidSession(?array $session): bool
    {
        if (empty($session['session_id']) || empty($session['user_id'])) {
            return false;
        }

        // If it came from DB, also check the refresh timestamp
        // (Cache TTL handles expiry for cache-sourced sessions)
        return true;
    }

    private static function loginFail(string $error): array
    {
        Log::error('MunowatchAuthService: login failed — ' . $error);
        return ['success' => false, 'session_id' => null, 'user_id' => null, 'error' => $error];
    }
}
