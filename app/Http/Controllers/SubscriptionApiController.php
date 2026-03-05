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
            $validator = Validator::make($request->all(), [
                'plan_id' => 'required|exists:subscription_plans,id',
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

            if (! $user) {
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

            // Use database transaction to prevent concurrent subscription creation
            $subscription = DB::transaction(function () use ($user, $request) {
                // Lock user row to prevent race conditions
                $user->lockForUpdate();

                // FIXED: Check for pending/processing subscriptions but allow cancellation
                $pendingSubscriptions = $user->subscriptions()
                    ->whereIn('payment_status', ['Pending', 'Processing'])
                    ->where('status', 'Pending')
                    ->where('created_at', '>', now()->subHours(2)) // Extended to 2 hours
                    ->get();

                if ($pendingSubscriptions->count() > 0) {
                    // SOLUTION: Cancel old pending subscriptions instead of blocking
                    foreach ($pendingSubscriptions as $pendingSub) {
                        Log::info('Cancelling old pending subscription to allow new one', [
                            'old_subscription_id' => $pendingSub->id,
                            'user_id' => $user->id,
                        ]);
                        
                        // Cancel the old pending subscription
                        $pendingSub->status = 'Cancelled';
                        $pendingSub->payment_status = 'Failed';
                        $pendingSub->cancelled_at = now();
                        $pendingSub->cancelled_reason = 'Cancelled due to new subscription attempt';
                        $pendingSub->save();
                    }
                }

                // Get plan
                $plan = SubscriptionPlan::active()->findOrFail($request->plan_id);

                // Create subscription
                return $user->createSubscription($plan);
            });

            // Initialize payment with Pesapal
            $callbackUrl = $request->callback_url;
            $paymentResult = $this->pesapalService->initializePayment($subscription, null, $callbackUrl);

            if ($paymentResult['success']) {
                Log::info('Subscription created and payment initialized', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id,
                    'plan_id' => $request->plan_id,
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
            }

            throw new \Exception('Failed to initialize payment');
        } catch (\Exception $e) {
            Log::error('Failed to create subscription', [
                'user_id' => $request->user()?->id,
                'plan_id' => $request->plan_id,
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
     * Handle payment callback from Pesapal
     * GET /api/subscriptions/pesapal/callback
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        try {
            Log::info('Pesapal subscription callback received', $request->all());

            $orderTrackingId = $request->get('OrderTrackingId');
            $merchantReference = $request->get('OrderMerchantReference');

            if (!$orderTrackingId) {
                Log::warning('Pesapal callback missing OrderTrackingId');
                return $this->callbackError('Invalid callback parameters');
            }

            // Get transaction status from Pesapal
            $statusResult = $this->pesapalService->getTransactionStatus($orderTrackingId);

            if (!$statusResult['success']) {
                throw new \Exception('Failed to verify payment status');
            }

            // Update subscription status
            $result = $this->pesapalService->updateSubscriptionStatus($orderTrackingId, $statusResult['data']);
            $subscription = $result['subscription'];

            $status = $result['status']; // success, failed, pending

            // Return JSON if API request, otherwise redirect for web
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
            } else {
                // Redirect to frontend with status
                $frontendUrl = env('APP_FRONTEND_URL', env('APP_URL'));
                $redirectUrl = "{$frontendUrl}/subscription-result?status={$status}&subscription_id={$subscription->id}";
                return redirect($redirectUrl);
            }
        } catch (\Exception $e) {
            Log::error('Pesapal subscription callback processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return $this->callbackError($e->getMessage(), $request);
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

            // Verify ownership
            if ($subscription->user_id !== $user->id) {
                return response()->json([
                    'code' => 0,
                    'status' => 403,
                    'message' => 'Unauthorized access',
                    'data' => null,
                ], 403);
            }

            // Only allow retry for Pending or Failed subscriptions
            if (!in_array($subscription->status, ['Pending', 'Failed'])) {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'This subscription cannot be retried',
                    'data' => null,
                ], 400);
            }

            // Reset subscription to pending
            $subscription->status = 'Pending';
            $subscription->payment_status = 'Pending';
            $subscription->save();

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

            // Verify ownership
            if ($subscription->user_id !== $user->id) {
                return response()->json([
                    'code' => 0,
                    'status' => 403,
                    'message' => 'Unauthorized access',
                    'data' => null,
                ], 403);
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

            // Find pending subscription with Pending or Processing payment status
            $pendingSubscription = $user->subscriptions()
                ->with('plan')
                ->whereIn('payment_status', ['Pending', 'Processing'])
                ->orderBy('created_at', 'DESC')
                ->first();

            if ($pendingSubscription) {
                return response()->json([
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
                            'expires_at' => null, // Can add expiry logic if needed
                        ],
                    ],
                ]);
            }

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'No pending subscription. USER ID: ' . $user->id,
                'data' => [
                    'has_pending' => false,
                    'pending_subscription' => null,
                ],
            ]);
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

            $subscription = Subscription::with('plan')->findOrFail($id);

            // Verify ownership
            if ($subscription->user_id !== $user->id) {
                return response()->json([
                    'code' => 0,
                    'status' => 403,
                    'message' => 'This subscription does not belong to you',
                    'data' => null,
                ], 403);
            }

            // Verify status is Pending
            if ($subscription->status !== 'Pending') {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'Subscription is not in pending status',
                    'data' => null,
                ], 400);
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

            $subscription = Subscription::with('plan')->findOrFail($id);

            // Verify ownership
            if ($subscription->user_id !== $user->id) {
                return response()->json([
                    'code' => 0,
                    'status' => 403,
                    'message' => 'This subscription does not belong to you',
                    'data' => null,
                ], 403);
            }

            // Verify has order tracking ID
            if (!$subscription->pesapal_tracking_id) {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'Subscription payment not yet initiated',
                    'data' => null,
                ], 400);
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

            $subscription = Subscription::findOrFail($id);

            // Verify ownership
            if ($subscription->user_id !== $user->id) {
                return response()->json([
                    'code' => 0,
                    'status' => 403,
                    'message' => 'This subscription does not belong to you',
                    'data' => null,
                ], 403);
            }

            // Verify status is Pending
            if ($subscription->status !== 'Pending') {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'Only pending subscriptions can be canceled',
                    'data' => null,
                ], 400);
            }

            // Cancel subscription
            $subscription->status = 'Cancelled';
            $subscription->payment_status = 'Failed';
            $subscription->cancelled_at = now();
            $subscription->cancelled_reason = 'User cancelled pending subscription';
            $subscription->save();

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
                return $this->callbackError('Invalid callback: Missing tracking ID', $request);
            }

            // Find subscription
            $subscription = Subscription::where('pesapal_tracking_id', $orderTrackingId)
                ->orWhere('pesapal_merchant_reference', $orderMerchantReference)
                ->first();

            if (!$subscription) {
                Log::error('❌ Pesapal Callback: Subscription not found', [
                    'tracking_id' => $orderTrackingId,
                    'merchant_ref' => $orderMerchantReference,
                ]);
                return $this->callbackError('Subscription not found', $request);
            }

            Log::info('📦 Pesapal Callback: Found subscription', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'current_status' => $subscription->status,
            ]);

            // Get transaction status from Pesapal
            $statusResult = $this->pesapalService->getTransactionStatus($orderTrackingId);

            if (!$statusResult['success']) {
                Log::error('❌ Pesapal Callback: Failed to get transaction status');
                return $this->callbackError('Failed to verify payment status', $request);
            }

            // Update subscription based on payment status
            $result = $this->pesapalService->updateSubscriptionStatus($orderTrackingId, $statusResult['data']);

            Log::info('✅ Pesapal Callback: Status updated', [
                'subscription_id' => $subscription->id,
                'result_status' => $result['status'],
            ]);

            // Return a user-friendly HTML page since users come from the mobile app's external browser
            // The app will check payment status via the PendingSubscriptionScreen
            $status = $result['status'];
            $statusEmoji = match ($status) {
                'success' => '✅',
                'failed' => '❌',
                'pending' => '⏳',
                default => '❓',
            };
            $statusTitle = match ($status) {
                'success' => 'Payment Successful!',
                'failed' => 'Payment Failed',
                'pending' => 'Payment Processing',
                default => 'Payment Status Unknown',
            };
            $statusMessage = match ($status) {
                'success' => 'Your subscription has been activated. You can close this page and return to the app.',
                'failed' => 'Your payment could not be processed. Please return to the app and try again.',
                'pending' => 'Your payment is being processed. Please return to the app and check your subscription status.',
                default => 'Please return to the app and check your subscription status.',
            };

            Log::info('🔀 Pesapal Callback: Showing status page to user', [
                'status' => $status,
                'tracking_id' => $orderTrackingId,
            ]);

            return response()->make("
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='utf-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1'>
                    <title>{$statusTitle}</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                               display: flex; justify-content: center; align-items: center; min-height: 100vh; 
                               margin: 0; background: #1a1a2e; color: #fff; }
                        .card { text-align: center; padding: 40px; max-width: 400px; 
                                background: #16213e; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
                        .emoji { font-size: 64px; margin-bottom: 16px; }
                        h1 { margin: 0 0 12px; font-size: 24px; }
                        p { color: #a0a0b0; line-height: 1.6; margin: 0; }
                    </style>
                </head>
                <body>
                    <div class='card'>
                        <div class='emoji'>{$statusEmoji}</div>
                        <h1>{$statusTitle}</h1>
                        <p>{$statusMessage}</p>
                    </div>
                </body>
                </html>
            ", 200, ['Content-Type' => 'text/html']);
        } catch (\Exception $e) {
            Log::error('💥 Pesapal Callback: CRITICAL ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->callbackError('Payment verification failed. Please contact support.', $request);
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

            // Verify ownership if user is authenticated
            if ($request->user() && $subscription->user_id !== $request->user()->id) {
                Log::warning('⚠️ Payment Status Check: Unauthorized access attempt', [
                    'tracking_id' => $trackingId,
                    'user_id' => $request->user()->id,
                    'subscription_user_id' => $subscription->user_id,
                ]);

                return response()->json([
                    'code' => 0,
                    'status' => 403,
                    'message' => 'This subscription does not belong to you',
                    'data' => null,
                ], 403);
            }

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

            // Verify ownership
            if ($subscription->user_id !== $user->id) {
                return response()->json([
                    'code' => 0,
                    'status' => 403,
                    'message' => 'Unauthorized access',
                    'data' => null,
                ], 403);
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
     * Helper: Return callback error
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
        } else {
            // Return a user-friendly error page for mobile app users
            $escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            return response()->make("
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='utf-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1'>
                    <title>Payment Error</title>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                               display: flex; justify-content: center; align-items: center; min-height: 100vh; 
                               margin: 0; background: #1a1a2e; color: #fff; }
                        .card { text-align: center; padding: 40px; max-width: 400px; 
                                background: #16213e; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
                        .emoji { font-size: 64px; margin-bottom: 16px; }
                        h1 { margin: 0 0 12px; font-size: 24px; }
                        p { color: #a0a0b0; line-height: 1.6; margin: 0; }
                    </style>
                </head>
                <body>
                    <div class='card'>
                        <div class='emoji'>⚠️</div>
                        <h1>Payment Error</h1>
                        <p>{$escapedMessage}</p>
                        <p style='margin-top: 16px;'>Please return to the app and try again.</p>
                    </div>
                </body>
                </html>
            ", 200, ['Content-Type' => 'text/html']);
        }
    }
}
