<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Services\SubscriptionFlutterwaveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile stuck Pending Flutterwave transactions.
 *
 * The webhook was never configured in the Flutterwave dashboard, so payment
 * confirmations never arrived and transactions piled up in Pending forever
 * (12,000+ across all apps as of Jul 2026). This command polls Flutterwave's
 * verify API for each pending transaction and lets the existing service
 * activate / fail them exactly as the webhook would have.
 *
 * - Completed on Flutterwave  → subscription activated, transaction Completed
 * - Failed/cancelled          → transaction Failed
 * - Not found & older than --expire-days → marked Failed (customer never
 *   authorized the mobile-money prompt; Flutterwave discarded it)
 * - Not found & recent        → left Pending (customer may still be typing PIN)
 *
 * Scheduled every 15 minutes (newest first). For backlog cleanup run manually:
 *   php artisan subscriptions:check-pending-flutterwave --days=90 --limit=500
 */
class CheckPendingFlutterwave extends Command
{
    protected $signature = 'subscriptions:check-pending-flutterwave
                            {--age=10 : Only check transactions older than X minutes}
                            {--days=14 : Look back this many days}
                            {--limit=100 : Max transactions to check per run}
                            {--expire-days=3 : Mark not-found transactions older than this as Failed}
                            {--dry-run : List what would be checked without calling the API}';

    protected $description = 'Poll Flutterwave verify API for stuck Pending transactions and settle them';

    public function handle(SubscriptionFlutterwaveService $service): int
    {
        $age        = (int) $this->option('age');
        $days       = (int) $this->option('days');
        $limit      = (int) $this->option('limit');
        $expireDays = (int) $this->option('expire-days');
        $dryRun     = (bool) $this->option('dry-run');

        $pending = SubscriptionTransaction::where('payment_method', 'flutterwave')
            ->where('status', 'Pending')
            ->whereNotNull('merchant_reference')
            ->where('merchant_reference', '!=', '')
            ->where('created_at', '<', now()->subMinutes($age))
            ->where('created_at', '>', now()->subDays($days))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $total = $pending->count();
        $this->info("Found {$total} pending Flutterwave transaction(s) in the last {$days} day(s)." . ($dryRun ? ' [DRY RUN]' : ''));

        if ($total === 0) {
            return 0;
        }

        if ($dryRun) {
            foreach ($pending as $tx) {
                $this->line("  • Tx #{$tx->id} sub={$tx->subscription_id} user={$tx->user_id} {$tx->amount} ref={$tx->merchant_reference} ({$tx->created_at})");
            }
            return 0;
        }

        $activated = 0;
        $failed    = 0;
        $expired   = 0;
        $stillPending = 0;
        $errors    = 0;

        foreach ($pending as $tx) {
            try {
                $result = $service->processCallbackWithFallback($tx->merchant_reference);
                $status = $result['status'] ?? 'unknown';

                if ($status === 'success') {
                    $activated++;
                    $this->line("  ✓ Tx #{$tx->id} ({$tx->amount}) COMPLETED — subscription activated");
                } elseif ($status === 'pending') {
                    // Flutterwave has no record (customer never authorized the prompt).
                    // If it is old enough, close it out as Failed.
                    if ($tx->created_at->lt(now()->subDays($expireDays))) {
                        $this->expireTransaction($tx);
                        $expired++;
                    } else {
                        $stillPending++;
                    }
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('[CheckPendingFlutterwave] Verify error', [
                    'transaction_id' => $tx->id,
                    'tx_ref' => $tx->merchant_reference,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::table('subscription_transactions')
                ->where('id', $tx->id)
                ->increment('number_of_times_checked');

            usleep(200_000); // 200ms between API calls
        }

        $this->newLine();
        $this->table(
            ['Outcome', 'Count'],
            [
                ['Activated (payment found)', $activated],
                ['Failed at gateway', $failed],
                ['Expired (never authorized)', $expired],
                ['Still pending (recent)', $stillPending],
                ['Errors', $errors],
                ['Total checked', $total],
            ]
        );

        Log::info('[CheckPendingFlutterwave] Run complete', [
            'total' => $total, 'activated' => $activated, 'failed' => $failed,
            'expired' => $expired, 'still_pending' => $stillPending, 'errors' => $errors,
        ]);

        return 0;
    }

    /**
     * Close out a transaction Flutterwave has no record of: the customer was
     * shown the mobile-money prompt but never entered a PIN, and the gateway
     * discarded it. Never touches subscriptions that already went Active.
     */
    private function expireTransaction(SubscriptionTransaction $tx): void
    {
        $tx->status = 'Failed';
        $tx->error_message = 'Expired — payment was never authorized by the customer (no record at Flutterwave).';
        $tx->save();

        $subscription = Subscription::find($tx->subscription_id);
        if ($subscription
            && $subscription->status === 'Pending'
            && $subscription->payment_status !== 'Completed'
        ) {
            $subscription->status = 'Failed';
            $subscription->payment_status = 'Failed';
            $subscription->failed_at = now();
            $subscription->payment_failure_reason = 'Transaction has been expired.';
            $subscription->save();
        }

        $this->line("  ✗ Tx #{$tx->id} ({$tx->amount}) expired — never authorized");
    }
}
