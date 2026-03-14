<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MovieDownload;
use App\Models\MovieModel;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ═══════════════════════════════════════════════════════════════
 *  V2 Download Tracking Controller
 * ═══════════════════════════════════════════════════════════════
 *
 *  Lightweight endpoints for recording & querying download stats,
 *  especially gallery downloads which were previously untracked.
 *
 *  Endpoints:
 *    POST /api/v2/downloads/record   – Record a download event
 *    GET  /api/v2/downloads/stats    – Download statistics
 * ═══════════════════════════════════════════════════════════════
 */
class DownloadController extends Controller
{
    use ApiResponser;

    /**
     * Record a download event (gallery or in-app).
     *
     * Required: movie_model_id, download_type (gallery|in_app)
     * Optional: title, genre, vj, content_type, episode_number, url
     */
    public function record(Request $request)
    {
        $request->validate([
            'movie_model_id' => 'required|integer',
            'download_type'  => 'required|in:gallery,in_app',
        ]);

        $user = auth()->user();
        $movieId = (int) $request->input('movie_model_id');

        $movie = MovieModel::find($movieId);
        if (!$movie) {
            return $this->error('Movie not found', 404);
        }

        $download = new MovieDownload();
        $download->user_id        = $user->id;
        $download->movie_model_id = $movieId;
        $download->download_type  = $request->input('download_type');
        $download->status         = $request->input('download_type') === 'gallery' ? 'Gallery' : 'Pending';
        $download->local_id       = $request->input('local_id', 'gallery_' . time() . '_' . $user->id);
        $download->title          = $request->input('title', $movie->title);
        $download->url            = $request->input('url', $movie->get_video_url ?? '');
        $download->image_url      = $request->input('image_url', $movie->thumbnail ?? '');
        $download->description    = $request->input('description', '');
        $download->genre          = $request->input('genre', $movie->genre ?? '');
        $download->vj             = $request->input('vj', $movie->vj ?? '');
        $download->content_type   = $request->input('content_type', $movie->content_type ?? '');
        $download->episode_number = $request->input('episode_number', '');
        $download->download_started_at  = now();
        $download->download_completed_at = $request->input('download_type') === 'gallery' ? now() : null;
        $download->save();

        Log::info('Download recorded', [
            'user_id'        => $user->id,
            'movie_model_id' => $movieId,
            'download_type'  => $download->download_type,
        ]);

        return $this->success([
            'download_id' => $download->id,
            'download_type' => $download->download_type,
        ], 'Download recorded successfully');
    }

    /**
     * Get download statistics for a movie or overall.
     *
     * Query params:
     *   movie_model_id  – optional, filter to one movie
     *   days            – optional, last N days (default 30)
     */
    public function stats(Request $request)
    {
        $days    = min((int) ($request->input('days', 30)), 365);
        $movieId = $request->input('movie_model_id');
        $since   = now()->subDays($days);

        $query = MovieDownload::where('created_at', '>=', $since);
        if ($movieId) {
            $query->where('movie_model_id', (int) $movieId);
        }

        // ── Totals ────────────────────────────────────────
        $totals = (clone $query)->select(
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN download_type = 'gallery' THEN 1 ELSE 0 END) as gallery"),
            DB::raw("SUM(CASE WHEN download_type = 'in_app' THEN 1 ELSE 0 END) as in_app")
        )->first();

        // ── Unique users who downloaded ───────────────────
        $uniqueUsers = (clone $query)->distinct('user_id')->count('user_id');
        $uniqueUsersGallery = (clone $query)->where('download_type', 'gallery')->distinct('user_id')->count('user_id');
        $uniqueUsersInApp   = (clone $query)->where('download_type', 'in_app')->distinct('user_id')->count('user_id');

        // ── Daily breakdown ──────────────────────────────
        $daily = (clone $query)->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN download_type = 'gallery' THEN 1 ELSE 0 END) as gallery"),
            DB::raw("SUM(CASE WHEN download_type = 'in_app' THEN 1 ELSE 0 END) as in_app")
        )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // ── Weekly breakdown ─────────────────────────────
        $weekly = (clone $query)->select(
            DB::raw('YEARWEEK(created_at, 1) as week'),
            DB::raw('MIN(DATE(created_at)) as week_start'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN download_type = 'gallery' THEN 1 ELSE 0 END) as gallery"),
            DB::raw("SUM(CASE WHEN download_type = 'in_app' THEN 1 ELSE 0 END) as in_app")
        )
            ->groupBy(DB::raw('YEARWEEK(created_at, 1)'))
            ->orderBy('week')
            ->get();

        // ── Top movies by total downloads ────────────────
        $topMoviesRaw = (clone $query)->select(
            'movie_model_id',
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN download_type = 'gallery' THEN 1 ELSE 0 END) as gallery"),
            DB::raw("SUM(CASE WHEN download_type = 'in_app' THEN 1 ELSE 0 END) as in_app"),
            DB::raw('COUNT(DISTINCT user_id) as unique_users')
        )
            ->groupBy('movie_model_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $topGalleryRaw = (clone $query)->where('download_type', 'gallery')
            ->select(
                'movie_model_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->groupBy('movie_model_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $topInAppRaw = (clone $query)->where('download_type', 'in_app')
            ->select(
                'movie_model_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->groupBy('movie_model_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        // Batch-load all referenced movies in 1 query instead of N+1
        $allMovieIds = $topMoviesRaw->pluck('movie_model_id')
            ->merge($topGalleryRaw->pluck('movie_model_id'))
            ->merge($topInAppRaw->pluck('movie_model_id'))
            ->unique()->toArray();
        $moviesMap = MovieModel::select('id', 'title', 'thumbnail_url')
            ->whereIn('id', $allMovieIds)->get()->keyBy('id');

        $topMovies = $topMoviesRaw->map(function ($row) use ($moviesMap) {
            $movie = $moviesMap->get($row->movie_model_id);
            return [
                'movie_id'     => $row->movie_model_id,
                'title'        => $movie->title ?? 'Unknown',
                'thumbnail'    => $movie->thumbnail_url ?? '',
                'total'        => (int) $row->total,
                'gallery'      => (int) $row->gallery,
                'in_app'       => (int) $row->in_app,
                'unique_users' => (int) $row->unique_users,
            ];
        });

        $topGallery = $topGalleryRaw->map(function ($row) use ($moviesMap) {
            $movie = $moviesMap->get($row->movie_model_id);
            return [
                'movie_id'     => $row->movie_model_id,
                'title'        => $movie->title ?? 'Unknown',
                'thumbnail'    => $movie->thumbnail_url ?? '',
                'total'        => (int) $row->total,
                'unique_users' => (int) $row->unique_users,
            ];
        });

        $topInApp = $topInAppRaw->map(function ($row) use ($moviesMap) {
            $movie = $moviesMap->get($row->movie_model_id);
            return [
                'movie_id'     => $row->movie_model_id,
                'title'        => $movie->title ?? 'Unknown',
                'thumbnail'    => $movie->thumbnail_url ?? '',
                'total'        => (int) $row->total,
                'unique_users' => (int) $row->unique_users,
            ];
        });

        return $this->success([
            'period_days'  => $days,
            'totals'       => [
                'total'   => (int) $totals->total,
                'gallery' => (int) $totals->gallery,
                'in_app'  => (int) $totals->in_app,
            ],
            'unique_users' => [
                'total'   => $uniqueUsers,
                'gallery' => $uniqueUsersGallery,
                'in_app'  => $uniqueUsersInApp,
            ],
            'daily'            => $daily,
            'weekly'           => $weekly,
            'top_movies'       => $topMovies,
            'top_gallery'      => $topGallery,
            'top_in_app'       => $topInApp,
        ]);
    }
}
