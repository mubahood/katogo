<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\Utils;
use App\Services\SubscriptionPesapalService;
use App\Services\PaymentStatusChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * V2 Subscription Fix Controller
 * 
 * Provides robust endpoints for diagnosing and fixing subscription payment issues.
 * Designed to give mobile apps 360° control over resolving stuck/failed payments.
 * 
 * ROOT CAUSES ADDRESSED:
 * 1. IPN callbacks failing silently (network issues, server errors)
 * 2. Race conditions between IPN and callback
 * 3. Pesapal completing payment but local status staying Pending/Failed
 * 4. Token expiration during long payment flows
 * 5. User closing browser before callback fires
 */
class SubscriptionFixController extends Controller
{
    protected SubscriptionPesapalService $pesapalService;
    protected PaymentStatusChecker $statusChecker;

    public function __construct(
        SubscriptionPesapalService $pesapalService,
        PaymentStatusChecker $statusChecker
    ) {
        $this->pesapalService = $pesapalService;
        $this->statusChecker = $statusChecker;
    }

    /**
     * GET /api/v2/subscriptions/fixable
     * 
     * List ALL subscriptions that might need fixing for the authenticated user.
     * Returns pending, failed, and processing subscriptions with diagnostic info.
     */
    public function fixable(Request $request)
    {
        try {
            $user = $request->user() ?? Utils::get_user($request);

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            // Find all subscriptions that COULD need fixing
            $fixable = $user->subscriptions()
                ->with(['plan', 'transactions'])
                ->where(function ($q) {
                    $q->whereIn('status', ['Pending', 'Failed', 'Processing'])
                      ->orWhere(function ($q2) {
                          // Also catch Active subs with Pending/Processing payment
                          $q2->where('status', 'Active')
                             ->whereIn('payment_status', ['Pending', 'Processing']);
                      })
                      ->orWhere(function ($q2) {
                          // Catch "Cancelled" subs that might have been paid
                          // (cancelled due to new subscription attempt while payment was processing)
                          $q2->where('status', 'Cancelled')
                             ->where('cancelled_reason', 'LIKE', '%new subscription attempt%')
                             ->whereNotNull('pesapal_tracking_id')
                             ->where('created_at', '>', now()->subDays(7));
                      });
                })
                ->where('created_at', '>', now()->subDays(30)) // Only last 30 days
                ->orderBy('created_at', 'desc')
                ->get();

            $fixableList = $fixable->map(function ($sub) {
                $ageMinutes = now()->diffInMinutes($sub->created_at);
                $canFix = !empty($sub->pesapal_tracking_id);
                
                // Determine urgency
                $urgency = 'low';
                if ($sub->payment_status === 'Processing' || ($sub->status === 'Pending' && $ageMinutes > 10)) {
                    $urgency = 'medium';
                }
                if ($sub->status === 'Failed' && $ageMinutes < 1440) { // Less than 24h
                    $urgency = 'high';
                }
                if ($sub->status === 'Cancelled' && $sub->cancelled_reason && str_contains($sub->cancelled_reason, 'new subscription')) {
                    $urgency = 'high';
                }

                return [
                    'id' => $sub->id,
                    'plan_name' => $sub->plan->name ?? 'Unknown Plan',
                    'plan_id' => $sub->plan_id,
                    'status' => $sub->status,
                    'payment_status' => $sub->payment_status,
                    'amount' => $sub->amount_paid,
                    'currency' => $sub->currency,
                    'has_tracking_id' => $canFix,
                    'tracking_id' => $sub->pesapal_tracking_id,
                    'merchant_reference' => $sub->pesapal_merchant_reference,
                    'payment_url' => $sub->payment_url,
                    'can_fix' => $canFix,
                    'urgency' => $urgency,
                    'age_minutes' => (int) $ageMinutes,
                    'age_text' => $this->formatAge($ageMinutes),
                    'created_at' => $sub->created_at?->toIso8601String(),
                    'failed_at' => $sub->failed_at?->toIso8601String(),
                    'cancelled_reason' => $sub->cancelled_reason,
                    'transactions_count' => $sub->transactions->count(),
                    'last_transaction_status' => $sub->transactions->sortByDesc('created_at')->first()?->status,
                ];
            });

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => $fixableList->isEmpty() 
                    ? 'No subscriptions need fixing - all payments are healthy!' 
                    : $fixableList->count() . ' subscription(s) may need attention',
                'data' => [
                    'fixable_count' => $fixableList->count(),
                    'subscriptions' => $fixableList->values()->toArray(),
                    'auto_fix_available' => $fixableList->where('can_fix', true)->count() > 0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('V2 SubscriptionFix: fixable() failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Failed to check fixable subscriptions',
                'data' => null,
            ], 500);
        }
    }

    /**
     * POST /api/v2/subscriptions/auto-fix
     * 
     * Automatically checks ALL pending/failed subscriptions against Pesapal
     * and activates any that have been paid. This is the "magic fix" endpoint.
     * 
     * Called automatically when user opens the app (after 10 minutes)
     * and manually from the Fix Payments screen.
     */
    public function autoFix(Request $request)
    {
        try {
            $user = $request->user() ?? Utils::get_user($request);

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            // Rate limit: max 1 auto-fix per 2 minutes per user
            $rateLimitKey = "subscription_autofix_{$user->id}";
            if (Cache::has($rateLimitKey)) {
                $retryAfter = Cache::get($rateLimitKey);
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => 'Auto-fix was recently run. Please wait a moment.',
                    'data' => [
                        'fixed_count' => 0,
                        'checked_count' => 0,
                        'results' => [],
                        'rate_limited' => true,
                        'retry_after_seconds' => max(0, $retryAfter - time()),
                    ],
                ]);
            }
            Cache::put($rateLimitKey, time() + 120, 120); // 2 minute cooldown

            Log::info('🔧 V2 SubscriptionFix: auto-fix started', [
                'user_id' => $user->id,
            ]);

            // Get all subscriptions that might need fixing
            $candidates = $user->subscriptions()
                ->whereIn('status', ['Pending', 'Failed', 'Cancelled'])
                ->whereNotNull('pesapal_tracking_id')
                ->where('created_at', '>', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Also check Processing payment_status on Active subs
            $processingActive = $user->subscriptions()
                ->where('status', 'Active')
                ->whereIn('payment_status', ['Pending', 'Processing'])
                ->whereNotNull('pesapal_tracking_id')
                ->where('created_at', '>', now()->subDays(30))
                ->get();

            $allCandidates = $candidates->merge($processingActive)->unique('id');

            $results = [];
            $fixedCount = 0;
            $checkedCount = 0;

            foreach ($allCandidates as $subscription) {
                $checkedCount++;
                $result = $this->fixSingleSubscription($subscription);
                $results[] = $result;

                if ($result['fixed']) {
                    $fixedCount++;
                }

                // Small delay to avoid hammering Pesapal
                if ($checkedCount < $allCandidates->count()) {
                    usleep(500000); // 500ms
                }
            }

            $message = match (true) {
                $fixedCount > 0 => "🎉 Fixed {$fixedCount} subscription(s)! Your payment has been confirmed.",
                $checkedCount > 0 => "All {$checkedCount} subscription(s) checked - no fixes needed at this time.",
                default => 'No subscriptions need fixing right now.',
            };

            Log::info('✅ V2 SubscriptionFix: auto-fix completed', [
                'user_id' => $user->id,
                'checked' => $checkedCount,
                'fixed' => $fixedCount,
            ]);

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => $message,
                'data' => [
                    'fixed_count' => $fixedCount,
                    'checked_count' => $checkedCount,
                    'results' => $results,
                    'rate_limited' => false,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('💥 V2 SubscriptionFix: auto-fix failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Auto-fix encountered an error. Please try again.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * POST /api/v2/subscriptions/{id}/force-check
     * 
     * Force recheck a SPECIFIC subscription against Pesapal.
     * Works even for Failed/Cancelled subscriptions.
     * This is the "I definitely paid but it still shows pending" fix.
     */
    public function forceCheck(Request $request, $id)
    {
        try {
            $user = $request->user() ?? Utils::get_user($request);

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = $this->resolveUserActionableSubscription($user, (int) $id);

            if (!$subscription) {
                return response()->json([
                    'code' => 0,
                    'status' => 404,
                    'message' => 'Subscription not found',
                    'data' => null,
                ], 404);
            }

            // Must have tracking ID
            if (empty($subscription->pesapal_tracking_id)) {
                return response()->json([
                    'code' => 0,
                    'status' => 400,
                    'message' => 'This subscription has no payment tracking ID. Payment was never initiated.',
                    'data' => [
                        'suggestion' => 'Try creating a new subscription instead.',
                    ],
                ], 400);
            }

            // Already active and paid? No need to fix
            if ($subscription->status === 'Active' && $subscription->payment_status === 'Completed') {
                return response()->json([
                    'code' => 1,
                    'status' => 200,
                    'message' => '✅ This subscription is already active and paid!',
                    'data' => [
                        'subscription' => $subscription->toApiArray(),
                        'already_active' => true,
                        'fixed' => false,
                    ],
                ]);
            }

            Log::info('🔍 V2 SubscriptionFix: force-check started', [
                'subscription_id' => $id,
                'user_id' => $user->id,
                'current_status' => $subscription->status,
                'current_payment_status' => $subscription->payment_status,
            ]);

            // Force check with Pesapal (bypasses idempotency)
            $result = $this->fixSingleSubscription($subscription, true);

            $subscription->refresh();
            $subscription->load(['plan', 'transactions']);

            $message = $result['fixed']
                ? "🎉 Payment confirmed! Your subscription is now active."
                : "Payment status: {$result['pesapal_status']}. {$result['message']}";

            return response()->json([
                'code' => $result['fixed'] ? 1 : 0,
                'status' => 200,
                'message' => $message,
                'data' => [
                    'subscription' => $subscription->toApiArray(),
                    'fixed' => $result['fixed'],
                    'pesapal_status' => $result['pesapal_status'],
                    'pesapal_status_code' => $result['pesapal_status_code'],
                    'diagnostic' => $result,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('💥 V2 SubscriptionFix: force-check failed', [
                'subscription_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Force check failed: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * GET /api/v2/subscriptions/{id}/diagnostic
     * 
     * Full diagnostic of a payment: local status vs Pesapal status.
     * Shows everything the user needs to understand their payment situation.
     */
    public function diagnostic(Request $request, $id)
    {
        try {
            $user = $request->user() ?? Utils::get_user($request);

            if (!$user) {
                return response()->json([
                    'code' => 0,
                    'status' => 401,
                    'message' => 'Authentication required',
                    'data' => null,
                ], 401);
            }

            $subscription = Subscription::with(['plan', 'transactions'])->find($id);

            if (!$subscription || $subscription->user_id !== $user->id) {
                return response()->json([
                    'code' => 0,
                    'status' => 404,
                    'message' => 'Subscription not found',
                    'data' => null,
                ], 404);
            }

            // Local status
            $localDiagnostic = [
                'subscription_id' => $subscription->id,
                'local_status' => $subscription->status,
                'local_payment_status' => $subscription->payment_status,
                'has_tracking_id' => !empty($subscription->pesapal_tracking_id),
                'tracking_id' => $subscription->pesapal_tracking_id,
                'merchant_reference' => $subscription->pesapal_merchant_reference,
                'amount' => $subscription->amount_paid,
                'currency' => $subscription->currency,
                'plan_name' => $subscription->plan->name ?? 'Unknown',
                'plan_duration' => $subscription->days . ' days',
                'created_at' => $subscription->created_at?->toIso8601String(),
                'age_minutes' => (int) now()->diffInMinutes($subscription->created_at),
                'start_date' => $subscription->start_date_time?->toIso8601String(),
                'end_date' => $subscription->end_date_time?->toIso8601String(),
                'payment_confirmed_at' => $subscription->payment_confirmed_at?->toIso8601String(),
                'failed_at' => $subscription->failed_at?->toIso8601String(),
                'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
                'cancelled_reason' => $subscription->cancelled_reason,
            ];

            // Pesapal status (live check)
            $pesapalDiagnostic = [
                'checked' => false,
                'pesapal_status' => null,
                'pesapal_status_code' => null,
                'pesapal_payment_method' => null,
                'pesapal_confirmation_code' => null,
                'pesapal_amount' => null,
                'error' => null,
            ];

            if (!empty($subscription->pesapal_tracking_id)) {
                try {
                    $statusResult = $this->pesapalService->getTransactionStatus(
                        $subscription->pesapal_tracking_id
                    );

                    if ($statusResult['success']) {
                        $data = $statusResult['data'];
                        $pesapalDiagnostic = [
                            'checked' => true,
                            'pesapal_status' => $data['payment_status_description'] ?? $data['status'] ?? 'Unknown',
                            'pesapal_status_code' => $data['status_code'] ?? $data['payment_status_code'] ?? null,
                            'pesapal_payment_method' => $data['payment_method'] ?? null,
                            'pesapal_confirmation_code' => $data['confirmation_code'] ?? null,
                            'pesapal_amount' => $data['amount'] ?? null,
                            'pesapal_currency' => $data['currency'] ?? null,
                            'error' => null,
                        ];
                    } else {
                        $pesapalDiagnostic['error'] = $statusResult['error'] ?? 'Failed to query Pesapal';
                    }
                } catch (\Exception $e) {
                    $pesapalDiagnostic['error'] = $e->getMessage();
                }
            }

            // Mismatch detection
            $mismatch = false;
            $mismatchReason = null;
            $canAutoFix = false;

            $pesapalStatusCode = is_numeric($pesapalDiagnostic['pesapal_status_code']) 
                ? (int)$pesapalDiagnostic['pesapal_status_code'] 
                : null;

            if ($pesapalDiagnostic['checked']) {
                // Pesapal says PAID but local says not active
                if ($pesapalStatusCode === 1 && $subscription->status !== 'Active') {
                    $mismatch = true;
                    $mismatchReason = 'Pesapal confirms PAID but subscription is not Active. Use force-check to fix.';
                    $canAutoFix = true;
                }
                // Pesapal says FAILED but local still pending
                elseif ($pesapalStatusCode === 2 && $subscription->status === 'Pending') {
                    $mismatch = true;
                    $mismatchReason = 'Pesapal reports FAILED but subscription still shows Pending.';
                    $canAutoFix = true;
                }
                // Pesapal pending but local shows failed
                elseif ($pesapalStatusCode === 0 && $subscription->status === 'Failed') {
                    $mismatch = true;
                    $mismatchReason = 'Payment is still processing on Pesapal side. Wait a few minutes.';
                    $canAutoFix = false;
                }
            }

            // Build transactions log
            $transactionsLog = $subscription->transactions->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->transaction_type,
                    'status' => $tx->status,
                    'amount' => $tx->amount,
                    'payment_method' => $tx->payment_method,
                    'confirmation_code' => $tx->confirmation_code,
                    'error_message' => $tx->error_message,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            })->values()->toArray();

            return response()->json([
                'code' => 1,
                'status' => 200,
                'message' => 'Diagnostic complete',
                'data' => [
                    'local' => $localDiagnostic,
                    'pesapal' => $pesapalDiagnostic,
                    'mismatch' => [
                        'has_mismatch' => $mismatch,
                        'reason' => $mismatchReason,
                        'can_auto_fix' => $canAutoFix,
                    ],
                    'transactions' => $transactionsLog,
                    'recommendations' => $this->getRecommendations($subscription, $pesapalDiagnostic, $mismatch),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('V2 SubscriptionFix: diagnostic failed', [
                'subscription_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 0,
                'status' => 500,
                'message' => 'Diagnostic failed',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Fix a single subscription by querying Pesapal
     */
    private function fixSingleSubscription(Subscription $subscription, bool $force = false): array
    {
        $result = [
            'subscription_id' => $subscription->id,
            'previous_status' => $subscription->status,
            'previous_payment_status' => $subscription->payment_status,
            'fixed' => false,
            'pesapal_status' => null,
            'pesapal_status_code' => null,
            'message' => '',
            'error' => null,
        ];

        try {
            // Query Pesapal for actual payment status
            $statusResult = $this->pesapalService->getTransactionStatus(
                $subscription->pesapal_tracking_id
            );

            if (!$statusResult['success']) {
                $result['error'] = $statusResult['error'] ?? 'Failed to query Pesapal';
                $result['message'] = 'Could not reach Pesapal. Try again later.';
                return $result;
            }

            $data = $statusResult['data'];
            $pesapalStatus = $data['payment_status_description'] ?? $data['status'] ?? 'Unknown';
            $statusCode = $data['status_code'] ?? $data['payment_status_code'] ?? null;
            $statusCodeInt = is_numeric($statusCode) ? (int) $statusCode : null;

            $result['pesapal_status'] = $pesapalStatus;
            $result['pesapal_status_code'] = $statusCodeInt;

            // CASE 1: Pesapal says COMPLETED (status_code = 1)
            if ($statusCodeInt === 1 || strtolower($pesapalStatus) === 'completed') {
                // Only fix if not already Active+Completed
                if ($subscription->status !== 'Active' || $subscription->payment_status !== 'Completed') {
                    Log::info('🎉 V2 SubscriptionFix: ACTIVATING subscription!', [
                        'subscription_id' => $subscription->id,
                        'previous_status' => $subscription->status,
                    ]);

                    // Use DB transaction for safety
                    DB::transaction(function () use ($subscription, $data) {
                        $locked = Subscription::lockForUpdate()->find($subscription->id);
                        if (!$locked) return;

                        // Don't re-activate if already Active+Completed (race condition check)
                        if ($locked->status === 'Active' && $locked->payment_status === 'Completed') {
                            return;
                        }

                        $locked->status = 'Active';
                        $locked->payment_status = 'Completed';
                        
                        if (!$locked->start_date_time) {
                            $locked->start_date_time = now();
                        }
                        
                        if (!$locked->end_date_time) {
                            $start = $locked->start_date_time ?? now();
                            $locked->end_date_time = \Carbon\Carbon::parse($start)->addHours(((int) $locked->days) * 24);
                        }

                        if (!$locked->payment_confirmed_at) {
                            $locked->payment_confirmed_at = now();
                        }

                        $locked->failed_at = null; // Clear any failed timestamp
                        $locked->cancelled_at = null; // Clear cancelled if was cancelled
                        $locked->cancelled_reason = null;
                        
                        $locked->pesapal_response = array_merge(
                            $locked->pesapal_response ?? [],
                            ['v2_auto_fix' => $data, 'fixed_at' => now()->toIso8601String()]
                        );

                        $locked->save();

                        // Update/create transaction record
                        $transaction = SubscriptionTransaction::where('subscription_id', $locked->id)
                            ->where('pesapal_tracking_id', $locked->pesapal_tracking_id)
                            ->first();

                        if ($transaction) {
                            $transaction->status = 'Completed';
                            $transaction->payment_method = $data['payment_method'] ?? $transaction->payment_method;
                            $transaction->confirmation_code = $data['confirmation_code'] ?? $transaction->confirmation_code;
                            $transaction->payment_account = $data['payment_account'] ?? $data['account_number'] ?? null;
                            $transaction->error_message = null;
                            $transaction->response_payload = array_merge(
                                $transaction->response_payload ?? [],
                                ['v2_fix' => $data]
                            );
                            $transaction->save();
                        } else {
                            SubscriptionTransaction::create([
                                'subscription_id' => $locked->id,
                                'user_id' => $locked->user_id,
                                'transaction_type' => 'Initial',
                                'amount' => $locked->amount_paid,
                                'currency' => $locked->currency,
                                'status' => 'Completed',
                                'pesapal_tracking_id' => $locked->pesapal_tracking_id,
                                'merchant_reference' => $locked->pesapal_merchant_reference,
                                'payment_method' => $data['payment_method'] ?? null,
                                'confirmation_code' => $data['confirmation_code'] ?? null,
                                'payment_account' => $data['payment_account'] ?? $data['account_number'] ?? null,
                                'response_payload' => ['v2_fix' => $data],
                            ]);
                        }
                    });

                    $result['fixed'] = true;
                    $result['message'] = 'Payment confirmed! Subscription activated.';
                    $result['new_status'] = 'Active';
                    $result['new_payment_status'] = 'Completed';
                } else {
                    $result['message'] = 'Already active and paid.';
                    $result['new_status'] = 'Active';
                    $result['new_payment_status'] = 'Completed';
                }
            }
            // CASE 2: Pesapal says FAILED (status_code = 2)
            elseif ($statusCodeInt === 2 || in_array(strtolower($pesapalStatus), ['failed', 'invalid'])) {
                // Update local to Failed if not already
                if ($subscription->status !== 'Failed') {
                    $subscription->status = 'Failed';
                    $subscription->payment_status = 'Failed';
                    $subscription->failed_at = now();
                    $subscription->pesapal_response = array_merge(
                        $subscription->pesapal_response ?? [],
                        ['v2_fix_check' => $data]
                    );
                    $subscription->save();
                }

                $result['message'] = 'Payment was not completed on Pesapal. You can retry or create a new subscription.';
                $result['new_status'] = 'Failed';
                $result['new_payment_status'] = 'Failed';
            }
            // CASE 3: Pesapal says PENDING (status_code = 0 or other)
            else {
                $result['message'] = 'Payment is still being processed by Pesapal. Please wait a few more minutes and try again.';
                $result['new_status'] = $subscription->status;
                $result['new_payment_status'] = 'Processing';

                // Update to Processing if still Pending
                if ($subscription->payment_status === 'Pending') {
                    $subscription->payment_status = 'Processing';
                    $subscription->save();
                }
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['message'] = 'Error checking payment: ' . $e->getMessage();

            Log::error('V2 SubscriptionFix: fixSingle failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Generate smart recommendations based on diagnostic results
     */
    private function getRecommendations(Subscription $subscription, array $pesapal, bool $mismatch): array
    {
        $recs = [];

        if ($mismatch && ($pesapal['pesapal_status_code'] ?? null) == 1) {
            $recs[] = [
                'action' => 'force_check',
                'icon' => '🔧',
                'title' => 'Fix This Payment',
                'description' => 'Pesapal has confirmed your payment. Tap to activate your subscription now.',
                'priority' => 1,
            ];
        }

        if ($subscription->status === 'Pending' && !empty($subscription->payment_url)) {
            $recs[] = [
                'action' => 'complete_payment',
                'icon' => '💳',
                'title' => 'Complete Payment',
                'description' => 'Open the payment page to complete your transaction.',
                'priority' => 2,
                'url' => $subscription->payment_url,
            ];
        }

        if (in_array($subscription->status, ['Failed', 'Cancelled'])) {
            $recs[] = [
                'action' => 'retry_payment',
                'icon' => '🔄',
                'title' => 'Retry Payment',
                'description' => 'Start a new payment attempt for this subscription.',
                'priority' => 3,
            ];
        }

        if (empty($subscription->pesapal_tracking_id)) {
            $recs[] = [
                'action' => 'new_subscription',
                'icon' => '➕',
                'title' => 'Create New Subscription',
                'description' => 'This payment was never initiated. Start fresh with a new subscription.',
                'priority' => 4,
            ];
        }

        $recs[] = [
            'action' => 'contact_support',
            'icon' => '💬',
            'title' => 'Contact Support',
            'description' => 'If the issue persists, our support team can help resolve it.',
            'priority' => 5,
        ];

        // Sort by priority
        usort($recs, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $recs;
    }

    /**
     * Format age in human-readable text
     */
    private function formatAge(float $minutes): string
    {
        if ($minutes < 1) return 'Just now';
        if ($minutes < 60) return (int) $minutes . ' min ago';
        if ($minutes < 1440) return (int) ($minutes / 60) . ' hours ago';
        return (int) ($minutes / 1440) . ' days ago';
    }

    /**
     * Resolve action subscription for current user and avoid hard failures on stale IDs.
     */
    private function resolveUserActionableSubscription($user, int $hintId): ?Subscription
    {
        $requested = Subscription::with(['plan', 'transactions'])->find($hintId);

        if ($requested && (int) $requested->user_id === (int) $user->id) {
            return $requested;
        }

        return $user->subscriptions()
            ->with(['plan', 'transactions'])
            ->whereIn('status', ['Pending', 'Failed', 'Cancelled', 'Processing'])
            ->orderByDesc('created_at')
            ->first();
    }
}
