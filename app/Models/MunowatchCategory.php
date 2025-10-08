<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Log;

/**
 * MunowatchCategory Model
 * 
 * Manages munowatch video categories for organized crawling.
 * Each category represents a content type (movies, series, korean, animation)
 * with its own crawling parameters and status tracking.
 */
class MunowatchCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'munowatch_id',
        'name',
        'slug',
        'description',
        'api_endpoint',
        'api_parameters',
        'status',
        'is_featured',
        'sort_order',
        'total_pages',
        'current_page',
        'total_videos_found',
        'new_videos_last_crawl',
        'crawl_status',
        'error_message',
        'last_crawled_at',
        'next_crawl_at',
        'crawl_frequency_hours',
        'videos_per_page',
        'success_rate',
        'metadata',
        'parent_category_id'
    ];

    protected $casts = [
        'api_parameters' => 'array',
        'metadata' => 'array',
        'is_featured' => 'boolean',
        'last_crawled_at' => 'datetime',
        'next_crawl_at' => 'datetime',
        'success_rate' => 'decimal:2'
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_DEPRECATED = 'deprecated';

    // Crawl status constants
    const CRAWL_PENDING = 'pending';
    const CRAWL_IN_PROGRESS = 'in_progress';
    const CRAWL_COMPLETED = 'completed';
    const CRAWL_FAILED = 'failed';
    const CRAWL_PAUSED = 'paused';

    // Default category IDs based on munowatch API
    const CATEGORY_MOVIES = 1;
    const CATEGORY_SERIES = 2;
    const CATEGORY_KOREAN = 3;
    const CATEGORY_ANIMATION = 4;

    /**
     * Scope: Get active categories only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Get featured categories
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Get categories ready for crawling
     */
    public function scopeReadyForCrawl($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where('crawl_status', '!=', self::CRAWL_IN_PROGRESS)
                    ->where(function($q) {
                        $q->whereNull('next_crawl_at')
                          ->orWhere('next_crawl_at', '<=', Carbon::now());
                    });
    }

    /**
     * Scope: Order by crawling priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('is_featured', 'desc')
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('last_crawled_at', 'asc');
    }

    /**
     * Get the API URL for this category with proper parameters
     */
    public function getApiUrl($page = 1, $userId = '169464')
    {
        // Base URL: https://munowatch.org/api/list/{pipe}/{pid}/{uid}/{lid}
        $baseUrl = 'https://munowatch.org/api/list';
        $pipe = 1; // Browse type
        $pid = $this->munowatch_id; // Category ID
        $uid = $userId; // User ID
        $lid = $page; // Page number

        return "{$baseUrl}/{$pipe}/{$pid}/{$uid}/{$lid}";
    }

    /**
     * Mark category as being crawled
     */
    public function startCrawling()
    {
        $this->update([
            'crawl_status' => self::CRAWL_IN_PROGRESS,
            'error_message' => null,
            'last_crawled_at' => Carbon::now()
        ]);

        Log::info('Started crawling munowatch category', [
            'category_id' => $this->id,
            'munowatch_id' => $this->munowatch_id,
            'name' => $this->name
        ]);
    }

    /**
     * Mark category crawling as completed
     */
    public function completeCrawling($newVideosCount = 0)
    {
        $this->update([
            'crawl_status' => self::CRAWL_COMPLETED,
            'new_videos_last_crawl' => $newVideosCount,
            'total_videos_found' => $this->total_videos_found + $newVideosCount,
            'next_crawl_at' => Carbon::now()->addHours($this->crawl_frequency_hours),
            'error_message' => null
        ]);

        Log::info('Completed crawling munowatch category', [
            'category_id' => $this->id,
            'munowatch_id' => $this->munowatch_id,
            'name' => $this->name,
            'new_videos' => $newVideosCount
        ]);
    }

    /**
     * Mark category crawling as failed
     */
    public function failCrawling($errorMessage)
    {
        $this->update([
            'crawl_status' => self::CRAWL_FAILED,
            'error_message' => $errorMessage,
            'next_crawl_at' => Carbon::now()->addHours(1) // Retry in 1 hour
        ]);

        Log::error('Failed crawling munowatch category', [
            'category_id' => $this->id,
            'munowatch_id' => $this->munowatch_id,
            'name' => $this->name,
            'error' => $errorMessage
        ]);
    }

    /**
     * Get next category for rotation-based crawling
     */
    public static function getNextForCrawling()
    {
        return self::active()
                  ->readyForCrawl()
                  ->byPriority()
                  ->first();
    }

    /**
     * Reset current page for fresh crawling cycle
     */
    public function resetCrawling()
    {
        $this->update([
            'current_page' => 0,
            'crawl_status' => self::CRAWL_PENDING,
            'error_message' => null
        ]);
    }

    /**
     * Increment current page
     */
    public function nextPage()
    {
        $this->increment('current_page');
        return $this->current_page;
    }

    /**
     * Check if category has more pages to crawl
     */
    public function hasMorePages()
    {
        return $this->total_pages == 0 || $this->current_page < $this->total_pages;
    }

    /**
     * Update success rate based on crawling results
     */
    public function updateSuccessRate($successful, $total)
    {
        if ($total > 0) {
            $rate = ($successful / $total) * 100;
            $this->update(['success_rate' => round($rate, 2)]);
        }
    }

    /**
     * Get formatted display name
     */
    public function getDisplayNameAttribute()
    {
        return ucfirst($this->name) . " (ID: {$this->munowatch_id})";
    }

    /**
     * Check if category is due for crawling
     */
    public function isDueForCrawling()
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->crawl_status === self::CRAWL_IN_PROGRESS) {
            return false;
        }

        return $this->next_crawl_at === null || $this->next_crawl_at->isPast();
    }

    /**
     * Get relationship to crawler pages
     */
    public function crawlerPages()
    {
        return $this->hasMany(MovieCrawlerPage::class, 'munowatch_category_id', 'id');
    }

    /**
     * Seed default munowatch categories
     */
    public static function seedDefaultCategories()
    {
        $categories = [
            [
                'munowatch_id' => self::CATEGORY_MOVIES,
                'name' => 'Movies',
                'slug' => 'movies',
                'description' => 'Full-length movies from various genres',
                'api_endpoint' => 'list/1/1/{uid}/{page}',
                'is_featured' => true,
                'sort_order' => 1,
                'crawl_frequency_hours' => 12
            ],
            [
                'munowatch_id' => self::CATEGORY_SERIES,
                'name' => 'Series',
                'slug' => 'series',
                'description' => 'TV series and episodic content',
                'api_endpoint' => 'list/1/2/{uid}/{page}',
                'is_featured' => true,
                'sort_order' => 2,
                'crawl_frequency_hours' => 8
            ],
            [
                'munowatch_id' => self::CATEGORY_KOREAN,
                'name' => 'Korean',
                'slug' => 'korean',
                'description' => 'Korean dramas and movies',
                'api_endpoint' => 'list/1/3/{uid}/{page}',
                'is_featured' => false,
                'sort_order' => 3,
                'crawl_frequency_hours' => 24
            ],
            [
                'munowatch_id' => self::CATEGORY_ANIMATION,
                'name' => 'Animation',
                'slug' => 'animation',
                'description' => 'Animated movies and series',
                'api_endpoint' => 'list/1/4/{uid}/{page}',
                'is_featured' => false,
                'sort_order' => 4,
                'crawl_frequency_hours' => 48
            ]
        ];

        foreach ($categories as $categoryData) {
            self::updateOrCreate(
                ['munowatch_id' => $categoryData['munowatch_id']],
                $categoryData
            );
        }

        Log::info('Seeded default munowatch categories', [
            'categories_count' => count($categories)
        ]);
    }
}
