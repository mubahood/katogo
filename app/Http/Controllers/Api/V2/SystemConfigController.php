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
            $storageBase = rtrim(config('app.url'), '/') . '/storage/';

            $resolveMovies = function ($movies) use ($storageBase) {
                return $movies->map(function ($m) use ($storageBase) {
                    $arr = $m->toArray();
                    // Resolve relative thumbnail to full URL
                    if (!empty($arr['thumbnail_url']) && !str_starts_with($arr['thumbnail_url'], 'http')) {
                        $arr['thumbnail_url'] = $storageBase . ltrim($arr['thumbnail_url'], '/');
                    }
                    // url getter already handles local_video_link, but expose it cleanly
                    unset($arr['local_video_link']);
                    return $arr;
                })->values()->toArray();
            };

            // Common playability guard — same rules as /api/ios/movies
            $playableScope = function ($q) {
                $q->where('status', 'Active')
                  ->whereNotNull('url')->where('url', '!=', '')
                  ->whereNotNull('thumbnail_url')->where('thumbnail_url', '!=', '')
                  ->where(function ($inner) {
                      $inner->where('is_premium', 'No')
                            ->orWhere('is_premium', false)
                            ->orWhere('is_premium', 0)
                            ->orWhereNull('is_premium');
                  });
            };

            $movieFields = [
                'id', 'title', 'url', 'local_video_link', 'thumbnail_url', 'genre',
                'type', 'vj', 'year', 'duration', 'rating', 'is_premium', 'platform_type',
            ];

            if (!empty($movieIds)) {
                $data['ios_review_movies'] = Cache::remember(
                    'ios_review_movies_' . md5(implode(',', $movieIds)),
                    600,
                    fn() => $resolveMovies(
                        MovieModel::whereIn('id', $movieIds)
                            ->tap($playableScope)
                            ->select($movieFields)
                            ->get()
                    )
                );
            } else {
                // No specific IDs — show top active ios/all movies that are playable
                $data['ios_review_movies'] = Cache::remember(
                    'ios_review_movies_default',
                    600,
                    fn() => $resolveMovies(
                        MovieModel::whereIn('platform_type', ['ios', 'all'])
                            ->tap($playableScope)
                            ->orderBy('views_time_count', 'desc')
                            ->limit(10)
                            ->select($movieFields)
                            ->get()
                    )
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
