<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;


class User extends Administrator implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected $table = 'admin_users';

    //company
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    //boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $name = "";
            if ($model->first_name != null && strlen($model->first_name) > 0) {
                $name = $model->first_name;
            }
            if ($model->last_name != null && strlen($model->last_name) > 0) {
                $name .= " " . $model->last_name;
            }
            $name = trim($name);

            if ($name != null && strlen($name) > 0) {
                $model->name = $name;
            }
            $model->username = $model->email;

            if ($model->password == null || strlen($model->password) < 3) {
                $model->password = bcrypt('admin');
            }

            if ($model->phone_number == null && strlen($model->phone_number) > 6) {
                $phone_number = $model->phone_number;
                $existing_user = User::where('phone_number', $phone_number)->first();
                if ($existing_user != null) {
                    throw new \Exception('Phone number already exists');
                }
            }

            if ($model->email == null && strlen($model->email) > 6) {
                $email = $model->email;
                $existing_user = User::where('email', $email)->first();
                if ($existing_user != null) {
                    throw new \Exception('Email already exists');
                }
            }

            //do the same for username
            if ($model->username == null && strlen($model->username) > 6) {
                $username = $model->username;
                $existing_user = User::where('username', $username)->first();
                if ($existing_user != null) {
                    throw new \Exception('Username already exists');
                }
            }

            return $model;
        });


        static::updating(function ($model) {
            $name = "";
            if ($model->first_name != null && strlen($model->first_name) > 0) {
                $name = $model->first_name;
            }
            if ($model->last_name != null && strlen($model->last_name) > 0) {
                $name .= " " . $model->last_name;
            }
            $name = trim($name);

            if ($name != null && strlen($name) > 0) {
                $model->name = $name;
            }

            if ($model->phone_number == null && strlen($model->phone_number) > 6) {
                $phone_number = $model->phone_number;
                $existing_user = User::where('phone_number', $phone_number)->where('id', '!=', $model->id)->first();
                if ($existing_user != null) {
                    throw new \Exception('Phone number already exists');
                }
            }
            if ($model->email == null && strlen($model->email) > 6) {
                $email = $model->email;
                $existing_user = User::where('email', $email)->where('id', '!=', $model->id)->first();
                if ($existing_user != null) {
                    throw new \Exception('Email already exists');
                }
            }
            //do the same for username
            if ($model->username == null && strlen($model->username) > 6) {
                $username = $model->username;
                $existing_user = User::where('username', $username)->where('id', '!=', $model->id)->first();
                if ($existing_user != null) {
                    throw new \Exception('Username already exists');
                }
            }

            $model->username = $model->email;
            return $model;
        });

        // Handle manual cascade deletes for moderation-related data
        static::deleting(function ($user) {
            // Delete content reports where user is reporter or reported user
            \App\Models\ContentReport::where('reporter_id', $user->id)->delete();
            \App\Models\ContentReport::where('reported_user_id', $user->id)->delete();

            // Delete user blocks where user is blocker or blocked
            \App\Models\UserBlock::where('blocker_id', $user->id)->delete();
            \App\Models\UserBlock::where('blocked_user_id', $user->id)->delete();

            // Delete moderation logs for this user
            \App\Models\ContentModerationLog::where('user_id', $user->id)->delete();

            // Set moderator_id to null for logs where this user was the moderator
            \App\Models\ContentModerationLog::where('moderator_id', $user->id)
                ->update(['moderator_id' => null]);

            // Set moderator_id to null for reports where this user was the moderator
            \App\Models\ContentReport::where('moderator_id', $user->id)
                ->update(['moderator_id' => null]);
        });
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    // getter for avatar
    public function getAvatarAttribute($value)
    {
        return $value;
        if ($value == null || strlen($value) < 3) {
            return url('logo.png');
        }
        $path = public_path('storage/' . $value);
        if (!file_exists($path)) {
            return url('logo.png');
        }
        return $value;
    }

    //getter for online_status
    public function getOnlineStatusAttribute($value)
    {
        $last_online_at = $this->last_online_at;
        if ($last_online_at == null || strlen($last_online_at) < 3) {
            $this->last_online_at = $this->updated_at;
            $this->save();
        }
        $last_online_at = null;
        try {
            $last_online_at = \Carbon\Carbon::parse($this->last_online_at);
        } catch (\Exception $e) {
            return 'Offline';
        }
        $now = \Carbon\Carbon::now();
        //mins ago
        $diff = $last_online_at->diffInMinutes($now);
        if ($diff < 25) {
            return 'Online';
        }
        return Utils::time_ago($last_online_at) . ' ago';
    }

    //setter for languages_spoken if is array to json

    /**
     * Moderation-related relationships
     */

    // Content reports made by this user
    public function contentReports()
    {
        return $this->hasMany(\App\Models\ContentReport::class, 'reporter_id');
    }

    // Content reports about this user
    public function reportsAgainst()
    {
        return $this->hasMany(\App\Models\ContentReport::class, 'reported_user_id');
    }

    // Reports moderated by this user (if admin/moderator)
    public function moderatedReports()
    {
        return $this->hasMany(\App\Models\ContentReport::class, 'moderator_id');
    }

    // Users blocked by this user
    public function blockedUsers()
    {
        return $this->hasMany(\App\Models\UserBlock::class, 'blocker_id');
    }

    // Users who blocked this user
    public function blockedBy()
    {
        return $this->hasMany(\App\Models\UserBlock::class, 'blocked_user_id');
    }

    // Moderation logs for this user
    public function moderationLogs()
    {
        return $this->hasMany(\App\Models\ContentModerationLog::class, 'user_id');
    }

    // Moderation actions taken by this user (if admin/moderator)
    public function moderationActions()
    {
        return $this->hasMany(\App\Models\ContentModerationLog::class, 'moderator_id');
    }

    /**
     * Check if user is blocked by another user
     */
    public function isBlockedBy($userId)
    {
        return \App\Models\UserBlock::where('blocker_id', $userId)
            ->where('blocked_user_id', $this->id)
            ->active()
            ->exists();
    }

    /**
     * Check if user has blocked another user  
     */
    public function hasBlocked($userId)
    {
        return \App\Models\UserBlock::where('blocker_id', $this->id)
            ->where('blocked_user_id', $userId)
            ->active()
            ->exists();
    }

    /**
     * ========================================
     * SUBSCRIPTION-RELATED METHODS
     * ========================================
     */

    /**
     * Get all subscriptions for this user
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class, 'user_id');
    }

    /**
     * Get subscription transactions
     */
    public function subscriptionTransactions()
    {
        return $this->hasMany(\App\Models\SubscriptionTransaction::class, 'user_id');
    }

    /**
     * Get the user's currently active subscription
     * Returns the most recent active subscription
     * CRITICAL: Includes GRACE PERIOD - subscription is active until grace period ends
     * 
     * @return \App\Models\Subscription|null
     */
    public function activeSubscription()
    {
        // CRITICAL: Get ALL Active subscriptions and check properly
        // Don't filter by end_date_time here - let isActive() handle it with grace period
        $subscriptions = $this->subscriptions()
            ->where('status', 'Active')
            ->where('payment_status', 'Completed') // Only completed payments
            ->orderBy('end_date_time', 'desc')
            ->get();

        // CRITICAL: Check each subscription with grace period included
        foreach ($subscriptions as $subscription) {
            if ($subscription->isActive(true)) { // Include grace period
                return $subscription;
            }
        }

        return null;
    }

    /**
     * Check if user has an active subscription
     * This is the main method used for access control
     * 
     * @param bool $includeGracePeriod Include grace period in check (default: true)
     * @return bool
     */
    public function hasActiveSubscription($includeGracePeriod = true)
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return false;
        }

        return $subscription->isActive($includeGracePeriod);
    }

    /**
     * Check if user subscription is expiring soon
     * 
     * @param int $days Number of days to check (default: 7)
     * @return bool
     */
    public function isSubscriptionExpiringSoon($days = 7)
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return false;
        }

        $daysRemaining = $subscription->daysRemaining();
        return $daysRemaining > 0 && $daysRemaining <= $days;
    }

    /**
     * Get days remaining on current subscription
     * 
     * @return int
     */
    public function subscriptionDaysRemaining()
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return 0;
        }

        return $subscription->daysRemaining();
    }

    /**
     * Check if user is in grace period
     * 
     * @return bool
     */
    public function isInSubscriptionGracePeriod()
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return false;
        }

        return $subscription->isInGracePeriod();
    }

    /**
     * Get subscription status for display
     * 
     * @return array
     */
    public function getSubscriptionStatus()
    {
        $subscription = $this->activeSubscription();

        if (!$subscription) {
            return [
                'has_subscription' => false,
                'has_active_subscription' => false, // FIXED: Added this key
                'status' => 'No Subscription',
                'is_active' => false,
                'days_remaining' => 0,
                'hours_remaining' => 0, // FIXED: Added this key
                'end_date' => null,
                'is_in_grace_period' => false,
            ];
        }

        // Check if subscription is truly active (including grace period)
        $isActive = $subscription->isActive(true);

        return [
            'has_subscription' => true,
            'has_active_subscription' => $isActive, // FIXED: Added this key with correct logic
            'status' => $subscription->status,
            'is_active' => $isActive,
            'days_remaining' => $subscription->daysRemaining(true), // Include grace period
            'hours_remaining' => $subscription->hoursRemaining(),
            'end_date' => $subscription->end_date_time,
            'formatted_end_date' => $subscription->getFormattedEndDate(),
            'is_in_grace_period' => $subscription->isInGracePeriod(),
            'plan' => $subscription->plan ? $subscription->plan->toApiArray() : null,
            'subscription' => $subscription->toApiArray(),
        ];
    }

    /**
     * Get pending subscription (awaiting payment)
     * 
     * @return \App\Models\Subscription|null
     */
    public function pendingSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'Pending')
            ->where('payment_status', 'Pending')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if user has a pending subscription payment
     * 
     * @return bool
     */
    public function hasPendingSubscription()
    {
        return $this->pendingSubscription() !== null;
    }

    /**
     * Get subscription history (completed subscriptions)
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function subscriptionHistory($limit = 10)
    {
        return $this->subscriptions()
            ->whereIn('status', ['Active', 'Expired', 'Cancelled'])
            ->where('payment_status', 'Completed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate total spent on subscriptions
     * 
     * @return float
     */
    public function totalSubscriptionSpent()
    {
        return $this->subscriptions()
            ->where('payment_status', 'Completed')
            ->sum('amount_paid');
    }

    /**
     * Check if user can download content
     * Based on subscription plan limits
     * 
     * @return bool
     */
    public function canDownload()
    {
        $subscription = $this->activeSubscription();

        if (!$subscription || !$subscription->plan) {
            return false;
        }

        // If plan has no download limit (NULL), allow unlimited
        if ($subscription->plan->max_downloads === null) {
            return true;
        }

        // Count downloads during this subscription period
        // TODO: Implement download tracking if needed
        // For now, just check if plan allows downloads
        return $subscription->plan->max_downloads > 0;
    }

    /**
     * Check if user has ad-free experience
     * 
     * @return bool
     */
    public function isAdFree()
    {
        $subscription = $this->activeSubscription();

        if (!$subscription || !$subscription->plan) {
            return false;
        }

        return $subscription->plan->ad_free;
    }

    /**
     * Check if user can watch HD content
     * 
     * @return bool
     */
    public function canWatchHD()
    {
        $subscription = $this->activeSubscription();

        if (!$subscription || !$subscription->plan) {
            return false;
        }

        return $subscription->plan->hd_streaming;
    }

    /**
     * Create a new subscription for this user
     * Handles extension logic if user already has active subscription
     * 
     * @param \App\Models\SubscriptionPlan $plan
     * @return \App\Models\Subscription
     */
    public function createSubscription(\App\Models\SubscriptionPlan $plan)
    {
        $activeSubscription = $this->activeSubscription();

        $sub = 'SUB-';
        $app_type = 'ugflix';
        if (strtolower($this->app_type) == 'lugaflix') {
            $sub = 'LUG-';
            $app_type = 'lugaflix';
        }


        if ($activeSubscription && $activeSubscription->end_date_time > now()) {
            // EXTEND: Add days to existing subscription
            $startDate = \Carbon\Carbon::parse($activeSubscription->end_date_time);
            $endDate = $startDate->copy()->addDays($plan->duration_days);

            $subscription = new \App\Models\Subscription([
                'user_id' => $this->id,
                'plan_id' => $plan->id,
                'days' => $plan->duration_days,
                'start_date_time' => $startDate,
                'end_date_time' => $endDate,
                'amount_paid' => $plan->getActualPrice(),
                'currency' => $plan->currency,
                'is_extension' => true,
                'extended_from_id' => $activeSubscription->id,
                'status' => 'Pending', // Will be activated after payment
                'app_type' => $app_type,
            ]);
        } else {
            // NEW: Create fresh subscription
            $startDate = now();
            $endDate = $startDate->copy()->addDays($plan->duration_days);

            $subscription = new \App\Models\Subscription([
                'user_id' => $this->id,
                'plan_id' => $plan->id,
                'days' => $plan->duration_days,
                'start_date_time' => $startDate,
                'end_date_time' => $endDate,
                'amount_paid' => $plan->getActualPrice(),
                'currency' => $plan->currency,
                'status' => 'Pending', // Will be activated after payment
                'app_type' => $app_type,
            ]);
        }

        $subscription->save();
        return $subscription;
    }

    /**
     * Give free subscription to user if they don't already have one
     * 
     * CRITICAL: This method is designed to be called multiple times safely
     * It includes comprehensive checks to prevent duplicates and errors
     * 
     * @param bool $forceNew Force creation of new free trial even if user has subscriptions
     * @return array Result with status, message, and subscription data
     */
    public function giveFreeSubscription($forceNew = false)
    {
        return false;
    }

    /**
     * Check if user is eligible for free trial
     * 
     * @return bool
     */
    public function isEligibleForFreeTrial()
    {
        // Check if user already has any subscription (active or expired)
        $hasAnySubscription = $this->subscriptions()
            ->whereIn('status', ['Active', 'Expired', 'Completed'])
            ->whereIn('payment_status', ['Completed', 'Free'])
            ->exists();

        return !$hasAnySubscription;
    }

    /**
     * Auto-assign free trial if user is eligible
     * This is a safe wrapper around giveFreeSubscription
     * 
     * @return array
     */
    public function autoAssignFreeTrial()
    {
        return;


        return $this->giveFreeSubscription();
    }
}
