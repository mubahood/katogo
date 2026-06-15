<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubtitleFile extends Model
{
    protected $table = 'subtitle_files';

    protected $fillable = [
        'movie_id',
        'language',
        'label',
        'url',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function movie()
    {
        return $this->belongsTo(MovieModel::class, 'movie_id');
    }
}
