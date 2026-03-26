<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TriviaQuestion extends Model
{
    use HasFactory;

    protected $table = 'trivia_questions';

    protected $fillable = [
        'question',
        'difficulty',
        'category',
        'format',
        'correct_answer',
        'wrong_answers',
        'hint',
        'image_url',
        'points',
        'timer_seconds',
        'version',
        'status',
    ];

    protected $casts = [
        'wrong_answers' => 'array',
        'points' => 'integer',
        'timer_seconds' => 'integer',
        'version' => 'integer',
    ];

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOfDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeNewerThan($query, int $version)
    {
        return $query->where('version', '>', $version);
    }
}
