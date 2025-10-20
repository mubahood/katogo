<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MovieSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'search_term',
        'search_term_normalized',
        'ip_address',
        'user_agent',
        'platform',
        'search_count',
        'results_count',
        'has_results',
        'found_movie_ids',
        'click_count',
        'first_searched_at',
        'last_searched_at',
    ];

    protected $casts = [
        'first_searched_at' => 'datetime',
        'last_searched_at' => 'datetime',
        'has_results' => 'boolean',
    ];

    /**
     * Relationship to user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get found movies as array
     */
    public function getFoundMovieIdsArrayAttribute()
    {
        return json_decode($this->found_movie_ids, true) ?? [];
    }

    /**
     * Set found movies from array
     */
    public function setFoundMovieIdsArrayAttribute($value)
    {
        $this->attributes['found_movie_ids'] = json_encode($value);
    }

    /**
     * Smart logging method to avoid duplicate searches
     * This implements the intelligent deduplication logic
     */
    public static function logSearch($searchTerm, $resultsCount = 0, $foundMovieIds = [], $userId = null, $request = null)
    {
        // Normalize search term
        $normalized = strtolower(trim($searchTerm));
        
        // Ignore very short searches (less than 2 characters)
        if (strlen($normalized) < 2) {
            return null;
        }

        // Get user info
        $ipAddress = $request ? $request->ip() : request()->ip();
        $userAgent = $request ? $request->userAgent() : request()->userAgent();
        
        // Look for recent similar searches (last 5 minutes)
        $fiveMinutesAgo = Carbon::now()->subMinutes(5);
        
        // Build query to find similar recent searches
        $query = self::where('search_term_normalized', 'LIKE', $normalized . '%')
            ->where('last_searched_at', '>=', $fiveMinutesAgo);
        
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('ip_address', $ipAddress);
        }
        
        $recentSearch = $query->orderBy('last_searched_at', 'desc')->first();
        
        // If we found a recent search that starts with the same letters
        if ($recentSearch) {
            // Only update if the new search is longer (user is typing more)
            if (strlen($normalized) > strlen($recentSearch->search_term_normalized)) {
                $recentSearch->search_term = $searchTerm;
                $recentSearch->search_term_normalized = $normalized;
                $recentSearch->search_count += 1;
                $recentSearch->results_count = $resultsCount;
                $recentSearch->has_results = $resultsCount > 0;
                $recentSearch->found_movie_ids = json_encode($foundMovieIds);
                $recentSearch->last_searched_at = now();
                $recentSearch->user_agent = $userAgent;
                $recentSearch->save();
                
                return $recentSearch;
            }
            
            // If it's the same length or shorter, just update the count and timestamp
            $recentSearch->search_count += 1;
            $recentSearch->last_searched_at = now();
            $recentSearch->save();
            
            return $recentSearch;
        }
        
        // No recent similar search found, create a new entry
        return self::create([
            'user_id' => $userId,
            'search_term' => $searchTerm,
            'search_term_normalized' => $normalized,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'platform' => 'web',
            'search_count' => 1,
            'results_count' => $resultsCount,
            'has_results' => $resultsCount > 0,
            'found_movie_ids' => json_encode($foundMovieIds),
            'first_searched_at' => now(),
            'last_searched_at' => now(),
        ]);
    }

    /**
     * Record when user clicks on a search result
     */
    public function recordClick()
    {
        $this->increment('click_count');
    }

    /**
     * Get popular searches
     */
    public static function getPopularSearches($limit = 20, $days = 30)
    {
        return self::where('created_at', '>=', Carbon::now()->subDays($days))
            ->where('has_results', true)
            ->orderBy('search_count', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get searches with no results (to identify missing content)
     */
    public static function getSearchesWithNoResults($limit = 50, $days = 30)
    {
        return self::where('created_at', '>=', Carbon::now()->subDays($days))
            ->where('has_results', false)
            ->orderBy('search_count', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Get trending searches (recently popular)
     */
    public static function getTrendingSearches($limit = 20, $hours = 24)
    {
        return self::where('last_searched_at', '>=', Carbon::now()->subHours($hours))
            ->where('has_results', true)
            ->orderBy('search_count', 'desc')
            ->take($limit)
            ->get();
    }
}
