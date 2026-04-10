<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * VerifyMigration Command
 *
 * Validates the data integrity after running migrate:old-server.
 * Checks that imported users exist, subscriptions are linked correctly,
 * and transaction records match expectations.
 *
 * Usage:
 *   php artisan migrate:verify --data-path=/path/to/transition-data
 */
class VerifyMigration extends Command
{
    protected $signature = 'migrate:verify
                            {--data-path= : Path to the directory containing parsed_users.json and parsed_transactions.json}';

    protected $description = 'Verify the integrity of migrated data from old server';

    public function handle(): int
    {
        $dataPath = $this->option('data-path');
        if (!$dataPath) {
            $this->error('--data-path is required.');
            return self::FAILURE;
        }

        $usersFile = rtrim($dataPath, '/') . '/parsed_users.json';
        $txnsFile  = rtrim($dataPath, '/') . '/parsed_transactions.json';

        $oldUsers = json_decode(file_get_contents($usersFile), true);
        $oldTxns  = json_decode(file_get_contents($txnsFile), true);

        $this->info('Starting migration verification...');
        $this->newLine();

        $issues   = [];
        $warnings = [];

        // ── 1. Verify all old users exist in new DB ─────────────
        $this->info('1. Checking user accounts...');
        $oldEmails = [];
        foreach ($oldUsers as $u) {
            $email = strtolower(trim($u['email'] ?? ''));
            if ($email) {
                $oldEmails[$email] = $u;
            }
        }

        $existingEmails = DB::table('admin_users')
            ->whereNotNull('email')
            ->pluck('email', 'id')
            ->mapWithKeys(fn($email, $id) => [strtolower(trim($email)) => $id])
            ->toArray();

        $missingUsers = [];
        foreach ($oldEmails as $email => $u) {
            if (!isset($existingEmails[$email])) {
                $missingUsers[] = $email;
            }
        }

        if (empty($missingUsers)) {
            $this->info('   ✅ All ' . number_format(count($oldEmails)) . ' old users found in new DB');
        } else {
            $this->error('   ❌ ' . count($missingUsers) . ' users MISSING from new DB');
            foreach (array_slice($missingUsers, 0, 10) as $email) {
                $this->line("      - {$email}");
            }
            if (count($missingUsers) > 10) {
                $this->line('      ... and ' . (count($missingUsers) - 10) . ' more');
            }
            $issues[] = count($missingUsers) . ' users missing';
        }

        // ── 2. Verify imported users are tagged ──────────────────
        $this->newLine();
        $this->info('2. Checking import tags...');
        $importedCount = DB::table('admin_users')
            ->where('is_imported', 'Yes')
            ->where('import_source', 'like', 'old_server_csv%')
            ->count();
        $this->info("   ℹ️  {$importedCount} users tagged as imported from old server");

        // ── 3. Verify active subscriptions ───────────────────────
        $this->newLine();
        $this->info('3. Checking active subscriptions...');

        $activeOldUsers = [];
        foreach ($oldEmails as $email => $u) {
            $sub = $u['subscription'] ?? '';
            if (str_starts_with($sub, 'Active')) {
                $parts    = explode('|', $sub);
                $expiryStr = $parts[1] ?? null;
                if ($expiryStr) {
                    try {
                        $expiry = \Carbon\Carbon::createFromFormat('d-M-y', trim($expiryStr));
                        if ($expiry->isFuture()) {
                            $activeOldUsers[$email] = $expiry->toDateString();
                        }
                    } catch (\Exception $e) {
                        // ignore date parse errors
                    }
                }
            }
        }

        $this->info('   Active subs with future expiry in CSV: ' . count($activeOldUsers));

        $missingActiveSubs = 0;
        $correctActiveSubs = 0;
        foreach ($activeOldUsers as $email => $expectedExpiry) {
            $userId = $existingEmails[$email] ?? null;
            if (!$userId) continue;

            $activeSub = DB::table('subscriptions')
                ->where('user_id', $userId)
                ->where('status', 'Active')
                ->where('end_date_time', '>=', now())
                ->first();

            if ($activeSub) {
                $correctActiveSubs++;
            } else {
                $missingActiveSubs++;
                if ($missingActiveSubs <= 5) {
                    $this->warn("   ⚠️  No active sub for {$email} (expected expiry: {$expectedExpiry})");
                }
            }
        }

        if ($missingActiveSubs === 0) {
            $this->info("   ✅ All {$correctActiveSubs} active subscriptions verified");
        } else {
            $this->warn("   ⚠️  {$missingActiveSubs} users missing active subs, {$correctActiveSubs} correct");
            $warnings[] = "{$missingActiveSubs} active subs missing";
        }

        // ── 4. Verify transactions ───────────────────────────────
        $this->newLine();
        $this->info('4. Checking transactions...');

        $txnTrackingIds = [];
        foreach ($oldTxns as $t) {
            $tid = $t['tracking_id'] ?? null;
            if ($tid) {
                $txnTrackingIds[] = $tid;
            }
        }

        $existingTxns = DB::table('subscription_transactions')
            ->whereIn('pesapal_tracking_id', $txnTrackingIds)
            ->count();

        $this->info("   Old transactions with tracking IDs: " . count($txnTrackingIds));
        $this->info("   Found in new DB: {$existingTxns}");

        if ($existingTxns >= count($txnTrackingIds) * 0.9) {
            $this->info('   ✅ Transaction migration looks complete');
        } else {
            $warnings[] = 'Some transactions may be missing';
            $this->warn('   ⚠️  Some transactions may be missing');
        }

        // ── 5. Check for orphaned subscriptions ──────────────────
        $this->newLine();
        $this->info('5. Checking for orphaned records...');

        $orphanedSubs = DB::table('subscriptions')
            ->leftJoin('admin_users', 'subscriptions.user_id', '=', 'admin_users.id')
            ->whereNull('admin_users.id')
            ->count();

        if ($orphanedSubs > 0) {
            $this->error("   ❌ {$orphanedSubs} subscriptions reference non-existent users");
            $issues[] = "{$orphanedSubs} orphaned subscriptions";
        } else {
            $this->info('   ✅ No orphaned subscriptions');
        }

        $orphanedTxns = DB::table('subscription_transactions')
            ->leftJoin('admin_users', 'subscription_transactions.user_id', '=', 'admin_users.id')
            ->whereNull('admin_users.id')
            ->count();

        if ($orphanedTxns > 0) {
            $this->error("   ❌ {$orphanedTxns} transactions reference non-existent users");
            $issues[] = "{$orphanedTxns} orphaned transactions";
        } else {
            $this->info('   ✅ No orphaned transactions');
        }

        // ── Summary ──────────────────────────────────────────────
        $this->newLine();
        $this->info('═══════════════════════════════════════════════');
        if (empty($issues)) {
            $this->info('  ✅ VERIFICATION PASSED');
            if (!empty($warnings)) {
                $this->warn('  Warnings: ' . implode(', ', $warnings));
            }
        } else {
            $this->error('  ❌ VERIFICATION FOUND ISSUES');
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
        }
        $this->info('═══════════════════════════════════════════════');

        return empty($issues) ? self::SUCCESS : self::FAILURE;
    }
}
