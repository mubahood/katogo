<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FlwTestDirectCharge extends Command
{
    protected $signature = 'flw:test
                            {--phone=0761103575 : Phone number to charge}
                            {--amount=1000 : Amount in UGX}
                            {--network= : MTN or AIRTEL (auto-detected if omitted)}
                            {--verify= : Skip charge — just verify a tx_ref}
                            {--v3 : Use v3 redirect mode instead of v4 direct push}';

    protected $description = 'Test Flutterwave direct Uganda mobile money charge (v4 pure push by default)';

    private string $v3BaseUrl;
    private string $v4BaseUrl;
    private string $secretKey;

    public function handle(): int
    {
        $this->v3BaseUrl = 'https://api.flutterwave.com/v3';
        $this->v4BaseUrl = 'https://api.flutterwave.com';
        $this->secretKey = (string) config('flutterwave.secret_key', '');

        if (empty($this->secretKey)) {
            $this->error('FLW_SECRET_KEY not set in .env');
            return 1;
        }

        if ($txRef = $this->option('verify')) {
            return $this->runVerify((string) $txRef);
        }

        $rawPhone = (string) $this->option('phone');
        $amount   = (int) $this->option('amount');
        $phone    = $this->normalizePhone($rawPhone);

        if ($phone === '') {
            $this->error("Cannot parse phone number: {$rawPhone}");
            return 1;
        }

        $network = strtoupper((string) ($this->option('network') ?: $this->detectNetwork($phone)));

        if ($this->option('v3')) {
            return $this->runV3Charge($phone, $network, $amount);
        }

        return $this->runV4DirectPush($phone, $network, $amount);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // V4 — TRUE DIRECT PUSH (no redirect URL, USSD fires immediately)
    // ──────────────────────────────────────────────────────────────────────────

    private function runV4DirectPush(string $phone, string $network, int $amount): int
    {
        $txRef = 'KTG-TEST-' . strtoupper(Str::random(6)) . '-' . time();

        $this->line('');
        $this->line('  <fg=cyan>Flutterwave V4 Direct Push Test</>');
        $this->line('  (No URL — USSD fires straight to the phone)');
        $this->line('  ─────────────────────────────────────────');
        $this->line("  Phone   : <fg=yellow>{$phone}</>");
        $this->line("  Network : <fg=yellow>{$network}</>");
        $this->line("  Amount  : <fg=yellow>UGX " . number_format($amount) . "</>");
        $this->line("  tx_ref  : <fg=yellow>{$txRef}</>");
        $this->line('');

        if (!$this->confirm('Proceed? (a USSD push will be sent directly to the phone)', true)) {
            $this->line('Cancelled.');
            return 0;
        }

        // ── Step 1: Create customer ──────────────────────────────────────────
        $this->line('');
        $this->line('  [1/3] Creating customer...');

        $localNumber = substr($phone, 3); // strip 256 → 9-digit local number

        $custResp = $this->v4Post('/customers', [
            'email' => 'test@mruodel.com',
            'name'  => [
                'first' => 'Test',
                'last'  => 'User',
            ],
            'phone' => [
                'country_code' => '256',
                'number'       => $localNumber,
            ],
        ]);

        if (!$custResp['ok']) {
            $this->error("  Customer creation failed: " . $custResp['message']);
            $this->line('  Response: ' . json_encode($custResp['body'], JSON_PRETTY_PRINT));
            return 1;
        }

        $customerId = $custResp['body']['data']['id'] ?? null;
        if (empty($customerId)) {
            $this->error("  No customer ID in response.");
            $this->line(json_encode($custResp['body'], JSON_PRETTY_PRINT));
            return 1;
        }

        $this->line("         Customer ID: <fg=green>{$customerId}</>");

        // ── Step 2: Create payment method ────────────────────────────────────
        $this->line('  [2/3] Creating payment method...');

        $pmResp = $this->v4Post('/payment-methods', [
            'type'         => 'mobile_money',
            'mobile_money' => [
                'country_code' => '256',
                'network'      => $network,
                'phone_number' => $localNumber,
            ],
        ]);

        if (!$pmResp['ok']) {
            $this->error("  Payment method creation failed: " . $pmResp['message']);
            $this->line('  Response: ' . json_encode($pmResp['body'], JSON_PRETTY_PRINT));
            return 1;
        }

        $paymentMethodId = $pmResp['body']['data']['id'] ?? null;
        if (empty($paymentMethodId)) {
            $this->error("  No payment method ID in response.");
            $this->line(json_encode($pmResp['body'], JSON_PRETTY_PRINT));
            return 1;
        }

        $this->line("         Payment Method ID: <fg=green>{$paymentMethodId}</>");

        // ── Step 3: Create charge ─────────────────────────────────────────────
        $this->line('  [3/3] Initiating charge (direct push)...');

        $chargeResp = $this->v4Post('/charges', [
            'customer_id'       => $customerId,
            'payment_method_id' => $paymentMethodId,
            'amount'            => $amount,
            'currency'          => 'UGX',
            'reference'         => $txRef,
        ]);

        if (!$chargeResp['ok']) {
            $this->error("  Charge failed: " . $chargeResp['message']);
            $this->line('  Response: ' . json_encode($chargeResp['body'], JSON_PRETTY_PRINT));
            return 1;
        }

        $chargeData  = $chargeResp['body']['data'] ?? [];
        $chargeId    = $chargeData['id'] ?? null;
        $chargeStatus = $chargeData['status'] ?? '—';
        $nextAction  = $chargeData['next_action'] ?? [];
        $actionType  = $nextAction['type'] ?? '—';
        $note        = $nextAction['payment_instruction']['note'] ?? null;

        $this->line('');
        $this->line('  ┌──────────────────────────────────────────────────────────┐');
        $this->line('  │  USSD push sent — check your phone now                   │');
        $this->line('  │  Enter your mobile money PIN when prompted               │');
        $this->line('  └──────────────────────────────────────────────────────────┘');
        $this->line('');
        $this->line("  Charge ID  : <fg=green>{$chargeId}</>");
        $this->line("  Status     : <fg=yellow>{$chargeStatus}</>");
        $this->line("  Next action: {$actionType}");
        if ($note) {
            $this->line("  Note       : {$note}");
        }
        $this->line('');
        $this->line("  tx_ref (for verification): <fg=yellow>{$txRef}</>");
        $this->line('');
        $this->line('  After entering PIN, verify with:');
        $this->line("  <fg=cyan>php artisan flw:test --verify={$txRef}</>");
        $this->line('');

        if ($this->confirm('Auto-poll for confirmation? (every 8s, up to 3 min)', true)) {
            $this->pollForConfirmation($txRef);
        }

        return 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // V3 — Redirect mode (fallback, kept for comparison)
    // ──────────────────────────────────────────────────────────────────────────

    private function runV3Charge(string $phone, string $network, int $amount): int
    {
        $txRef = 'KTG-TEST-' . strtoupper(Str::random(6)) . '-' . time();

        $this->line('');
        $this->line('  <fg=cyan>Flutterwave V3 Charge Test (redirect mode)</>');
        $this->line("  Phone: {$phone} | Network: {$network} | Amount: UGX " . number_format($amount));
        $this->line('');

        $response = $this->v3Http()->post($this->v3BaseUrl . '/charges?type=mobile_money_uganda', [
            'phone_number' => $phone,
            'network'      => $network,
            'amount'       => $amount,
            'currency'     => 'UGX',
            'email'        => 'test@mruodel.com',
            'fullname'     => 'Test User',
            'tx_ref'       => $txRef,
            'redirect_url' => url('/flw-test-callback'),
            'meta'         => ['test' => true],
        ]);

        $body = $response->json();

        if (!$response->successful() || ($body['status'] ?? '') !== 'success') {
            $this->error('Charge initiation failed.');
            $this->line(json_encode($body, JSON_PRETTY_PRINT));
            return 1;
        }

        $redirectUrl = $body['meta']['authorization']['redirect'] ?? null;

        $this->line("  Redirect URL: <fg=cyan>{$redirectUrl}</>");
        $this->line("  tx_ref: <fg=yellow>{$txRef}</>");
        $this->line('  Open the URL above in your browser, then enter your PIN.');

        return 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Verification
    // ──────────────────────────────────────────────────────────────────────────

    private function runVerify(string $txRef): int
    {
        $this->line('');
        $this->line("  Verifying: <fg=yellow>{$txRef}</>");
        $result = $this->checkVerification($txRef);
        $this->renderVerificationResult($result);
        return 0;
    }

    private function pollForConfirmation(string $txRef): void
    {
        $maxAttempts = 23;
        $attempt     = 0;

        $this->line('  Polling (enter your PIN on the phone now)...');
        $this->line('');

        while ($attempt < $maxAttempts) {
            sleep(8);
            $attempt++;
            $this->line("  [{$attempt}/{$maxAttempts}] Checking...");

            $result    = $this->checkVerification($txRef);
            $flwStatus = strtolower((string) ($result['data']['status'] ?? 'not_found'));

            if (in_array($flwStatus, ['successful', 'success', 'completed'], true)) {
                $this->line('');
                $this->line('  ┌─────────────────────────────┐');
                $this->line('  │  ✓  PAYMENT CONFIRMED!       │');
                $this->line('  └─────────────────────────────┘');
                $this->renderVerificationResult($result);
                return;
            }

            if ($flwStatus === 'failed') {
                $this->error('  ✗  Payment FAILED or declined.');
                $this->renderVerificationResult($result);
                return;
            }
        }

        $this->warn('  Timed out after 3 minutes.');
        $this->line("  Verify manually: php artisan flw:test --verify={$txRef}");
    }

    private function checkVerification(string $txRef): array
    {
        $response = $this->v3Http()
            ->get($this->v3BaseUrl . '/transactions/verify_by_reference', ['tx_ref' => $txRef]);

        if (!$response->successful()) {
            return ['status' => 'error', 'message' => 'HTTP ' . $response->status(), 'data' => []];
        }

        return $response->json() ?? [];
    }

    private function renderVerificationResult(array $result): void
    {
        $data      = $result['data'] ?? [];
        $flwStatus = $data['status'] ?? '—';
        $amount    = $data['amount'] ?? 0;
        $settled   = $data['amount_settled'] ?? 0;
        $currency  = $data['currency'] ?? '—';
        $payType   = $data['payment_type'] ?? '—';
        $flwRef    = $data['flw_ref'] ?? '—';
        $txRef     = $data['tx_ref'] ?? '—';
        $customer  = $data['customer'] ?? [];

        $this->line('');
        $this->line('  Verification Result:');
        $this->line('  ───────────────────────────────────────');
        $this->line("  Status       : <fg=" . ($flwStatus === 'successful' ? 'green' : 'red') . ">{$flwStatus}</>");
        $this->line("  Amount       : {$currency} " . number_format((float) $amount));
        $this->line("  Settled      : {$currency} " . number_format((float) $settled));
        $this->line("  Payment type : {$payType}");
        $this->line("  FLW ref      : {$flwRef}");
        $this->line("  tx_ref       : {$txRef}");
        $this->line("  Customer     : " . ($customer['name'] ?? '—') . ' / ' . ($customer['phone_number'] ?? '—'));
        $this->line('');

        if ($flwStatus === 'successful') {
            $this->line('  <fg=green>✓ VERIFIED — safe to grant subscription</>');
        } elseif ($flwStatus === 'failed') {
            $this->error('  ✗ FAILED — do not grant subscription');
        } elseif ($flwStatus === 'pending') {
            $this->warn('  ⏳ PENDING — customer has not entered PIN yet');
        } else {
            $this->line('  Full response: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTTP helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function v4Post(string $path, array $payload): array
    {
        $traceId      = Str::uuid()->toString();
        $idempotentKey = Str::uuid()->toString();

        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'X-Trace-Id'        => $traceId,
                'X-Idempotency-Key' => $idempotentKey,
            ])
            ->timeout(20)
            ->post($this->v4BaseUrl . $path, $payload);

        $body = $response->json() ?? [];
        $ok   = $response->successful() && ($body['status'] ?? '') === 'success';

        return [
            'ok'      => $ok,
            'message' => $body['message'] ?? ('HTTP ' . $response->status()),
            'body'    => $body,
            'status'  => $response->status(),
        ];
    }

    private function v3Http()
    {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(20);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Phone utilities
    // ──────────────────────────────────────────────────────────────────────────

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') return '';

        if (str_starts_with($digits, '256') && strlen($digits) >= 12) {
            return substr($digits, 0, 12);
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '256' . substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '256' . $digits;
        }

        return $digits;
    }

    private function detectNetwork(string $normalizedPhone): string
    {
        // Prefix = digits 4-5 of the full number (after country code 256)
        // Uganda allocations (NCA-verified, 2025):
        //   MTN:   031, 039, 076 (granted Mar 2025), 077, 078, 079
        //   Airtel: 020, 070, 071, 074, 075
        $prefix = substr($normalizedPhone, 3, 2);

        return match ($prefix) {
            '20', '70', '71', '74', '75' => 'AIRTEL',
            '31', '39', '76', '77', '78', '79' => 'MTN',
            default => 'MTN',
        };
    }
}
