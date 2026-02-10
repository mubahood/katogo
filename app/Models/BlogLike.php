<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogLike extends Model
{
    protected $table = 'blog_likes';

    protected $fillable = [
        'user_id', 'likeable_type', 'likeable_id',
    ];
}
