<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * RepairSubscriptions Command
 *
 * Scans all subscriptions for corrupted or inconsistent data and repairs them.
 * Safe to run multiple times (idempotent). Run after any code fixes to clean
 * up legacy bad data, and daily via the scheduler as a safety net.
 *
 * Usage:
 *   php artisan subscriptions:repair             # live repair
 *   php artisan subscriptions:repair --dry-run   # preview only, no DB writes
 *   php artisan subscriptions:repair -v          # verbose row-level output
 */
class RepairSubscriptions extends Command
{
    protected $signature = 'subscriptions:repair
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Repair corrupted subscription dates and sync statuses';

    /** Counters */
    private int $fixedNullEnd      = 0;
    private int $fixedWrongEnd     = 0;
    private int $fixedNullStart    = 0;
    private int $fixedPendingDates = 0;
    private int $markedExpired     = 0;
    private int $errors            = 0;

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $this->info('');
        $this->info('════════════════════════════════════════════════════════');
        $this->info('  Subscription Repair — ' . ($dry ? 'DRY RUN (no changes)' : 'LIVE MODE'));
        $this->info('════════════════════════════════════════════════════════');
        $this->info('');

        // Run repairs in a safe order: fix dates first, then mark expired.
        $this->repairNullStartDate($dry);
        $this->repairNullEndDate($dry);
        $this->repairWrongEndDate($dry);
        $this->repairPendingPresetDates($dry);
        $this->markTrulyExpired($dry);

        $this->printSummary($dry);

        return 0;
    }

    // ────────────────────────────────────────────────────────────────────
    // Step 1 — Active subscriptions with NULL start_date_time
    // ────────────────────────────────────────────────────────────────────

    private function repairNullStartDate(bool $dry): void
    {
        $this->info('1/5 Checking Active subscriptions with NULL start_date_time...');

        $subs = Subscription::where('status', 'Active')
            ->whereNull('start_date_time')
            ->get();

        if ($subs->isEmpty()) {
            $this->line('     ✅ None found.');
            return;
        }

        $this->warn("     Found {$subs->count()} record(s).");

        foreach ($subs as $sub) {
            try {
                // Use payment_confirmed_at if available, otherwise created_at, otherwise now
                $start = $sub->payment_confirmed_at ?? $sub->created_at ?? now();
                $days  = max(1, (int) $sub->days);

                $sub->start_date_time  = $start;
                $sub->end_date_time    = Carbon::parse($start)->addDays($days);
                $sub->grace_period_end = Carbon::parse($sub->end_date_time)->addDays(3);

                if ($this->getOutput()->isVerbose()) {
                    $this->line("     → #{$sub->id}: start={$sub->start_date_time} end={$sub->end_date_time}");
                }

                if (!$dry) {
                    $sub->saveQuietly();
                }

                $this->fixedNullStart++;

                Log::info('subscriptions:repair fixed null start_date_time', [
                    'id' => $sub->id, 'start' => $sub->start_date_time,
                    'end' => $sub->end_date_time, 'dry' => $dry,
                ]);
            } catch (\Throwable $e) {
                $this->errors++;
                Log::error('subscriptions:repair error on null start', ['id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->line("     Fixed {$this->fixedNullStart} record(s).");
    }

    // ────────────────────────────────────────────────────────────────────
    // Step 2 — Active subscriptions with NULL end_date_time
    // ────────────────────────────────────────────────────────────────────

    private function repairNullEndDate(bool $dry): void
    {
        $this->info('2/5 Checking Active subscriptions with NULL end_date_time...');

        $subs = Subscription::where('status', 'Active')
            ->whereNull('end_date_time')
            ->whereNotNull('start_date_time')
            ->get();

        if ($subs->isEmpty()) {
            $this->line('     ✅ None found.');
            return;
        }

        $this->warn("     Found {$subs->count()} record(s).");

        foreach ($subs as $sub) {
            try {
                $days = max(1, (int) $sub->days);

                $sub->end_date_time    = Carbon::parse($sub->start_date_time)->addDays($days);
                $sub->grace_period_end = Carbon::parse($sub->end_date_time)->addDays(3);

                if ($this->getOutput()->isVerbose()) {
                    $this->line("     → #{$sub->id}: end={$sub->end_date_time} grace={$sub->grace_period_end}");
                }

                if (!$dry) {
                    $sub->saveQuietly();
                }

                $this->fixedNullEnd++;

                Log::info('subscriptions:repair fixed null end_date_time', [
                    'id' => $sub->id, 'end' => $sub->end_date_time, 'dry' => $dry,
                ]);
            } catch (\Throwable $e) {
                $this->errors++;
                Log::error('subscriptions:repair error on null end', ['id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->line("     Fixed {$this->fixedNullEnd} record(s).");
    }

    // ────────────────────────────────────────────────────────────────────
    // Step 3 — Active subscriptions with clearly wrong end_date_time
    //
    // "Wrong" means the actual difference between end and start is MORE THAN
    // 10 days beyond the stored plan duration (corrupted by admin scripts),
    // OR the end is before the start (impossible).
    // ────────────────────────────────────────────────────────────────────

    private function repairWrongEndDate(bool $dry): void
    {
        $this->info('3/5 Checking Active subscriptions with wrong end_date_time...');

        $subs = Subscription::where('status', 'Active')
            ->whereNotNull('start_date_time')
            ->whereNotNull('end_date_time')
            ->get()
            ->filter(function ($sub) {
                $storedDays  = max(1, (int) $sub->days);
                $actualDays  = (int) Carbon::parse($sub->start_date_time)
                    ->diffInDays(Carbon::parse($sub->end_date_time), false);

                // Flag if actual duration is more than 10 days off from stored days,
                // or if end_date_time is before start_date_time (negative diff).
                return $actualDays < 0 || abs($actualDays - $storedDays) > 10;
            });

        if ($subs->isEmpty()) {
            $this->line('     ✅ None found.');
            return;
        }

        $this->warn("     Found {$subs->count()} record(s).");

        foreach ($subs as $sub) {
            try {
                $days = max(1, (int) $sub->days);
                $oldEnd = $sub->end_date_time;

                $sub->end_date_time    = Carbon::parse($sub->start_date_time)->addDays($days);
                $sub->grace_period_end = Carbon::parse($sub->end_date_time)->addDays(3);

                if ($this->getOutput()->isVerbose()) {
                    $this->line("     → #{$sub->id}: old_end={$oldEnd} new_end={$sub->end_date_time} (days={$days})");
                }

                if (!$dry) {
                    $sub->saveQuietly();
                }

                $this->fixedWrongEnd++;

                Log::info('subscriptions:repair fixed wrong end_date_time', [
                    'id' => $sub->id, 'old_end' => $oldEnd,
                    'new_end' => $sub->end_date_time, 'days' => $days, 'dry' => $dry,
                ]);
            } catch (\Throwable $e) {
                $this->errors++;
                Log::error('subscriptions:repair error on wrong end', ['id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->line("     Fixed {$this->fixedWrongEnd} record(s).");
    }

    // ────────────────────────────────────────────────────────────────────
    // Step 4 — Pending subscriptions that have dates pre-set
    //
    // Pending means payment hasn't been confirmed yet.
    // Pre-set dates are wrong — they should be NULL until payment succeeds.
    // ────────────────────────────────────────────────────────────────────

    private function repairPendingPresetDates(bool $dry): void
    {
        $this->info('4/5 Checking Pending subscriptions with pre-set dates...');

        $subs = Subscription::whereIn('status', ['Pending', 'Processing'])
            ->where(function ($q) {
                $q->whereNotNull('start_date_time')
                  ->orWhereNotNull('end_date_time');
            })
            ->get();

        if ($subs->isEmpty()) {
            $this->line('     ✅ None found.');
            return;
        }

        $this->warn("     Found {$subs->count()} record(s) — clearing pre-set dates.");

        foreach ($subs as $sub) {
            try {
                if ($this->getOutput()->isVerbose()) {
                    $this->line("     → #{$sub->id}: clearing start={$sub->start_date_time} end={$sub->end_date_time}");
                }

                $sub->start_date_time  = null;
                $sub->end_date_time    = null;
                $sub->grace_period_end = null;

                if (!$dry) {
                    $sub->saveQuietly();
                }

                $this->fixedPendingDates++;

                Log::info('subscriptions:repair cleared pending pre-set dates', [
                    'id' => $sub->id, 'dry' => $dry,
                ]);
            } catch (\Throwable $e) {
                $this->errors++;
                Log::error('subscriptions:repair error on pending dates', ['id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->line("     Cleared {$this->fixedPendingDates} record(s).");
    }

    // ────────────────────────────────────────────────────────────────────
    // Step 5 — Mark truly expired Active subscriptions as Expired
    //
    // Runs AFTER date repairs so we don't accidentally expire a subscription
    // whose end_date_time was just now set correctly to the future.
    // ────────────────────────────────────────────────────────────────────

    private function markTrulyExpired(bool $dry): void
    {
        $this->info('5/5 Marking expired Active subscriptions...');

        $now  = now();
        $subs = Subscription::where('status', 'Active')
            ->where(function ($q) use ($now) {
                // Grace period ended
                $q->where(function ($inner) use ($now) {
                    $inner->whereNotNull('grace_period_end')
                          ->where('grace_period_end', '<', $now);
                })
                // Or no grace period and end_date_time is past
                ->orWhere(function ($inner) use ($now) {
                    $inner->whereNull('grace_period_end')
                          ->whereNotNull('end_date_time')
                          ->where('end_date_time', '<', $now);
                });
            })
            ->get();

        if ($subs->isEmpty()) {
            $this->line('     ✅ None found.');
            return;
        }

        $this->warn("     Found {$subs->count()} expired Active subscription(s).");

        foreach ($subs as $sub) {
            try {
                if ($this->getOutput()->isVerbose()) {
                    $this->line("     → #{$sub->id}: end={$sub->end_date_time} grace={$sub->grace_period_end}");
                }

                if (!$dry) {
                    $sub->status = 'Expired';
                    $sub->saveQuietly();
                }

                $this->markedExpired++;

                Log::info('subscriptions:repair marked as Expired', [
                    'id' => $sub->id, 'end' => $sub->end_date_time, 'dry' => $dry,
                ]);
            } catch (\Throwable $e) {
                $this->errors++;
                Log::error('subscriptions:repair error marking expired', ['id' => $sub->id, 'error' => $e->getMessage()]);
            }
        }

        $this->line("     Marked {$this->markedExpired} subscription(s) as Expired.");
    }

    // ────────────────────────────────────────────────────────────────────
    // Summary
    // ────────────────────────────────────────────────────────────────────

    private function printSummary(bool $dry): void
    {
        $this->info('');
        $this->info('════════════════════════════════════════════════════════');
        $this->info('  Repair Summary' . ($dry ? ' (DRY RUN)' : ''));
        $this->info('════════════════════════════════════════════════════════');
        $this->line("  Fixed null start_date_time  : {$this->fixedNullStart}");
        $this->line("  Fixed null end_date_time    : {$this->fixedNullEnd}");
        $this->line("  Fixed wrong end_date_time   : {$this->fixedWrongEnd}");
        $this->line("  Cleared pending pre-set dates: {$this->fixedPendingDates}");
        $this->line("  Marked as Expired           : {$this->markedExpired}");

        if ($this->errors > 0) {
            $this->error("  Errors                      : {$this->errors}");
        }

        $this->info('════════════════════════════════════════════════════════');

        $totalFixed = $this->fixedNullStart + $this->fixedNullEnd + $this->fixedWrongEnd
            + $this->fixedPendingDates + $this->markedExpired;

        if ($dry) {
            $this->warn("  DRY RUN: would have repaired {$totalFixed} record(s). Run without --dry-run to apply.");
        } else {
            $this->info("  Done. Repaired/updated {$totalFixed} record(s).");
        }
        $this->info('');
    }
}
