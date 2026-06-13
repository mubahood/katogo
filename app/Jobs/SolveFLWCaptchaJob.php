<?php

namespace App\Jobs;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Solves the Flutterwave math captcha headlessly using a Node.js/Puppeteer subprocess,
 * then triggers the USSD push to the customer's phone.
 *
 * Dispatched immediately after Flutterwave returns a redirect URL during payment init.
 * The app meanwhile shows "Check your phone" — no redirect URL or WebView needed.
 */
class SolveFLWCaptchaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 3;
    public int $timeout = 90;

    public function __construct(
        public readonly int    $subscriptionId,
        public readonly string $redirectUrl,
        public readonly string $txRef,
    ) {}

    public function backoff(): array
    {
        return [10, 30]; // retry after 10s, then 30s
    }

    public function handle(): void
    {
        // Idempotency: skip if subscription is already past captcha stage or completed
        $subscription = Subscription::find($this->subscriptionId);
        if (!$subscription) {
            Log::warning('SolveFLWCaptchaJob: subscription not found', ['id' => $this->subscriptionId]);
            return;
        }
        if (in_array($subscription->payment_status, ['Completed', 'AwaitingPIN', 'Active'], true)) {
            Log::info('SolveFLWCaptchaJob: skipping, already ' . $subscription->payment_status, [
                'subscription_id' => $this->subscriptionId,
                'tx_ref' => $this->txRef,
            ]);
            return;
        }

        $scriptPath = base_path('scripts/flw-solve-captcha.mjs');
        $nodeBin    = $this->findNode();

        if (!$nodeBin) {
            Log::error('SolveFLWCaptchaJob: node not found', ['tx_ref' => $this->txRef]);
            $this->markFailed('Node.js not available on server');
            return;
        }

        if (!file_exists($scriptPath)) {
            Log::error('SolveFLWCaptchaJob: solver script missing', ['path' => $scriptPath]);
            $this->markFailed('Captcha solver script not found');
            return;
        }

        Log::info('SolveFLWCaptchaJob: starting', [
            'tx_ref'       => $this->txRef,
            'redirect_url' => substr($this->redirectUrl, 0, 80) . '...',
        ]);

        // Chromium cache is in /var/cache/puppeteer (www-data-owned).
        // HOME=/var/www so www-data can write temp files; PUPPETEER_CACHE_DIR points to the pre-installed binary.
        $process = new Process(
            [$nodeBin, $scriptPath, $this->redirectUrl, $this->txRef],
            base_path(),
            [
                'HOME'                 => '/var/www',
                'DISPLAY'              => '',
                'PUPPETEER_CACHE_DIR'  => '/var/cache/puppeteer',
            ],
            null,
            80  // 80-second timeout (job timeout is 90)
        );

        $process->run();

        $output  = trim($process->getOutput());
        $errOut  = trim($process->getErrorOutput());
        $decoded = json_decode($output ?: $errOut, true);

        if ($process->isSuccessful() && ($decoded['ok'] ?? false)) {
            Log::info('SolveFLWCaptchaJob: captcha solved, USSD triggered', [
                'tx_ref'   => $this->txRef,
                'question' => $decoded['question'] ?? '?',
                'answer'   => $decoded['answer']   ?? '?',
            ]);

            // Mark subscription as "awaiting PIN" so the app can show the right message
            $subscription->where('id', $this->subscriptionId)
                ->where('payment_status', '!=', 'Completed')
                ->update([
                    'payment_status' => 'AwaitingPIN',
                    'updated_at'     => now(),
                ]);
        } else {
            $error = $decoded['error'] ?? $errOut ?: 'Unknown error (exit ' . $process->getExitCode() . ')';
            Log::warning('SolveFLWCaptchaJob: captcha solve failed', [
                'tx_ref' => $this->txRef,
                'error'  => $error,
                'output' => $output,
            ]);
            $this->markFailed($error);
        }
    }

    private function markFailed(string $reason): void
    {
        // Only mark CaptchaFailed on the last attempt — earlier attempts will retry
        if ($this->attempts() < $this->tries) {
            Log::info('SolveFLWCaptchaJob: attempt ' . $this->attempts() . '/' . $this->tries . ' failed, will retry', [
                'tx_ref' => $this->txRef,
                'reason' => $reason,
            ]);
            $this->release($this->backoff()[$this->attempts() - 1] ?? 30);
            return;
        }

        // Mark as CaptchaFailed so app fallback flow knows to use WebView
        Subscription::where('id', $this->subscriptionId)
            ->where('payment_status', '!=', 'Completed')
            ->update([
                'payment_status' => 'CaptchaFailed',
                'updated_at'     => now(),
            ]);

        Log::warning('SolveFLWCaptchaJob: marked as CaptchaFailed', [
            'subscription_id' => $this->subscriptionId,
            'reason'          => $reason,
        ]);
    }

    private function findNode(): ?string
    {
        // Common Node.js paths on Ubuntu/Debian servers and macOS dev
        $candidates = [
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/opt/homebrew/bin/node',
            '/home/katogo/.nvm/versions/node/current/bin/node',
        ];

        foreach ($candidates as $path) {
            if (is_executable($path)) return $path;
        }

        // Last resort: which node
        $which = trim(shell_exec('which node 2>/dev/null') ?? '');
        return ($which && is_executable($which)) ? $which : null;
    }
}
