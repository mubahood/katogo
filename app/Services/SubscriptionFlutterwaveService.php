<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionFlutterwaveService
{
    private string $baseUrl;
    private ?string $secretKey;
    private string $currency;
    private string $country;
    private string $paymentOptions;
    private int $timeout;
    private string $appBaseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('flutterwave.base_url', 'https://api.flutterwave.com'), '/');
        $this->secretKey = config('flutterwave.secret_key');
        $this->currency = (string) config('flutterwave.currency', 'UGX');
        $this->country = (string) config('flutterwave.country', 'UG');
        $this->paymentOptions = (string) config('flutterwave.payment_options', 'mobilemoneyuganda');
        $this->timeout = (int) config('flutterwave.timeout', 20);
        $this->appBaseUrl = rtrim((string) config('app.production_url', config('app.url', 'https://katogo.schooldynamics.ug')), '/');
    }

    private function http()
    {
        if (empty($this->secretKey)) {
            throw new \RuntimeException('Flutterwave payment system misconfigured: missing FLW_SECRET_KEY in .env');
        }

        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout($this->timeout);
    }

    private function defaultCallbackUrl(): string
    {
        return $this->appBaseUrl . '/api/subscriptions/flutterwave/callback';
    }

    private function detectUgandanNetwork(string $normalizedPhone): string
    {
        $prefix = substr($normalizedPhone, 3, 2);

        return match ($prefix) {
            '70', '74', '75' => 'AIRTEL',
            '76', '77', '78', '39' => 'MTN',
            default => 'MTN',
        };
    }

    private function toArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizePhone(?string $phone): string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        // UG local formats -> international format without plus
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

    private function extractPhoneFromSubscription(Subscription $item): string
    {
        $fw = $this->toArray($item->flutterwave_response ?? null);
        $ps = $this->toArray($item->pesapal_response ?? null);

        $candidates = [
            $fw['data']['customer']['phonenumber'] ?? null,
            $fw['data']['customer']['phone_number'] ?? null,
            $fw['meta']['preferred_phone'] ?? null,
            $ps['billing_address']['phone_number'] ?? null,
            $ps['phone_number'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePhone((string) ($candidate ?? ''));
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function resolvePreferredPhone(Subscription $subscription, $user): string
    {
        $latest = Subscription::where('user_id', $subscription->user_id)
            ->where(function ($q) {
                $q->whereNotNull('flutterwave_response')
                    ->orWhereNotNull('pesapal_response');
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($latest as $prev) {
            $fromHistory = $this->extractPhoneFromSubscription($prev);
            if ($fromHistory !== '') {
                return $fromHistory;
            }
        }

        $profileCandidates = [
            $user->phone_number ?? null,
            $user->phone_number_1 ?? null,
            $user->phone ?? null,
            $user->telephone ?? null,
            $user->mobile ?? null,
        ];

        foreach ($profileCandidates as $candidate) {
            $normalized = $this->normalizePhone((string) ($candidate ?? ''));
            if ($normalized !== '') {
                Log::info('Flutterwave: using phone from user profile', [
                    'subscription_id' => $subscription->id,
                ]);
                return $normalized;
            }
        }

        return '';
    }

    /**
     * Ensure each flutterwave payment has a persistent transaction audit row.
     * This is idempotent and safe to call across init/callback/webhook flows.
     */
    private function upsertTransactionRecord(Subscription $subscription, array $gatewayPayload, string $status, ?string $errorMessage = null): SubscriptionTransaction
    {
        $txRef = (string) ($subscription->flutterwave_reference ?: $subscription->pesapal_merchant_reference ?: '');
        $flwRef = (string) ($gatewayPayload['flw_ref'] ?? $gatewayPayload['flwRef'] ?? $subscription->flutterwave_transaction_id ?? '');
        $paymentType = (string) ($gatewayPayload['payment_type'] ?? $gatewayPayload['paymentType'] ?? 'flutterwave');
        $phone = (string) ($gatewayPayload['customer']['phone_number'] ?? $gatewayPayload['customer.phone'] ?? '');

        $query = SubscriptionTransaction::where('subscription_id', $subscription->id);
        if ($txRef !== '') {
            $query->where(function ($q) use ($txRef) {
                $q->where('merchant_reference', $txRef)
                    ->orWhere('pesapal_tracking_id', $txRef);
            });
        }

        $transaction = $query->orderByDesc('id')->first();

        if (!$transaction) {
            $transaction = new SubscriptionTransaction();
            $transaction->subscription_id = $subscription->id;
            $transaction->user_id = $subscription->user_id;
            $transaction->transaction_type = $subscription->is_extension ? 'Renewal' : 'Initial';
            $transaction->platform = $subscription->app_type;
            $transaction->amount = $subscription->amount_paid;
            $transaction->currency = $subscription->currency;
            $transaction->merchant_reference = $txRef !== '' ? $txRef : null;
            $transaction->pesapal_tracking_id = $txRef !== '' ? $txRef : null;
        }

        $transaction->status = $status;
        $transaction->payment_method = $paymentType !== '' ? $paymentType : 'flutterwave';
        $transaction->confirmation_code = $flwRef !== '' ? $flwRef : ($transaction->confirmation_code ?: null);
        $transaction->payment_account = $phone !== '' ? $phone : ($transaction->payment_account ?: null);
        $transaction->error_message = $errorMessage;
        $transaction->response_payload = array_merge(
            $transaction->response_payload ?? [],
            ['flutterwave' => $gatewayPayload]
        );
        $transaction->save();

        return $transaction;
    }

    public function initializePayment(Subscription $subscription, ?string $callbackUrl = null, ?string $preferredPhoneOverride = null): array
    {
        $subscription->loadMissing('user', 'plan');

        $user = $subscription->user;
        if (!$user) {
            throw new \RuntimeException('Subscription user is missing.');
        }

        $txRef = $subscription->pesapal_merchant_reference;
        if (empty($txRef)) {
            throw new \RuntimeException('Subscription merchant reference is missing.');
        }

        $preferredPhone = $this->normalizePhone($preferredPhoneOverride);
        if ($preferredPhone === '') {
            $preferredPhone = $this->resolvePreferredPhone($subscription, $user);
        }
        if ($preferredPhone === '') {
            // No phone available — fall back to Flutterwave hosted checkout.
            // The hosted page lets the user enter their phone or pay by card.
            return $this->generateHostedCheckoutLink($subscription, $callbackUrl);
        }

        $payload = [
            'tx_ref' => $txRef,
            'voucher' => $txRef,
            'amount' => number_format((float) $subscription->amount_paid, 2, '.', ''),
            'currency' => $subscription->currency ?: $this->currency,
            'network' => $this->detectUgandanNetwork($preferredPhone),
            'email' => (string) ($user->email ?: ('user' . $user->id . '@example.com')),
            'phone_number' => $preferredPhone,
            'fullname' => trim((string) ($user->name ?: (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')))) ?: 'Subscriber',
            'client_ip' => request()?->ip(),
            'device_fingerprint' => substr(hash('sha256', (string) ($user->id . '|' . $txRef . '|' . ($subscription->user_agent ?? 'mobile'))), 0, 32),
            'redirect_url' => $callbackUrl ?: $this->defaultCallbackUrl(),
            'meta' => [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'plan_id' => $subscription->plan_id,
                'preferred_phone' => $preferredPhone,
                'source' => $preferredPhoneOverride ? 'client_override' : 'profile_or_history',
            ],
        ];

        $response = $this->http()->post($this->baseUrl . '/v3/charges?type=mobile_money_uganda', $payload);

        $body = $response->json();
        if (!$response->successful() || !is_array($body)) {
            $msg = is_array($body) ? ($body['message'] ?? 'Unknown Flutterwave error') : ('HTTP ' . $response->status());
            throw new \RuntimeException('Flutterwave initialize failed: ' . $msg);
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $authorization = is_array($body['meta']['authorization'] ?? null)
            ? $body['meta']['authorization']
            : (is_array($data['meta']['authorization'] ?? null) ? $data['meta']['authorization'] : []);
        $nextAction = is_array($data['next_action'] ?? null) ? $data['next_action'] : [];

        $link = $data['link']
            ?? $authorization['redirect']
            ?? $nextAction['redirect_url']['url']
            ?? null;

        $paymentMessage = $authorization['note']
            ?? $nextAction['payment_instruction']['note']
            ?? null;

        if (empty($link) && empty($paymentMessage)) {
            throw new \RuntimeException('Flutterwave did not return a payment action.');
        }

        $subscription->payment_url = $link;
        $subscription->payment_status = strtolower((string) ($data['status'] ?? 'pending')) === 'successful'
            ? 'Completed'
            : 'Processing';
        $subscription->payment_method = 'flutterwave';
        $subscription->flutterwave_reference = $txRef;
        $subscription->flutterwave_transaction_id = (string) ($data['id'] ?? $subscription->flutterwave_transaction_id ?? '');
        $subscription->flutterwave_response = $body;
        $subscription->save();

        // Guardrail: do not proceed without a persisted transaction ledger row.
        $this->upsertTransactionRecord($subscription, $data, 'Pending');

        return [
            'success' => true,
            'order_tracking_id' => $txRef,
            'merchant_reference' => $txRef,
            'redirect_url' => $link,
            'payment_message' => $paymentMessage,
            'next_action_type' => $nextAction['type'] ?? null,
            'payment_status' => $subscription->payment_status,
            'gateway' => 'flutterwave',
        ];
    }

    public function verifyByReference(string $txRef): array
    {
        $response = $this->http()->get($this->baseUrl . '/v3/transactions/verify_by_reference', [
            'tx_ref' => $txRef,
        ]);

        $data = $response->json();
        if (!$response->successful() || !is_array($data)) {
            $msg = is_array($data) ? ($data['message'] ?? 'Unknown Flutterwave verification error') : ('HTTP ' . $response->status());
            throw new \RuntimeException('Flutterwave verification failed: ' . $msg);
        }

        return $data;
    }

    public function verifyByTransactionId(string $transactionId): array
    {
        $response = $this->http()->get($this->baseUrl . '/v3/transactions/' . urlencode($transactionId) . '/verify');

        $data = $response->json();
        if (!$response->successful() || !is_array($data)) {
            $msg = is_array($data) ? ($data['message'] ?? 'Unknown Flutterwave verification error') : ('HTTP ' . $response->status());
            throw new \RuntimeException('Flutterwave transaction verification failed: ' . $msg);
        }

        return $data;
    }

    public function processCallback(string $txRef): array
    {
        return $this->processCallbackWithFallback($txRef, null);
    }

    public function processCallbackWithFallback(string $txRef, ?string $transactionId = null): array
    {
        $subscription = Subscription::where('pesapal_merchant_reference', $txRef)
            ->orWhere('flutterwave_reference', $txRef)
            ->first();

        if (!$subscription) {
            throw new \RuntimeException('Subscription not found for Flutterwave reference: ' . $txRef);
        }

        // Idempotency guard: never downgrade an already confirmed payment.
        if ($subscription->status === 'Active' && $subscription->payment_status === 'Completed') {
            $transaction = $this->upsertTransactionRecord(
                $subscription,
                ['tx_ref' => $txRef],
                'Completed',
                null
            );

            return [
                'status' => 'success',
                'subscription' => $subscription,
                'transaction' => $transaction,
                'verified' => ['status' => 'local_already_paid'],
            ];
        }

        try {
            $verified = $this->verifyByReference($txRef);
        } catch (\Exception $primaryException) {
            $message = (string) $primaryException->getMessage();

            if ($this->isVerificationNotFoundError($message) && empty($transactionId)) {
                // "No transaction found" is common before the customer completes
                // authorization on mobile money prompt. Keep it pending.
                $subscription->status = 'Pending';
                $subscription->payment_status = 'Processing';
                $subscription->failed_at = null;
                $subscription->payment_failure_reason = null;
                $subscription->save();

                $transaction = $this->upsertTransactionRecord(
                    $subscription,
                    ['tx_ref' => $txRef],
                    'Pending',
                    null
                );

                Cache::forget("sub_pending_{$subscription->user_id}");
                Cache::forget("active_sub_{$subscription->user_id}");
                Cache::forget("v2_pay_check_{$subscription->user_id}");

                return [
                    'status' => 'pending',
                    'subscription' => $subscription,
                    'transaction' => $transaction,
                    'verified' => ['status' => 'error', 'message' => $message],
                ];
            }

            if (empty($transactionId)) {
                throw $primaryException;
            }

            $verified = $this->verifyByTransactionId($transactionId);
            $verifiedTxRef = (string) ($verified['data']['tx_ref'] ?? $verified['data']['txRef'] ?? '');
            if ($verifiedTxRef !== '' && $verifiedTxRef !== $txRef) {
                throw new \RuntimeException('Flutterwave verification mismatch: tx_ref does not match callback reference.');
            }

            Log::warning('Flutterwave verify_by_reference failed; verify_by_transaction_id fallback succeeded', [
                'tx_ref' => $txRef,
                'transaction_id' => $transactionId,
                'error' => $message,
            ]);
        }

        $flwStatus = strtolower((string) ($verified['data']['status'] ?? ''));
        $gatewayRef = (string) ($verified['data']['flw_ref'] ?? '');
        $gatewayData = is_array($verified['data'] ?? null) ? $verified['data'] : [];
        $isNotFoundPayload = $this->isVerificationNotFoundPayload($verified);

        $subscription->payment_method = 'flutterwave';
        $subscription->flutterwave_reference = $txRef;
        $subscription->flutterwave_transaction_id = $gatewayRef ?: ($subscription->flutterwave_transaction_id ?? null);
        $subscription->flutterwave_response = $verified;

        if (in_array($flwStatus, ['successful', 'success', 'completed'], true)) {
            $subscription->save();

            /** @var SubscriptionActivationService $activationService */
            $activationService = app(SubscriptionActivationService::class);
            $subscription = $activationService->activatePaidSubscription(
                $subscription,
                'flutterwave_callback_success'
            );

            $transaction = $this->upsertTransactionRecord($subscription, $gatewayData, 'Completed');
            $this->syncSubscriptionPaymentTransactionsToCompleted($subscription);

            // Flush hot caches so clients immediately observe activated subscription
            Cache::forget("sub_pending_{$subscription->user_id}");
            Cache::forget("active_sub_{$subscription->user_id}");
            Cache::forget("v2_pay_check_{$subscription->user_id}");

            Log::info('Flutterwave payment completed', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'tx_ref' => $txRef,
                'flw_ref' => $gatewayRef,
            ]);

            return [
                'status' => 'success',
                'subscription' => $subscription,
                'transaction' => $transaction,
                'verified' => $verified,
            ];
        }

        if (in_array($flwStatus, ['failed', 'cancelled', 'canceled', 'error'], true) && !$isNotFoundPayload) {
            $subscription->status = 'Failed';
            $subscription->payment_status = 'Failed';
            $subscription->failed_at = now();
            $subscription->payment_failure_reason = (string) ($verified['data']['processor_response'] ?? $verified['message'] ?? 'Payment failed');
            $subscription->save();

            $transaction = $this->upsertTransactionRecord(
                $subscription,
                $gatewayData,
                'Failed',
                $subscription->payment_failure_reason
            );

            Cache::forget("sub_pending_{$subscription->user_id}");
            Cache::forget("active_sub_{$subscription->user_id}");
            Cache::forget("v2_pay_check_{$subscription->user_id}");

            return [
                'status' => 'failed',
                'subscription' => $subscription,
                'transaction' => $transaction,
                'verified' => $verified,
            ];
        }

        // Treat gateway "not found yet" verification payloads as transient.
        $subscription->status = 'Pending';
        $subscription->payment_status = 'Processing';
        $subscription->failed_at = null;
        $subscription->payment_failure_reason = null;
        $subscription->save();

        $transaction = $this->upsertTransactionRecord($subscription, $gatewayData, 'Pending');

        Cache::forget("sub_pending_{$subscription->user_id}");
        Cache::forget("active_sub_{$subscription->user_id}");
        Cache::forget("v2_pay_check_{$subscription->user_id}");

        return [
            'status' => 'pending',
            'subscription' => $subscription,
            'transaction' => $transaction,
            'verified' => $verified,
        ];
    }

    private function isVerificationNotFoundError(string $message): bool
    {
        $needle = strtolower($message);
        return str_contains($needle, 'no transaction was found for this id')
            || str_contains($needle, 'no transaction found')
            || str_contains($needle, '404');
    }

    private function isVerificationNotFoundPayload(array $verified): bool
    {
        $message = strtolower(implode(' ', array_filter([
            (string) ($verified['message'] ?? ''),
            (string) ($verified['data']['processor_response'] ?? ''),
            (string) ($verified['data']['status_message'] ?? ''),
            (string) ($verified['data']['response_message'] ?? ''),
        ])));

        return $this->isVerificationNotFoundError($message);
    }

    private function syncSubscriptionPaymentTransactionsToCompleted(Subscription $subscription): void
    {
        SubscriptionTransaction::where('subscription_id', $subscription->id)
            ->where(function ($q) {
                $q->whereNull('transaction_type')
                    ->orWhere('transaction_type', '!=', 'Withdrawal');
            })
            ->where('status', '!=', 'Completed')
            ->get()
            ->each(function (SubscriptionTransaction $tx) {
                $tx->status = 'Completed';
                $tx->error_message = null;
                $tx->save();
            });
    }

    /**
     * Generate a Flutterwave hosted checkout link — no phone number required.
     * The user/admin opens this URL on FLW's own page and enters phone there.
     * Uses POST /v3/payments (standard payment link, not direct charge).
     */
    public function generateHostedCheckoutLink(Subscription $subscription, ?string $callbackUrl = null): array
    {
        $subscription->loadMissing('user', 'plan');

        $user = $subscription->user;
        if (!$user) {
            throw new \RuntimeException('Subscription user is missing.');
        }

        $txRef = $subscription->pesapal_merchant_reference;
        if (empty($txRef)) {
            throw new \RuntimeException('Subscription merchant reference is missing.');
        }

        $payload = [
            'tx_ref'       => $txRef,
            'amount'       => number_format((float) $subscription->amount_paid, 2, '.', ''),
            'currency'     => $subscription->currency ?: $this->currency,
            'redirect_url' => $callbackUrl ?: $this->defaultCallbackUrl(),
            'payment_options' => 'mobilemoneyuganda',
            'customer'     => [
                'email'       => (string) ($user->email ?: ('user' . $user->id . '@example.com')),
                'name'        => trim((string) ($user->name ?: (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')))) ?: 'Subscriber',
            ],
            'customizations' => [
                'title'       => 'Katogo Subscription',
                'description' => ($subscription->plan->name ?? 'Premium') . ' plan payment',
            ],
            'meta' => [
                'subscription_id' => $subscription->id,
                'user_id'         => $subscription->user_id,
                'plan_id'         => $subscription->plan_id,
                'source'          => 'admin_hosted_link',
            ],
        ];

        $response = $this->http()->post($this->baseUrl . '/v3/payments', $payload);

        $body = $response->json();
        if (!$response->successful() || !is_array($body)) {
            $msg = is_array($body) ? ($body['message'] ?? 'Unknown Flutterwave error') : ('HTTP ' . $response->status());
            throw new \RuntimeException('Flutterwave hosted checkout failed: ' . $msg);
        }

        $link = $body['data']['link'] ?? null;
        if (empty($link)) {
            throw new \RuntimeException('Flutterwave did not return a hosted checkout link.');
        }

        $subscription->payment_url    = $link;
        $subscription->payment_method = 'flutterwave';
        $subscription->flutterwave_reference = $txRef;
        $subscription->flutterwave_response  = $body;
        $subscription->save();

        return [
            'success'            => true,
            'order_tracking_id'  => $txRef,
            'merchant_reference' => $txRef,
            'redirect_url'       => $link,
            'payment_message'    => null,
            'gateway'            => 'flutterwave',
            'mode'               => 'hosted_checkout',
        ];
    }

    public function isValidWebhook(string $rawBody, ?string $signature): bool
    {
        $secretHash = (string) config('flutterwave.secret_hash', '');

        // FLW_SECRET_HASH not configured: allow through with a warning so live webhooks
        // keep working while the hash is being configured in the Flutterwave dashboard.
        if (empty($secretHash)) {
            Log::warning('FLW webhook: FLW_SECRET_HASH not set in .env — all webhooks allowed through. Set this in Flutterwave dashboard → Settings → Webhooks → Secret Hash.');
            return true;
        }

        if (empty($signature)) {
            Log::warning('FLW webhook: verif-hash header missing from request');
            return false;
        }

        // Flutterwave sends the raw secret hash as the verif-hash header value
        // (it is NOT an HMAC — it is a plain static string set in the dashboard).
        return hash_equals($secretHash, $signature);
    }
}
