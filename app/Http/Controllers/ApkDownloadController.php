<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tracked APK download redirects.
 *
 * GET /app/download/{variant}?src=whatsapp&sid=...
 *
 * Logs the download server-side (counts EVERY real download, including links
 * shared directly to WhatsApp/Telegram that never touch the landing pages),
 * then 302-redirects to the file on Bunny CDN. The CDN carries the bytes;
 * this endpoint only records one row per request.
 */
class ApkDownloadController extends Controller
{
    private const VARIANTS = [
        'arm64'     => 'https://lugaflix-cdn.b-cdn.net/app/lugaflix-arm64.apk',
        'arm32'     => 'https://lugaflix-cdn.b-cdn.net/app/lugaflix-arm32.apk',
        'universal' => 'https://lugaflix-cdn.b-cdn.net/app/lugaflix-latest.apk',
    ];

    public function download(Request $request, string $variant)
    {
        if (!isset(self::VARIANTS[$variant])) {
            $variant = 'arm64';
        }

        try {
            $ua = $request->userAgent() ?? '';
            DB::table('apk_downloads')->insert([
                'variant'      => $variant,
                'session_id'   => Str::limit((string) $request->get('sid', ''), 64, '') ?: null,
                'src'          => Str::limit((string) $request->get('src', ''), 60, '') ?: null,
                'ip_address'   => $request->ip(),
                'device_type'  => $this->deviceType($ua),
                'os'           => $this->os($ua),
                'user_agent'   => Str::limit($ua, 500),
                'referrer_url' => Str::limit((string) $request->headers->get('referer', ''), 500) ?: null,
                'created_at'   => now(),
            ]);
        } catch (\Throwable) {
            // Tracking must never block a download.
        }

        return redirect()->away(self::VARIANTS[$variant]);
    }

    private function deviceType(string $ua): string
    {
        if (preg_match('/tablet|ipad/i', $ua)) return 'tablet';
        if (preg_match('/mobile|android|iphone/i', $ua)) return 'mobile';
        return 'desktop';
    }

    private function os(string $ua): string
    {
        if (stripos($ua, 'android') !== false) return 'Android';
        if (preg_match('/iphone|ipad|ios/i', $ua)) return 'iOS';
        if (stripos($ua, 'windows') !== false) return 'Windows';
        if (stripos($ua, 'mac os') !== false) return 'macOS';
        if (stripos($ua, 'linux') !== false) return 'Linux';
        return 'Other';
    }
}
