<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MovieModel;
use App\Models\SystemConfig;
use App\Models\Utils;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
     * Resolve relative image paths to full storage URLs.
     * Also strips the raw local_video_link column from output.
     */
    private function resolveMovie(array $arr): array
    {
        $base = rtrim(config('app.url'), '/') . '/storage/';
        foreach (['thumbnail_url', 'image_url', 'poster_url'] as $field) {
            if (!empty($arr[$field]) && !str_starts_with((string) $arr[$field], 'http')) {
                $arr[$field] = $base . ltrim((string) $arr[$field], '/');
            }
        }
        unset($arr['local_video_link']);
        return $arr;
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
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->whereNotNull('thumbnail_url')
            ->where('thumbnail_url', '!=', '')
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
                'url', 'local_video_link',
            ]);

        if ($genre !== '') {
            $query->where('genre', 'like', "%{$genre}%");
        }

        $movies = $query->orderBy('views_time_count', 'desc')
                        ->paginate($perPage);

        $resolved = array_map(
            fn($m) => $this->resolveMovie($m->toArray()),
            $movies->items()
        );

        return response()->json([
            'code'    => 1,
            'message' => 'OK',
            'data'    => [
                'movies'       => $resolved,
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
                'is_premium', 'platform_type', 'url', 'local_video_link',
            ])
            ->first();

        if (!$movie) {
            return response()->json(['code' => 0, 'message' => 'Movie not found.', 'data' => null], 404);
        }

        return response()->json([
            'code'    => 1,
            'message' => 'OK',
            'data'    => $this->resolveMovie($movie->toArray()),
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

    /**
     * DELETE /api/ios/account
     *
     * Permanently delete the authenticated user's account.
     * Requires JWT auth. Irreversible — anonymises personal data and
     * hard-deletes subscriptions, tokens, and the account row.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        // Use auth guard directly — Utils::get_user() falls back to a guest user
        // which would bypass the null check; auth()->user() is null when unauthenticated.
        $user = auth('api')->user();
        if (!$user || (int) $user->id < 1) {
            return response()->json(['code' => 0, 'message' => 'Not authenticated.'], 401);
        }

        $id = (int) $user->id;

        if ($id === 1) {
            return response()->json([
                'code'    => 0,
                'message' => 'This is the admin account and cannot be deleted.',
            ], 403);
        }

        $account = Administrator::find($id);
        if (!$account) {
            return response()->json(['code' => 0, 'message' => 'Account not found.'], 404);
        }

        try {
            DB::transaction(function () use ($id, $account) {
                // Wipe personal data before deleting
                $account->name     = 'Deleted User';
                $account->username = 'deleted_' . $id;
                $account->email    = 'deleted_' . $id . '@deleted.local';
                $account->password = '';
                $account->remember_token = null;
                $account->save();

                // Remove related data (best-effort, silent on missing tables)
                $tables = [
                    'subscriptions'     => 'user_id',
                    'transactions'      => 'user_id',
                    'watch_histories'   => 'user_id',
                    'watchlists'        => 'user_id',
                    'device_tokens'     => 'user_id',
                    'support_tickets'   => 'user_id',
                ];
                foreach ($tables as $table => $col) {
                    try {
                        DB::table($table)->where($col, $id)->delete();
                    } catch (\Exception) {
                        // table may not exist — skip silently
                    }
                }

                // Hard-delete the account row
                $account->delete();
            });
        } catch (\Exception) {
            return response()->json([
                'code'    => 0,
                'message' => 'Could not delete account. Please try again.',
            ], 500);
        }

        return response()->json([
            'code'    => 1,
            'message' => 'Your account has been permanently deleted.',
        ]);
    }
}
