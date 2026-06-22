<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // Only user with id=1 is admin
    private function checkAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user() ?? Utils::get_user($request);

        if (!$user) {
            return response()->json(['code' => 0, 'message' => 'Authentication required'], 401);
        }

        if ((int) $user->id !== 1) {
            return response()->json(['code' => 0, 'message' => 'Admin access required'], 403);
        }

        return null;
    }

    /**
     * GET /api/v2/admin/stats
     * Dashboard stats for the admin panel.
     */
    public function stats(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $totalUsers       = User::count();
        $totalSubs        = Subscription::count();
        $activeSubs       = Subscription::where('status', 'Active')->count();
        $pendingSubs      = Subscription::where('status', 'Pending')->count();
        $failedSubs       = Subscription::where('status', 'Failed')->count();

        $revenueToday     = Subscription::where('status', 'Active')
            ->whereDate('payment_confirmed_at', today())
            ->sum('amount_paid');

        $revenueMonth     = Subscription::where('status', 'Active')
            ->whereYear('payment_confirmed_at', now()->year)
            ->whereMonth('payment_confirmed_at', now()->month)
            ->sum('amount_paid');

        $newUsersToday    = User::whereDate('created_at', today())->count();
        $newUsersMonth    = User::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => [
                'total_users'     => $totalUsers,
                'new_users_today' => $newUsersToday,
                'new_users_month' => $newUsersMonth,
                'total_subs'      => $totalSubs,
                'active_subs'     => $activeSubs,
                'pending_subs'    => $pendingSubs,
                'failed_subs'     => $failedSubs,
                'revenue_today'   => (float) $revenueToday,
                'revenue_month'   => (float) $revenueMonth,
            ],
        ]);
    }

    /**
     * GET /api/v2/admin/users/search?q=...
     * Search users by ID, email, name, or phone.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $q = trim((string) $request->input('q', ''));

        if (strlen($q) < 1) {
            return response()->json(['code' => 0, 'message' => 'Enter a search term'], 400);
        }

        $users = User::select('id', 'name', 'first_name', 'last_name', 'email', 'phone_number', 'created_at', 'status')
            ->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('id', (int) $q);
                } else {
                    $query->where('email', 'like', "%$q%")
                        ->orWhere('name', 'like', "%$q%")
                        ->orWhere('first_name', 'like', "%$q%")
                        ->orWhere('last_name', 'like', "%$q%")
                        ->orWhere('phone_number', 'like', "%$q%")
                        ->orWhere('username', 'like', "%$q%");
                }
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => $users->map(fn($u) => $this->formatUser($u)),
        ]);
    }

    /**
     * GET /api/v2/admin/users/{userId}/subscriptions
     * Get all subscriptions for a specific user.
     */
    public function getUserSubscriptions(Request $request, int $userId): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $user = User::select('id', 'name', 'first_name', 'last_name', 'email', 'phone_number', 'created_at', 'status')
            ->find($userId);

        if (!$user) {
            return response()->json(['code' => 0, 'message' => 'User not found'], 404);
        }

        $subs = Subscription::with('plan')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => [
                'user'          => $this->formatUser($user),
                'subscriptions' => $subs->map(fn($s) => $this->formatSubscription($s)),
            ],
        ]);
    }

    /**
     * POST /api/v2/admin/subscriptions/{subId}/activate
     * Force-activate a subscription regardless of payment state.
     */
    public function activateSubscription(Request $request, int $subId): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $sub = Subscription::find($subId);
        if (!$sub) {
            return response()->json(['code' => 0, 'message' => 'Subscription not found'], 404);
        }

        DB::transaction(function () use ($sub) {
            $days  = $sub->days ?? ($sub->plan ? ($sub->plan->days ?? 30) : 30);
            $start = now();
            $end   = now()->addDays($days);

            $sub->status             = 'Active';
            $sub->payment_status     = 'Completed';
            $sub->start_date_time    = $start;
            $sub->end_date_time      = $end;
            $sub->grace_period_end   = $end->copy()->addDays(3);
            $sub->payment_confirmed_at = now();
            $sub->cancelled_at       = null;
            $sub->cancelled_reason   = null;
            $sub->failed_at          = null;
            $sub->save();
        });

        return response()->json([
            'code'    => 1,
            'message' => 'Subscription activated successfully.',
            'data'    => $this->formatSubscription($sub->fresh()),
        ]);
    }

    /**
     * POST /api/v2/admin/subscriptions/{subId}/cancel
     * Cancel a subscription.
     */
    public function cancelSubscription(Request $request, int $subId): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $sub = Subscription::find($subId);
        if (!$sub) {
            return response()->json(['code' => 0, 'message' => 'Subscription not found'], 404);
        }

        $reason = $request->input('reason', 'Cancelled by admin');

        $sub->status            = 'Cancelled';
        $sub->cancelled_at      = now();
        $sub->cancelled_reason  = $reason;
        $sub->cancelled_by      = 'admin';
        $sub->save();

        return response()->json([
            'code'    => 1,
            'message' => 'Subscription cancelled.',
            'data'    => $this->formatSubscription($sub->fresh()),
        ]);
    }

    /**
     * GET /api/v2/admin/recent-subscriptions
     * Latest 30 subscriptions across all users.
     */
    public function recentSubscriptions(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $subs = Subscription::with(['plan', 'user' => fn($q) => $q->select('id', 'name', 'first_name', 'last_name', 'email', 'phone_number')])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => $subs->map(function ($s) {
                $arr = $this->formatSubscription($s);
                if ($s->user) {
                    $arr['user'] = $this->formatUser($s->user);
                }
                return $arr;
            }),
        ]);
    }

    /**
     * GET /api/v2/admin/plans
     * List all active subscription plans for admin UI.
     */
    public function listPlans(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $plans = SubscriptionPlan::where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get(['id', 'name', 'price', 'discount_percentage', 'duration_days', 'currency', 'is_featured', 'is_popular', 'is_trial']);

        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => $plans->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'duration_days'=> $p->duration_days,
                'currency'     => $p->currency ?? 'UGX',
                'price'        => (float) $p->price,
                'actual_price' => (float) $p->getActualPrice(),
                'is_featured'  => (bool) $p->is_featured,
                'is_popular'   => (bool) $p->is_popular,
                'is_trial'     => (bool) $p->is_trial,
            ]),
        ]);
    }

    /**
     * POST /api/v2/admin/users/{userId}/subscriptions/create
     * Create a new subscription for a user.
     *
     * Body:
     *   plan_id      int     required
     *   is_paid      bool    optional (default false) — if true, activates immediately
     *   payment_method string optional (default "cash")
     *   notes        string  optional
     */
    public function createUserSubscription(Request $request, int $userId): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['code' => 0, 'message' => 'User not found'], 404);
        }

        $planId = (int) $request->input('plan_id', 0);
        if ($planId <= 0) {
            return response()->json(['code' => 0, 'message' => 'plan_id is required'], 422);
        }

        $plan = SubscriptionPlan::find($planId);
        if (!$plan || $plan->status !== 'Active') {
            return response()->json(['code' => 0, 'message' => 'Plan not found or inactive'], 404);
        }

        $isPaid        = filter_var($request->input('is_paid', false), FILTER_VALIDATE_BOOLEAN);
        $paymentMethod = trim((string) $request->input('payment_method', 'cash')) ?: 'cash';
        $notes         = trim((string) $request->input('notes', ''));
        $actualPrice   = $plan->getActualPrice();
        $days          = (int) $plan->duration_days;

        $sub = DB::transaction(function () use ($user, $plan, $isPaid, $paymentMethod, $notes, $actualPrice, $days) {
            $start = now();
            $end   = $start->copy()->addDays($days);

            $sub = new Subscription();
            $sub->user_id           = $user->id;
            $sub->plan_id           = $plan->id;
            $sub->days              = $days;
            $sub->currency          = $plan->currency ?? 'UGX';
            $sub->amount_paid       = $actualPrice;
            $sub->payment_method    = $paymentMethod;
            $sub->payment_gateway   = 'admin';
            $sub->source            = 'admin_panel';
            $sub->notes             = $notes ?: null;

            if ($isPaid) {
                $sub->status               = 'Active';
                $sub->payment_status       = 'Completed';
                $sub->start_date_time      = $start;
                $sub->end_date_time        = $end;
                $sub->grace_period_end     = $end->copy()->addDays(3);
                $sub->payment_confirmed_at = now();
            } else {
                $sub->status         = 'Pending';
                $sub->payment_status = 'Pending';
            }

            $sub->save();
            return $sub;
        });

        return response()->json([
            'code'    => 1,
            'message' => $isPaid
                ? "Subscription created and activated for {$user->name}."
                : "Subscription created (pending payment) for {$user->name}.",
            'data'    => $this->formatSubscription($sub->fresh()->load('plan')),
        ]);
    }

    /**
     * GET /api/v2/admin/system-status
     * Get current system config flags.
     */
    public function systemStatus(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $config = SystemConfig::instance();

        return response()->json([
            'code'    => 1,
            'message' => 'ok',
            'data'    => [
                'ios_review_mode'     => (bool) $config->ios_review_mode,
                'maintenance_mode'    => (bool) $config->maintenance_mode,
                'maintenance_message' => $config->maintenance_message ?? '',
                'min_android_version' => $config->min_android_version ?? '',
                'min_ios_version'     => $config->min_ios_version ?? '',
            ],
        ]);
    }

    /**
     * POST /api/v2/admin/system/toggle-ios-review
     */
    public function toggleIosReview(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $config = SystemConfig::instance();
        $config->ios_review_mode = !$config->ios_review_mode;
        $config->save();
        Cache::forget('system_config');

        return response()->json([
            'code'    => 1,
            'message' => $config->ios_review_mode ? 'iOS review mode ENABLED' : 'iOS review mode DISABLED',
            'data'    => ['ios_review_mode' => (bool) $config->ios_review_mode],
        ]);
    }

    /**
     * POST /api/v2/admin/system/toggle-maintenance
     */
    public function toggleMaintenance(Request $request): JsonResponse
    {
        if ($err = $this->checkAdmin($request)) return $err;

        $config = SystemConfig::instance();
        $config->maintenance_mode = !$config->maintenance_mode;
        $config->save();
        Cache::forget('system_config');

        return response()->json([
            'code'    => 1,
            'message' => $config->maintenance_mode ? 'Maintenance mode ENABLED' : 'Maintenance mode DISABLED',
            'data'    => ['maintenance_mode' => (bool) $config->maintenance_mode],
        ]);
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function formatUser(User $u): array
    {
        $name = trim("{$u->first_name} {$u->last_name}");
        if ($name === '') $name = $u->name ?? "User #{$u->id}";

        return [
            'id'           => $u->id,
            'name'         => $name,
            'email'        => $u->email ?? '',
            'phone_number' => $u->phone_number ?? '',
            'status'       => $u->status ?? '',
            'created_at'   => (string) $u->created_at,
        ];
    }

    private function formatSubscription(Subscription $s): array
    {
        return [
            'id'                   => $s->id,
            'user_id'              => $s->user_id,
            'plan_name'            => $s->plan ? $s->plan->name : 'Unknown',
            'plan_days'            => $s->plan ? ($s->plan->days ?? 0) : 0,
            'status'               => $s->status ?? '',
            'payment_status'       => $s->payment_status ?? '',
            'amount'               => (float) ($s->amount_paid ?? 0),
            'currency'             => $s->currency ?? 'UGX',
            'payment_method'       => $s->payment_method ?? '',
            'start_date_time'      => (string) $s->start_date_time,
            'end_date_time'        => (string) $s->end_date_time,
            'payment_confirmed_at' => (string) $s->payment_confirmed_at,
            'created_at'           => (string) $s->created_at,
            'days'                 => $s->days ?? 0,
            'pesapal_tracking_id'  => $s->pesapal_tracking_id ?? '',
            'flutterwave_reference'=> $s->flutterwave_reference ?? '',
            'cancelled_reason'     => $s->cancelled_reason ?? '',
        ];
    }
}
