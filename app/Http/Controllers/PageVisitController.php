<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageVisitController extends Controller
{
    /**
     * Record a new page visit.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'session_id'   => 'required|string|max:64',
            'page_url'     => 'required|string|max:500',
            'referrer_url' => 'nullable|string|max:2000',
            'utm_source'   => 'nullable|string|max:100',
            'utm_medium'   => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
        ]);

        $ua = $request->userAgent() ?? '';

        $visit = PageVisit::create([
            'session_id'   => $data['session_id'],
            'ip_address'   => $request->ip(),
            'user_agent'   => Str::limit($ua, 500),
            'device_type'  => $this->detectDeviceType($ua),
            'os'           => $this->detectOS($ua),
            'browser'      => $this->detectBrowser($ua),
            'referrer_url' => $data['referrer_url'] ?? null,
            'utm_source'   => $data['utm_source'] ?? null,
            'utm_medium'   => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'page_url'     => $data['page_url'],
            'landed_at'    => now(),
        ]);

        return response()->json(['id' => $visit->id]);
    }

    /**
     * Update an existing visit with event data (click, leave).
     */
    public function event(Request $request)
    {
        $data = $request->validate([
            'session_id'            => 'required|string|max:64',
            'button_clicked'        => 'nullable|string|in:android,ios,web,video',
            'time_on_page_seconds'  => 'nullable|integer|min:0|max:86400',
        ]);

        $visit = PageVisit::where('session_id', $data['session_id'])
            ->latest('id')
            ->first();

        if (!$visit) {
            return response()->json(['ok' => false], 404);
        }

        $updates = [];
        if (!empty($data['button_clicked'])) {
            $updates['button_clicked'] = $data['button_clicked'];
        }
        if (isset($data['time_on_page_seconds'])) {
            $updates['time_on_page_seconds'] = $data['time_on_page_seconds'];
            $updates['left_at'] = now();
        }

        if ($updates) {
            $visit->update($updates);
        }

        return response()->json(['ok' => true]);
    }

    private function detectDeviceType(string $ua): string
    {
        if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) return 'tablet';
        if (preg_match('/mobile|android|iphone|ipod|phone|blackberry|opera mini|iemobile/i', $ua)) return 'mobile';
        return 'desktop';
    }

    private function detectOS(string $ua): string
    {
        $patterns = [
            'iOS'        => '/iphone|ipad|ipod/i',
            'Windows'    => '/windows/i',
            'macOS'      => '/macintosh|mac os x/i',
            'Android'    => '/android/i',
            'Linux'      => '/linux/i',
            'Chrome OS'  => '/cros/i',
        ];
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $ua)) return $name;
        }
        return 'Other';
    }

    private function detectBrowser(string $ua): string
    {
        $patterns = [
            'Edge'    => '/edg(e|a|ios)?/i',
            'Opera'   => '/opr|opera/i',
            'Chrome'  => '/chrome|crios/i',
            'Firefox' => '/firefox|fxios/i',
            'Safari'  => '/safari/i',
            'IE'      => '/msie|trident/i',
        ];
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $ua)) return $name;
        }
        return 'Other';
    }
}
