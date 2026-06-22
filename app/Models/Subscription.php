<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'days',
        'start_date_time',
        'end_date_time',
        'grace_period_end',
        'status',
        'auto_renew',
        'payment_method',
        'payment_gateway',
        'payment_status',
        'pesapal_transaction_id',
        'pesapal_tracking_id',
        'pesapal_merchant_reference',
        'pesapal_signature',
        'pesapal_response',
        'flutterwave_reference',
        'flutterwave_transaction_id',
        'flutterwave_response',
        'payment_url',
        'payment_confirmed_at',
        'failed_at',
        'amount_paid',
        'currency',
        'is_extension',
        'extended_from_id',
        'cancelled_at',
        'cancelled_reason',
        'payment_failure_reason',
        'cancelled_by',
        'ip_address',
        'user_agent',
        'expiry_notification_sent',
        'expiry_notification_at',
    ];

    protected $casts = [
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
        'grace_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
        'expiry_notification_at' => 'datetime',
        'auto_renew' => 'boolean',
        'is_extension' => 'boolean',
        'expiry_notification_sent' => 'boolean',
        'amount_paid' => 'decimal:2',
        'pesapal_response' => 'array',
        'flutterwave_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            // Auto-set grace period (3 days after end_date_time).
            // Only when end_date_time is already known at creation (rare for Pending subs).
            if (!$subscription->grace_period_end && $subscription->end_date_time) {
                $subscription->grace_period_end = Carbon::parse($subscription->end_date_time)->addDays(3);
            }

            $user = User::find($subscription->user_id);
            if ($user == null) {
                throw new \Exception("User with ID {$subscription->user_id} not found for Subscription creation");
            }

            $sub = 'SUB-';
            switch (strtolower($user->app_type ?? '')) {
                case 'lugaflix':
                    $sub = 'LUG-';
                    $subscription->app_type = 'lugaflix';
                    break;
                case 'muno_app':
                    $sub = 'MUN-';
                    $subscription->app_type = 'muno_app';
                    break;
                case 'web':
                    $sub = 'WEB-';
                    $subscription->app_type = 'web';
                    break;
                default:
                    $sub = 'SUB-';
                    $subscription->app_type = 'ugflix';
                    break;
            }

            // Generate merchant reference if not provided
            if (!$subscription->pesapal_merchant_reference) {
                $subscription->pesapal_merchant_reference = $sub . $subscription->user_id . '-' . time();
            }

            // Set IP and User Agent from request if available
            if (!$subscription->ip_address && request()) {
                $subscription->ip_address = request()->ip();
                $subscription->user_agent = request()->userAgent();
                $subscription->platform = $user->platform;
            }
        });

        static::updating(function ($subscription) {
            // If status changed to Active, ensure start + end + grace are all set
            if ($subscription->isDirty('status') && $subscription->status === 'Active') {
                if (!$subscription->start_date_time) {
                    $subscription->start_date_time = now();
                }
                // Ensure end_date_time is always set when Active
                if (!$subscription->end_date_time) {
                    $days = max(1, (int) $subscription->days);
                    $subscription->end_date_time = Carbon::parse($subscription->start_date_time)->addHours($days * 24);
                }
                // Ensure grace_period_end is always set when Active
                if (!$subscription->grace_period_end && $subscription->end_date_time) {
                    $subscription->grace_period_end = Carbon::parse($subscription->end_date_time)->addDays(3);
                }
            }

            // If end_date_time changed, keep grace_period_end in sync (3 days)
            if ($subscription->isDirty('end_date_time') && $subscription->end_date_time) {
                $subscription->grace_period_end = Carbon::parse($subscription->end_date_time)->addDays(3);
            }
        });

        static::saved(function ($subscription) {
            self::forgetSubscriptionCaches((int) $subscription->user_id);
        });

        // Handle cascading deletes in code (not database)
        static::deleting(function ($subscription) {
            // Delete related transactions
            $subscription->transactions()->delete();

            self::forgetSubscriptionCaches((int) $subscription->user_id);

            Log::info('Subscription deleted', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'status' => $subscription->status,
            ]);
        });
    }

    /**
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function transactions()
    {
        return $this->hasMany(SubscriptionTransaction::class, 'subscription_id');
    }

    public function extendedFrom()
    {
        return $this->belongsTo(Subscription::class, 'extended_from_id');
    }

    public function extensions()
    {
        return $this->hasMany(Subscription::class, 'extended_from_id');
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Scopes
     */

    public function scopeActive($query)
    {
        return $query->where('status', 'Active')
            ->where('end_date_time', '>', now());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'Expired');
    }

    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('status', 'Active')
            ->where('end_date_time', '<=', now()->addDays($days))
            ->where('end_date_time', '>', now());
    }

    public function scopeNeedingExpiryNotification($query)
    {
        return $query->where('status', 'Active')
            ->where('end_date_time', '<=', now()->addDays(3))
            ->where('end_date_time', '>', now())
            ->where('expiry_notification_sent', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if subscription is currently active
     * CRITICAL: Uses MINUTE-LEVEL precision - subscription is active until the VERY LAST MINUTE
     * 
     * @param bool $includeGracePeriod
     * @return bool
     */
    public function isActive($includeGracePeriod = false)
    {
        // Must have Active status
        if ($this->status !== 'Active') {
            return false;
        }

        // CRITICAL: Use current timestamp with SECOND precision
        $now = now();
        $endDate = Carbon::parse($this->end_date_time);

        // If including grace period, check against grace_period_end
        if ($includeGracePeriod && $this->grace_period_end) {
            $endDate = Carbon::parse($this->grace_period_end);
        }

        // CRITICAL: lte() = less than or equal (includes the exact second)
        // Subscription is active UNTIL THE VERY LAST SECOND of end_date_time
        // Example: If end_date_time is 2025-10-06 18:42:24
        //          Subscription is active at 18:42:24.000000
        //          Subscription expires at   18:42:24.000001
        return $now->lte($endDate);
    }

    /**
     * Check if subscription is expired
     * CRITICAL: Uses MINUTE-LEVEL precision - subscription expires AFTER the last minute
     * 
     * @return bool
     */
    public function isExpired()
    {
        // If explicitly marked as expired
        if ($this->status === 'Expired') {
            return true;
        }

        // CRITICAL: Use current timestamp with SECOND precision
        $now = now();
        $endDate = Carbon::parse($this->end_date_time);
        $graceEnd = $this->grace_period_end ? Carbon::parse($this->grace_period_end) : null;

        // CRITICAL: gt() = greater than (NOT greater than or equal)
        // Subscription expires AFTER the last second of grace period (or end date)
        // Example: If grace_period_end is 2025-10-09 18:42:24
        //          At 18:42:24.000000 -> NOT expired (still active)
        //          At 18:42:24.000001 -> EXPIRED
        return $now->gt($graceEnd ?? $endDate);
    }

    /**
     * Check if in grace period
     * CRITICAL: Uses MINUTE-LEVEL precision for grace period checking
     * 
     * @return bool
     */
    public function isInGracePeriod()
    {
        // Must have Active status and grace period configured
        if ($this->status !== 'Active' || !$this->grace_period_end) {
            return false;
        }

        // CRITICAL: Use current timestamp with SECOND precision
        $now = now();
        $endDate = Carbon::parse($this->end_date_time);
        $graceEnd = Carbon::parse($this->grace_period_end);

        // In grace period if:
        // - Past the end_date_time (gt = greater than)
        // - Still within grace_period_end (lte = less than or equal)
        // Example: end_date = 2025-10-06 18:42:24, grace_end = 2025-10-09 18:42:24
        //          At 2025-10-06 18:42:24.000001 -> IN GRACE PERIOD
        //          At 2025-10-09 18:42:24.000000 -> STILL IN GRACE PERIOD
        //          At 2025-10-09 18:42:24.000001 -> EXPIRED (not in grace)
        return $now->gt($endDate) && $now->lte($graceEnd);
    }

    /**
     * Check if payment is completed
     * 
     * @return bool
     */
    public function isPaid()
    {
        return $this->payment_status === 'Completed';
    }

    /**
     * Check if payment is pending
     * 
     * @return bool
     */
    public function isPaymentPending()
    {
        return $this->payment_status === 'Pending';
    }

    /**
     * Get days remaining
     * 
     * @param bool $includeGracePeriod
     * @return int
     */
    public function daysRemaining($includeGracePeriod = false)
    {
        if (!$this->isActive($includeGracePeriod)) {
            return 0;
        }

        $now = now();
        $endDate = Carbon::parse($this->end_date_time);

        if ($includeGracePeriod && $this->grace_period_end) {
            $endDate = Carbon::parse($this->grace_period_end);
        }

        return max(0, $now->diffInDays($endDate, false));
    }

    /**
     * Get hours remaining
     * 
     * @return int
     */
    public function hoursRemaining()
    {
        if (!$this->isActive()) {
            return 0;
        }

        $now = now();
        $endDate = Carbon::parse($this->end_date_time);

        return max(0, $now->diffInHours($endDate, false));
    }

    /**
     * Activate subscription
     * Called after successful payment
     */
    public function activate()
    {
        $this->status = 'Active';
        $this->payment_status = 'Completed';

        if (!$this->start_date_time) {
            $this->start_date_time = now();
        }

        // Ensure end_date_time is always valid — never leave it NULL or in the past
        $days = (int) $this->days;
        if ($days <= 0) {
            $this->load('plan');
            $days = $this->plan ? (int) $this->plan->duration_days : 3;
            $this->days = $days;
        }
        if (!$this->end_date_time || $this->end_date_time->isPast()) {
            $this->end_date_time = Carbon::parse($this->start_date_time)->addHours($days * 24);
        }

        // Always keep grace_period_end in sync
        $this->grace_period_end = Carbon::parse($this->end_date_time)->addDays(3);

        $this->save();

        // Clear the activeSubscription cache so the manifest immediately reflects the new status
        Cache::forget("active_sub_{$this->user_id}");
        Cache::forget("v2_pay_check_{$this->user_id}");
        Cache::forget("v1_sub_info_{$this->user_id}");

        Log::info('Subscription activated', [
            'subscription_id' => $this->id,
            'user_id'         => $this->user_id,
            'start_date'      => $this->start_date_time,
            'end_date'        => $this->end_date_time,
            'grace_end'       => $this->grace_period_end,
        ]);
    }

    private static function forgetSubscriptionCaches(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        Cache::forget("active_sub_{$userId}");
        Cache::forget("v2_pay_check_{$userId}");
        Cache::forget("v1_sub_info_{$userId}");
    }

    /**
     * Mark subscription as expired
     */
    public function markAsExpired()
    {
        $this->status = 'Expired';
        $this->save();

        Log::info('Subscription expired', [
            'subscription_id' => $this->id,
            'user_id' => $this->user_id,
        ]);
    }

    /**
     * Mark subscription as failed
     * 
     * @param string $reason
     */
    public function markAsFailed($reason = null)
    {
        $this->status = 'Failed';
        $this->payment_status = 'Failed';

        if ($reason) {
            $this->cancelled_reason = $reason;
        }

        $this->save();

        Log::warning('Subscription failed', [
            'subscription_id' => $this->id,
            'user_id' => $this->user_id,
            'reason' => $reason,
        ]);
    }

    /**
     * Cancel subscription
     * 
     * @param int $cancelledBy User ID who cancelled
     * @param string $reason
     */
    public function cancel($cancelledBy = null, $reason = null)
    {
        $this->status = 'Cancelled';
        $this->cancelled_at = now();
        $this->cancelled_by = $cancelledBy;
        $this->cancelled_reason = $reason;
        $this->save();

        Log::info('Subscription cancelled', [
            'subscription_id' => $this->id,
            'user_id' => $this->user_id,
            'cancelled_by' => $cancelledBy,
            'reason' => $reason,
        ]);
    }

    /**
     * Send expiry notification
     */
    public function sendExpiryNotification()
    {
        // TODO: Implement email/SMS notification
        // Can use Laravel's notification system or queued jobs

        $this->expiry_notification_sent = true;
        $this->expiry_notification_at = now();
        $this->save();

        Log::info('Expiry notification sent', [
            'subscription_id' => $this->id,
            'user_id' => $this->user_id,
            'days_remaining' => $this->daysRemaining(),
        ]);
    }

    /**
     * Get formatted end date
     * 
     * @param string $format
     * @return string
     */
    public function getFormattedEndDate($format = 'M j, Y g:i A')
    {
        return Carbon::parse($this->end_date_time)->format($format);
    }

    /**
     * Get status badge color
     * For UI display
     * 
     * @return string
     */
    public function getStatusColor()
    {
        return match ($this->status) {
            'Active' => 'success',
            'Pending' => 'warning',
            'Expired' => 'danger',
            'Cancelled' => 'secondary',
            'Failed' => 'danger',
            default => 'info',
        };
    }

    /**
     * To API array
     * 
     * @return array
     */
    public function toApiArray()
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'plan' => $this->plan ? $this->plan->toApiArray() : null,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'start_date' => $this->start_date_time?->toISOString(),
            'end_date' => $this->end_date_time?->toISOString(),
            'grace_period_end' => $this->grace_period_end?->toISOString(),
            'days_remaining' => $this->daysRemaining(false),
            'hours_remaining' => $this->hoursRemaining(),
            'is_active' => $this->isActive(true), // include grace period — consistent with manifest
            'is_in_grace_period' => $this->isInGracePeriod(),
            'is_expired' => $this->isExpired(),
            'amount_paid' => $this->amount_paid,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_gateway' => $this->payment_gateway ?? $this->payment_method,
            'auto_renew' => $this->auto_renew,
            'is_extension' => $this->is_extension,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_reason' => $this->cancelled_reason,
            'created_at' => $this->created_at?->toISOString(),
            'order_tracking_id' => $this->payment_gateway === 'flutterwave'
                ? $this->flutterwave_reference
                : $this->pesapal_tracking_id,
            'merchant_reference' => $this->pesapal_merchant_reference,
            'payment_url' => $this->payment_url,
        ];
    }
}
