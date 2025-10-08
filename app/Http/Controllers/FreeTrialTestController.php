<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Traits\ApiResponser;

class FreeTrialTestController extends Controller
{
    use ApiResponser;

    /**
     * Test the free trial system
     * GET /api/test-free-trial/{user_id}
     */
    public function testFreeTrial(Request $request, $userId = null)
    {
        try {
            // Use provided user ID or get from request
            if (!$userId) {
                $userId = $request->get('user_id', 1); // Default to user ID 1
            }

            $user = User::find($userId);
            if (!$user) {
                return $this->error("User with ID {$userId} not found", 404);
            }

            // Test the free trial assignment
            $result = $user->giveFreeSubscription();

            // Get updated subscription status
            $subscriptionStatus = $user->getSubscriptionStatus();

            return response()->json([
                'success' => true,
                'message' => 'Free trial test completed',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'free_trial_result' => $result,
                    'subscription_status' => $subscriptionStatus,
                    'is_eligible_for_trial' => $user->isEligibleForFreeTrial(),
                    'has_active_subscription' => $user->hasActiveSubscription(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Free trial test failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Free trial test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test auto-assignment (simulates what happens in real endpoints)
     * GET /api/test-auto-assignment/{user_id}
     */
    public function testAutoAssignment(Request $request, $userId = null)
    {
        try {
            if (!$userId) {
                $userId = $request->get('user_id', 1);
            }

            $user = User::find($userId);
            if (!$user) {
                return $this->error("User with ID {$userId} not found", 404);
            }

            // Test the auto-assignment method (safe wrapper)
            $result = $user->autoAssignFreeTrial();

            return response()->json([
                'success' => true,
                'message' => 'Auto-assignment test completed',
                'data' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'auto_assignment_result' => $result,
                    'subscription_status' => $user->getSubscriptionStatus(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->error('Auto-assignment test failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get free trial plan details
     * GET /api/test-free-trial-plan
     */
    public function getFreeTrialPlan()
    {
        try {
            $freeTrialPlan = SubscriptionPlan::where('slug', 'free-trial-15-days')
                ->orWhere('name', 'Free Trial')
                ->first();

            if (!$freeTrialPlan) {
                return $this->error('Free trial plan not found. Please run: php artisan db:seed --class=FreeTrialPlanSeeder', 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Free trial plan found',
                'data' => [
                    'plan' => $freeTrialPlan->toArray(),
                    'all_plans_count' => SubscriptionPlan::count(),
                    'active_plans_count' => SubscriptionPlan::where('status', 'Active')->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->error('Failed to get free trial plan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get statistics about free trial usage
     * GET /api/test-free-trial-stats
     */
    public function getFreeTrialStats()
    {
        try {
            $freeTrialPlan = SubscriptionPlan::where('slug', 'free-trial-15-days')->first();
            
            if (!$freeTrialPlan) {
                return $this->error('Free trial plan not found', 404);
            }

            $stats = [
                'plan_id' => $freeTrialPlan->id,
                'plan_name' => $freeTrialPlan->name,
                'total_subscriptions' => Subscription::where('plan_id', $freeTrialPlan->id)->count(),
                'active_subscriptions' => Subscription::where('plan_id', $freeTrialPlan->id)->where('status', 'Active')->count(),
                'expired_subscriptions' => Subscription::where('plan_id', $freeTrialPlan->id)->where('status', 'Expired')->count(),
                'recent_subscriptions' => Subscription::where('plan_id', $freeTrialPlan->id)->where('created_at', '>=', now()->subDays(7))->count(),
                'users_with_free_trial' => User::whereHas('subscriptions', function($query) use ($freeTrialPlan) {
                    $query->where('plan_id', $freeTrialPlan->id);
                })->count(),
                'users_eligible_for_trial' => User::whereDoesntHave('subscriptions', function($query) {
                    $query->whereIn('status', ['Active', 'Expired', 'Completed'])
                          ->whereIn('payment_status', ['Completed', 'Free']);
                })->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Free trial statistics',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->error('Failed to get free trial stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clean up test data (remove free trial subscriptions for testing)
     * DELETE /api/test-free-trial-cleanup/{user_id}
     */
    public function cleanupTestData(Request $request, $userId = null)
    {
        try {
            if (!$userId) {
                $userId = $request->get('user_id', 1);
            }

            $user = User::find($userId);
            if (!$user) {
                return $this->error("User with ID {$userId} not found", 404);
            }

            $freeTrialPlan = SubscriptionPlan::where('slug', 'free-trial-15-days')->first();
            if (!$freeTrialPlan) {
                return $this->error('Free trial plan not found', 404);
            }

            // Delete user's free trial subscriptions (for testing only)
            $deletedCount = Subscription::where('user_id', $user->id)
                ->where('plan_id', $freeTrialPlan->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deletedCount} free trial subscriptions for user {$userId}",
                'data' => [
                    'user_id' => $userId,
                    'deleted_subscriptions' => $deletedCount,
                    'user_can_now_get_trial' => $user->isEligibleForFreeTrial(),
                ]
            ]);

        } catch (\Exception $e) {
            return $this->error('Cleanup failed: ' . $e->getMessage(), 500);
        }
    }
}