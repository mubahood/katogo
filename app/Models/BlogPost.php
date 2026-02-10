<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $table = 'blog_posts';

    protected $fillable = [
        'title', 'content', 'excerpt', 'image_url', 'category',
        'author_id', 'author_name', 'status', 'views_count',
        'likes_count', 'comments_count', 'is_pinned', 'comments_enabled',
    ];

    protected $casts = [
        'is_pinned'        => 'boolean',
        'comments_enabled' => 'boolean',
        'views_count'      => 'integer',
        'likes_count'      => 'integer',
        'comments_count'   => 'integer',
    ];

    public function comments()
    {
        return $this->hasMany(BlogComment::class);
    }

    public function likes()
    {
        return $this->hasMany(BlogLike::class, 'likeable_id')
            ->where('likeable_type', 'blog_post');
    }

    public static function hasUserLiked(int $userId, int $postId): bool
    {
        return BlogLike::where('user_id', $userId)
            ->where('likeable_type', 'blog_post')
            ->where('likeable_id', $postId)
            ->exists();
    }
}
