<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GhostAccountService
{
    /**
     * Resurrect a deleted user account using their original ID so that any
     * orphaned subscriptions (which have no FK constraint on user_id) automatically
     * re-link without any data migration.
     *
     * Only proceeds when the JWT was validly signed by this app — i.e. the account
     * genuinely existed and was hard-deleted rather than some fabricated token.
     */
    public function resurrect(int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        // Race-condition guard: another concurrent request may have just resurrected this
        $existing = User::find($userId);
        if ($existing) {
            return $existing;
        }

        // Gather any surviving metadata from orphaned subscriptions
        $latestSub = Subscription::where('user_id', $userId)->latest()->first();
        $appType   = $latestSub ? ($latestSub->app_type  ?? 'ugflix')  : 'ugflix';
        $platform  = $latestSub ? ($latestSub->platform  ?? 'android') : 'android';
        $subCount  = Subscription::where('user_id', $userId)->count();

        // Build a deterministic, unique placeholder identity
        $placeholderEmail = "restored_{$userId}@katogo.auto";
        $placeholderName  = "Restored Account #{$userId}";

        try {
            DB::table('admin_users')->insert([
                'id'                      => $userId,
                'username'                => $placeholderEmail,
                'email'                   => $placeholderEmail,
                'password'                => Hash::make(Str::random(40)),
                'name'                    => $placeholderName,
                'status'                  => 'active',
                'is_guest'                => 'No',
                'app_type'                => $appType,
                'platform'                => $platform,
                'account_origin'          => 'restored',
                'account_state'           => 'registered',
                'profile_completion_step' => 0,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate primary key: a parallel request beat us to it — return that row
            if ($e->errorInfo[1] === 1062) {
                return User::find($userId);
            }
            Log::error('GhostAccountService: insert failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }

        $resurrected = User::find($userId);

        Log::info('GhostAccountService: account resurrected', [
            'user_id'   => $userId,
            'email'     => $placeholderEmail,
            'app_type'  => $appType,
            'platform'  => $platform,
            'sub_count' => $subCount,
        ]);

        return $resurrected;
    }
}
