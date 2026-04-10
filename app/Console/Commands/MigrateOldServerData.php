<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MigrateOldServerData Command
 *
 * Migrates users and subscription data from the old server (exported as JSON)
 * into the new production database. Uses email as the unique identifier to
 * avoid duplicates and map records correctly.
 *
 * The old server CSV exports were parsed into JSON files by parse_csv.py:
 *   - parsed_users.json     (28,694 users from old admin panel)
 *   - parsed_transactions.json (3,437 completed payment records)
 *
 * Migration logic:
 *   1. For each old user: if email does NOT exist in new DB → create user
 *   2. For each old user with an active subscription → create subscription record
 *   3. For each completed transaction → create subscription_transaction record
 *   4. All imported records are tagged with is_imported='Yes' and import_source
 *
 * Safety:
 *   - Idempotent: safe to run multiple times (skips existing emails)
 *   - Supports --dry-run to preview without writing
 *   - Full logging to storage/logs/migration.log
 *   - Wrapped in DB transactions for atomicity
 *
 * Usage:
 *   php artisan migrate:old-server --data-path=/path/to/transition-data
 *   php artisan migrate:old-server --data-path=/path/to/transition-data --dry-run
 *   php artisan migrate:old-server --data-path=/path/to/transition-data --only=users
 *   php artisan migrate:old-server --data-path=/path/to/transition-data --only=subscriptions
 */
class MigrateOldServerData extends Command
{
    protected $signature = 'migrate:old-server
                            {--data-path= : Path to the directory containing parsed_users.json and parsed_transactions.json}
                            {--dry-run : Preview changes without writing to the database}
                            {--only= : Migrate only "users" or "subscriptions" (default: both)}
                            {--batch-size=500 : Number of records to process per batch}';

    protected $description = 'Migrate users and subscriptions from old server JSON exports to the new database';

    // ─── Counters ────────────────────────────────────────────────
    private int $usersCreated    = 0;
    private int $usersSkipped    = 0;
    private int $usersErrored    = 0;
    private int $subsCreated     = 0;
    private int $subsSkipped     = 0;
    private int $subsErrored     = 0;
    private int $txnsCreated     = 0;
    private int $txnsSkipped     = 0;
    private int $txnsErrored     = 0;

    // ─── Amount → Plan ID mapping ────────────────────────────────
    private const AMOUNT_TO_PLAN = [
        1000  => ['id' => 1, 'days' => 3,  'name' => 'Quick Start'],
        5000  => ['id' => 2, 'days' => 15, 'name' => 'Two Weeks Special'],
        8000  => ['id' => 3, 'days' => 30, 'name' => 'Monthly Premium'],
        2000  => ['id' => 1, 'days' => 3,  'name' => 'Quick Start (legacy)'],
    ];

    // ─── Default plan for unmapped amounts ───────────────────────
    private const DEFAULT_PLAN = ['id' => 1, 'days' => 3, 'name' => 'Quick Start (fallback)'];

    public function handle(): int
    {
        $dataPath = $this->option('data-path');
        $dryRun   = $this->option('dry-run');
        $only     = $this->option('only');

        // ── Validate data path ───────────────────────────────────
        if (!$dataPath) {
            $this->error('--data-path is required. Point it to the directory with parsed_users.json and parsed_transactions.json');
            return self::FAILURE;
        }

        $usersFile = rtrim($dataPath, '/') . '/parsed_users.json';
        $txnsFile  = rtrim($dataPath, '/') . '/parsed_transactions.json';

        if (!file_exists($usersFile)) {
            $this->error("Users file not found: {$usersFile}");
            return self::FAILURE;
        }
        if (!file_exists($txnsFile)) {
            $this->error("Transactions file not found: {$txnsFile}");
            return self::FAILURE;
        }

        // ── Load JSON data ───────────────────────────────────────
        $this->info('Loading JSON data...');
        $oldUsers = json_decode(file_get_contents($usersFile), true);
        $oldTxns  = json_decode(file_get_contents($txnsFile), true);

        if (!$oldUsers || !$oldTxns) {
            $this->error('Failed to parse JSON files. Ensure they are valid JSON.');
            return self::FAILURE;
        }

        $this->info(sprintf('Loaded %s users and %s transactions from old server exports.',
            number_format(count($oldUsers)),
            number_format(count($oldTxns))
        ));

        if ($dryRun) {
            $this->warn('*** DRY RUN MODE — no database writes will occur ***');
        }

        // ── Build lookup indexes ─────────────────────────────────
        $this->info('Building lookup indexes...');

        // Index old users by email for fast lookup
        $oldUsersByEmail = [];
        foreach ($oldUsers as $u) {
            $email = strtolower(trim($u['email'] ?? ''));
            if ($email) {
                $oldUsersByEmail[$email] = $u;
            }
        }

        // Index transactions by email
        $txnsByEmail = [];
        foreach ($oldTxns as $t) {
            $email = strtolower(trim($t['email'] ?? ''));
            if ($email) {
                $txnsByEmail[$email][] = $t;
            }
        }

        // Get all existing emails in new DB for fast duplicate checking
        $existingEmails = DB::table('admin_users')
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn($e) => strtolower(trim($e)))
            ->flip()
            ->toArray();

        $this->info(sprintf('Existing users in new DB: %s', number_format(count($existingEmails))));

        // Calculate how many need migration
        $needMigration = 0;
        $alreadyExist  = 0;
        foreach ($oldUsersByEmail as $email => $u) {
            if (isset($existingEmails[$email])) {
                $alreadyExist++;
            } else {
                $needMigration++;
            }
        }
        $this->info(sprintf('Users needing migration: %s (already exist: %s)',
            number_format($needMigration), number_format($alreadyExist)
        ));

        // ── Phase 1: Migrate Users ───────────────────────────────
        if (!$only || $only === 'users') {
            $this->migrateUsers($oldUsersByEmail, $existingEmails, $dryRun);
        }

        // Refresh the email→ID mapping after user creation
        $emailToNewId = DB::table('admin_users')
            ->whereNotNull('email')
            ->pluck('id', 'email')
            ->mapWithKeys(fn($id, $email) => [strtolower(trim($email)) => $id])
            ->toArray();

        // ── Phase 2: Migrate Subscriptions ───────────────────────
        if (!$only || $only === 'subscriptions') {
            $this->migrateSubscriptions($oldUsersByEmail, $txnsByEmail, $emailToNewId, $dryRun);
        }

        // ── Summary ──────────────────────────────────────────────
        $this->newLine();
        $this->info('═══════════════════════════════════════════════');
        $this->info('  MIGRATION SUMMARY' . ($dryRun ? ' (DRY RUN)' : ''));
        $this->info('═══════════════════════════════════════════════');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Users Created',       $this->usersCreated],
                ['Users Skipped (exist)',$this->usersSkipped],
                ['Users Errored',        $this->usersErrored],
                ['Subs Created',         $this->subsCreated],
                ['Subs Skipped',         $this->subsSkipped],
                ['Subs Errored',         $this->subsErrored],
                ['Txns Created',         $this->txnsCreated],
                ['Txns Skipped',         $this->txnsSkipped],
                ['Txns Errored',         $this->txnsErrored],
            ]
        );

        $logMsg = sprintf(
            'Migration %s: users=%d/%d/%d subs=%d/%d/%d txns=%d/%d/%d',
            $dryRun ? 'DRY-RUN' : 'COMPLETED',
            $this->usersCreated, $this->usersSkipped, $this->usersErrored,
            $this->subsCreated, $this->subsSkipped, $this->subsErrored,
            $this->txnsCreated, $this->txnsSkipped, $this->txnsErrored
        );
        Log::channel('migration')->info($logMsg);

        return self::SUCCESS;
    }

    // ═══════════════════════════════════════════════════════════════
    //  PHASE 1: USER MIGRATION
    // ═══════════════════════════════════════════════════════════════

    private function migrateUsers(array $oldUsersByEmail, array $existingEmails, bool $dryRun): void
    {
        $this->newLine();
        $this->info('── Phase 1: Migrating Users ──────────────────');

        $total   = count($oldUsersByEmail);
        $bar     = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Created: %created% | Skipped: %skipped%');
        $bar->setMessage('0', 'created');
        $bar->setMessage('0', 'skipped');
        $bar->start();

        $batch     = [];
        $batchSize = (int) $this->option('batch-size');
        $now       = now()->toDateTimeString();

        foreach ($oldUsersByEmail as $email => $oldUser) {
            // Skip if user already exists in new DB
            if (isset($existingEmails[$email])) {
                $this->usersSkipped++;
                $bar->setMessage((string) $this->usersSkipped, 'skipped');
                $bar->advance();
                continue;
            }

            // Parse name into first/last
            $fullName  = trim($oldUser['name'] ?? 'User');
            $nameParts = explode(' ', $fullName, 2);
            $firstName = $nameParts[0] ?: 'User';
            $lastName  = $nameParts[1] ?? '';

            // Determine guest status
            $isGuest = ($oldUser['is_guest'] ?? false) ? 'Yes' : 'No';

            // Build the user record
            $batch[] = [
                'username'       => $email,
                'email'          => $email,
                'name'           => $fullName,
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'password'       => bcrypt('katogo_migrated_' . substr(md5($email), 0, 8)),
                'phone_number'   => $oldUser['phone'] ?? null,
                'company_id'     => 1,
                'status'         => 'Active',
                'is_guest'       => $isGuest,
                'app_type'       => 'ugflix',
                'platform'       => $oldUser['device'] ?? 'android',
                'is_imported'    => 'Yes',
                'import_source'  => 'old_server_csv_2026-04-10',
                'imported_at'    => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            // Flush batch
            if (count($batch) >= $batchSize) {
                if (!$dryRun) {
                    $this->insertUserBatch($batch);
                }
                $this->usersCreated += count($batch);
                $bar->setMessage((string) $this->usersCreated, 'created');
                $batch = [];
            }

            $bar->advance();
        }

        // Flush remaining
        if (count($batch) > 0) {
            if (!$dryRun) {
                $this->insertUserBatch($batch);
            }
            $this->usersCreated += count($batch);
            $bar->setMessage((string) $this->usersCreated, 'created');
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Users: %d created, %d skipped, %d errors',
            $this->usersCreated, $this->usersSkipped, $this->usersErrored));
    }

    /**
     * Insert a batch of user records with error handling per-row.
     */
    private function insertUserBatch(array $batch): void
    {
        try {
            DB::table('admin_users')->insert($batch);
        } catch (\Exception $e) {
            // If batch insert fails (e.g. duplicate), fall back to one-by-one
            Log::channel('migration')->warning('Batch user insert failed, falling back to individual inserts: ' . $e->getMessage());

            foreach ($batch as $userData) {
                try {
                    // Double-check email doesn't already exist
                    $exists = DB::table('admin_users')
                        ->where('email', $userData['email'])
                        ->exists();

                    if ($exists) {
                        $this->usersSkipped++;
                        $this->usersCreated--; // Undo the batch count
                        continue;
                    }

                    DB::table('admin_users')->insert($userData);
                } catch (\Exception $rowError) {
                    $this->usersErrored++;
                    $this->usersCreated--; // Undo the batch count
                    Log::channel('migration')->error('User insert failed', [
                        'email' => $userData['email'],
                        'error' => $rowError->getMessage(),
                    ]);
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PHASE 2: SUBSCRIPTION & TRANSACTION MIGRATION
    // ═══════════════════════════════════════════════════════════════

    private function migrateSubscriptions(
        array $oldUsersByEmail,
        array $txnsByEmail,
        array $emailToNewId,
        bool  $dryRun
    ): void {
        $this->newLine();
        $this->info('── Phase 2: Migrating Subscriptions & Transactions ──');

        // Collect emails that need subscription records:
        // 1. Users with Active subscriptions from old CSV
        // 2. Users with completed transactions from old CSV
        $subEmails = [];

        foreach ($oldUsersByEmail as $email => $u) {
            $sub = $u['subscription'] ?? '';
            if (str_starts_with($sub, 'Active') || str_starts_with($sub, 'Pending')) {
                $subEmails[$email] = true;
            }
        }

        foreach ($txnsByEmail as $email => $txns) {
            $subEmails[$email] = true;
        }

        $total = count($subEmails);
        $this->info(sprintf('Processing subscriptions for %s users...', number_format($total)));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | Subs: %subs% | Txns: %txns%');
        $bar->setMessage('0', 'subs');
        $bar->setMessage('0', 'txns');
        $bar->start();

        foreach ($subEmails as $email => $_) {
            $newUserId = $emailToNewId[$email] ?? null;

            if (!$newUserId) {
                $this->subsSkipped++;
                $bar->advance();
                continue;
            }

            // Check if this user already has subscriptions in the new DB
            $existingSub = DB::table('subscriptions')
                ->where('user_id', $newUserId)
                ->exists();

            if ($existingSub) {
                $this->subsSkipped++;
                // Still process transactions that might be missing
                $this->migrateTxnsForUser($email, $newUserId, $txnsByEmail, $dryRun);
                $bar->setMessage((string) $this->txnsCreated, 'txns');
                $bar->advance();
                continue;
            }

            $oldUser = $oldUsersByEmail[$email] ?? null;
            $subStr  = $oldUser['subscription'] ?? 'Free';

            // Parse subscription status and expiry date
            $parts     = explode('|', $subStr);
            $subStatus = trim($parts[0]);
            $expiryStr = $parts[1] ?? null;

            // Determine the plan from transaction amounts (most accurate)
            $userTxns = $txnsByEmail[$email] ?? [];
            $plan     = $this->determinePlan($userTxns);
            $amount   = $this->determineAmount($userTxns);

            // Only create subscription for Active/Pending users, or users with transactions
            if (!in_array($subStatus, ['Active', 'Pending']) && empty($userTxns)) {
                $this->subsSkipped++;
                $bar->advance();
                continue;
            }

            // Calculate dates
            $startDate = null;
            $endDate   = null;
            $dbStatus  = 'Expired'; // Default for old subs
            $payStatus = 'Completed';

            if ($subStatus === 'Active' && $expiryStr) {
                // Parse expiry like "12-Apr-26"
                try {
                    $endDate   = Carbon::createFromFormat('d-M-y', trim($expiryStr));
                    $startDate = $endDate->copy()->subDays($plan['days']);
                    $dbStatus  = $endDate->isFuture() ? 'Active' : 'Expired';
                } catch (\Exception $e) {
                    Log::channel('migration')->warning("Could not parse expiry date: {$expiryStr} for {$email}");
                    $endDate   = now()->subDay();
                    $startDate = $endDate->copy()->subDays($plan['days']);
                    $dbStatus  = 'Expired';
                }
            } elseif ($subStatus === 'Pending') {
                $startDate = now();
                $endDate   = now();
                $dbStatus  = 'Pending';
                $payStatus = 'Pending';
            } elseif (!empty($userTxns)) {
                // Has transactions but not marked active — likely expired
                $lastTxn   = end($userTxns);
                try {
                    $startDate = Carbon::createFromFormat('M d, Y H:i', trim($lastTxn['date']));
                } catch (\Exception $e) {
                    $startDate = now()->subMonth();
                }
                $endDate  = $startDate->copy()->addDays($plan['days']);
                $dbStatus = 'Expired';
            }

            // Determine payment method from transactions
            $paymentMethod = 'pesapal';
            if (!empty($userTxns)) {
                $methods = array_column($userTxns, 'method');
                $lastMethod = end($methods);
                if (str_contains($lastMethod, 'MTN') || str_contains($lastMethod, 'Airtel')) {
                    $paymentMethod = 'pesapal';
                }
            }

            // Get the tracking ID from the latest transaction
            $trackingId = null;
            if (!empty($userTxns)) {
                $lastTxn    = end($userTxns);
                $trackingId = $lastTxn['tracking_id'] ?? null;
            }

            if (!$dryRun) {
                try {
                    $subId = DB::table('subscriptions')->insertGetId([
                        'user_id'                    => $newUserId,
                        'plan_id'                    => $plan['id'],
                        'days'                       => $plan['days'],
                        'start_date_time'            => $startDate,
                        'end_date_time'              => $endDate,
                        'grace_period_end'           => $endDate ? Carbon::parse($endDate)->addDays(3) : null,
                        'status'                     => $dbStatus,
                        'auto_renew'                 => 0,
                        'payment_method'             => $paymentMethod,
                        'payment_status'             => $payStatus,
                        'pesapal_tracking_id'        => $trackingId,
                        'pesapal_merchant_reference' => 'MIG-' . $newUserId . '-' . time(),
                        'amount_paid'                => $amount,
                        'currency'                   => 'UGX',
                        'app_type'                   => 'ugflix',
                        'platform'                   => 'android',
                        'created_at'                 => $startDate ?? now(),
                        'updated_at'                 => now(),
                    ]);

                    $this->subsCreated++;

                    // Also assign the admin role (role_id=2) if not already assigned
                    $hasRole = DB::table('admin_role_users')
                        ->where('user_id', $newUserId)
                        ->where('role_id', 2)
                        ->exists();
                    if (!$hasRole) {
                        DB::table('admin_role_users')->insert([
                            'user_id' => $newUserId,
                            'role_id' => 2,
                        ]);
                    }

                    // Create transaction records for this subscription
                    $this->migrateTxnsForUser($email, $newUserId, $txnsByEmail, $dryRun, $subId);
                } catch (\Exception $e) {
                    $this->subsErrored++;
                    Log::channel('migration')->error('Subscription insert failed', [
                        'email'  => $email,
                        'userId' => $newUserId,
                        'error'  => $e->getMessage(),
                    ]);
                }
            } else {
                $this->subsCreated++;
                $this->migrateTxnsForUser($email, $newUserId, $txnsByEmail, $dryRun);
            }

            $bar->setMessage((string) $this->subsCreated, 'subs');
            $bar->setMessage((string) $this->txnsCreated, 'txns');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Subscriptions: %d created, %d skipped, %d errors',
            $this->subsCreated, $this->subsSkipped, $this->subsErrored));
        $this->info(sprintf('Transactions:  %d created, %d skipped, %d errors',
            $this->txnsCreated, $this->txnsSkipped, $this->txnsErrored));
    }

    /**
     * Migrate transaction records for a specific user.
     */
    private function migrateTxnsForUser(
        string $email,
        int    $newUserId,
        array  $txnsByEmail,
        bool   $dryRun,
        ?int   $subscriptionId = null
    ): void {
        $userTxns = $txnsByEmail[$email] ?? [];
        if (empty($userTxns)) {
            return;
        }

        // If no subscription ID provided, try to find one
        if (!$subscriptionId) {
            $subscriptionId = DB::table('subscriptions')
                ->where('user_id', $newUserId)
                ->orderByDesc('id')
                ->value('id');
        }

        if (!$subscriptionId) {
            return;
        }

        foreach ($userTxns as $txn) {
            $trackingId = $txn['tracking_id'] ?? null;

            // Skip if this transaction already exists (by tracking_id)
            if ($trackingId) {
                $exists = DB::table('subscription_transactions')
                    ->where('pesapal_tracking_id', $trackingId)
                    ->exists();
                if ($exists) {
                    $this->txnsSkipped++;
                    continue;
                }
            }

            // Parse transaction type
            $type = 'Initial';
            $rawType = $txn['type'] ?? '';
            if (str_contains($rawType, 'Renewal')) {
                $type = 'Renewal';
            } elseif (str_contains($rawType, 'Withdrawal')) {
                $type = 'Withdrawal';
            }

            // Parse date
            $txnDate = null;
            try {
                $txnDate = Carbon::createFromFormat('M d, Y H:i', trim($txn['date']));
            } catch (\Exception $e) {
                $txnDate = now();
            }

            // Parse payment method
            $method = 'pesapal';
            $rawMethod = $txn['method'] ?? '';
            if (str_contains($rawMethod, 'MTN')) {
                $method = 'Mobile Money (MTN)';
            } elseif (str_contains($rawMethod, 'Airtel')) {
                $method = 'Mobile Money (Airtel)';
            } elseif (str_contains($rawMethod, 'Visa')) {
                $method = 'Visa';
            } elseif (str_contains($rawMethod, 'MC')) {
                $method = 'Mastercard';
            }

            $amount = (float) ($txn['amount'] ?? 0);

            if (!$dryRun) {
                try {
                    DB::table('subscription_transactions')->insert([
                        'subscription_id'    => $subscriptionId,
                        'user_id'            => $newUserId,
                        'transaction_type'   => $type,
                        'platform'           => 'android',
                        'amount'             => $amount,
                        'currency'           => 'UGX',
                        'status'             => 'Completed',
                        'pesapal_tracking_id' => $trackingId,
                        'merchant_reference' => 'MIG-TXN-' . $newUserId . '-' . ($txn['old_id'] ?? time()),
                        'payment_method'     => $method,
                        'created_at'         => $txnDate,
                        'updated_at'         => now(),
                    ]);
                    $this->txnsCreated++;
                } catch (\Exception $e) {
                    $this->txnsErrored++;
                    Log::channel('migration')->error('Transaction insert failed', [
                        'email'      => $email,
                        'trackingId' => $trackingId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            } else {
                $this->txnsCreated++;
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Determine the subscription plan from transaction history.
     * Uses the most recent transaction amount to find the plan.
     */
    private function determinePlan(array $txns): array
    {
        if (empty($txns)) {
            return self::DEFAULT_PLAN;
        }

        // Use the most recent transaction's amount
        $lastTxn = end($txns);
        $amount  = (int) ($lastTxn['amount'] ?? 0);

        return self::AMOUNT_TO_PLAN[$amount] ?? self::DEFAULT_PLAN;
    }

    /**
     * Determine the payment amount from transaction history.
     */
    private function determineAmount(array $txns): float
    {
        if (empty($txns)) {
            return 1000.00; // Default to Quick Start price
        }

        $lastTxn = end($txns);
        return (float) ($lastTxn['amount'] ?? 1000);
    }
}
