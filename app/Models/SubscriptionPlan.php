<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_luganda',
        'name_swahili',
        'slug',
        'description',
        'description_luganda',
        'description_swahili',
        'price',
        'currency',
        'duration_days',
        'features',
        'features_luganda',
        'features_swahili',
        'status',
        'is_featured',
        'sort_order',
        'discount_percentage',
        'is_trial',
        'max_downloads',
        'max_watchlist',
        'ad_free',
        'hd_streaming',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_trial' => 'boolean',
        'ad_free' => 'boolean',
        'hd_streaming' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method - Handle auto-generation of slug
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }

            // Ensure unique slug
            $originalSlug = $plan->slug;
            $count = 1;
            while (self::where('slug', $plan->slug)->exists()) {
                $plan->slug = $originalSlug . '-' . $count;
                $count++;
            }
        });

        static::updating(function ($plan) {
            if ($plan->isDirty('name') && empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });

        // Handle soft deletes - mark related subscriptions
        static::deleting(function ($plan) {
            // Only allow deletion if no active subscriptions exist
            $activeCount = $plan->subscriptions()->whereIn('status', ['Active', 'Pending'])->count();
            
            if ($activeCount > 0) {
                throw new \Exception("Cannot delete plan with {$activeCount} active subscriptions. Please expire or cancel them first.");
            }

            // Update expired subscriptions to reference null plan
            $plan->subscriptions()->where('status', 'Expired')->update(['plan_id' => null]);
        });
    }

    /**
     * Get subscriptions for this plan
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get active subscriptions count
     */
    public function activeSubscriptionsCount()
    {
        return $this->subscriptions()->where('status', 'Active')->count();
    }

    /**
     * Get creator user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get updater user
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope: Get only active plans
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope: Get featured plans
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('price', 'asc');
    }

    /**
     * Get plan name in specific language
     * 
     * @param string $lang 'en', 'lg' (Luganda), 'sw' (Swahili)
     * @return string
     */
    public function getNameInLanguage($lang = 'en')
    {
        switch ($lang) {
            case 'lg':
            case 'luganda':
                return $this->name_luganda ?: $this->name;
            case 'sw':
            case 'swahili':
                return $this->name_swahili ?: $this->name;
            default:
                return $this->name;
        }
    }

    /**
     * Get plan description in specific language
     * 
     * @param string $lang 'en', 'lg', 'sw'
     * @return string
     */
    public function getDescriptionInLanguage($lang = 'en')
    {
        switch ($lang) {
            case 'lg':
            case 'luganda':
                return $this->description_luganda ?: $this->description;
            case 'sw':
            case 'swahili':
                return $this->description_swahili ?: $this->description;
            default:
                return $this->description;
        }
    }

    /**
     * Get plan features in specific language
     * 
     * @param string $lang 'en', 'lg', 'sw'
     * @return string
     */
    public function getFeaturesInLanguage($lang = 'en')
    {
        switch ($lang) {
            case 'lg':
            case 'luganda':
                return $this->features_luganda ?: $this->features;
            case 'sw':
            case 'swahili':
                return $this->features_swahili ?: $this->features;
            default:
                return $this->features;
        }
    }

    /**
     * Get actual price after discount
     * 
     * @return float
     */
    public function getActualPrice()
    {
        if ($this->discount_percentage > 0) {
            $discount = ($this->price * $this->discount_percentage) / 100;
            return $this->price - $discount;
        }
        return $this->price;
    }

    /**
     * Get formatted price
     * 
     * @param bool $withCurrency
     * @return string
     */
    public function getFormattedPrice($withCurrency = true)
    {
        $price = number_format($this->getActualPrice(), 0);
        return $withCurrency ? "{$this->currency} {$price}" : $price;
    }

    /**
     * Get human-readable duration
     * 
     * @return string
     */
    public function getDurationText()
    {
        if ($this->duration_days == 1) {
            return '1 Day';
        } elseif ($this->duration_days == 7) {
            return '1 Week';
        } elseif ($this->duration_days == 14) {
            return '2 Weeks';
        } elseif ($this->duration_days == 30) {
            return '1 Month';
        } elseif ($this->duration_days == 90) {
            return '3 Months';
        } elseif ($this->duration_days == 365) {
            return '1 Year';
        } else {
            return "{$this->duration_days} Days";
        }
    }

    /**
     * Get daily cost
     * 
     * @return float
     */
    public function getDailyCost()
    {
        return round($this->getActualPrice() / $this->duration_days, 2);
    }

    /**
     * Check if plan is popular/best value
     * Usually the plan with longest duration or highest active subscriptions
     * 
     * @return bool
     */
    public function isPopular()
    {
        // Mark 30-day plan as popular by default, or can be based on active subscriptions
        return $this->duration_days == 30 || $this->is_featured;
    }

    /**
     * Get features as array
     * 
     * @param string $lang
     * @return array
     */
    public function getFeaturesArray($lang = 'en')
    {
        $featuresHtml = $this->getFeaturesInLanguage($lang);
        
        if (empty($featuresHtml)) {
            return [];
        }

        // Extract list items from HTML
        preg_match_all('/<li>(.*?)<\/li>/s', $featuresHtml, $matches);
        
        return $matches[1] ?? [];
    }

    /**
     * To API array
     * 
     * @param string $lang
     * @return array
     */
    public function toApiArray($lang = 'en')
    {
        return [
            'id' => $this->id,
            'name' => $this->getNameInLanguage($lang),
            'slug' => $this->slug,
            'description' => $this->getDescriptionInLanguage($lang),
            'price' => $this->price,
            'actual_price' => $this->getActualPrice(),
            'formatted_price' => $this->getFormattedPrice(),
            'currency' => $this->currency,
            'duration_days' => $this->duration_days,
            'duration_text' => $this->getDurationText(),
            'daily_cost' => $this->getDailyCost(),
            'features' => $this->getFeaturesInLanguage($lang),
            'features_array' => $this->getFeaturesArray($lang),
            'is_featured' => $this->is_featured,
            'is_popular' => $this->isPopular(),
            'is_trial' => $this->is_trial,
            'discount_percentage' => $this->discount_percentage,
            'max_downloads' => $this->max_downloads,
            'max_watchlist' => $this->max_watchlist,
            'ad_free' => $this->ad_free,
            'hd_streaming' => $this->hd_streaming,
            'status' => $this->status,
            'active_subscriptions' => $this->activeSubscriptionsCount(),
        ];
    }
}
