<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Check Expired Subscriptions Command
 * 
 * Runs daily to check for expired subscriptions and update their status
 * Should be scheduled to run every day
 * 
 * Usage: php artisan subscriptions:check-expired
 */
class CheckExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-expired 
                            {--dry-run : Run without making changes}
                            {--force : Force check even if recently run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired subscriptions and update their status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        $this->info('🔍 Checking for expired subscriptions...');
        
        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
        }

        $now = now();

        // ── Step 0: Fix Active subscriptions with NULL end_date_time ────────────
        // These are corrupted records (payment confirmed but dates never calculated).
        // Repair them so they expire naturally rather than being left broken forever.
        $nullEndSubs = Subscription::where('status', 'Active')
            ->whereNull('end_date_time')
            ->whereNotNull('start_date_time')
            ->get();

        if ($nullEndSubs->count() > 0) {
            $this->warn("⚠️  Found {$nullEndSubs->count()} Active subscription(s) with NULL end_date_time — repairing...");
            foreach ($nullEndSubs as $sub) {
                $days = max(1, (int) $sub->days);
                $sub->end_date_time    = Carbon::parse($sub->start_date_time)->addDays($days);
                $sub->grace_period_end = Carbon::parse($sub->end_date_time)->addDays(3);
                if (!$isDryRun) {
                    $sub->saveQuietly();
                }
                Log::info('subscriptions:check-expired repaired NULL end_date_time', [
                    'id' => $sub->id, 'days' => $days,
                    'end_date_time' => $sub->end_date_time,
                ]);
            }
            $this->info("  Repaired {$nullEndSubs->count()} subscription(s).");
        }

        // ── Step 1: Find and mark expired subscriptions ──────────────────────────
        // Find subscriptions that are:
        // 1. Status is 'Active'
        // 2. Grace period has ended (or end_date_time if no grace period)
        $expiredSubscriptions = Subscription::where('status', 'Active')
            ->where(function ($query) use ($now) {
                // Check grace period first
                $query->where(function ($q) use ($now) {
                    $q->whereNotNull('grace_period_end')
                      ->where('grace_period_end', '<', $now);
                })
                // Or check end_date_time if no grace period
                ->orWhere(function ($q) use ($now) {
                    $q->whereNull('grace_period_end')
                      ->where('end_date_time', '<', $now);
                });
            })
            ->get();

        $count = $expiredSubscriptions->count();

        if ($count === 0) {
            $this->info('✅ No expired subscriptions found.');
            return 0;
        }

        $this->info("Found {$count} expired subscription(s)");

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $updated = 0;
        $errors = 0;

        foreach ($expiredSubscriptions as $subscription) {
            try {
                if (!$isDryRun) {
                    $subscription->markAsExpired();
                    $updated++;
                }

                // Log the expiration
                Log::info('Subscription expired', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'plan_id' => $subscription->plan_id,
                    'end_date' => $subscription->end_date_time,
                    'dry_run' => $isDryRun,
                ]);

                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->line("  • Subscription #{$subscription->id} (User #{$subscription->user_id}) expired");
                }

            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to mark subscription as expired', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);

                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->error("  • Failed: Subscription #{$subscription->id} - {$e->getMessage()}");
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        if ($isDryRun) {
            $this->info("📊 Would have marked {$count} subscription(s) as expired");
        } else {
            $this->info("✅ Successfully marked {$updated} subscription(s) as expired");
        }

        if ($errors > 0) {
            $this->error("❌ Failed to process {$errors} subscription(s)");
        }

        // Also check for subscriptions still in 'Pending' status for more than 24 hours
        $this->newLine();
        $this->info('🔍 Checking for abandoned pending subscriptions...');

        $abandonedThreshold = now()->subHours(24);
        $abandonedSubscriptions = Subscription::where('status', 'Pending')
            ->where('created_at', '<', $abandonedThreshold)
            ->get();

        $abandonedCount = $abandonedSubscriptions->count();

        if ($abandonedCount > 0) {
            $this->warn("Found {$abandonedCount} abandoned pending subscription(s) (older than 24 hours)");
            
            if (!$isDryRun && $this->confirm('Mark these as failed?', true)) {
                foreach ($abandonedSubscriptions as $subscription) {
                    $subscription->markAsFailed('Payment abandoned - no completion after 24 hours');
                }
                $this->info("✅ Marked {$abandonedCount} abandoned subscription(s) as failed");
            }
        } else {
            $this->info('✅ No abandoned pending subscriptions found');
        }

        return 0;
    }
}
