<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckSubscription Middleware
 * 
 * Ensures user has an active subscription before accessing protected routes
 * Can be applied to routes or controllers
 * 
 * Usage in routes:
 * Route::middleware(['auth', 'subscription'])->group(function () {
 *     Route::get('/movies', [MovieController::class, 'index']);
 * });
 * 
 * Usage in controller:
 * public function __construct() {
 *     $this->middleware('subscription');
 * }
 */
class CheckSubscription
{
    /**
     * Routes that should be excluded from subscription check
     * Even if middleware is applied globally
     * 
     * @var array
     */
    protected $excludedRoutes = [
        'api/auth/*',
        'api/subscription-plans',
        'api/subscriptions/create',
        'api/subscriptions/pesapal/*',
        'api/subscriptions/my-subscription',
        'api/subscriptions/check-status',
        'api/subscriptions/retry-payment',
        'api/me',
        'api/manifest',
        'api/random-movie', // Public endpoint
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $gracePeriod = 'true'): Response
    {
        // Skip check for excluded routes
        foreach ($this->excludedRoutes as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // Get authenticated user
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse('Authentication required');
        }

        // Allow grace period by default (can be disabled per route)
        $includeGracePeriod = $gracePeriod !== 'false';

        // Check if user has active subscription
        if (!$user->hasActiveSubscription($includeGracePeriod)) {
            Log::warning('Subscription required', [
                'user_id' => $user->id,
                'route' => $request->path(),
                'method' => $request->method(),
            ]);

            return $this->subscriptionRequiredResponse($user);
        }

        // Log subscription access for analytics (optional)
        if (config('subscription.log_access', false)) {
            Log::info('Subscription access granted', [
                'user_id' => $user->id,
                'route' => $request->path(),
                'days_remaining' => $user->subscriptionDaysRemaining(),
            ]);
        }

        return $next($request);
    }

    /**
     * Return unauthorized response
     * 
     * @param string $message
     * @return Response
     */
    protected function unauthorizedResponse($message = 'Authentication required')
    {
        return response()->json([
            'code' => 0,
            'status' => 401,
            'message' => $message,
            'data' => null,
        ], 401);
    }

    /**
     * Return subscription required response
     * 
     * @param \App\Models\User $user
     * @return Response
     */
    protected function subscriptionRequiredResponse($user)
    {
        $subscriptionStatus = $user->getSubscriptionStatus();
        
        $message = 'Active subscription required to access this content';
        
        // Provide more specific messages
        if ($user->isInSubscriptionGracePeriod()) {
            $message = 'Your subscription has expired but you are in a grace period. Please renew to continue accessing content.';
        } elseif ($user->hasPendingSubscription()) {
            $message = 'You have a pending subscription payment. Please complete the payment to activate your subscription.';
        } elseif ($subscriptionStatus['has_subscription'] && $subscriptionStatus['status'] === 'Expired') {
            $message = 'Your subscription has expired. Please renew to continue enjoying our content.';
        }

        return response()->json([
            'code' => 0,
            'status' => 403,
            'message' => $message,
            'data' => [
                'require_subscription' => true, // Frontend checks this flag
                'subscription_required' => true, // Backward compatibility
                'subscription_status' => $subscriptionStatus,
                'pending_subscription' => $user->hasPendingSubscription(),
                'action_url' => url('/api/subscription-plans'), // Frontend will redirect here
            ],
        ], 403);
    }
}
