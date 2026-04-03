<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogComment;
use App\Models\BlogLike;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * ═══════════════════════════════════════════════════════════
 *  V2 Blog API Controller
 * ═══════════════════════════════════════════════════════════
 *
 *  Simple blog/news module for posting updates to app users.
 *
 *  Endpoints:
 *    GET    /api/v2/blog              – Paginated blog posts
 *    GET    /api/v2/blog/{id}         – Single post + comments
 *    POST   /api/v2/blog/{id}/like    – Toggle like on post
 *    POST   /api/v2/blog/{id}/comment – Add comment
 *    POST   /api/v2/blog/comment/{id}/like    – Toggle like on comment
 *    POST   /api/v2/blog/comment/{id}/report  – Report comment
 *
 *  Design:
 *    • Max 20 posts per page
 *    • Comments paginated (20 per page)
 *    • Proper moderation (report → hidden after threshold)
 * ═══════════════════════════════════════════════════════════
 */
class BlogController extends Controller
{
    use ApiResponser;

    protected const PER_PAGE = 20;
    protected const MAX_PER_PAGE = 50;
    protected const REPORT_THRESHOLD = 3; // Auto-hide after N reports

    protected const LIST_FIELDS = [
        'id', 'title', 'excerpt', 'image_url', 'category',
        'author_name', 'views_count', 'likes_count', 'comments_count',
        'is_pinned', 'created_at',
    ];

    protected const DETAIL_FIELDS = [
        'id', 'title', 'content', 'excerpt', 'image_url', 'category',
        'author_name', 'status', 'views_count', 'likes_count', 'comments_count',
        'is_pinned', 'comments_enabled', 'created_at', 'updated_at',
    ];

    /**
     * Resolve image_url to a full URL.
     * Admin uploads store relative paths like "images/xxx.jpg".
     * This prepends the storage base so the mobile app can load them.
     */
    private function resolveImageUrl(?string $imageUrl): string
    {
        if (empty($imageUrl)) return '';
        // Already a full URL
        if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
            return $imageUrl;
        }
        return url('storage/' . ltrim($imageUrl, '/'));
    }

    // ────────────────────────────────────────────────
    //  GET /api/v2/blog — List posts (paginated)
    // ────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $perPage = min((int) $request->get('per_page', self::PER_PAGE), self::MAX_PER_PAGE);
        $category = $request->get('category');

        $query = BlogPost::select(self::LIST_FIELDS)
            ->where('status', 'Active')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        if ($category) {
            $query->where('category', $category);
        }

        $posts = $query->paginate($perPage);

        // Check if user has liked each post
        $postIds = collect($posts->items())->pluck('id')->toArray();
        $likedIds = BlogLike::where('user_id', $user->id)
            ->where('likeable_type', 'blog_post')
            ->whereIn('likeable_id', $postIds)
            ->pluck('likeable_id')
            ->toArray();

        $items = collect($posts->items())->map(function ($post) use ($likedIds) {
            $data = $post->toArray();
            $data['has_liked'] = in_array($post->id, $likedIds);
            $data['time_ago'] = $this->timeAgo($post->created_at);
            $data['image_url'] = $this->resolveImageUrl($post->image_url);
            return $data;
        })->toArray();

        Log::info("[V2:blog] list page={$posts->currentPage()} count=" . count($items));

        return $this->success([
            'posts' => $items,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
            ],
        ], 'Blog posts retrieved successfully.');
    }

    // ────────────────────────────────────────────────
    //  GET /api/v2/blog/{id} — Single post + comments
    // ────────────────────────────────────────────────
    public function show(Request $request, $id)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $post = BlogPost::select(self::DETAIL_FIELDS)->find($id);
        if (!$post || $post->status !== 'Active') {
            return $this->error('Post not found.', 404);
        }

        // Increment view count and refresh
        BlogPost::where('id', $id)->increment('views_count');
        $post->refresh();

        $postData = $post->toArray();
        $postData['has_liked'] = BlogPost::hasUserLiked($user->id, $id);
        $postData['time_ago'] = $this->timeAgo($post->created_at);
        $postData['image_url'] = $this->resolveImageUrl($post->image_url);

        // Fetch comments (paginated)
        $commentsPage = (int) $request->get('comments_page', 1);
        $comments = BlogComment::where('blog_post_id', $id)
            ->where('status', 'Active')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', $commentsPage);

        $commentIds = collect($comments->items())->pluck('id')->toArray();
        $likedCommentIds = BlogLike::where('user_id', $user->id)
            ->where('likeable_type', 'blog_comment')
            ->whereIn('likeable_id', $commentIds)
            ->pluck('likeable_id')
            ->toArray();

        $commentItems = collect($comments->items())->map(function ($c) use ($likedCommentIds) {
            $data = $c->toArray();
            $data['has_liked'] = in_array($c->id, $likedCommentIds);
            $data['time_ago'] = $this->timeAgo($c->created_at);
            return $data;
        })->toArray();

        Log::info("[V2:blog] show id={$id} comments_page={$commentsPage}");

        return $this->success([
            'post'     => $postData,
            'comments' => $commentItems,
            'comments_pagination' => [
                'current_page' => $comments->currentPage(),
                'last_page'    => $comments->lastPage(),
                'total'        => $comments->total(),
            ],
        ], 'Post retrieved successfully.');
    }

    // ────────────────────────────────────────────────
    //  POST /api/v2/blog/{id}/like — Toggle like
    // ────────────────────────────────────────────────
    public function toggleLike(Request $request, $id)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $post = BlogPost::find($id);
        if (!$post) {
            return $this->error('Post not found.', 404);
        }

        $existing = BlogLike::where('user_id', $user->id)
            ->where('likeable_type', 'blog_post')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            BlogPost::where('id', $id)->decrement('likes_count');
            $action = 'unliked';
        } else {
            BlogLike::create([
                'user_id'       => $user->id,
                'likeable_type' => 'blog_post',
                'likeable_id'   => $id,
            ]);
            BlogPost::where('id', $id)->increment('likes_count');
            $action = 'liked';
        }

        $newCount = BlogPost::where('id', $id)->value('likes_count');
        Log::info("[V2:blog] like post={$id} user={$user->id} action={$action}");

        return $this->success([
            'action'      => $action,
            'liked'       => $action === 'liked',
            'likes_count' => $newCount,
        ], ucfirst($action) . '!');
    }

    // ────────────────────────────────────────────────
    //  POST /api/v2/blog/{id}/comment — Add comment
    // ────────────────────────────────────────────────
    public function addComment(Request $request, $id)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $post = BlogPost::find($id);
        if (!$post) {
            return $this->error('Post not found.', 404);
        }

        if (!$post->comments_enabled) {
            return $this->error('Comments are disabled for this post.');
        }

        $content = trim($request->get('content', ''));
        if (empty($content)) {
            return $this->error('Comment cannot be empty.');
        }
        if (strlen($content) > 1000) {
            return $this->error('Comment is too long (max 1000 characters).');
        }

        $fullUser = User::find($user->id);
        $comment = BlogComment::create([
            'blog_post_id' => $id,
            'user_id'      => $user->id,
            'user_name'    => $fullUser ? ($fullUser->name ?? 'User') : 'User',
            'content'      => $content,
        ]);

        BlogPost::where('id', $id)->increment('comments_count');

        Log::info("[V2:blog] comment post={$id} user={$user->id} comment_id={$comment->id}");

        return $this->success([
            'comment' => [
                'id'        => $comment->id,
                'content'   => $comment->content,
                'user_name' => $comment->user_name,
                'user_id'   => $comment->user_id,
                'has_liked' => false,
                'likes_count' => 0,
                'time_ago'  => 'Just now',
                'created_at' => $comment->created_at,
            ],
            'comments_count' => BlogPost::where('id', $id)->value('comments_count'),
        ], 'Comment added.');
    }

    // ────────────────────────────────────────────────
    //  POST /api/v2/blog/comment/{id}/like
    // ────────────────────────────────────────────────
    public function toggleCommentLike(Request $request, $id)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $comment = BlogComment::find($id);
        if (!$comment) {
            return $this->error('Comment not found.', 404);
        }

        $existing = BlogLike::where('user_id', $user->id)
            ->where('likeable_type', 'blog_comment')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            BlogComment::where('id', $id)->decrement('likes_count');
            $action = 'unliked';
        } else {
            BlogLike::create([
                'user_id'       => $user->id,
                'likeable_type' => 'blog_comment',
                'likeable_id'   => $id,
            ]);
            BlogComment::where('id', $id)->increment('likes_count');
            $action = 'liked';
        }

        return $this->success([
            'action' => $action,
            'liked'  => $action === 'liked',
            'likes_count' => BlogComment::where('id', $id)->value('likes_count'),
        ]);
    }

    // ────────────────────────────────────────────────
    //  POST /api/v2/blog/comment/{id}/report
    // ────────────────────────────────────────────────
    public function reportComment(Request $request, $id)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        $comment = BlogComment::find($id);
        if (!$comment) {
            return $this->error('Comment not found.', 404);
        }

        // Simple approach: increment a "reported" count
        // After threshold, auto-hide
        $reportCount = BlogLike::where('likeable_type', 'blog_comment_report')
            ->where('likeable_id', $id)
            ->count();

        $alreadyReported = BlogLike::where('user_id', $user->id)
            ->where('likeable_type', 'blog_comment_report')
            ->where('likeable_id', $id)
            ->exists();

        if ($alreadyReported) {
            return $this->error('You have already reported this comment.');
        }

        BlogLike::create([
            'user_id'       => $user->id,
            'likeable_type' => 'blog_comment_report',
            'likeable_id'   => $id,
        ]);

        if ($reportCount + 1 >= self::REPORT_THRESHOLD) {
            BlogComment::where('id', $id)->update(['status' => 'Hidden']);
            Log::warning("[V2:blog] comment auto-hidden id={$id} reports=" . ($reportCount + 1));
        }

        Log::info("[V2:blog] report comment={$id} user={$user->id}");

        return $this->success(['reported' => true], 'Comment reported. Thank you.');
    }

    // ────────────────────────────────────────────────
    //  GET /api/v2/blog/marquee — Latest unseen posts (max 5, last 90 days)
    //  Client sends `seen_ids` as comma-separated string of post IDs
    //  that the user has already viewed in the marquee.
    // ────────────────────────────────────────────────
    public function marquee(Request $request)
    {
        $user = Utils::get_user($request);
        if (!$user) {
            return $this->error('Authentication required.', 401);
        }

        // Client sends comma-separated IDs of posts already seen
        $seenRaw = $request->get('seen_ids', '');
        $seenIds = array_filter(array_map('intval', explode(',', $seenRaw)));

        // Cache marquee posts for 5 minutes per seen_ids combination
        $cacheKey = 'blog_marquee_' . md5(implode(',', $seenIds));
        $posts = Cache::remember($cacheKey, 300, function () use ($seenIds) {
            $query = BlogPost::select(['id', 'title', 'category', 'created_at'])
                ->where('status', 'Active')
                ->where('created_at', '>=', now()->subDays(90))
                ->orderByDesc('is_pinned')
                ->orderByDesc('created_at')
                ->limit(10);

            if (!empty($seenIds)) {
                $query->whereNotIn('id', $seenIds);
            }

            return $query->get()->take(5)->map(function ($p) {
                return [
                    'id'         => $p->id,
                    'title'      => $p->title,
                    'category'   => $p->category,
                    'time_ago'   => $this->timeAgo($p->created_at),
                    'created_at' => $p->created_at,
                ];
            })->values()->toArray();
        });

        // Write pre-bootstrap cache (shared - same for all users)
        $cacheDir = storage_path('api_cache');
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        @file_put_contents("{$cacheDir}/shared_" . md5('/api/v2/blog/marquee'), json_encode(['code' => 1, 'status' => 1, 'message' => '', 'data' => ['posts' => $posts]]));

        // LiteSpeed Cache: cache this response at the web server level
        return $this->success(['posts' => $posts])
            ->header('X-LiteSpeed-Cache-Control', 'public, max-age=120')
            ->header('X-LiteSpeed-Tag', 'blog_marquee')
            ->header('Cache-Control', 'public, max-age=120')
            ->withoutHeader('Vary');
    }

    // ────────────────────────────────────────────────
    //  Helper: Human-readable time ago
    // ────────────────────────────────────────────────
    private function timeAgo($datetime): string
    {
        if (!$datetime) return '';
        try {
            $now = now();
            $diff = $now->diff(\Carbon\Carbon::parse($datetime));

            if ($diff->y > 0) return $diff->y . 'y ago';
            if ($diff->m > 0) return $diff->m . 'mo ago';
            if ($diff->d > 0) return $diff->d . 'd ago';
            if ($diff->h > 0) return $diff->h . 'h ago';
            if ($diff->i > 0) return $diff->i . 'm ago';
            return 'Just now';
        } catch (\Exception $e) {
            return '';
        }
    }
}
