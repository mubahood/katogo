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
                'tx_ref'          => $this->txRef,
            ]);
            return;
        }

        Log::info('SolveFLWCaptchaJob: starting', [
            'tx_ref'       => $this->txRef,
            'redirect_url' => substr($this->redirectUrl, 0, 80) . '...',
        ]);

        // Strategy 1: PHP HTTP solver — no browser needed, works on any server
        $httpResult = $this->tryHttpSolve();
        if ($httpResult['ok'] ?? false) {
            Log::info('SolveFLWCaptchaJob: HTTP solver success', [
                'tx_ref'   => $this->txRef,
                'question' => $httpResult['question'] ?? '?',
                'answer'   => $httpResult['answer']   ?? '?',
            ]);
            $this->markAwaitingPin();
            return;
        }

        Log::info('SolveFLWCaptchaJob: HTTP solver did not succeed, trying Puppeteer', [
            'tx_ref'      => $this->txRef,
            'http_reason' => $httpResult['error'] ?? 'unknown',
        ]);

        // Strategy 2: Puppeteer/Node.js headless Chrome
        $scriptPath = base_path('scripts/flw-solve-captcha.mjs');
        $nodeBin    = $this->findNode();

        if (!$nodeBin || !file_exists($scriptPath)) {
            Log::error('SolveFLWCaptchaJob: node or script missing, cannot fall back to Puppeteer', [
                'has_node'   => (bool) $nodeBin,
                'has_script' => file_exists($scriptPath),
            ]);
            $this->markFailed('HTTP solver failed and Puppeteer not available');
            return;
        }

        [$homeDir, $puppeteerCacheDir, $chromeTmp, $ldLibPath] = $this->resolveChromePaths();
        if (!is_dir($chromeTmp)) {
            @mkdir($chromeTmp, 0755, true);
        }

        Log::info('SolveFLWCaptchaJob: launching Puppeteer', [
            'tx_ref'              => $this->txRef,
            'puppeteer_cache_dir' => $puppeteerCacheDir,
            'ld_library_path'     => $ldLibPath ?: 'system',
        ]);

        $env = [
            'HOME'                 => $homeDir,
            'DISPLAY'              => '',
            'PUPPETEER_CACHE_DIR'  => $puppeteerCacheDir,
            'XDG_CONFIG_HOME'      => $chromeTmp,
            'XDG_CACHE_HOME'       => $chromeTmp,
            'TMPDIR'               => $chromeTmp,
        ];
        if ($ldLibPath) {
            $env['LD_LIBRARY_PATH'] = $ldLibPath;
        }

        $process = new Process(
            [$nodeBin, $scriptPath, $this->redirectUrl, $this->txRef],
            base_path(),
            $env,
            null,
            80
        );

        $process->run();

        $output  = trim($process->getOutput());
        $errOut  = trim($process->getErrorOutput());
        $decoded = json_decode($output ?: $errOut, true);

        if ($process->isSuccessful() && ($decoded['ok'] ?? false)) {
            Log::info('SolveFLWCaptchaJob: Puppeteer solver success', [
                'tx_ref'   => $this->txRef,
                'question' => $decoded['question'] ?? '?',
                'answer'   => $decoded['answer']   ?? '?',
            ]);
            $this->markAwaitingPin();
        } else {
            $error = $decoded['error'] ?? $errOut ?: 'Unknown error (exit ' . $process->getExitCode() . ')';
            Log::warning('SolveFLWCaptchaJob: Puppeteer solver failed', [
                'tx_ref' => $this->txRef,
                'error'  => $error,
            ]);
            $this->markFailed($error);
        }
    }

    /**
     * Pure PHP HTTP captcha solver.
     *
     * FLW's math captcha validates the answer CLIENT-SIDE only.
     * The page JS sends a hardcoded ?solution=123456 once the user passes the
     * math check locally — the server only checks the token is valid and unused,
     * not the numeric value. Any solution value is accepted.
     * Confirmed by testing: solution=999, solution=5 both return HTTP 200 "success".
     */
    private function tryHttpSolve(): array
    {
        $ua        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        $submitUrl = $this->redirectUrl . '?solution=123456';

        $ch = curl_init($submitUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_REFERER        => $this->redirectUrl,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json, */*; q=0.01',
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: https://checkout.flutterwave.com',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        Log::info('SolveFLWCaptchaJob: HTTP POST result', [
            'tx_ref'    => $this->txRef,
            'http_code' => $code,
            'response'  => substr((string) $body, 0, 200),
        ]);

        $decoded = json_decode((string) $body, true);

        if ($code === 200 && ($decoded['status'] ?? '') === 'success') {
            return ['ok' => true, 'question' => 'http-direct', 'answer' => 123456];
        }

        return [
            'ok'    => false,
            'error' => ($decoded['message'] ?? "HTTP $code: " . substr((string) $body, 0, 100)),
        ];
    }

    private function markAwaitingPin(): void
    {
        Subscription::where('id', $this->subscriptionId)
            ->whereNotIn('payment_status', ['Completed', 'Active'])
            ->update(['payment_status' => 'AwaitingPIN', 'updated_at' => now()]);
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

        Subscription::where('id', $this->subscriptionId)
            ->where('payment_status', '!=', 'Completed')
            ->update(['payment_status' => 'CaptchaFailed', 'updated_at' => now()]);

        Log::warning('SolveFLWCaptchaJob: marked as CaptchaFailed after all retries', [
            'subscription_id' => $this->subscriptionId,
            'reason'          => $reason,
        ]);
    }

    /**
     * Detect Puppeteer/Chrome paths for the current server environment.
     * Returns [homeDir, puppeteerCacheDir, chromeTmpDir, ldLibraryPath].
     */
    private function resolveChromePaths(): array
    {
        // Hetzner: system-wide Puppeteer cache under /var/cache/puppeteer (no extra LD_LIBRARY_PATH needed)
        if (is_dir('/var/cache/puppeteer')) {
            return ['/var/www', '/var/cache/puppeteer', '/var/www/chrome-tmp', ''];
        }

        // Resolve the home dir of whichever user PHP runs as (muhindo on Namecheap)
        $pwEntry = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
            ? @posix_getpwuid(posix_geteuid())
            : null;
        $homeDir = $pwEntry['dir'] ?? ($_SERVER['HOME'] ?? '/tmp');

        $npmCache  = $homeDir . '/.cache/puppeteer';
        $chromeTmp = is_writable(storage_path()) ? storage_path('chrome-tmp') : '/tmp/chrome-tmp';

        // Namecheap (AlmaLinux): Chrome needs user-space libs extracted to ~/chrome-libs
        $ldLibPath = is_dir($homeDir . '/chrome-libs') ? $homeDir . '/chrome-libs' : '';

        return [$homeDir, $npmCache, $chromeTmp, $ldLibPath];
    }

    private function findNode(): ?string
    {
        // Common Node.js paths on Ubuntu/Debian/AlmaLinux servers and macOS dev
        $candidates = [
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/opt/homebrew/bin/node',
            '/home/katogo/.nvm/versions/node/current/bin/node',
            '/home/muhindo/.nvm/versions/node/current/bin/node',
        ];

        foreach ($candidates as $path) {
            if (is_executable($path)) return $path;
        }

        // Last resort: which node
        $which = trim(shell_exec('which node 2>/dev/null') ?? '');
        return ($which && is_executable($which)) ? $which : null;
    }
}
