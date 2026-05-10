<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionActivationService
{
    /**
     * Centralized, idempotent activation for successful paid subscriptions.
     *
     * Rules:
     * - If this subscription is already Active+Completed with valid dates, keep dates (idempotent).
     * - Otherwise, start from the latest paid active end-date when present (day stacking).
     * - Always compute duration in hours (days * 24) to avoid date drift.
     */
    public function activatePaidSubscription(Subscription $subscription, string $source = 'unknown', array $options = []): Subscription
    {
        $result = $this->activatePaidSubscriptionWithAudit($subscription, $source, $options);

        return $result['subscription'];
    }

    /**
     * Same as activatePaidSubscription, but also returns a structured audit payload.
     *
     * @return array{subscription: Subscription, audit: array<string, mixed>}
     */
    public function activatePaidSubscriptionWithAudit(Subscription $subscription, string $source = 'unknown', array $options = []): array
    {
        return DB::transaction(function () use ($subscription, $source, $options) {
            $locked = Subscription::with('plan')->lockForUpdate()->findOrFail($subscription->id);
            $now = now();
            $previousStart = $locked->start_date_time;
            $previousEnd = $locked->end_date_time;
            $previousStatus = $locked->status;
            $previousPaymentStatus = $locked->payment_status;

            $days = $this->resolveDurationDays($locked);
            $alreadyActiveCompleted =
                $locked->status === 'Active'
                && $locked->payment_status === 'Completed'
                && $locked->start_date_time !== null
                && $locked->end_date_time !== null;

            $forceNow = (bool) ($options['force_start_now'] ?? false);
            $anchorMeta = $this->resolveStartAnchorMeta($locked, $now, $forceNow);
            $anchor = $anchorMeta['anchor'];

            $locked->status = 'Active';
            $locked->payment_status = 'Completed';
            $locked->days = $days;

            if (!$alreadyActiveCompleted) {
                $locked->start_date_time = $anchor;
                $locked->end_date_time = Carbon::parse($anchor)->addHours($days * 24);
            }

            if (!$locked->end_date_time || Carbon::parse($locked->end_date_time)->lte($locked->start_date_time ?: $now)) {
                $baseStart = $locked->start_date_time ?: $anchor;
                $locked->start_date_time = $baseStart;
                $locked->end_date_time = Carbon::parse($baseStart)->addHours($days * 24);
            }

            $locked->grace_period_end = Carbon::parse($locked->end_date_time)->addDays(3);

            if (!$locked->payment_confirmed_at) {
                $locked->payment_confirmed_at = $now;
            }

            $locked->failed_at = null;
            $locked->payment_failure_reason = null;
            $locked->cancelled_at = null;
            $locked->cancelled_reason = null;
            $locked->payment_url = null;

            if ($locked->isDirty()) {
                $locked->save();
            }

            $this->forgetCaches((int) $locked->user_id);

            $audit = [
                'source' => $source,
                'anchor_source' => $anchorMeta['anchor_source'],
                'used_stacking' => !$alreadyActiveCompleted && $anchorMeta['anchor_source'] !== 'now',
                'already_active_completed' => $alreadyActiveCompleted,
                'duration_days' => $days,
                'previous_status' => $previousStatus,
                'previous_payment_status' => $previousPaymentStatus,
                'previous_start_date_time' => $previousStart?->toIso8601String(),
                'previous_end_date_time' => $previousEnd?->toIso8601String(),
                'activation_anchor' => $anchor->toIso8601String(),
                'resolved_latest_active_end' => $anchorMeta['resolved_latest_active_end'],
                'resolved_extended_parent_end' => $anchorMeta['resolved_extended_parent_end'],
                'new_start_date_time' => $locked->start_date_time?->toIso8601String(),
                'new_end_date_time' => $locked->end_date_time?->toIso8601String(),
                'new_grace_period_end' => $locked->grace_period_end?->toIso8601String(),
            ];

            Log::info('SubscriptionActivationService: activation normalized', [
                'subscription_id' => $locked->id,
                'user_id' => $locked->user_id,
                'source' => $source,
                'days' => $days,
                'start_date_time' => $locked->start_date_time,
                'end_date_time' => $locked->end_date_time,
                'grace_period_end' => $locked->grace_period_end,
                'is_extension' => (bool) $locked->is_extension,
                'extended_from_id' => $locked->extended_from_id,
                'audit' => $audit,
            ]);

            return [
                'subscription' => $locked->fresh(['plan']),
                'audit' => $audit,
            ];
        });
    }

    private function resolveDurationDays(Subscription $subscription): int
    {
        $days = max(0, (int) $subscription->days);

        if ($days <= 0) {
            $subscription->loadMissing('plan');
            $days = max(0, (int) ($subscription->plan->duration_days ?? 0));
        }

        return max(1, $days);
    }

    private function resolveStartAnchorMeta(Subscription $subscription, Carbon $now, bool $forceNow = false): array
    {
        if ($forceNow) {
            return [
                'anchor'                     => $now->copy(),
                'anchor_source'              => 'forced_now_batch_fix',
                'resolved_extended_parent_end' => null,
                'resolved_latest_active_end' => null,
            ];
        }

        $anchor = $now->copy();
        $anchorSource = 'now';
        $resolvedExtendedParentEnd = null;
        $resolvedLatestActiveEnd = null;

        if ($subscription->is_extension && $subscription->extended_from_id) {
            $parent = Subscription::lockForUpdate()->find($subscription->extended_from_id);
            if ($parent && $parent->end_date_time) {
                $parentEnd = Carbon::parse($parent->end_date_time);
                $resolvedExtendedParentEnd = $parentEnd->toIso8601String();
                if ($parentEnd->gt($anchor)) {
                    $anchor = $parentEnd;
                    $anchorSource = 'extended_parent_end';
                }
            }
        }

        $latestPaidActive = Subscription::query()
            ->where('user_id', $subscription->user_id)
            ->where('id', '!=', $subscription->id)
            ->where('status', 'Active')
            ->where('payment_status', 'Completed')
            ->whereNotNull('end_date_time')
            ->orderByDesc('end_date_time')
            ->lockForUpdate()
            ->first();

        if ($latestPaidActive && $latestPaidActive->end_date_time) {
            $latestEnd = Carbon::parse($latestPaidActive->end_date_time);
            $resolvedLatestActiveEnd = $latestEnd->toIso8601String();
            if ($latestEnd->gt($anchor)) {
                $anchor = $latestEnd;
                $anchorSource = 'latest_paid_active_end';
            }
        }

        return [
            'anchor' => $anchor,
            'anchor_source' => $anchorSource,
            'resolved_extended_parent_end' => $resolvedExtendedParentEnd,
            'resolved_latest_active_end' => $resolvedLatestActiveEnd,
        ];
    }

    private function forgetCaches(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        Cache::forget("sub_pending_{$userId}");
        Cache::forget("active_sub_{$userId}");
        Cache::forget("v2_pay_check_{$userId}");
        Cache::forget("v1_sub_info_{$userId}");
    }
}
