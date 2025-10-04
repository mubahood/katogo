<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Check Pending Payments Command
 * 
 * Checks pending subscription payments with Pesapal
 * Useful for catching payments that were completed but callback failed
 * Should be scheduled to run every 15 minutes or hourly
 * 
 * Usage: php artisan subscriptions:check-pending-payments
 */
class CheckPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-pending-payments 
                            {--age=15 : Check payments older than X minutes}
                            {--limit=50 : Maximum number of subscriptions to check}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check pending subscription payments status with Pesapal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $ageMinutes = (int) $this->option('age');
        $limit = (int) $this->option('limit');

        $this->info("🔍 Checking pending subscription payments (older than {$ageMinutes} minutes)...");
        
        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
        }

        $threshold = now()->subMinutes($ageMinutes);

        // Find subscriptions with pending payment
        $pendingSubscriptions = Subscription::where('status', 'Pending')
            ->where('payment_status', 'Pending')
            ->whereNotNull('pesapal_tracking_id')
            ->where('created_at', '<', $threshold)
            ->where('created_at', '>', now()->subDays(7)) // Don't check subscriptions older than 7 days
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $count = $pendingSubscriptions->count();

        if ($count === 0) {
            $this->info('✅ No pending subscriptions to check.');
            return 0;
        }

        $this->info("Found {$count} pending subscription(s) to check");

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $activated = 0;
        $stillPending = 0;
        $failed = 0;
        $errors = 0;

        foreach ($pendingSubscriptions as $subscription) {
            try {
                if (!$isDryRun) {
                    // Check status with Pesapal
                    $status = $this->checkPaymentStatus($subscription);

                    if ($status['success']) {
                        $paymentStatus = $status['status'] ?? null;

                        if ($paymentStatus === 'COMPLETED' || $paymentStatus === 'Completed') {
                            // Payment was successful - activate subscription
                            $subscription->activate();
                            $activated++;

                            Log::info('Pending subscription activated after status check', [
                                'subscription_id' => $subscription->id,
                                'pesapal_tracking_id' => $subscription->pesapal_tracking_id,
                            ]);

                            if ($this->getOutput()->isVerbose()) {
                                $this->newLine();
                                $this->info("  ✅ Activated: Subscription #{$subscription->id}");
                            }

                        } elseif ($paymentStatus === 'FAILED' || $paymentStatus === 'INVALID') {
                            // Payment failed
                            $subscription->markAsFailed('Payment verification failed');
                            $failed++;

                            Log::warning('Pending subscription marked as failed after status check', [
                                'subscription_id' => $subscription->id,
                                'pesapal_tracking_id' => $subscription->pesapal_tracking_id,
                                'status' => $paymentStatus,
                            ]);

                            if ($this->getOutput()->isVerbose()) {
                                $this->newLine();
                                $this->warn("  ❌ Failed: Subscription #{$subscription->id}");
                            }

                        } else {
                            // Still pending
                            $stillPending++;

                            if ($this->getOutput()->isVerbose()) {
                                $this->newLine();
                                $this->line("  ⏳ Still Pending: Subscription #{$subscription->id}");
                            }
                        }
                    } else {
                        // Error checking status
                        $errors++;
                        Log::error('Failed to check subscription payment status', [
                            'subscription_id' => $subscription->id,
                            'error' => $status['error'] ?? 'Unknown error',
                        ]);
                    }
                } else {
                    // Dry run - just log
                    Log::info('Dry run - would check payment status', [
                        'subscription_id' => $subscription->id,
                        'pesapal_tracking_id' => $subscription->pesapal_tracking_id,
                    ]);
                }

            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to process pending subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->error("  • Error: Subscription #{$subscription->id} - {$e->getMessage()}");
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Activated', $activated],
                ['Still Pending', $stillPending],
                ['Failed', $failed],
                ['Errors', $errors],
                ['Total Checked', $count],
            ]
        );

        if ($isDryRun) {
            $this->info('⚠️  DRY RUN - No actual changes were made');
        }

        return 0;
    }

    /**
     * Check payment status with Pesapal
     * 
     * @param Subscription $subscription
     * @return array
     */
    protected function checkPaymentStatus($subscription)
    {
        try {
            $pesapalService = app(\App\Services\SubscriptionPesapalService::class);
            $result = $pesapalService->getTransactionStatus($subscription->pesapal_tracking_id);

            if ($result['success']) {
                // Extract status from response
                $statusData = $result['data'];
                $statusCode = $statusData['status_code'] ?? $statusData['payment_status_code'] ?? null;
                $paymentStatus = $statusData['payment_status_description'] ?? $statusData['status'] ?? 'PENDING';

                // Map status code to readable status
                if ($statusCode == 1 || strtolower($paymentStatus) === 'completed') {
                    return ['success' => true, 'status' => 'COMPLETED', 'data' => $statusData];
                } elseif ($statusCode == 2 || in_array(strtolower($paymentStatus), ['failed', 'invalid'])) {
                    return ['success' => true, 'status' => 'FAILED', 'data' => $statusData];
                } else {
                    return ['success' => true, 'status' => 'PENDING', 'data' => $statusData];
                }
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
