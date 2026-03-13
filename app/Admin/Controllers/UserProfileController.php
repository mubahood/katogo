<?php

namespace App\Admin\Controllers;

use App\Models\ChatMessage;
use App\Models\ContentReport;
use App\Models\MovieDownload;
use App\Models\MovieLike;
use App\Models\MovieModel;
use App\Models\MovieView;
use App\Models\MovieWishlist;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Models\UserBlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserProfileController
{
    public function show($id)
    {
        $user = User::findOrFail($id);

        // ── Subscription data ────────────────────────────
        $subscriptions = Subscription::where('user_id', $id)
            ->orderBy('end_date_time', 'desc')
            ->get();
        $activeSub = $subscriptions->first(fn($s) => $s->status === 'Active' && $s->end_date_time >= now());
        $totalSpent = $subscriptions->sum('amount_paid');
        $transactions = SubscriptionTransaction::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // ── Watch activity ───────────────────────────────
        $views = MovieView::where('user_id', $id);
        $totalViews = (clone $views)->count();
        $totalWatchSeconds = (clone $views)->sum('progress');
        $totalWatchHours = round($totalWatchSeconds / 3600, 1);
        $recentViews = (clone $views)->orderBy('updated_at', 'desc')
            ->limit(20)->get();
        $viewMovieIds = $recentViews->pluck('movie_model_id')->unique()->toArray();
        $viewMovies = MovieModel::whereIn('id', $viewMovieIds)
            ->get(['id', 'title', 'thumbnail_url', 'genre', 'type'])
            ->keyBy('id');

        // Views by day (last 30 days)
        $viewsByDay = MovieView::where('user_id', $id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('cnt', 'date')
            ->toArray();

        // ── Downloads ────────────────────────────────────
        $downloads = MovieDownload::where('user_id', $id);
        $totalDownloads = (clone $downloads)->count();
        $inAppDownloads = (clone $downloads)->where('download_type', 'in_app')->count();
        $galleryDownloads = (clone $downloads)->where('download_type', 'gallery')->count();
        $recentDownloads = MovieDownload::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)->get();

        // ── Likes & Wishlist ─────────────────────────────
        $totalLikes = MovieLike::where('user_id', $id)->count();
        $recentLikes = MovieLike::where('user_id', $id)
            ->orderBy('created_at', 'desc')->limit(20)->get();
        $likeMovieIds = $recentLikes->pluck('movie_model_id')->unique()->toArray();
        $likeMovies = MovieModel::whereIn('id', $likeMovieIds)
            ->get(['id', 'title', 'thumbnail_url'])->keyBy('id');

        $totalWishlist = MovieWishlist::where('user_id', $id)->count();
        $recentWishlist = MovieWishlist::where('user_id', $id)
            ->orderBy('created_at', 'desc')->limit(20)->get();
        $wishMovieIds = $recentWishlist->pluck('movie_model_id')->unique()->toArray();
        $wishMovies = MovieModel::whereIn('id', $wishMovieIds)
            ->get(['id', 'title', 'thumbnail_url'])->keyBy('id');

        // ── Chat activity ────────────────────────────────
        $sentMessages = ChatMessage::where('sender_id', $id)->count();
        $receivedMessages = ChatMessage::where('receiver_id', $id)->count();
        $uniqueChats = ChatMessage::where('sender_id', $id)
            ->distinct('receiver_id')->count('receiver_id');

        // ── Moderation ───────────────────────────────────
        $reportsMade = ContentReport::where('reporter_id', $id)->count();
        $reportsAgainst = ContentReport::where('reported_user_id', $id)->count();
        $blockedUsers = UserBlock::where('blocker_id', $id)->count();
        $blockedBy = UserBlock::where('blocked_user_id', $id)->count();

        // ── Genre preferences (from views) ──────────────
        $genreBreakdown = [];
        if ($totalViews > 0) {
            $viewedMovieIds = MovieView::where('user_id', $id)
                ->pluck('movie_model_id')->unique()->toArray();
            $genres = MovieModel::whereIn('id', $viewedMovieIds)
                ->whereNotNull('genre')->where('genre', '!=', '')
                ->pluck('genre')->toArray();
            $counts = [];
            foreach ($genres as $g) {
                foreach (array_map('trim', preg_split('/[,\/]/', $g)) as $part) {
                    $part = ucfirst(strtolower($part));
                    if (strlen($part) > 1) {
                        $counts[$part] = ($counts[$part] ?? 0) + 1;
                    }
                }
            }
            arsort($counts);
            $genreBreakdown = array_slice($counts, 0, 10, true);
        }

        // ── Account age & engagement ─────────────────────
        $accountAge = $user->created_at ? $user->created_at->diffForHumans(null, true) : 'Unknown';
        $daysSinceRegistered = $user->created_at ? $user->created_at->diffInDays(now()) : 0;
        $avgViewsPerDay = $daysSinceRegistered > 0 ? round($totalViews / $daysSinceRegistered, 1) : 0;

        return view('admin.users.profile', compact(
            'user',
            'subscriptions', 'activeSub', 'totalSpent', 'transactions',
            'totalViews', 'totalWatchHours', 'recentViews', 'viewMovies', 'viewsByDay',
            'totalDownloads', 'inAppDownloads', 'galleryDownloads', 'recentDownloads',
            'totalLikes', 'recentLikes', 'likeMovies',
            'totalWishlist', 'recentWishlist', 'wishMovies',
            'sentMessages', 'receivedMessages', 'uniqueChats',
            'reportsMade', 'reportsAgainst', 'blockedUsers', 'blockedBy',
            'genreBreakdown',
            'accountAge', 'daysSinceRegistered', 'avgViewsPerDay'
        ));
    }
}
