<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemConfigController extends Controller
{
    /**
     * GET /api/v2/system-config
     *
     * Returns app configuration. Called at startup by all clients.
     * iOS clients receive ios_review_mode status and, when active,
     * the curated list of review movies.
     *
     * Android clients always receive ios_review_mode = false.
     */
    public function index(Request $request): JsonResponse
    {
        $platform = strtolower(trim($request->input('platform_type', 'android')));
        $isIos    = ($platform === 'ios');

        $config = Cache::remember('system_config', 300, fn() => SystemConfig::instance());

        // Base response — safe for all platforms
        $data = [
            'maintenance_mode'    => $config->maintenance_mode,
            'maintenance_message' => $config->maintenance_message,
            'min_android_version' => $config->min_android_version,
            'min_ios_version'     => $config->min_ios_version,
            // Android always gets false so the check is a no-op on Android
            'ios_review_mode'     => $isIos && $config->ios_review_mode,
            'ios_review_message'  => $config->ios_review_message,
            'ios_review_movies'   => [],
        ];

        // Only load review movies when iOS review is actually active
        if ($isIos && $config->ios_review_mode) {
            $movieIds = $config->ios_review_movie_ids_array;
            if (!empty($movieIds)) {
                $data['ios_review_movies'] = Cache::remember(
                    'ios_review_movies_' . md5(implode(',', $movieIds)),
                    600,
                    fn() => MovieModel::whereIn('id', $movieIds)
                        ->where('status', 'Active')
                        ->select([
                            'id', 'title', 'thumbnail_url', 'genre',
                            'type', 'vj', 'year', 'duration', 'rating',
                            'is_premium',
                        ])
                        ->get()
                        ->toArray()
                );
            } else {
                // No specific IDs set — serve a small safe default set
                $data['ios_review_movies'] = Cache::remember(
                    'ios_review_movies_default',
                    600,
                    fn() => MovieModel::where('status', 'Active')
                        ->where('is_premium', false)
                        ->orderBy('views_time_count', 'desc')
                        ->limit(10)
                        ->select([
                            'id', 'title', 'thumbnail_url', 'genre',
                            'type', 'vj', 'year', 'duration', 'rating',
                            'is_premium',
                        ])
                        ->get()
                        ->toArray()
                );
            }
        }

        return response()->json([
            'code'    => 1,
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/v2/system-config/toggle-ios-review  (admin/internal use)
     * Quick toggle via API — also available through admin panel.
     */
    public function toggleIosReview(Request $request): JsonResponse
    {
        $config = SystemConfig::instance();
        $config->ios_review_mode = !$config->ios_review_mode;
        $config->save();

        Cache::forget('system_config');

        return response()->json([
            'code'    => 1,
            'message' => 'iOS review mode is now ' . ($config->ios_review_mode ? 'ON' : 'OFF'),
            'data'    => ['ios_review_mode' => $config->ios_review_mode],
        ]);
    }
}
