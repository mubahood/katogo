<?php

namespace App\Models;

use App\Services\SubscriptionPesapalService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SubscriptionTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'user_id',
        'transaction_type',
        'platform',
        'amount',
        'currency',
        'status',
        'pesapal_tracking_id',
        'merchant_reference',
        'payment_method',
        'confirmation_code',
        'payment_account',
        'request_payload',
        'response_payload',
        'error_message',
        'ip_address',
        'user_agent',
        'refunded_by',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'refunded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            // Set IP and User Agent from request if available
            if (!$transaction->ip_address && request()) {
                $transaction->ip_address = request()->ip();
                $transaction->user_agent = request()->userAgent();
            }
        });
    }

    /**
     * Relationships
     */

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function refundedByUser()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * Scopes
     */

    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'Failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'Refunded');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if transaction is completed
     * 
     * @return bool
     */
    public function isCompleted()
    {
        return $this->status === 'Completed';
    }

    /**
     * Check if transaction is pending
     * 
     * @return bool
     */
    public function isPending()
    {
        return $this->status === 'Pending';
    }

    /**
     * Check if transaction failed
     * 
     * @return bool
     */
    public function isFailed()
    {
        return $this->status === 'Failed';
    }

    /**
     * Check if transaction was refunded
     * 
     * @return bool
     */
    public function isRefunded()
    {
        return $this->status === 'Refunded';
    }

    /**
     * Mark transaction as completed
     * 
     * @param array $responseData
     */
    public function markAsCompleted($responseData = [])
    {
        $this->status = 'Completed';

        if (!empty($responseData)) {
            $this->response_payload = array_merge($this->response_payload ?? [], $responseData);
        }

        $this->save();

        Log::info('Transaction completed', [
            'transaction_id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'amount' => $this->amount,
        ]);
    }

    /**
     * Mark transaction as failed
     * 
     * @param string $errorMessage
     */
    public function markAsFailed($errorMessage = null)
    {
        $this->status = 'Failed';

        if ($errorMessage) {
            $this->error_message = $errorMessage;
        }

        $this->save();

        Log::warning('Transaction failed', [
            'transaction_id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'error' => $errorMessage,
        ]);
    }

    /**
     * Process refund
     * 
     * @param int $refundedBy User ID
     * @param string $reason
     */
    public function processRefund($refundedBy, $reason = null)
    {
        $this->status = 'Refunded';
        $this->refunded_by = $refundedBy;
        $this->refunded_at = now();
        $this->refund_reason = $reason;
        $this->save();

        Log::info('Transaction refunded', [
            'transaction_id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'refunded_by' => $refundedBy,
            'reason' => $reason,
        ]);

        // Update related subscription
        if ($this->subscription) {
            $this->subscription->payment_status = 'Refunded';
            $this->subscription->save();
        }
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
            'Completed' => 'success',
            'Pending' => 'warning',
            'Processing' => 'info',
            'Failed' => 'danger',
            'Refunded' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get formatted amount
     * 
     * @param bool $withCurrency
     * @return string
     */
    public function getFormattedAmount($withCurrency = true)
    {
        $amount = number_format($this->amount, 0);
        return $withCurrency ? "{$this->currency} {$amount}" : $amount;
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
            'subscription_id' => $this->subscription_id,
            'transaction_type' => $this->transaction_type,
            'amount' => $this->amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'confirmation_code' => $this->confirmation_code,
            'pesapal_tracking_id' => $this->pesapal_tracking_id,
            'merchant_reference' => $this->merchant_reference,
            'error_message' => $this->error_message,
            'refunded_at' => $this->refunded_at?->toISOString(),
            'refund_reason' => $this->refund_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    public function check_payment_status()
    {
        $pay = $this;
        $number_of_times_checked = (int) $pay->number_of_times_checked;
        $pesapalService = new SubscriptionPesapalService();
        $resp = $pesapalService->getTransactionStatus($pay->pesapal_tracking_id);
        $is_paid = false;
        if ($resp != null) {
            if (isset($resp['data']) && isset($resp['data']['status_code'])) {
                if (isset($resp['data']['payment_status_description'])) {
                    $payment_status_description = strtolower($resp['data']['payment_status_description']);
                    $payment_status_description = trim($payment_status_description);
                    if ($payment_status_description == 'completed') {
                        $payment_status_description = $resp['data']['payment_status_description'];
                        $is_paid = true;
                    }
                }
            }
        }


        if ($is_paid) {
            $pay->status = 'Completed';
            $pay->save();
            if ($pay->subscription != null) {
                if ($pay->subscription->status != 'Active') {
                    $pay->subscription->activate();
                }
            }
        }

        $number_of_times_checked++;
        $pay->number_of_times_checked = $number_of_times_checked;
        $pay->save();
    }
}
