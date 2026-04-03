<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Utils;
use App\Services\SubscriptionPesapalService;
use App\Services\PaymentStatusChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Subscription API Controller
 * 
 * Handles all subscription-related API endpoints
 */
class SubscriptionApiController extends Controller
{
    protected $pesapalService;
    protected $statusChecker;

    public function __construct(
        SubscriptionPesapalService $pesapalService,
        PaymentStatusChecker $statusChecker
    ) {
        $this->pesapalService = $pesapalService;
        $this->statusChecker = $statusChecker;
    }

    /**
     * List all available subscription plans
     * GET /api/subscription-plans
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listPlans(Request $request)
    {
        try {
            $lang = $request->get('lang', 'en'); // en, lg, sw

            $plans = SubscriptionPlan::active()
                ->where('is_trial', false) // FIXED: Exclude free trial plans from API
                ->where('price', '>', 0) // FIXED: Exclude free plans (price = 0)
                ->ordered()
                ->get()
                ->map(function ($plan) use ($lang) {
                    return $plan->toApiArray($lang);
                });

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Subscription plans retrieved successfully',
                'data' => $plans,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list subscription plans', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to retrieve subscription plans',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Create a new subscription
     * POST /api/subscriptions/create
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            // ===== INPUT VALIDATION =====
            $validator = Validator::make($request->all(), [
                'plan_id' => 'required|integer|exists:subscription_plans,id',
                'callback_url' => 'url|nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                    'data' => ['errors' => $validator->errors()],
                ], 400);
            }

            // ===== AUTHENTICATION =====
            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required. Please log in and try again.',
                    'data' => null,
                ], 401);
            }

            // ===== VERIFY USER HAS EMAIL =====
            if (empty($user->email)) {
                Log::warning('Subscription attempt by user without email', ['user_id' => $user->id]);
            }

            // ===== PRE-WARM PESAPAL CREDENTIALS (speed optimization) =====
            // Authenticate + register IPN in parallel-safe way BEFORE the DB
            // transaction so the slow network round-trips happen outside the lock.
            // Both results are cached, so initializePayment() will hit cache only.
            try {
                $this->pesapalService->authenticate();
                $this->pesapalService->registerIpnUrl();
            } catch (\Exception $warmupEx) {
                // Non-fatal — initializePayment() will retry. Just log.
                Log::warning('Pesapal pre-warm failed (will retry in initializePayment)', [
                    'error' => $warmupEx->getMessage(),
                ]);
            }

            // ===== CREATE SUBSCRIPTION (DB TRANSACTION) =====
            $subscription = DB::transaction(function () use ($user, $request) {
                // Lock user row to prevent race conditions
                $user->lockForUpdate();

                // Cancel ALL non-completed subscriptions for this user so nothing blocks the new payment.
                // Previously only cancelled subs >2hrs or without payment URL — this caused
                // "pending subscription exists" blocks when users retried quickly.
                $blockingSubscriptions = $user->subscriptions()
                    ->whereIn('status', ['Pending', 'Failed', 'Processing'])
                    ->get();

                foreach ($blockingSubscriptions as $pendingSub) {
                    Log::info('Cancelling blocking subscription for new payment attempt', [
                        'old_subscription_id' => $pendingSub->id,
                        'user_id' => $user->id,
                        'old_status' => $pendingSub->status,
                        'old_payment_status' => $pendingSub->payment_status,
                        'age_minutes' => now()->diffInMinutes($pendingSub->created_at),
                    ]);

                    $pendingSub->status = 'Cancelled';
                    $pendingSub->payment_status = 'Failed';
                    $pendingSub->cancelled_at = now();
                    $pendingSub->cancelled_reason = 'Automatically cancelled: user started new subscription';
                    $pendingSub->save();
                }

                // Clear pending cache so getPending() reflects the new state immediately
                Cache::forget("sub_pending_{$user->id}");

                // Get plan (active plans only)
                $plan = SubscriptionPlan::active()->findOrFail($request->plan_id);

                if ((float) $plan->getActualPrice() <= 0) {
                    throw new \Exception('Selected plan has invalid price. Please contact support.');
                }

                // Create subscription record
                return $user->createSubscription($plan);
            });

            // ===== VERIFY SUBSCRIPTION WAS CREATED =====
            if (!$subscription || !$subscription->id) {
                throw new \Exception('Failed to create subscription record');
            }

            if (empty($subscription->pesapal_merchant_reference)) {
                throw new \Exception('Subscription created but missing merchant reference');
            }

            // Eager-load plan + user to avoid extra queries in initializePayment()
            $subscription->load('plan', 'user');

            // ===== INITIALIZE PESAPAL PAYMENT =====
            $callbackUrl = $request->callback_url;

            Log::info('Initiating Pesapal payment', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_id' => $request->plan_id,
                'amount' => $subscription->amount_paid,
                'callback_url' => $callbackUrl ?? 'default',
            ]);

            $paymentResult = $this->pesapalService->initializePayment($subscription, null, $callbackUrl);

            if (!$paymentResult['success'] || empty($paymentResult['redirect_url'])) {
                // Clean up — mark subscription as failed
                $subscription->status = 'Failed';
                $subscription->payment_status = 'Failed';
                $subscription->failed_at = now();
                $subscription->cancelled_reason = 'Payment initialization failed: no redirect URL';
                $subscription->save();

                throw new \Exception('Payment gateway did not return a payment link. Please try again.');
            }

            Log::info('Subscription created and payment initialized', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_id' => $request->plan_id,
                'tracking_id' => $paymentResult['order_tracking_id'],
                'redirect_url_domain' => parse_url($paymentResult['redirect_url'], PHP_URL_HOST),
            ]);

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Subscription created successfully. Please complete payment.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'order_tracking_id' => $paymentResult['order_tracking_id'],
                    'merchant_reference' => $paymentResult['merchant_reference'],
                    'redirect_url' => $paymentResult['redirect_url'],
                    'amount' => $subscription->amount_paid,
                    'currency' => $subscription->currency,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => 0,
                'status' => 404,
                'message' => 'Selected subscription plan not found or inactive.',
                'data' => null,
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to create subscription', [
                'user_id' => $user->id ?? $request->user()?->id ?? 'unknown',
                'plan_id' => $request->plan_id ?? null,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);

            // CRITICAL: Clean up the subscription if it was created but payment init failed
            if (isset($subscription) && $subscription && $subscription->id) {
                if (empty($subscription->pesapal_tracking_id)) {
                    $subscription->status = 'Failed';
                    $subscription->payment_status = 'Failed';
                    $subscription->failed_at = now();
                    $subscription->payment_failure_reason = 'Payment initialization failed: ' . $e->getMessage();
                    $subscription->cancelled_reason = 'Payment initialization failed';
                    $subscription->save();

                    Log::warning('Subscription marked as Failed due to payment init failure', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Return user-friendly message
            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Unable to process payment at this time. Please try again later.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Handle payment callback from Pesapal
     * GET /api/subscriptions/pesapal/callback
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        try {
            Log::info('Pesapal subscription callback received (legacy)', $request->all());

            $orderTrackingId = $request->get('OrderTrackingId');
            $merchantReference = $request->get('OrderMerchantReference');

            if (!$orderTrackingId) {
                Log::warning('Pesapal callback missing OrderTrackingId');
                return $this->callbackPage('error', 'Invalid Callback', 'The callback was missing required parameters. Please return to the app.');
            }

            // Get transaction status from Pesapal
            try {
                $statusResult = $this->pesapalService->getTransactionStatus($orderTrackingId);

                if (!$statusResult['success']) {
                    return $this->callbackPage('pending', 'Verifying Payment', 'We are confirming your payment. Please return to the app — your subscription will activate automatically.');
                }

                // Update subscription status
                $result = $this->pesapalService->updateSubscriptionStatus($orderTrackingId, $statusResult['data']);
                $subscription = $result['subscription'];
                $status = $result['status'];

                // Return JSON if API request
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json([
                        'code' => $status === 'success' ? 1 : 0,
                        'status' => 200,
                        'message' => $this->getStatusMessage($status),
                        'data' => [
                            'subscription_id' => $subscription->id,
                            'status' => $subscription->status,
                            'payment_status' => $subscription->payment_status,
                            'order_tracking_id' => $orderTrackingId,
                        ],
                    ]);
                }

                $planName = $subscription->plan->name ?? 'Premium';
                return match ($status) {
                    'success' => $this->callbackPage('success', 'Payment Successful!', "Your {$planName} subscription is now active. Enjoy unlimited access!"),
                    'failed' => $this->callbackPage('failed', 'Payment Failed', 'Your payment could not be processed. Please return to the app and try again.'),
                    default => $this->callbackPage('pending', 'Payment Processing', 'Your payment is being verified. Please return to the app — your subscription will activate automatically.'),
                };
            } catch (\Exception $statusEx) {
                Log::warning('Callback status check failed, showing pending', ['error' => $statusEx->getMessage()]);
                return $this->callbackPage('pending', 'Verifying Payment', 'We are confirming your payment. Please return to the app — your subscription will activate automatically.');
            }
        } catch (\Exception $e) {
            Log::error('Pesapal subscription callback processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return $this->callbackPage('error', 'Something Went Wrong', 'We encountered an error processing your payment callback. Please return to the app and check your subscription status.');
        }
    }

    /**
     * Handle IPN notifications from Pesapal
     * POST /api/subscriptions/pesapal/ipn
     * 
     * ENHANCED: Added better error handling and retry queue
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ipn(Request $request)
    {
        try {
            Log::info('Pesapal subscription IPN received', $request->all());

            $orderTrackingId = $request->get('OrderTrackingId');
            $merchantReference = $request->get('OrderMerchantReference');

            if (!$orderTrackingId) {
                Log::warning('Pesapal IPN missing OrderTrackingId');
                return response()->json([
                    'error' => 'Missing OrderTrackingId'
                ], 400);
            }

            // ENHANCED: Use robust status checker instead of direct processing
            // This ensures retries and proper error handling
            $subscription = Subscription::where('pesapal_tracking_id', $orderTrackingId)->first();

            if (!$subscription) {
                Log::error('Pesapal IPN: Subscription not found', [
                    'tracking_id' => $orderTrackingId,
                    'merchant_reference' => $merchantReference,
                ]);

                // Still respond with success to Pesapal to avoid repeated IPN calls
                return response()->json([
                    'orderNotificationType' => 'IPNCHANGE',
                    'orderTrackingId' => $orderTrackingId,
                    'orderMerchantReference' => $merchantReference,
                    'status' => 200
                ]);
            }

            // Check payment status with retry mechanism
            $checkResult = $this->statusChecker->checkPaymentStatus($subscription, [
                'max_retries' => 2,
                'retry_delay' => 1,
            ]);

            if (!$checkResult['success']) {
                Log::error('Pesapal IPN: Status check failed', [
                    'tracking_id' => $orderTrackingId,
                    'error' => $checkResult['error'] ?? 'Unknown error',
                ]);

                // Queue for retry
                // TODO: Implement IPN retry queue for failed processing
            }

            // Respond to Pesapal
            return response()->json([
                'orderNotificationType' => 'IPNCHANGE',
                'orderTrackingId' => $orderTrackingId,
                'orderMerchantReference' => $merchantReference,
                'status' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('Pesapal subscription IPN processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still respond with success to prevent repeated IPN calls
            // The CheckPendingPayments command will catch these
            return response()->json([
                'orderNotificationType' => 'IPNCHANGE',
                'orderTrackingId' => $request->get('OrderTrackingId'),
                'orderMerchantReference' => $request->get('OrderMerchantReference'),
                'status' => 200
            ]);
        }
    }

    /**
     * Get current user's subscription status
     * GET /api/subscriptions/my-subscription
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mySubscription(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

      
            $subscriptionStatus = $user->getSubscriptionStatus();

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Subscription status retrieved successfully',
                'data' => $subscriptionStatus,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get subscription status', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to retrieve subscription status',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get subscription history
     * GET /api/subscriptions/history
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            // CHECKPOINT 2: Auto-assign free trial if user is eligible
            try {
                $freeTrialResult = $user->autoAssignFreeTrial();
                if ($freeTrialResult['success']) {
                    Log::info("CHECKPOINT 2 - Free trial assigned successfully in history endpoint", [
                        'user_id' => $user->id,
                        'subscription_created' => $freeTrialResult['subscription'] ?? null
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("CHECKPOINT 2 - Error in autoAssignFreeTrial in history endpoint", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            // $limit = $request->get('limit', 10);
            $history = $user->subscriptions;

            $data = $history->map(function ($subscription) {
                return $subscription->toApiArray();
            });

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Subscription history retrieved successfully',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get subscription history', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to retrieve subscription history',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Retry payment for failed subscription
     * POST /api/subscriptions/retry-payment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function retryPayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'subscription_id' => 'required|exists:subscriptions,id',
                'callback_url' => 'url|nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'Validation failed',
                    'data' => ['errors' => $validator->errors()],
                ], 400);
            }

            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = Subscription::findOrFail($request->subscription_id);

            // Resolve to current user's actionable subscription when client sends stale/wrong IDs.
            if ($subscription->user_id !== $user->id) {
                $resolved = $this->resolveUserActionableSubscription($user, (int) $request->subscription_id);
                if (!$resolved) {
                    return response()->json([
                        'code' => 0,
                        'status' => 404,
                        'message' => 'No actionable subscription found for this user',
                        'data' => null,
                    ], 404);
                }
                $subscription = $resolved;
            }

            // If already active and paid, return success without forcing a retry.
            if ($subscription->status === 'Active' && $subscription->payment_status === 'Completed') {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Subscription is already active and paid',
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'order_tracking_id' => $subscription->pesapal_tracking_id,
                        'merchant_reference' => $subscription->pesapal_merchant_reference,
                        'redirect_url' => $subscription->payment_url,
                    ],
                ]);
            }

            // Reset subscription to pending
            $subscription->status = 'Pending';
            $subscription->payment_status = 'Pending';
            $subscription->save();

            // Flush pending cache so getPending() reflects new state
            Cache::forget("sub_pending_{$user->id}");

            // Initialize payment again
            $callbackUrl = $request->callback_url;
            $paymentResult = $this->pesapalService->initializePayment($subscription, null, $callbackUrl);

            if ($paymentResult['success']) {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Payment retry initiated. Please complete payment.',
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'order_tracking_id' => $paymentResult['order_tracking_id'],
                        'merchant_reference' => $paymentResult['merchant_reference'],
                        'redirect_url' => $paymentResult['redirect_url'],
                    ],
                ]);
            }

            throw new \Exception('Failed to initialize payment');
        } catch (\Exception $e) {
            Log::error('Failed to retry payment', [
                'user_id' => $request->user()?->id,
                'subscription_id' => $request->subscription_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * Manually check payment status
     * POST /api/subscriptions/check-status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'subscription_id' => 'required|exists:subscriptions,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'Validation failed',
                    'data' => ['errors' => $validator->errors()],
                ], 400);
            }

            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = Subscription::findOrFail($request->subscription_id);

            if ($subscription->user_id !== $user->id) {
                $resolved = $this->resolveUserActionableSubscription($user, (int) $request->subscription_id);
                if (!$resolved) {
                    return response()->json([
                        'code' => 0,
                        'status' => 404,
                        'message' => 'No actionable subscription found for this user',
                        'data' => null,
                    ], 404);
                }
                $subscription = $resolved;
            }

            // Check if already fully active+completed — no need to re-check
            if ($subscription->status === 'Active' && $subscription->payment_status === 'Completed') {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Subscription is already active and paid',
                    'data' => $subscription->toApiArray(),
                ]);
            }

            // Check Pending, Failed, Processing, or Cancelled subscriptions
            // (Previously only checked 'Pending' — missed Failed subs where Pesapal actually completed)
            if (!in_array($subscription->status, ['Pending', 'Failed', 'Processing', 'Cancelled'])) {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Subscription status is already finalized',
                    'data' => $subscription->toApiArray(),
                ]);
            }

            // ENHANCED: Use robust status checker with retry mechanism
            if ($subscription->pesapal_tracking_id) {
                $checkResult = $this->statusChecker->checkPaymentStatus($subscription, [
                    'max_retries' => 3,
                    'retry_delay' => 2,
                    'exponential_backoff' => true,
                ]);

                if ($checkResult['success']) {
                    $subscription->refresh();

                    Log::info('Payment status check completed successfully', [
                        'subscription_id' => $subscription->id,
                        'status' => $subscription->status,
                        'attempts' => $checkResult['attempts'] ?? 1,
                    ]);
                } else {
                    Log::error('Payment status check failed', [
                        'subscription_id' => $subscription->id,
                        'error' => $checkResult['error'] ?? 'Unknown error',
                    ]);

                    return response()->json([
                        'code' => 0,
                        'status' => 500,
                        'message' => 'Failed to verify payment status: ' . ($checkResult['error'] ?? 'Please try again'),
                        'data' => null,
                    ], 500);
                }
            }

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Payment status checked successfully',
                'data' => $subscription->toApiArray(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check payment status', [
                'user_id' => $request->user()?->id,
                'subscription_id' => $request->subscription_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to check payment status',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get pending subscription for current user
     * GET /api/subscriptions/pending
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPending(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            // Throttle: cache per user for 60 seconds
            $cacheKey = "sub_pending_{$user->id}";
            $result = Cache::remember($cacheKey, 60, function () use ($user) {
                // Find pending subscription with Pending or Processing payment status
                $pendingSubscription = $user->subscriptions()
                    ->with('plan')
                    ->whereIn('payment_status', ['Pending', 'Processing'])
                    ->orderBy('created_at', 'DESC')
                    ->first();

                if ($pendingSubscription) {
                    return [
                        'code' => 1,
                        'status' => 200,
                        'message' => 'Pending subscription found',
                        'data' => [
                            'has_pending' => true,
                            'pending_subscription' => [
                                'id' => $pendingSubscription->id,
                                'user_id' => $pendingSubscription->user_id,
                                'plan' => $pendingSubscription->plan ? [
                                    'id' => $pendingSubscription->plan->id,
                                    'name' => $pendingSubscription->plan->name,
                                    'currency' => $pendingSubscription->plan->currency,
                                    'price' => $pendingSubscription->plan->price,
                                    'duration_days' => $pendingSubscription->plan->duration_days,
                                ] : null,
                                'amount' => $pendingSubscription->amount_paid,
                                'currency' => $pendingSubscription->currency,
                                'status' => $pendingSubscription->status,
                                'payment_status' => $pendingSubscription->payment_status,
                                'order_tracking_id' => $pendingSubscription->pesapal_tracking_id,
                                'merchant_reference' => $pendingSubscription->pesapal_merchant_reference,
                                'payment_url' => $pendingSubscription->payment_url ?? null,
                                'created_at' => $pendingSubscription->created_at->toIso8601String(),
                                'expires_at' => null,
                            ],
                        ],
                    ];
                }

                return [
                    'code' => 1,
                    'status' => 200,
                    'message' => 'No pending subscription. USER ID: ' . $user->id,
                    'data' => [
                        'has_pending' => false,
                        'pending_subscription' => null,
                    ],
                ];
            });

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to get pending subscription', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to get pending subscription',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Initiate payment for pending subscription
     * POST /api/subscriptions/{id}/initiate-payment
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiatePayment(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = $this->resolveUserActionableSubscription($user, (int) $id, true);

            if (!$subscription) {
                return response()->json([
                    'code' => 0,
                    'status' => 404,
                    'message' => 'No actionable subscription found for this user',
                    'data' => null,
                ], 404);
            }

            // Never block payment on status shape. For non-active records, normalize to pending.
            if (!($subscription->status === 'Active' && $subscription->payment_status === 'Completed')) {
                $subscription->status = 'Pending';
                $subscription->payment_status = 'Pending';
                $subscription->save();
            }

            // Check if payment already initiated
            if ($subscription->pesapal_tracking_id && $subscription->payment_url) {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Payment already initiated',
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'order_tracking_id' => $subscription->pesapal_tracking_id,
                        'merchant_reference' => $subscription->pesapal_merchant_reference,
                        'redirect_url' => $subscription->payment_url,
                        'amount' => $subscription->amount_paid,
                        'currency' => $subscription->currency,
                    ],
                ]);
            }

            // Initialize payment with Pesapal
            $paymentResult = $this->pesapalService->initializePayment($subscription);

            if ($paymentResult['success']) {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Payment initiated successfully',
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'order_tracking_id' => $paymentResult['order_tracking_id'],
                        'merchant_reference' => $paymentResult['merchant_reference'],
                        'redirect_url' => $paymentResult['redirect_url'],
                        'amount' => $subscription->amount_paid,
                        'currency' => $subscription->currency,
                    ],
                ]);
            }

            throw new \Exception('Failed to initialize payment');
        } catch (\Exception $e) {
            Log::error('Failed to initiate pending payment', [
                'user_id' => $request->user()?->id,
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to initiate payment',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Check payment status for pending subscription
     * POST /api/subscriptions/{id}/check-payment
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPendingPayment(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = $this->resolveUserActionableSubscription($user, (int) $id, true);

            if (!$subscription) {
                return response()->json([
                    'code' => 0,
                    'status' => 404,
                    'message' => 'No actionable subscription found for this user',
                    'data' => null,
                ], 404);
            }

            // Auto-heal: if payment was never initiated, initialize now and return redirect URL.
            if (!$subscription->pesapal_tracking_id) {
                $paymentResult = $this->pesapalService->initializePayment($subscription, null, $request->callback_url);

                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Payment initialized successfully',
                    'data' => [
                        'success' => true,
                        'status' => $subscription->status,
                        'payment_status' => $subscription->payment_status,
                        'redirect_url' => $paymentResult['redirect_url'] ?? null,
                        'order_tracking_id' => $paymentResult['order_tracking_id'] ?? null,
                        'subscription' => $subscription->fresh()->toApiArray(),
                    ],
                ]);
            }

            // Query Pesapal for payment status
            $statusResult = $this->pesapalService->getTransactionStatus($subscription->pesapal_tracking_id);

            if (!$statusResult['success']) {
                throw new \Exception('Failed to verify payment status with Pesapal');
            }

            // Update subscription based on Pesapal response
            $result = $this->pesapalService->updateSubscriptionStatus($subscription->pesapal_tracking_id, $statusResult['data']);
            $subscription->refresh();

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => $this->getStatusMessage($result['status']),
                'data' => [
                    'success' => true,
                    'status' => $subscription->status,
                    'payment_status' => $subscription->payment_status,
                    'subscription' => $subscription->toApiArray(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check pending payment', [
                'user_id' => $request->user()?->id,
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to check payment status',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Cancel pending subscription
     * POST /api/subscriptions/{id}/cancel
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelPending(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = $this->resolveUserActionableSubscription($user, (int) $id, true);

            if (!$subscription) {
                return response()->json([
                    'code' => 0,
                    'status' => 404,
                    'message' => 'No actionable subscription found for this user',
                    'data' => null,
                ], 404);
            }

            // Idempotent cancel behavior to avoid hard-stop failures.
            if ($subscription->status === 'Cancelled') {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Subscription is already canceled',
                    'data' => [
                        'success' => true,
                        'message' => 'Subscription already canceled.',
                    ],
                ]);
            }

            if ($subscription->status === 'Active' && $subscription->payment_status === 'Completed') {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Subscription is already active',
                    'data' => [
                        'success' => true,
                        'message' => 'Subscription is active and cannot be canceled from pending flow.',
                    ],
                ]);
            }

            // Cancel subscription
            $subscription->status = 'Cancelled';
            $subscription->payment_status = 'Failed';
            $subscription->cancelled_at = now();
            $subscription->cancelled_reason = 'User cancelled pending subscription';
            $subscription->save();

            // Flush pending cache so getPending() reflects cancellation immediately
            Cache::forget("sub_pending_{$user->id}");

            Log::info('Pending subscription canceled', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Subscription canceled successfully',
                'data' => [
                    'success' => true,
                    'message' => 'Subscription canceled successfully.',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel pending subscription', [
                'user_id' => $request->user()?->id,
                'subscription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to cancel subscription',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Pesapal Payment Callback
     * GET /api/subscriptions/pesapal/callback
     * 
     * This is called when user returns from Pesapal payment page
     * Frontend will handle the actual UI display
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function pesapalCallback(Request $request)
    {
        try {
            Log::info('🔔 Pesapal Callback: Received callback', [
                'params' => $request->all(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            $orderTrackingId = $request->input('OrderTrackingId');
            $orderMerchantReference = $request->input('OrderMerchantReference');

            if (!$orderTrackingId) {
                Log::error('❌ Pesapal Callback: Missing OrderTrackingId');
                return $this->callbackPage('error', 'Invalid Callback', 'The payment callback was missing required tracking information. Please return to the app and try again.');
            }

            // Find subscription — try tracking ID first, then merchant reference
            $subscription = Subscription::where('pesapal_tracking_id', $orderTrackingId)->first();

            if (!$subscription && $orderMerchantReference) {
                $subscription = Subscription::where('pesapal_merchant_reference', $orderMerchantReference)->first();
            }

            if (!$subscription) {
                Log::error('❌ Pesapal Callback: Subscription not found', [
                    'tracking_id' => $orderTrackingId,
                    'merchant_ref' => $orderMerchantReference,
                ]);
                return $this->callbackPage('error', 'Subscription Not Found', 'We could not find a matching subscription for this payment. If you were charged, please contact our support team.');
            }

            Log::info('📦 Pesapal Callback: Found subscription', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'current_status' => $subscription->status,
                'current_payment_status' => $subscription->payment_status,
            ]);

            // Capture app_type for use in the Open App button
            $subAppType = $subscription->app_type ?? null;

            // If subscription is already active (IPN arrived first), show success immediately
            if ($subscription->status === 'Active' && $subscription->payment_status === 'Completed') {
                Log::info('✅ Pesapal Callback: Subscription already active (IPN processed first)');
                $planName = $subscription->plan->name ?? 'Premium';
                return $this->callbackPage('success', 'Payment Successful!', "Your {$planName} subscription is now active. Enjoy unlimited access to all content!", null, null, $subAppType);
            }

            // Try to get transaction status from Pesapal
            // IMPORTANT: This can fail if Pesapal hasn't finished processing — that's OK
            try {
                $statusResult = $this->pesapalService->getTransactionStatus($orderTrackingId);

                if ($statusResult['success']) {
                    // Update subscription based on payment status
                    $result = $this->pesapalService->updateSubscriptionStatus($orderTrackingId, $statusResult['data']);
                    $status = $result['status'];

                    Log::info('✅ Pesapal Callback: Status updated', [
                        'subscription_id' => $subscription->id,
                        'result_status' => $status,
                    ]);

                    $planName = $subscription->plan->name ?? 'Premium';

                    if ($status === 'success') {
                        return $this->callbackPage('success', 'Payment Successful!', "Your {$planName} subscription is now active. Enjoy unlimited access to all content!", null, null, $subAppType);
                    } elseif ($status === 'failed') {
                        // Build detailed API response message for user
                        $apiResponse = $result['api_response'] ?? [];
                        $apiDetail = $apiResponse['payment_status'] ?? 'Failed';
                        if (!empty($apiResponse['description'])) {
                            $apiDetail .= ' — ' . $apiResponse['description'];
                        }
                        if (!empty($apiResponse['message'])) {
                            $apiDetail .= ' (' . $apiResponse['message'] . ')';
                        }
                        if (!empty($apiResponse['payment_method'])) {
                            $apiDetail .= ' via ' . $apiResponse['payment_method'];
                        }

                        // Reload subscription to get payment_url for retry
                        $subscription->refresh();
                        $retryUrl = $subscription->payment_url;

                        return $this->callbackPage(
                            'failed',
                            'Payment Failed',
                            'Your payment could not be processed. Please try again or use a different payment method.',
                            $apiDetail,
                            $retryUrl,
                            $subAppType
                        );
                    } else {
                        // Payment still processing
                        return $this->callbackPage('pending', 'Payment Processing', 'Your payment is being verified. This usually takes a few moments. Please return to the app — your subscription will activate automatically once confirmed.', null, null, $subAppType);
                    }
                } else {
                    // Status check failed but that's OK — payment may still be processing
                    Log::warning('⚠️ Pesapal Callback: Status check failed, showing pending page', [
                        'error' => $statusResult['error'] ?? 'unknown',
                    ]);
                    return $this->callbackPage('pending', 'Verifying Payment', 'We are confirming your payment with the payment provider. Please return to the app — your subscription will activate automatically once your payment is confirmed.', null, null, $subAppType);
                }

            } catch (\Exception $statusException) {
                // Status check threw an exception — show pending, not error
                Log::warning('⚠️ Pesapal Callback: Exception during status check, showing pending page', [
                    'error' => $statusException->getMessage(),
                    'subscription_id' => $subscription->id,
                ]);
                return $this->callbackPage('pending', 'Verifying Payment', 'We are confirming your payment with the payment provider. Please return to the app — your subscription will activate automatically once your payment is confirmed.', null, null, $subAppType);
            }

        } catch (\Exception $e) {
            Log::error('💥 Pesapal Callback: CRITICAL ERROR', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);

            return $this->callbackPage('error', 'Something Went Wrong', 'We encountered an unexpected error while processing your payment callback. Please return to the app and check your subscription status. If you were charged, your subscription will be activated automatically.');
        }
    }

    /**
     * Pesapal IPN (Instant Payment Notification)
     * POST /api/subscriptions/pesapal/ipn
     * 
     * This is called by Pesapal servers when payment status changes
     * Should return 200 OK quickly and process asynchronously
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pesapalIpn(Request $request)
    {
        try {
            Log::info('📢 Pesapal IPN: Received notification', [
                'params' => $request->all(),
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            $orderTrackingId = $request->input('OrderTrackingId');
            $orderMerchantReference = $request->input('OrderMerchantReference');
            $orderNotificationType = $request->input('OrderNotificationType');

            if (!$orderTrackingId) {
                Log::error('❌ Pesapal IPN: Missing OrderTrackingId');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Missing tracking ID'
                ], 400);
            }

            // Process IPN
            $result = $this->pesapalService->processIpnCallback($orderTrackingId, $orderMerchantReference);
 
            // Return 200 OK immediately
            return response()->json([
                'status' => 'success',
                'message' => 'IPN processed'
            ], 200);
        } catch (\Exception $e) {
            Log::error('💥 Pesapal IPN: Processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still return 200 OK to prevent Pesapal from retrying
            return response()->json([
                'status' => 'error',
                'message' => 'IPN logged for manual processing'
            ], 200);
        }
    }

    /**
     * Check payment status and return subscription with manifest
     * GET /api/subscriptions/payment-status/{trackingId}
     * 
     * @param Request $request
     * @param string $trackingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentStatus(Request $request, $trackingId)
    {
        try {
            Log::info('🔍 Payment Status Check: Starting', [
                'tracking_id' => $trackingId,
                'user_id' => $request->user()?->id,
            ]);

            $subscription = Subscription::where('pesapal_tracking_id', $trackingId)
                ->with(['plan', 'transactions'])
                ->first();

            if (!$subscription) {
                Log::error('❌ Payment Status Check: Subscription not found', [
                    'tracking_id' => $trackingId,
                ]);

                return response()->json([
                    'code' => 0,
                    'status' => 404,
                    'message' => 'Subscription not found',
                    'data' => null,
                ], 404);
            }

            // NOTE: Ownership hard-stop removed here to avoid payment confirmation failures
            // when clients hold stale auth state during callback finalization.

            // Check with Pesapal if not yet completed
            if ($subscription->payment_status !== 'Completed') {
                 
                $statusResult = $this->pesapalService->getTransactionStatus($trackingId);

                if ($statusResult['success']) {
                    $result = $this->pesapalService->updateSubscriptionStatus($trackingId, $statusResult['data']);
                    $subscription->refresh();
 
                }
            }

            // Build comprehensive response with manifest
            $manifest = $this->buildSubscriptionManifest($subscription);

  

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Payment status retrieved successfully',
                'data' => [
                    'subscription' => $subscription->toApiArray(),
                    'manifest' => $manifest,
                    'is_active' => $subscription->isActive(),
                    'is_paid' => $subscription->isPaid(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('💥 Payment Status Check: CRITICAL ERROR', [
                'tracking_id' => $trackingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to check payment status',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Build comprehensive manifest for subscription
     * Used for debugging and tracking
     * 
     * @param Subscription $subscription
     * @return array
     */
    private function buildSubscriptionManifest($subscription)
    {
        return [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'plan' => [
                'id' => $subscription->plan->id ?? null,
                'name' => $subscription->plan->name ?? null,
                'duration_days' => $subscription->days,
            ],
            'status' => [
                'subscription_status' => $subscription->status,
                'payment_status' => $subscription->payment_status,
                'is_active' => $subscription->isActive(),
                'is_paid' => $subscription->isPaid(),
                'is_expired' => $subscription->isExpired(),
                'in_grace_period' => $subscription->isInGracePeriod(),
            ],
            'dates' => [
                'created_at' => $subscription->created_at?->toIso8601String(),
                'start_date_time' => $subscription->start_date_time?->toIso8601String(),
                'end_date_time' => $subscription->end_date_time?->toIso8601String(),
                'grace_period_end' => $subscription->grace_period_end?->toIso8601String(),
                'payment_confirmed_at' => $subscription->payment_confirmed_at?->toIso8601String(),
                'failed_at' => $subscription->failed_at?->toIso8601String(),
            ],
            'payment' => [
                'amount_paid' => $subscription->amount_paid,
                'currency' => $subscription->currency,
                'payment_method' => $subscription->payment_method,
                'pesapal_tracking_id' => $subscription->pesapal_tracking_id,
                'pesapal_merchant_reference' => $subscription->pesapal_merchant_reference,
            ],
            'transactions' => $subscription->transactions->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->transaction_type,
                    'amount' => $tx->amount,
                    'status' => $tx->status,
                    'payment_method' => $tx->payment_method,
                    'confirmation_code' => $tx->confirmation_code,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            })->toArray(),
            'metadata' => [
                'is_extension' => $subscription->is_extension,
                'extended_from_id' => $subscription->extended_from_id,
                'auto_renew' => $subscription->auto_renew,
                'ip_address' => $subscription->ip_address,
            ],
        ];
    }

    /**
     * Force verify payment status (Admin/Support endpoint)
     * POST /api/subscriptions/force-verify
     * 
     * Manually forces a payment verification check, bypassing idempotency checks.
     * Useful for resolving stuck payments where callbacks failed.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceVerify(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'subscription_id' => 'required|exists:subscriptions,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'Validation failed',
                    'data' => ['errors' => $validator->errors()],
                ], 400);
            }

            $user = $request->user();

            if (!$user) {
                $user = Utils::get_user($request);
            }

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = Subscription::findOrFail($request->subscription_id);

            if ($subscription->user_id !== $user->id) {
                $resolved = $this->resolveUserActionableSubscription($user, (int) $request->subscription_id);
                if (!$resolved) {
                    return response()->json([
                        'code' => 0,
                        'status' => 404,
                        'message' => 'No actionable subscription found for this user',
                        'data' => null,
                    ], 404);
                }
                $subscription = $resolved;
            }

            // Force verify payment
            $verifyResult = $this->statusChecker->forceVerifyPayment($subscription);

            if ($verifyResult['success']) {
                $subscription->refresh();

                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Payment verification completed',
                    'data' => [
                        'subscription' => $subscription->toApiArray(),
                        'verification_result' => $verifyResult,
                    ],
                ]);
            } else {
                return response()->json([
                    'code' => 0,
                    'status' => 500,
                    'message' => 'Force verification failed: ' . ($verifyResult['error'] ?? 'Unknown error'),
                    'data' => null,
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to force verify payment', [
                'user_id' => $request->user()?->id,
                'subscription_id' => $request->subscription_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Force verification failed',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Helper: Get status message
     */
    private function getStatusMessage($status)
    {
        return match ($status) {
            'success' => 'Payment completed successfully. Your subscription is now active!',
            'failed' => 'Payment failed. Please try again or contact support.',
            'pending' => 'Payment is being processed. Please wait...',
            default => 'Payment status unknown',
        };
    }

    /**
     * Resolve a user-owned subscription for payment actions.
     * Treats incoming ID as a hint and falls back to latest actionable subscription.
     */
    private function resolveUserActionableSubscription($user, int $hintId, bool $withPlan = false)
    {
        $query = $withPlan ? Subscription::with('plan') : Subscription::query();
        $requested = $query->find($hintId);

        if ($requested && (int) $requested->user_id === (int) $user->id) {
            return $requested;
        }

        if ($requested && (int) $requested->user_id !== (int) $user->id) {
            Log::warning('Subscription action fallback: incoming subscription does not belong to user', [
                'incoming_subscription_id' => $hintId,
                'incoming_user_id' => $requested->user_id,
                'request_user_id' => $user->id,
            ]);
        }

        $fallbackQuery = $withPlan ? $user->subscriptions()->with('plan') : $user->subscriptions();

        return $fallbackQuery
            ->whereIn('status', ['Pending', 'Failed', 'Cancelled', 'Processing'])
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Helper: Return branded callback page for all statuses
     * Matches the app's dark theme with green/orange branding
     * 
     * @param string $status 'success' | 'failed' | 'pending' | 'error'
     * @param string $title Page title
     * @param string $message Body message
     * @return \Illuminate\Http\Response
     */
    private function callbackPage(string $status, string $title, string $message, ?string $apiDetail = null, ?string $retryUrl = null, ?string $appType = null)
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $escapedApiDetail = $apiDetail ? htmlspecialchars($apiDetail, ENT_QUOTES, 'UTF-8') : null;

        // Status-specific styling
        $config = match ($status) {
            'success' => [
                'icon' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="40" fill="#086433" opacity="0.15"/><circle cx="40" cy="40" r="30" fill="#086433" opacity="0.25"/><path d="M26 40L35 49L54 30" stroke="#47C757" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                'accent' => '#47C757',
                'glow' => 'rgba(8, 100, 51, 0.3)',
                'badge' => 'Subscription Active',
                'badgeBg' => 'rgba(71, 199, 87, 0.15)',
                'badgeColor' => '#47C757',
            ],
            'pending' => [
                'icon' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="40" fill="#FF9800" opacity="0.12"/><circle cx="40" cy="40" r="30" fill="#FF9800" opacity="0.2"/><circle cx="40" cy="40" r="14" stroke="#FF9800" stroke-width="3" fill="none"/><path d="M40 32V40L46 44" stroke="#FF9800" stroke-width="3" stroke-linecap="round"/></svg>',
                'accent' => '#FF9800',
                'glow' => 'rgba(255, 152, 0, 0.2)',
                'badge' => 'Verifying Payment',
                'badgeBg' => 'rgba(255, 152, 0, 0.15)',
                'badgeColor' => '#FF9800',
            ],
            'failed' => [
                'icon' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="40" fill="#FF5722" opacity="0.12"/><circle cx="40" cy="40" r="30" fill="#FF5722" opacity="0.2"/><path d="M30 30L50 50M50 30L30 50" stroke="#FF5722" stroke-width="4" stroke-linecap="round"/></svg>',
                'accent' => '#FF5722',
                'glow' => 'rgba(255, 87, 34, 0.2)',
                'badge' => 'Payment Failed',
                'badgeBg' => 'rgba(255, 87, 34, 0.15)',
                'badgeColor' => '#FF5722',
            ],
            default => [ // error
                'icon' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="40" fill="#FF9800" opacity="0.12"/><circle cx="40" cy="40" r="30" fill="#FF9800" opacity="0.2"/><path d="M40 28V44" stroke="#FF9800" stroke-width="4" stroke-linecap="round"/><circle cx="40" cy="52" r="2.5" fill="#FF9800"/></svg>',
                'accent' => '#FF9800',
                'glow' => 'rgba(255, 152, 0, 0.2)',
                'badge' => 'Attention Required',
                'badgeBg' => 'rgba(255, 152, 0, 0.15)',
                'badgeColor' => '#FF9800',
            ],
        };

        $icon = $config['icon'];
        $accent = $config['accent'];
        $glow = $config['glow'];
        $badge = $config['badge'];
        $badgeBg = $config['badgeBg'];
        $badgeColor = $config['badgeColor'];

        // ── Open App button ──────────────────────────────────────────────────
        // Package IDs and display names per app_type
        $appMeta = match (strtolower($appType ?? '')) {
            'lugaflix'  => ['name' => 'LugaFlix', 'package' => 'lugaflix.movies',  'scheme' => 'lugaflix',  'color' => '#3498db'],
            'muno_app'  => ['name' => 'Muno App',  'package' => 'muno.app',         'scheme' => 'munoapp',   'color' => '#e74c3c'],
            default     => ['name' => 'UG Flix',   'package' => 'ugflix.com',       'scheme' => 'ugflix',    'color' => '#47C757'],
        };

        $appName    = $appMeta['name'];
        $appPackage = $appMeta['package'];
        $appScheme  = $appMeta['scheme'];
        $appColor   = $appMeta['color'];
        $playStoreUrl = htmlspecialchars('https://play.google.com/store/apps/details?id=' . $appPackage, ENT_QUOTES, 'UTF-8');

        // Button shown on success + pending (user needs to return to the app)
        $openAppHtml = '';
        if (in_array($status, ['success', 'pending'])) {
            $openAppHtml = <<<OPENAPP

            <div class="open-app-wrap">
                <button class="open-app-btn" onclick="openApp()" style="background:{$appColor}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="vertical-align:middle;margin-right:8px;flex-shrink:0">
                        <rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="1.8" fill="none"/>
                        <circle cx="12" cy="18" r="1" fill="currentColor"/>
                    </svg>
                    Open {$appName}
                </button>
                <div class="open-app-hint" id="openAppHint" style="display:none">
                    App not installed? <a href="{$playStoreUrl}" target="_blank" rel="noopener" style="color:{$appColor}">Get it on Play Store</a>
                </div>
            </div>

            <script>
            function openApp() {
                var scheme = '{$appScheme}://open';
                var intentUrl = 'intent://open/#Intent;scheme={$appScheme};package={$appPackage};end';
                var fallbackUrl = '{$playStoreUrl}';

                // Try intent URL (Android) — falls back to Play Store if not installed
                var start = Date.now();
                window.location.href = intentUrl;

                // After 1.5s if we're still here, show the fallback hint
                setTimeout(function() {
                    if (Date.now() - start < 3000) {
                        document.getElementById('openAppHint').style.display = 'block';
                    }
                }, 1500);
            }
            </script>
OPENAPP;
        }
        // ────────────────────────────────────────────────────────────────────

        // Pre-compute dynamic values for heredoc
        $pendingClass = ($status === 'pending') ? ' pulse' : '';

        // Build API detail box HTML if we have API response detail
        $apiDetailHtml = '';
        if ($escapedApiDetail && in_array($status, ['failed', 'error'])) {
            $apiDetailHtml = <<<APIBOX
            <div class="api-detail-box">
                <div class="api-detail-label">Payment Gateway Response</div>
                <div class="api-detail-text">{$escapedApiDetail}</div>
            </div>
APIBOX;
        }

        // Build retry button HTML
        $retryButtonHtml = '';
        if (in_array($status, ['failed', 'error'])) {
            if ($retryUrl) {
                $escapedRetryUrl = htmlspecialchars($retryUrl, ENT_QUOTES, 'UTF-8');
                $retryButtonHtml = <<<RETRY
            <a href="{$escapedRetryUrl}" class="retry-button">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="vertical-align: middle; margin-right: 8px;">
                    <path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z" fill="currentColor"/>
                </svg>
                Retry Payment
            </a>
RETRY;
            }
            // Always show "Return to App" as secondary action
            $retryButtonHtml .= <<<CLOSEBTN
            <a href="javascript:void(0)" onclick="window.close(); setTimeout(function(){ document.querySelector('.close-hint').style.display='block'; }, 300);" class="close-button">
                Return to App
            </a>
            <div class="close-hint" style="display:none; margin-top: 8px; font-size: 12px; color: #6B7280;">
                Close this tab and reopen the app to try again.
            </div>
CLOSEBTN;
        }

        $ctaHtml = match ($status) {
            'success' => '<strong>You\'re all set!</strong><br>Close this page and return to the app to start watching.',
            'pending' => '<span class="spinner"></span> <strong>Checking payment status...</strong><br>This page will update automatically. You can also close it and check in the app.',
            'failed' => '<strong>What happened?</strong><br>The payment provider could not process your transaction. You can retry the same payment or return to the app and try a different payment method.',
            default => '<strong>Return to the app</strong><br>Check your subscription status in the app. If you were charged, your subscription will activate automatically.',
        };

        // Auto-refresh for pending status (check every 10 seconds, up to 3 times)
        $autoRefresh = ($status === 'pending')
            ? "<script>
                let refreshCount = 0;
                const maxRefresh = 3;
                setTimeout(function tick() {
                    refreshCount++;
                    if (refreshCount <= maxRefresh) {
                        location.reload();
                    }
                }, 10000);
               </script>"
            : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{$escapedTitle} — UGFlix</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #0D0D1A;
            color: #FFFFFF;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        /* Logo */
        .logo-section {
            margin-bottom: 32px;
        }
        .logo-text {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .logo-ug { color: #FFFFFF; }
        .logo-flix { color: #47C757; }
        .logo-tagline {
            font-size: 11px;
            color: #6B7280;
            margin-top: 4px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Status Card */
        .card {
            background: linear-gradient(145deg, #151526, #1A1A2E);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 40px 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px {$glow};
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, {$accent}, transparent);
        }

        /* Icon */
        .status-icon {
            margin-bottom: 24px;
        }

        /* Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: {$badgeBg};
            color: {$badgeColor};
            border: 1px solid {$badgeColor}33;
            margin-bottom: 20px;
        }

        /* Title */
        .status-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #FFFFFF;
            line-height: 1.3;
        }

        /* Message */
        .status-message {
            font-size: 15px;
            color: #9CA3AF;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
            margin: 0 -32px 24px;
        }

        /* CTA */
        .cta-text {
            font-size: 13px;
            color: #6B7280;
            line-height: 1.6;
        }
        .cta-text strong {
            color: #9CA3AF;
        }

        /* Spinner for pending */
        .spinner-wrap {
            display: inline-block;
            margin-bottom: 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,152,0,0.2);
            border-top-color: #FF9800;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }

        /* Footer */
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #4B5563;
        }
        .footer a {
            color: #6B7280;
            text-decoration: none;
        }

        /* API Detail Box */
        .api-detail-box {
            background: rgba(255, 87, 34, 0.08);
            border: 1px solid rgba(255, 87, 34, 0.2);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            text-align: left;
        }
        .api-detail-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #FF5722;
            margin-bottom: 6px;
        }
        .api-detail-text {
            font-size: 13px;
            color: #E0E0E0;
            line-height: 1.5;
            word-break: break-word;
        }

        /* Retry Button */
        .retry-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 14px 24px;
            margin-top: 16px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #086433, #0a7a3e);
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(8, 100, 51, 0.3);
            letter-spacing: 0.3px;
        }
        .retry-button:hover {
            background: linear-gradient(135deg, #0a7a3e, #0c9148);
            box-shadow: 0 6px 20px rgba(8, 100, 51, 0.45);
            transform: translateY(-1px);
        }
        .retry-button:active {
            transform: translateY(0);
        }

        /* Close/Return Button */
        .close-button {
            display: inline-block;
            width: 100%;
            padding: 12px 24px;
            margin-top: 4px;
            background: transparent;
            color: #9CA3AF;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .close-button:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #FFFFFF;
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Pulse animation for pending icon */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse { animation: pulse 2s ease-in-out infinite; }

        /* Open App Button */
        .open-app-wrap {
            margin-top: 20px;
        }
        .open-app-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 15px 24px;
            border: none;
            border-radius: 14px;
            color: #FFFFFF;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: opacity 0.2s, transform 0.15s;
            box-shadow: 0 4px 18px rgba(0,0,0,0.3);
        }
        .open-app-btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .open-app-btn:active { transform: translateY(0); opacity: 1; }
        .open-app-hint {
            margin-top: 10px;
            font-size: 13px;
            color: #6B7280;
            text-align: center;
        }
    </style>
    {$autoRefresh}
</head>
<body>
    <div class="container">
        <div class="logo-section">
            <div class="logo-text">
                <span class="logo-ug">UG</span><span class="logo-flix">Flix</span>
            </div>
            <div class="logo-tagline">Premium Entertainment</div>
        </div>

        <div class="card">
            <div class="status-icon{$pendingClass}">
                {$icon}
            </div>

            <div class="status-badge">{$badge}</div>

            <h1 class="status-title">{$escapedTitle}</h1>
            <p class="status-message">{$escapedMessage}</p>

            {$apiDetailHtml}

            <div class="divider"></div>

            <p class="cta-text">
                {$ctaHtml}
            </p>

            {$retryButtonHtml}

            {$openAppHtml}
        </div>

        <div class="footer">
            &copy; 2026 UGFlix &middot; Powered by Pesapal
        </div>
    </div>
</body>
</html>
HTML;

        return response()->make($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Legacy: Return callback error (kept for backward compatibility)
     * Now delegates to callbackPage
     */
    private function callbackError($message, $request = null)
    {
        if ($request && ($request->expectsJson() || $request->wantsJson())) {
            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => $message,
                'data' => null,
            ], 500);
        }

        return $this->callbackPage('error', 'Payment Error', $message);
    }

    /**
     * TEST ENDPOINT: Verify Pesapal API connectivity
     * GET /api/subscriptions/pesapal/test
     * 
     * Tests: Authentication → IPN Registration → (optionally) Order Submission
     * Does NOT require user authentication — for admin diagnostics only.
     * In production, protect this with an API key or IP whitelist.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pesapalTest(Request $request)
    {
        try {
            $submitOrder = $request->get('submit_order', false);
            $results = $this->pesapalService->testConnection((bool) $submitOrder);

            // Also get registered IPN list
            $ipnList = $this->pesapalService->getRegisteredIpnList();
            $results['registered_ipns'] = $ipnList;

            return response()->json([
                'code' => $results['overall'] === 'ALL PASSED' ? 1 : 0,
                'status' => 200,
                'message' => 'Pesapal API test: ' . $results['overall'],
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Pesapal test failed: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
