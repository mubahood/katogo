<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\PaymentStatusChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Check Pending Payments Command
 * 
 * Checks pending subscription payments with Pesapal
 * Useful for catching payments that were completed but callback failed
 * Should be scheduled to run every 15 minutes or hourly
 * 
 * ENHANCED: Now uses robust PaymentStatusChecker service with retry logic
 * 
 * Usage: php artisan subscriptions:check-pending-payments
 */
class CheckPendingPayments extends Command
{
    protected $statusChecker;

    public function __construct(PaymentStatusChecker $statusChecker)
    {
        parent::__construct();
        $this->statusChecker = $statusChecker;
    }
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
            
            // In dry run, just list what would be checked
            $threshold = now()->subMinutes($ageMinutes);
            $pendingSubscriptions = Subscription::where('status', 'Pending')
                ->where('payment_status', 'Pending')
                ->whereNotNull('pesapal_tracking_id')
                ->where('created_at', '<', $threshold)
                ->where('created_at', '>', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $count = $pendingSubscriptions->count();
            $this->info("Would check {$count} subscription(s)");
            
            foreach ($pendingSubscriptions as $sub) {
                $this->line("  • Subscription #{$sub->id} (User: {$sub->user_id}, Created: {$sub->created_at})");
            }
            
            return 0;
        }

        // ENHANCED: Use PaymentStatusChecker service with bulk check
        $results = $this->statusChecker->checkPendingPayments([
            'age_minutes' => $ageMinutes,
            'limit' => $limit,
            'max_age_hours' => 720, // 30 days — covers AwaitingPIN subs missed by webhook
        ]);

        // Display results
        $this->newLine();
        $this->info('📊 Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Activated', $results['activated']],
                ['Still Pending', $results['still_pending']],
                ['Failed', $results['failed']],
                ['Errors', $results['errors']],
                ['Total Checked', $results['total']],
            ]
        );

        // Show detailed errors if any
        if ($results['errors'] > 0 && $this->getOutput()->isVerbose()) {
            $this->newLine();
            $this->warn('Errors encountered:');
            foreach ($results['details'] as $detail) {
                if (isset($detail['error'])) {
                    $this->error("  • Subscription #{$detail['subscription_id']}: {$detail['error']}");
                }
            }
        }

        return 0;
    }
}
