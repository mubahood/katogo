<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiryMail;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send Expiry Notifications Command
 * 
 * Sends email notifications to users whose subscriptions are expiring soon
 * Should be scheduled to run daily
 * 
 * Usage: php artisan subscriptions:send-expiry-notifications
 */
class SendExpiryNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-expiry-notifications 
                            {--days=3 : Number of days before expiry to send notification}
                            {--dry-run : Run without sending emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send expiry notifications to users with subscriptions expiring soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $days = (int) $this->option('days');

        $this->info("🔍 Checking for subscriptions expiring in {$days} day(s)...");
        
        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE - No emails will be sent');
        }

        $now = now();
        $expiryThreshold = $now->copy()->addDays($days);

        // Find subscriptions that:
        // 1. Are Active
        // 2. Will expire within specified days
        // 3. Haven't received expiry notification yet
        $expiringSubscriptions = Subscription::where('status', 'Active')
            ->where('end_date_time', '<=', $expiryThreshold)
            ->where('end_date_time', '>', $now)
            ->where('expiry_notification_sent', false)
            ->with(['user', 'plan'])
            ->get();

        $count = $expiringSubscriptions->count();

        if ($count === 0) {
            $this->info('✅ No subscriptions requiring expiry notifications.');
            return 0;
        }

        $this->info("Found {$count} subscription(s) expiring soon");

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $sent = 0;
        $errors = 0;

        foreach ($expiringSubscriptions as $subscription) {
            try {
                $user = $subscription->user;
                
                if (!$user || !$user->email) {
                    $this->newLine();
                    $this->warn("  ⚠️  Skipping subscription #{$subscription->id} - User has no email");
                    continue;
                }

                $daysRemaining = $subscription->daysRemaining();

                if (!$isDryRun) {
                    // Send email notification
                    $this->sendExpiryEmail($user, $subscription, $daysRemaining);

                    // Mark notification as sent
                    $subscription->sendExpiryNotification();
                    $sent++;
                }
 

                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->line("  • Sent to {$user->email} (User #{$user->id}) - {$daysRemaining} day(s) remaining");
                }

            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to send expiry notification', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
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
            $this->info("📊 Would have sent {$count} notification(s)");
        } else {
            $this->info("✅ Successfully sent {$sent} notification(s)");
        }

        if ($errors > 0) {
            $this->error("❌ Failed to send {$errors} notification(s)");
        }

        return 0;
    }

    /**
     * Send expiry notification email
     * 
     * @param User $user
     * @param Subscription $subscription
     * @param int $daysRemaining
     */
    protected function sendExpiryEmail($user, $subscription, $daysRemaining)
    {
        // Queue the email so the command does not block on SMTP delivery
        Mail::to($user->email)
            ->queue(new SubscriptionExpiryMail($user, $subscription, $daysRemaining));
    }
}
