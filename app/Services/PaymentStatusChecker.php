<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Enhanced Payment Status Checker
 * 
 * Provides robust payment status verification with:
 * - Retry mechanisms with exponential backoff
 * - Transaction locking to prevent race conditions
 * - Idempotency checks
 * - Comprehensive error handling
 * - Fallback verification
 * 
 * Best Practices from Pesapal API Documentation:
 * 1. Always verify payment status via API before granting access
 * 2. Use IPN callbacks as triggers, not source of truth
 * 3. Implement retry logic for network failures
 * 4. Lock transactions during processing
 * 5. Log all payment state transitions
 */
class PaymentStatusChecker
{
    protected $pesapalService;

    public function __construct(SubscriptionPesapalService $pesapalService)
    {
        $this->pesapalService = $pesapalService;
    }

    /**
     * Check payment status with retry mechanism
     * 
     * @param Subscription $subscription
     * @param array $options
     * @return array
     */
    public function checkPaymentStatus(Subscription $subscription, array $options = [])
    {
        $maxRetries = $options['max_retries'] ?? 3;
        $retryDelay = $options['retry_delay'] ?? 2; // seconds
        $useExponentialBackoff = $options['exponential_backoff'] ?? true;

        Log::info('🔍 PaymentStatusChecker: Starting payment status check', [
            'subscription_id' => $subscription->id,
            'tracking_id' => $subscription->pesapal_tracking_id,
            'max_retries' => $maxRetries,
        ]);

        // Prevent concurrent status checks for same subscription
        $lockKey = "payment_check_{$subscription->id}";
        $lock = Cache::lock($lockKey, 30); // 30 second lock

        if (!$lock->get()) {
            Log::warning('⚠️ PaymentStatusChecker: Another check in progress', [
                'subscription_id' => $subscription->id,
            ]);

            return [
                'success' => false,
                'error' => 'Another status check is in progress',
                'retry_after' => 5,
            ];
        }

        try {
            // Verify tracking ID exists
            if (!$subscription->pesapal_tracking_id) {
                Log::error('❌ PaymentStatusChecker: No tracking ID found', [
                    'subscription_id' => $subscription->id,
                ]);

                return [
                    'success' => false,
                    'error' => 'No payment tracking ID found',
                ];
            }

            // Check if already processed (idempotency)
            if ($this->isAlreadyProcessed($subscription)) {
                Log::info('ℹ️ PaymentStatusChecker: Payment already processed', [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'payment_status' => $subscription->payment_status,
                ]);

                return [
                    'success' => true,
                    'status' => $subscription->status,
                    'payment_status' => $subscription->payment_status,
                    'already_processed' => true,
                ];
            }

            // Attempt status check with retries
            $attempt = 0;
            $lastError = null;

            while ($attempt < $maxRetries) {
                $attempt++;

                try {
                    Log::info("🔄 PaymentStatusChecker: Attempt {$attempt}/{$maxRetries}", [
                        'subscription_id' => $subscription->id,
                    ]);

                    // Query Pesapal API
                    $statusResult = $this->pesapalService->getTransactionStatus(
                        $subscription->pesapal_tracking_id
                    );

                    if ($statusResult['success']) {
                        // Process status update within database transaction
                        $result = DB::transaction(function () use ($subscription, $statusResult) {
                            // Re-fetch subscription with lock to prevent race conditions
                            $lockedSubscription = Subscription::lockForUpdate()
                                ->find($subscription->id);

                            if (!$lockedSubscription) {
                                throw new \Exception('Subscription not found');
                            }

                            // Double-check if already processed (within transaction)
                            if ($this->isAlreadyProcessed($lockedSubscription)) {
                                return [
                                    'success' => true,
                                    'status' => $lockedSubscription->status,
                                    'already_processed' => true,
                                ];
                            }

                            // Update subscription status
                            $updateResult = $this->pesapalService->updateSubscriptionStatus(
                                $subscription->pesapal_tracking_id,
                                $statusResult['data']
                            );

                            return $updateResult;
                        });

                        Log::info('✅ PaymentStatusChecker: Status check successful', [
                            'subscription_id' => $subscription->id,
                            'attempt' => $attempt,
                            'status' => $result['status'] ?? 'unknown',
                        ]);

                        $lock->release();
                        return array_merge($result, ['success' => true, 'attempts' => $attempt]);
                    }

                    // Status check failed
                    $lastError = $statusResult['error'] ?? 'Unknown error';
                    Log::warning("⚠️ PaymentStatusChecker: Attempt {$attempt} failed", [
                        'subscription_id' => $subscription->id,
                        'error' => $lastError,
                    ]);

                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::error("❌ PaymentStatusChecker: Attempt {$attempt} exception", [
                        'subscription_id' => $subscription->id,
                        'error' => $lastError,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                // Wait before retry (exponential backoff)
                if ($attempt < $maxRetries) {
                    $delay = $useExponentialBackoff 
                        ? $retryDelay * pow(2, $attempt - 1) 
                        : $retryDelay;
                    
                    Log::info("⏳ PaymentStatusChecker: Waiting {$delay}s before retry", [
                        'subscription_id' => $subscription->id,
                    ]);

                    sleep($delay);
                }
            }

            // All retries failed
            Log::error('❌ PaymentStatusChecker: All retries exhausted', [
                'subscription_id' => $subscription->id,
                'attempts' => $maxRetries,
                'last_error' => $lastError,
            ]);

            $lock->release();
            return [
                'success' => false,
                'error' => "Failed after {$maxRetries} attempts: {$lastError}",
                'attempts' => $maxRetries,
            ];

        } catch (\Exception $e) {
            Log::error('💥 PaymentStatusChecker: Critical error', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $lock->release();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if payment is already processed (idempotency check)
     * 
     * @param Subscription $subscription
     * @return bool
     */
    protected function isAlreadyProcessed(Subscription $subscription)
    {
        // Payment is considered processed if:
        // 1. Status is Active and payment_status is Completed (successful)
        // 2. Status is Failed and payment_status is Failed (failed)
        // 3. Status is Cancelled (cancelled)

        $processedStatuses = ['Active', 'Failed', 'Cancelled', 'Expired'];

        return in_array($subscription->status, $processedStatuses, true) 
            && in_array($subscription->payment_status, ['Completed', 'Failed'], true);
    }

    /**
     * Bulk check pending payments
     * 
     * @param array $options
     * @return array
     */
    public function checkPendingPayments(array $options = [])
    {
        $ageMinutes = $options['age_minutes'] ?? 15;
        $limit = $options['limit'] ?? 50;
        $maxAgeHours = $options['max_age_hours'] ?? 48; // Only check payments < 48 hours old

        Log::info('🔍 PaymentStatusChecker: Bulk check starting', [
            'age_minutes' => $ageMinutes,
            'limit' => $limit,
            'max_age_hours' => $maxAgeHours,
        ]);

        $threshold = now()->subMinutes($ageMinutes);
        $maxAgeThreshold = now()->subHours($maxAgeHours);

        // Find pending subscriptions
        $pendingSubscriptions = Subscription::whereIn('status', ['Pending'])
            ->whereIn('payment_status', ['Pending', 'Processing'])
            ->whereNotNull('pesapal_tracking_id')
            ->where('created_at', '<', $threshold)
            ->where('created_at', '>', $maxAgeThreshold)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $results = [
            'total' => $pendingSubscriptions->count(),
            'activated' => 0,
            'failed' => 0,
            'still_pending' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($pendingSubscriptions as $subscription) {
            try {
                $checkResult = $this->checkPaymentStatus($subscription, [
                    'max_retries' => 2,
                    'retry_delay' => 1,
                ]);

                if ($checkResult['success']) {
                    $status = $checkResult['status'] ?? 'unknown';

                    if ($status === 'success' || ($checkResult['subscription'] ?? null)?->status === 'Active') {
                        $results['activated']++;
                    } elseif ($status === 'failed' || ($checkResult['subscription'] ?? null)?->status === 'Failed') {
                        $results['failed']++;
                    } else {
                        $results['still_pending']++;
                    }
                } else {
                    $results['errors']++;
                }

                $results['details'][] = [
                    'subscription_id' => $subscription->id,
                    'result' => $checkResult,
                ];

            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('PaymentStatusChecker: Error in bulk check', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('✅ PaymentStatusChecker: Bulk check complete', $results);

        return $results;
    }

    /**
     * Force verify payment (admin/support use)
     * Ignores idempotency checks
     * 
     * @param Subscription $subscription
     * @return array
     */
    public function forceVerifyPayment(Subscription $subscription)
    {
        Log::warning('⚠️ PaymentStatusChecker: FORCE VERIFY initiated', [
            'subscription_id' => $subscription->id,
            'current_status' => $subscription->status,
            'current_payment_status' => $subscription->payment_status,
        ]);

        if (!$subscription->pesapal_tracking_id) {
            return [
                'success' => false,
                'error' => 'No tracking ID found',
            ];
        }

        try {
            // Query Pesapal directly
            $statusResult = $this->pesapalService->getTransactionStatus(
                $subscription->pesapal_tracking_id
            );

            if ($statusResult['success']) {
                // Update status in transaction
                $result = DB::transaction(function () use ($subscription, $statusResult) {
                    $lockedSubscription = Subscription::lockForUpdate()
                        ->find($subscription->id);

                    return $this->pesapalService->updateSubscriptionStatus(
                        $subscription->pesapal_tracking_id,
                        $statusResult['data']
                    );
                });

                Log::info('✅ PaymentStatusChecker: Force verify complete', [
                    'subscription_id' => $subscription->id,
                    'result' => $result['status'] ?? 'unknown',
                ]);

                return array_merge($result, ['success' => true]);
            }

            return $statusResult;

        } catch (\Exception $e) {
            Log::error('❌ PaymentStatusChecker: Force verify failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
