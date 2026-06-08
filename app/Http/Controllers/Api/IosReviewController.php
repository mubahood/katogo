<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Dedicated controller for the simplified iOS App Store review experience.
 *
 * All routes are PUBLIC (no JWT required) so Apple reviewers can browse
 * without an account. Auth endpoints (login/register) are handled by
 * the existing ApiController — we just proxy their semantics here.
 */
class IosReviewController extends Controller
{
    /**
     * GET /api/ios/config
     *
     * System config for iOS clients. Always returns ios_review_mode truthfully.
     */
    public function config(Request $request): JsonResponse
    {
        $config = Cache::remember('system_config', 300, fn() => SystemConfig::instance());

        return response()->json([
            'code'    => 1,
            'message' => 'OK',
            'data'    => [
                'ios_review_mode'    => (bool) $config->ios_review_mode,
                'ios_review_message' => $config->ios_review_message ?: 'Watch Luganda Translated Movies',
                'maintenance_mode'   => (bool) $config->maintenance_mode,
                'min_ios_version'    => (int) $config->min_ios_version,
            ],
        ]);
    }

    /**
     * GET /api/ios/movies?page=1&per_page=20&genre=Action
     *
     * Paginated movies for iOS review mode.
     * Returns only movies with platform_type = 'ios' OR 'all', and non-premium.
     */
    public function movies(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 50);
        $genre   = trim($request->input('genre', ''));

        $query = MovieModel::where('status', 'Active')
            ->where('platform_type', 'ios')
            ->where(function ($q) {
                $q->where('is_premium', 'No')
                  ->orWhere('is_premium', false)
                  ->orWhere('is_premium', 0)
                  ->orWhereNull('is_premium');
            })
            ->select([
                'id', 'title', 'thumbnail_url', 'image_url',
                'genre', 'type', 'vj', 'year', 'duration',
                'rating', 'imdb_rating', 'views_count',
                'description', 'is_premium', 'platform_type',
                'url', 'video_url',
            ]);

        if ($genre !== '') {
            $query->where('genre', 'like', "%{$genre}%");
        }

        $movies = $query->orderBy('views_time_count', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'code'    => 1,
            'message' => 'OK',
            'data'    => [
                'movies'       => $movies->items(),
                'current_page' => $movies->currentPage(),
                'last_page'    => $movies->lastPage(),
                'total'        => $movies->total(),
                'per_page'     => $movies->perPage(),
                'has_more'     => $movies->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/ios/movies/{id}
     *
     * Single movie detail for iOS review mode.
     */
    public function movie(int $id): JsonResponse
    {
        $movie = MovieModel::where('id', $id)
            ->where('status', 'Active')
            ->select([
                'id', 'title', 'thumbnail_url', 'image_url', 'poster_url',
                'genre', 'type', 'vj', 'year', 'duration', 'rating',
                'imdb_rating', 'imdb_votes', 'views_count', 'description',
                'director', 'stars', 'country', 'language',
                'is_premium', 'platform_type', 'url', 'video_url',
            ])
            ->first();

        if (!$movie) {
            return response()->json(['code' => 0, 'message' => 'Movie not found.', 'data' => null], 404);
        }

        return response()->json([
            'code'    => 1,
            'message' => 'OK',
            'data'    => $movie,
        ]);
    }

    /**
     * GET /api/ios/genres
     *
     * Distinct genre list for iOS review browsing.
     */
    public function genres(): JsonResponse
    {
        $genres = Cache::remember('ios_review_genres', 1800, function () {
            return MovieModel::where('status', 'Active')
                ->where('platform_type', 'ios')
                ->whereNotNull('genre')
                ->where('genre', '!=', '')
                ->distinct()
                ->pluck('genre')
                ->map(fn($g) => trim($g))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        });

        return response()->json([
            'code'    => 1,
            'message' => 'OK',
            'data'    => $genres,
        ]);
    }
}
