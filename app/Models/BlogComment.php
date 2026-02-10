<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogComment extends Model
{
    protected $table = 'blog_comments';

    protected $fillable = [
        'blog_post_id', 'user_id', 'user_name', 'content',
        'status', 'likes_count',
    ];

    protected $casts = [
        'likes_count' => 'integer',
    ];

    public function post()
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public static function hasUserLiked(int $userId, int $commentId): bool
    {
        return BlogLike::where('user_id', $userId)
            ->where('likeable_type', 'blog_comment')
            ->where('likeable_id', $commentId)
            ->exists();
    }
}
