<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AccountMergeService
{
    private const AUTO_MERGE_DEADLINE = '2026-05-31 23:59:59';

    /**
     * Merge is allowed up to and including 31 May 2026.
     */
    public function isAutoMergeWindowOpen(): bool
    {
        $tz = config('app.timezone', 'UTC');
        $deadline = Carbon::createFromFormat('Y-m-d H:i:s', self::AUTO_MERGE_DEADLINE, $tz);

        return Carbon::now($tz)->lessThanOrEqualTo($deadline);
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

            $this->moveUserLinkedRows($source->id, $target->id);
            $this->mergeScalarAccountFields($source, $target, $context);
            $this->mergeNumericCounters($source, $target);

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
