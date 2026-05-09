<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\PaymentStatusChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixBouncedSubscriptions extends Command
{
    protected $signature = 'subscriptions:fix-bounced
                            {--limit=200 : Number of latest bounced subscriptions to check}
                            {--age=0 : Only include subscriptions older than X minutes (0 = no age filter)}
                            {--max-retries=2 : Gateway retries per subscription check}
                            {--sleep-ms=250 : Delay between checks in milliseconds}
                            {--gateway=flutterwave : Gateway to check (flutterwave or pesapal)}
                            {--app-type= : Optional app scope (ugflix, lugaflix, muno_app, web)}
                            {--before-id=0 : Only include subscriptions with id lower than this value (resume helper)}
                            {--dry-run : Show what would be checked without calling gateways}';

    protected $description = 'Check latest bounced subscriptions one-by-one and fix statuses from gateway truth';

    public function __construct(private PaymentStatusChecker $statusChecker)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $ageMinutes = max(0, (int) $this->option('age'));
        $maxRetries = max(1, (int) $this->option('max-retries'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $beforeId = max(0, (int) $this->option('before-id'));
        $gateway = strtolower(trim((string) $this->option('gateway') ?: 'flutterwave'));
        $appType = strtolower(trim((string) ($this->option('app-type') ?? '')));
        if (!in_array($gateway, ['flutterwave', 'pesapal'], true)) {
            $this->error('Unsupported gateway option. Use flutterwave or pesapal.');
            return self::FAILURE;
        }
        if ($appType !== '' && !in_array($appType, ['ugflix', 'lugaflix', 'muno_app', 'web'], true)) {
            $this->error('Unsupported app-type option. Use ugflix, lugaflix, muno_app, or web.');
            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');

        $this->newLine();
        $this->info('Starting bounced subscription fix run...');
        $this->line('Mode: ' . ($dryRun ? 'DRY RUN' : 'LIVE'));
        $this->line(
            "Gateway: {$gateway} | App: " . ($appType !== '' ? $appType : 'all')
            . " | Limit: {$limit} | Age: {$ageMinutes} min | Before ID: " . ($beforeId > 0 ? $beforeId : 'none')
            . " | Retries: {$maxRetries} | Sleep: {$sleepMs}ms"
        );

        $query = Subscription::query()
            ->whereIn('status', ['Pending', 'Failed', 'Processing'])
            ->where(function ($q) {
                $q->whereNull('payment_status')
                    ->orWhereIn('payment_status', ['Pending', 'Processing', 'Failed']);
            })
            ->where(function ($q) {
                $q->whereNotNull('pesapal_tracking_id')
                    ->orWhereNotNull('flutterwave_reference')
                    ->orWhereNotNull('pesapal_merchant_reference');
            });

        if ($gateway === 'flutterwave') {
            $query->where(function ($q) {
                $q->whereRaw('LOWER(COALESCE(payment_gateway, "")) = ?', ['flutterwave'])
                    ->orWhereRaw('LOWER(COALESCE(payment_method, "")) = ?', ['flutterwave'])
                    ->orWhereNotNull('flutterwave_reference');
            });
        } else {
            $query->where(function ($q) {
                $q->whereRaw('LOWER(COALESCE(payment_gateway, "")) = ?', ['pesapal'])
                    ->orWhereRaw('LOWER(COALESCE(payment_method, "")) = ?', ['pesapal'])
                    ->orWhereNotNull('pesapal_tracking_id');
            });
        }

        if ($ageMinutes > 0) {
            $query->where('created_at', '<=', now()->subMinutes($ageMinutes));
        }

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        if ($appType !== '') {
            $query->whereRaw('LOWER(COALESCE(app_type, "")) = ?', [$appType]);
        }

        $subscriptions = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('No bounced subscriptions matched your filters.');
            return self::SUCCESS;
        }

        $this->info('Found ' . $subscriptions->count() . ' candidate subscription(s).');

        if ($dryRun) {
            $this->table(
                ['ID', 'User', 'Status', 'Payment', 'Gateway', 'Created'],
                $subscriptions->map(function (Subscription $s) {
                    return [
                        $s->id,
                        $s->user_id,
                        $s->status,
                        $s->payment_status,
                        $s->payment_gateway ?: $s->payment_method,
                        (string) $s->created_at,
                    ];
                })->toArray()
            );

            $this->info('Dry run complete.');
            return self::SUCCESS;
        }

        $stats = [
            'checked' => 0,
            'fixed_activated' => 0,
            'marked_failed' => 0,
            'still_pending_or_processing' => 0,
            'no_change' => 0,
            'errors' => 0,
        ];

        foreach ($subscriptions as $index => $subscription) {
            $stats['checked']++;

            $beforeStatus = (string) $subscription->status;
            $beforePayment = (string) $subscription->payment_status;
            $wasPaidActive = ($beforeStatus === 'Active' && $beforePayment === 'Completed');
            $resolvedGateway = strtolower(trim((string) ($subscription->payment_gateway ?: $subscription->payment_method ?: $gateway)));

            $this->line(sprintf(
                '[%d/%d] Checking #%d (user:%d, status:%s/%s)',
                $stats['checked'],
                $subscriptions->count(),
                $subscription->id,
                (int) $subscription->user_id,
                $beforeStatus,
                $beforePayment
            ));

            if ($resolvedGateway !== $gateway) {
                $this->warn('  -> SKIPPED: gateway is ' . $resolvedGateway . ', not ' . $gateway);
                $stats['no_change']++;
                continue;
            }

            try {
                $result = $this->statusChecker->checkPaymentStatus($subscription, [
                    'max_retries' => $maxRetries,
                    'retry_delay' => 1,
                    'exponential_backoff' => true,
                ]);

                $subscription->refresh();

                $afterStatus = (string) $subscription->status;
                $afterPayment = (string) $subscription->payment_status;
                $isPaidActive = ($afterStatus === 'Active' && $afterPayment === 'Completed');

                if (!$wasPaidActive && $isPaidActive) {
                    $stats['fixed_activated']++;
                    $this->info("  -> FIXED: now {$afterStatus}/{$afterPayment}");
                } elseif ($afterStatus === 'Failed' || $afterPayment === 'Failed') {
                    $stats['marked_failed']++;
                    $this->warn("  -> FAILED: now {$afterStatus}/{$afterPayment}");
                } elseif (in_array($afterStatus, ['Pending', 'Processing', 'Cancelled'], true)
                    || in_array($afterPayment, ['Pending', 'Processing'], true)) {
                    $stats['still_pending_or_processing']++;
                    $this->line("  -> PENDING: now {$afterStatus}/{$afterPayment}");
                } else {
                    $stats['no_change']++;
                    $this->line("  -> NO CHANGE: now {$afterStatus}/{$afterPayment}");
                }

                if (!($result['success'] ?? false) && isset($result['error'])) {
                    $stats['errors']++;
                    $this->error('  -> ERROR: ' . (string) $result['error']);
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->error('  -> EXCEPTION: ' . $e->getMessage());

                Log::error('subscriptions:fix-bounced failed on subscription', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($sleepMs > 0 && $index < $subscriptions->count() - 1) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        $this->info('Bounced fix summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Gateway', $gateway],
                ['App Type', $appType !== '' ? $appType : 'all'],
                ['Before ID', $beforeId > 0 ? $beforeId : 'none'],
                ['Checked', $stats['checked']],
                ['Fixed -> Active/Completed', $stats['fixed_activated']],
                ['Marked/Still Failed', $stats['marked_failed']],
                ['Still Pending/Processing', $stats['still_pending_or_processing']],
                ['No Change', $stats['no_change']],
                ['Errors', $stats['errors']],
            ]
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
