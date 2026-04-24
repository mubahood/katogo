<?php

namespace App\Services;

use App\Models\Subscription;
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
                Log::info('Flutterwave: using phone from payment history', [
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'source_subscription_id' => $prev->id,
                ]);
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
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                ]);
                return $normalized;
            }
        }

        return '';
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
            throw new \RuntimeException('Mobile Money payment requires a valid Ugandan phone number. Please update your phone number and try again.');
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

    public function processCallback(string $txRef): array
    {
        $verified = $this->verifyByReference($txRef);

        $subscription = Subscription::where('pesapal_merchant_reference', $txRef)
            ->orWhere('flutterwave_reference', $txRef)
            ->first();

        if (!$subscription) {
            throw new \RuntimeException('Subscription not found for Flutterwave reference: ' . $txRef);
        }

        $flwStatus = strtolower((string) ($verified['data']['status'] ?? ''));
        $gatewayRef = (string) ($verified['data']['flw_ref'] ?? '');

        $subscription->payment_method = 'flutterwave';
        $subscription->flutterwave_reference = $txRef;
        $subscription->flutterwave_transaction_id = $gatewayRef ?: ($subscription->flutterwave_transaction_id ?? null);
        $subscription->flutterwave_response = $verified;

        if ($flwStatus === 'successful') {
            if ($subscription->is_extension && $subscription->extendedFrom) {
                $startDate = $subscription->extendedFrom->end_date_time > now()
                    ? $subscription->extendedFrom->end_date_time
                    : now();
            } else {
                $startDate = now();
            }

            $subscription->start_date_time = $startDate;
            $subscription->end_date_time = $startDate->copy()->addDays($subscription->days);
            $subscription->grace_period_end = $subscription->end_date_time->copy()->addDays(3);
            $subscription->status = 'Active';
            $subscription->payment_status = 'Completed';
            $subscription->payment_confirmed_at = now();
            $subscription->failed_at = null;
            $subscription->payment_failure_reason = null;
            $subscription->cancelled_reason = null;
            $subscription->save();

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
                'verified' => $verified,
            ];
        }

        if (in_array($flwStatus, ['failed', 'cancelled'], true)) {
            $subscription->status = 'Failed';
            $subscription->payment_status = 'Failed';
            $subscription->failed_at = now();
            $subscription->payment_failure_reason = (string) ($verified['data']['processor_response'] ?? $verified['message'] ?? 'Payment failed');
            $subscription->save();

            Cache::forget("sub_pending_{$subscription->user_id}");
            Cache::forget("active_sub_{$subscription->user_id}");
            Cache::forget("v2_pay_check_{$subscription->user_id}");

            return [
                'status' => 'failed',
                'subscription' => $subscription,
                'verified' => $verified,
            ];
        }

        $subscription->status = 'Pending';
        $subscription->payment_status = 'Processing';
        $subscription->save();

        Cache::forget("sub_pending_{$subscription->user_id}");
        Cache::forget("active_sub_{$subscription->user_id}");
        Cache::forget("v2_pay_check_{$subscription->user_id}");

        return [
            'status' => 'pending',
            'subscription' => $subscription,
            'verified' => $verified,
        ];
    }

    public function isValidWebhook(string $rawBody, ?string $signature): bool
    {
        $secretHash = (string) config('flutterwave.secret_hash', '');
        if (empty($secretHash) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secretHash);
        return hash_equals($expected, $signature);
    }
}
