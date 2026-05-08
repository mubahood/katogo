<?php

namespace App\Services;

use App\Models\MergedAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AccountMergeService
{
    private const AUTO_MERGE_DEADLINE = '2026-09-30 23:59:59';
    private const MIN_OLD_ACCOUNT_AGE_HOURS = 24;
    private const MAX_MERGES_PER_ACCOUNT = 6;

    /**
     * Merge is allowed up to and including 30 September 2026.
     */
    public function isAutoMergeWindowOpen(): bool
    {
        $tz = config('app.timezone', 'UTC');
        $deadline = Carbon::createFromFormat('Y-m-d H:i:s', self::AUTO_MERGE_DEADLINE, $tz);

        return Carbon::now($tz)->lessThanOrEqualTo($deadline);
    }

    public function getMaxMergesPerAccount(): int
    {
        return self::MAX_MERGES_PER_ACCOUNT;
    }

    public function getMergeStatsForUser(int $userId): array
    {
        $completedMerges = $this->countCompletedMergesForUser($userId);

        return [
            'user_id' => $userId,
            'completed_merges' => $completedMerges,
            'remaining_merges' => max(0, self::MAX_MERGES_PER_ACCOUNT - $completedMerges),
            'max_merges' => self::MAX_MERGES_PER_ACCOUNT,
            'limit_reached' => $completedMerges >= self::MAX_MERGES_PER_ACCOUNT,
        ];
    }

    /**
     * Centralized account merge entry point.
     *
     * Moves all known user-linked records from $sourceUser to $targetUser and
     * consolidates profile/counters into target.
     */
    public function mergeIntoExistingAccount(User $sourceUser, User $targetUser, array $context = []): User
    {
        if ((int) $sourceUser->id === (int) $targetUser->id) {
            return $targetUser->fresh() ?? $targetUser;
        }

        $sourceId = (int) $sourceUser->id;
        $targetId = (int) $targetUser->id;

        return DB::transaction(function () use ($sourceId, $targetId, $context) {
            /** @var User $source */
            $source = User::where('id', $sourceId)->lockForUpdate()->firstOrFail();
            /** @var User $target */
            $target = User::where('id', $targetId)->lockForUpdate()->firstOrFail();

            $this->ensureMergeLimitNotReached((int) $source->id, 'old');
            $this->ensureMergeLimitNotReached((int) $target->id, 'new');

            $this->moveUserLinkedRows($source->id, $target->id);
            $this->mergeScalarAccountFields($source, $target, $context);
            $this->mergeNumericCounters($source, $target);
            $this->syncAdminAccessMappingsBidirectional($source->id, $target->id);

            if (Schema::hasColumn('admin_users', 'account_state')) {
                $target->account_state = 'registered';
            }

            $target->save();

            // Keep source account for audit/token compatibility, but mark merged.
            if (Schema::hasColumn('admin_users', 'account_state')) {
                $source->account_state = 'merged';
            }
            if (Schema::hasColumn('admin_users', 'status')) {
                $source->status = 'merged';
            }
            if (Schema::hasColumn('admin_users', 'remember_token')) {
                $source->remember_token = null;
            }
            $source->save();

            $this->recordMergedAccount($source, $target, $context);

            $this->clearUserScopedCaches($source->id);
            $this->clearUserScopedCaches($target->id);

            Log::info('ACCOUNT_MERGE_COMPLETED', [
                'source_user_id' => $source->id,
                'target_user_id' => $target->id,
                'reason' => $context['reason'] ?? 'profile_wizard_contact_conflict',
                'request_ip' => $context['request_ip'] ?? null,
                'request_user_agent' => $context['request_user_agent'] ?? null,
            ]);

            return $target->fresh() ?? $target;
        }, 3);
    }

    /**
     * Subscription-time merge flow by submitted phone number.
     *
     * Roadblocks:
     * - Merge window must be open.
     * - Phone must normalize to a valid UG phone.
     * - Source must be auto-generated account.
     * - Target must be a real/older account with valid email + valid phone.
     */
    public function mergeForSubscriptionByPhone(User $sourceUser, string $submittedPhone, array $context = []): array
    {
        return $this->mergeForSubscriptionByContact($sourceUser, $submittedPhone, null, $context);
    }

    /**
     * Subscription-time merge flow by submitted phone/email.
     *
     * Valid match keys:
     * - Uganda phone number
     * - Real email address
     */
    public function mergeForSubscriptionByContact(
        User $sourceUser,
        ?string $submittedPhone,
        ?string $submittedEmail,
        array $context = []
    ): array
    {
        if (!$this->isAutoMergeWindowOpen()) {
            return [
                'blocked' => true,
                'reason' => 'merge_window_closed',
                'message' => 'This contact is linked to another account. Please login with that account.',
            ];
        }

        $normalizedPhone = $this->normalizeUgandaPhoneOrNull($submittedPhone);
        $normalizedEmail = $this->normalizeRealEmailOrNull($submittedEmail);

        if (!$normalizedPhone && !$normalizedEmail) {
            return [
                'blocked' => false,
                'merged' => false,
                'phone_number' => null,
                'email' => null,
            ];
        }

        [$owner, $ownerConflict] = $this->findContactOwnerForMerge(
            $normalizedPhone,
            $normalizedEmail,
            (int) $sourceUser->id
        );

        if ($ownerConflict) {
            return [
                'blocked' => true,
                'reason' => 'contact_owner_conflict',
                'message' => 'Phone number and email are linked to different accounts. Please login to the correct account.',
                'phone_number' => $normalizedPhone,
                'email' => $normalizedEmail,
            ];
        }

        if (!$owner) {
            return [
                'blocked' => false,
                'merged' => false,
                'phone_number' => $normalizedPhone,
                'email' => $normalizedEmail,
            ];
        }

        if (!$this->isAutoGeneratedAccount($sourceUser)) {
            return [
                'blocked' => true,
                'reason' => 'source_not_auto_generated',
                'message' => 'This contact is linked to another account. Please login with that account.',
                'phone_number' => $normalizedPhone,
                'email' => $normalizedEmail,
                'owner_user_id' => $owner->id,
            ];
        }

        if (!$this->isEligibleOldRealAccount($owner, $sourceUser)) {
            return [
                'blocked' => true,
                'reason' => 'target_not_eligible_old_real_account',
                'message' => 'This contact is linked to another account. Please login with that account.',
                'phone_number' => $normalizedPhone,
                'email' => $normalizedEmail,
                'owner_user_id' => $owner->id,
            ];
        }

        $mergedUser = $this->mergeIntoExistingAccount($sourceUser, $owner, array_merge($context, [
            'incoming_phone' => $normalizedPhone,
            'incoming_email' => $normalizedEmail ?: strtolower(trim((string) ($owner->email ?? ''))),
            'reason' => $context['reason'] ?? 'subscription_create_phone_match',
            'match_type' => $this->resolveMatchType($normalizedPhone, $normalizedEmail),
        ]));

        return [
            'blocked' => false,
            'merged' => true,
            'from_user_id' => (int) $sourceUser->id,
            'to_user_id' => (int) $mergedUser->id,
            'phone_number' => $normalizedPhone,
            'email' => $normalizedEmail,
            'user' => $mergedUser,
        ];
    }

    private function findContactOwnerForMerge(?string $normalizedPhone, ?string $normalizedEmail, int $excludeUserId): array
    {
        $phoneOwner = $normalizedPhone
            ? $this->findPhoneOwnerForMerge($normalizedPhone, $excludeUserId)
            : null;

        $emailOwner = $normalizedEmail
            ? $this->findEmailOwnerForMerge($normalizedEmail, $excludeUserId)
            : null;

        if ($phoneOwner && $emailOwner && (int) $phoneOwner->id !== (int) $emailOwner->id) {
            return [null, true];
        }

        return [$phoneOwner ?: $emailOwner, false];
    }

    private function findEmailOwnerForMerge(string $normalizedEmail, int $excludeUserId): ?User
    {
        return User::where('id', '!=', $excludeUserId)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($normalizedEmail))])
            ->orderByDesc('id')
            ->first();
    }

    private function normalizeRealEmailOrNull(?string $input): ?string
    {
        $email = strtolower(trim((string) $input));
        if (!$this->isValidRealEmail($email)) {
            return null;
        }

        return $email;
    }

    private function normalizeUgandaPhoneOrNull(?string $input): ?string
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            $normalized = $digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $normalized = '256' . substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $normalized = '256' . $digits;
        } else {
            return null;
        }

        if (!preg_match('/^256(7[0-9]|3[0-9])[0-9]{7}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function findPhoneOwnerForMerge(string $normalizedPhone, int $excludeUserId): ?User
    {
        $digits = preg_replace('/\D+/', '', $normalizedPhone);
        $last9 = strlen($digits) >= 9 ? substr($digits, -9) : $digits;

        $variants = array_values(array_unique(array_filter([
            $normalizedPhone,
            '+' . $normalizedPhone,
            $digits,
            strlen($last9) === 9 ? ('0' . $last9) : null,
            $last9,
        ])));

        return User::where('id', '!=', $excludeUserId)
            ->where(function ($q) use ($variants, $digits, $last9) {
                $q->whereIn('phone_number', $variants);

                if (!empty($digits)) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                        [$digits]
                    );
                }

                if (!empty($last9)) {
                    $q->orWhere('phone_number', 'like', "%{$last9}");
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function isAutoGeneratedAccount(User $user): bool
    {
        $accountState = strtolower(trim((string) ($user->account_state ?? '')));
        $accountOrigin = strtolower(trim((string) ($user->account_origin ?? '')));
        $email = strtolower(trim((string) ($user->email ?? '')));
        $isGuest = strtolower(trim((string) ($user->is_guest ?? 'no')));

        if (in_array($accountState, ['auto_created', 'guest', 'temporary'], true)) {
            return true;
        }

        if (in_array($accountOrigin, ['auto_device', 'auto', 'guest'], true)) {
            return true;
        }

        if (str_contains($email, '@auto.') || str_contains($email, 'guest_')) {
            return true;
        }

        return in_array($isGuest, ['yes', 'true', '1'], true);
    }

    private function isEligibleOldRealAccount(User $target, User $source): bool
    {
        if ($this->isAutoGeneratedAccount($target)) {
            return false;
        }

        $email = strtolower(trim((string) ($target->email ?? '')));
        if (!$this->isValidRealEmail($email)) {
            return false;
        }

        $normalizedPhone = $this->normalizeUgandaPhoneOrNull((string) ($target->phone_number ?? ''));
        if (!$normalizedPhone) {
            return false;
        }

        if (empty($target->created_at) || empty($source->created_at)) {
            return false;
        }

        // Target must be older and not freshly created.
        if ($target->created_at->greaterThanOrEqualTo($source->created_at)) {
            return false;
        }

        $minCreatedAt = now()->subHours(self::MIN_OLD_ACCOUNT_AGE_HOURS);
        if ($target->created_at->greaterThan($minCreatedAt)) {
            return false;
        }

        return true;
    }

    private function isValidRealEmail(string $email): bool
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (str_contains($email, '@auto.') || str_contains($email, 'guest_') || str_contains($email, 'device-')) {
            return false;
        }

        return true;
    }

    private function mergeScalarAccountFields(User $source, User $target, array $context): void
    {
        // Prefer explicitly submitted contact values.
        $incomingPhone = trim((string) ($context['incoming_phone'] ?? ''));
        $incomingEmail = trim((string) ($context['incoming_email'] ?? ''));

        if ($incomingPhone !== '' && Schema::hasColumn('admin_users', 'phone_number')) {
            $target->phone_number = $incomingPhone;
        } elseif (empty($target->phone_number) && !empty($source->phone_number) && Schema::hasColumn('admin_users', 'phone_number')) {
            $target->phone_number = $source->phone_number;
        }

        if ($incomingEmail !== '' && Schema::hasColumn('admin_users', 'email')) {
            $target->email = $incomingEmail;
            if (Schema::hasColumn('admin_users', 'username')) {
                $target->username = $incomingEmail;
            }
        } elseif (empty($target->email) && !empty($source->email) && Schema::hasColumn('admin_users', 'email')) {
            $target->email = $source->email;
            if (Schema::hasColumn('admin_users', 'username')) {
                $target->username = $source->email;
            }
        }

        // Fill missing profile fields from source when target is blank.
        $fillableIfBlank = [
            'first_name',
            'last_name',
            'name',
            'avatar',
            'sex',
            'dob',
            'address',
            'bio',
            'tagline',
            'country',
            'state',
            'city',
            'phone_country_name',
            'phone_country_code',
            'phone_country_international',
            'preferred_genres',
            'content_maturity_level',
            'profile_photos',
            'last_password_change',
            'subscription_tier',
            'subscription_expires',
        ];

        foreach ($fillableIfBlank as $column) {
            if (!Schema::hasColumn('admin_users', $column)) {
                continue;
            }

            if (empty($target->{$column}) && !empty($source->{$column})) {
                $target->{$column} = $source->{$column};
            }
        }

        if (Schema::hasColumn('admin_users', 'completed_profile_pct')) {
            $target->completed_profile_pct = max(
                (int) ($target->completed_profile_pct ?? 0),
                (int) ($source->completed_profile_pct ?? 0)
            );
        }

        if (Schema::hasColumn('admin_users', 'profile_completion_step')) {
            $target->profile_completion_step = max(
                (int) ($target->profile_completion_step ?? 0),
                (int) ($source->profile_completion_step ?? 0)
            );
        }

        if (Schema::hasColumn('admin_users', 'profile_photo_skipped')) {
            $target->profile_photo_skipped = (bool) ($target->profile_photo_skipped ?? false)
                || (bool) ($source->profile_photo_skipped ?? false);
        }
    }

    private function mergeNumericCounters(User $source, User $target): void
    {
        $sumColumns = [
            'game_coins_balance',
            'credits_balance',
            'total_games_played',
            'total_games_won',
            'profile_views',
            'likes_received',
            'matches_count',
            'trending_notifications_today',
        ];

        foreach ($sumColumns as $column) {
            if (!Schema::hasColumn('admin_users', $column)) {
                continue;
            }

            $target->{$column} = (int) ($target->{$column} ?? 0) + (int) ($source->{$column} ?? 0);
        }

        if (Schema::hasColumn('admin_users', 'max_trending_notifications_per_day')) {
            $target->max_trending_notifications_per_day = max(
                (int) ($target->max_trending_notifications_per_day ?? 0),
                (int) ($source->max_trending_notifications_per_day ?? 0)
            );
        }
    }

    private function moveUserLinkedRows(int $sourceUserId, int $targetUserId): void
    {
        $this->moveCriticalSubscriptionData($sourceUserId, $targetUserId);

        $dbName = DB::getDatabaseName();
        $columns = $this->discoverUserReferenceColumns($dbName);

        foreach ($columns as $entry) {
            $table = $entry->table_name;
            $column = $entry->column_name;

            try {
                DB::table($table)
                    ->where($column, $sourceUserId)
                    ->update([$column => $targetUserId]);
            } catch (\Throwable $e) {
                Log::warning('ACCOUNT_MERGE_ROW_MOVE_FAILED', [
                    'table' => $table,
                    'column' => $column,
                    'source_user_id' => $sourceUserId,
                    'target_user_id' => $targetUserId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $this->movePersonalAccessTokens($sourceUserId, $targetUserId);
    }

    private function syncAdminAccessMappingsBidirectional(int $sourceUserId, int $targetUserId): void
    {
        if (Schema::hasTable('admin_role_users')) {
            $roleIds = DB::table('admin_role_users')
                ->whereIn('user_id', [$sourceUserId, $targetUserId])
                ->pluck('role_id')
                ->unique()
                ->values();

            foreach ([$sourceUserId, $targetUserId] as $userId) {
                foreach ($roleIds as $roleId) {
                    $exists = DB::table('admin_role_users')
                        ->where('user_id', $userId)
                        ->where('role_id', $roleId)
                        ->exists();

                    if (!$exists) {
                        DB::table('admin_role_users')->insert([
                            'user_id' => $userId,
                            'role_id' => $roleId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        if (Schema::hasTable('admin_user_permissions')) {
            $permissionIds = DB::table('admin_user_permissions')
                ->whereIn('user_id', [$sourceUserId, $targetUserId])
                ->pluck('permission_id')
                ->unique()
                ->values();

            foreach ([$sourceUserId, $targetUserId] as $userId) {
                foreach ($permissionIds as $permissionId) {
                    $exists = DB::table('admin_user_permissions')
                        ->where('user_id', $userId)
                        ->where('permission_id', $permissionId)
                        ->exists();

                    if (!$exists) {
                        DB::table('admin_user_permissions')->insert([
                            'user_id' => $userId,
                            'permission_id' => $permissionId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    private function resolveMatchType(?string $normalizedPhone, ?string $normalizedEmail): string
    {
        if ($normalizedPhone && $normalizedEmail) {
            return 'phone_or_email';
        }

        if ($normalizedPhone) {
            return 'phone';
        }

        if ($normalizedEmail) {
            return 'email';
        }

        return 'phone_or_email';
    }

    private function recordMergedAccount(User $source, User $target, array $context): void
    {
        if (!Schema::hasTable('merged_accounts')) {
            return;
        }

        try {
            MergedAccount::create([
                'source_user_id' => (int) $source->id,
                'target_user_id' => (int) $target->id,
                'source_email' => (string) ($source->email ?? ''),
                'source_phone_number' => (string) ($source->phone_number ?? ''),
                'target_email' => (string) ($target->email ?? ''),
                'target_phone_number' => (string) ($target->phone_number ?? ''),
                'match_type' => (string) ($context['match_type'] ?? 'phone_or_email'),
                'merge_reason' => (string) ($context['reason'] ?? 'account_merge'),
                'source_permissions' => $this->capturePermissionSnapshot((int) $source->id),
                'target_permissions' => $this->capturePermissionSnapshot((int) $target->id),
                'source_snapshot' => $this->captureUserSnapshot($source),
                'target_snapshot' => $this->captureUserSnapshot($target),
                'request_ip' => $context['request_ip'] ?? null,
                'request_user_agent' => $context['request_user_agent'] ?? null,
                'status' => 'completed',
                'merged_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MERGED_ACCOUNT_RECORD_FAILED', [
                'source_user_id' => $source->id,
                'target_user_id' => $target->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function capturePermissionSnapshot(int $userId): array
    {
        $snapshot = [
            'role_ids' => [],
            'permission_ids' => [],
        ];

        if (Schema::hasTable('admin_role_users')) {
            $snapshot['role_ids'] = DB::table('admin_role_users')
                ->where('user_id', $userId)
                ->pluck('role_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->toArray();
        }

        if (Schema::hasTable('admin_user_permissions')) {
            $snapshot['permission_ids'] = DB::table('admin_user_permissions')
                ->where('user_id', $userId)
                ->pluck('permission_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->toArray();
        }

        return $snapshot;
    }

    private function captureUserSnapshot(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? ''),
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'email' => (string) ($user->email ?? ''),
            'phone_number' => (string) ($user->phone_number ?? ''),
            'account_state' => (string) ($user->account_state ?? ''),
            'app_type' => (string) ($user->app_type ?? ''),
            'platform' => (string) ($user->platform ?? ''),
            'created_at' => (string) ($user->created_at ?? ''),
        ];
    }

    private function moveCriticalSubscriptionData(int $sourceUserId, int $targetUserId): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'user_id')) {
            DB::table('subscriptions')
                ->where('user_id', $sourceUserId)
                ->update(['user_id' => $targetUserId]);
        }

        if (Schema::hasTable('subscription_transactions') && Schema::hasColumn('subscription_transactions', 'user_id')) {
            DB::table('subscription_transactions')
                ->where('user_id', $sourceUserId)
                ->update(['user_id' => $targetUserId]);
        }

        $remainingSubscriptions = Schema::hasTable('subscriptions')
            ? DB::table('subscriptions')->where('user_id', $sourceUserId)->count()
            : 0;

        $remainingTransactions = Schema::hasTable('subscription_transactions')
            ? DB::table('subscription_transactions')->where('user_id', $sourceUserId)->count()
            : 0;

        if ($remainingSubscriptions > 0 || $remainingTransactions > 0) {
            throw new \RuntimeException('Critical subscription migration incomplete.');
        }
    }

    private function discoverUserReferenceColumns(string $dbName)
    {
        $candidateColumns = [
            'user_id',
            'sender_id',
            'receiver_id',
            'blocker_id',
            'blocked_user_id',
            'reporter_id',
            'reported_user_id',
            'moderator_id',
            'cancelled_by',
            'refunded_by',
            'uploaded_by',
            'created_by',
            'updated_by',
        ];

        $excludeTables = [
            'admin_users',
            'admin_operation_log',
            'admin_role_users',
            'admin_user_permissions',
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'sessions',
        ];

        return DB::table('information_schema.columns')
            ->selectRaw('TABLE_NAME as table_name, COLUMN_NAME as column_name')
            ->where('TABLE_SCHEMA', $dbName)
            ->where(function ($q) use ($candidateColumns) {
                $q->whereIn('COLUMN_NAME', $candidateColumns)
                    ->orWhere('COLUMN_NAME', 'like', '%\\_user_id');
            })
            ->whereNotIn('TABLE_NAME', $excludeTables)
            ->orderBy('TABLE_NAME')
            ->orderBy('COLUMN_NAME')
            ->get();
    }

    private function movePersonalAccessTokens(int $sourceUserId, int $targetUserId): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        $query = DB::table('personal_access_tokens')
            ->where('tokenable_id', $sourceUserId)
            ->whereIn('tokenable_type', [
                User::class,
                'App\\Models\\User',
                'Encore\\Admin\\Auth\\Database\\Administrator',
            ]);

        $query->update([
            'tokenable_id' => $targetUserId,
            'tokenable_type' => User::class,
        ]);
    }

    private function countCompletedMergesForUser(int $userId): int
    {
        if (!Schema::hasTable('merged_accounts')) {
            return 0;
        }

        return (int) DB::table('merged_accounts')
            ->where('status', 'completed')
            ->where(function ($q) use ($userId) {
                $q->where('source_user_id', $userId)
                    ->orWhere('target_user_id', $userId);
            })
            ->count();
    }

    private function ensureMergeLimitNotReached(int $userId, string $label): void
    {
        $count = $this->countCompletedMergesForUser($userId);
        if ($count >= self::MAX_MERGES_PER_ACCOUNT) {
            throw new \RuntimeException(
                sprintf(
                    'Merge limit reached for %s account (%d/%d). Please contact support for manual review.',
                    $label,
                    $count,
                    self::MAX_MERGES_PER_ACCOUNT
                )
            );
        }
    }

    private function clearUserScopedCaches(int $userId): void
    {
        foreach ([
            "sub_pending_{$userId}",
            "active_sub_{$userId}",
            "v1_pay_check_{$userId}",
            "v2_pay_check_{$userId}",
            "subscription_autofix_{$userId}",
        ] as $key) {
            Cache::forget($key);
        }
    }
}
