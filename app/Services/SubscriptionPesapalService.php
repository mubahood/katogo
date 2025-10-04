<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Subscription Pesapal Service
 * 
 * Handles all Pesapal payment integration for subscriptions
 * Based on the blitxpress implementation
 */
class SubscriptionPesapalService
{
    private $consumerKey;
    private $consumerSecret;
    private $baseUrl;
    private $ipnUrl;
    private $callbackUrl;

    public function __construct()
    {
        $this->consumerKey = env('PESAPAL_CONSUMER_KEY');
        $this->consumerSecret = env('PESAPAL_CONSUMER_SECRET');
        $this->baseUrl = env('PESAPAL_PRODUCTION_URL', 'https://pay.pesapal.com/v3');
        $this->ipnUrl = env('PESAPAL_IPN_URL', url('/api/subscriptions/pesapal/ipn'));
        $this->callbackUrl = env('PESAPAL_CALLBACK_URL', url('/api/subscriptions/pesapal/callback'));
    }

    /**
     * STEP 1: Authenticate with Pesapal
     * Returns JWT token for API calls
     */
    public function authenticate()
    {
        try {
            // Check cache first (tokens are valid for 5 minutes)
            $cacheKey = 'pesapal_subscription_token_' . md5($this->consumerKey);
            $cachedToken = Cache::get($cacheKey);
            
            if ($cachedToken) {
                Log::info('Pesapal Subscription: Using cached authentication token');
                return $cachedToken;
            }

            $payload = [
                'consumer_key' => $this->consumerKey,
                'consumer_secret' => $this->consumerSecret
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/api/Auth/RequestToken');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['token'])) {
                $token = $data['token'];
                
                // Cache token for 4 minutes (expires in 5)
                Cache::put($cacheKey, $token, 240);
                
                Log::info('Pesapal Subscription: Authentication successful');
                return $token;
            }

            throw new \Exception('Authentication failed: ' . ($data['error']['message'] ?? 'HTTP ' . $httpCode));

        } catch (\Exception $e) {
            Log::error('Pesapal Subscription: Authentication failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * STEP 2: Register IPN URL
     * Returns IPN ID for payment notifications
     */
    public function registerIpnUrl($ipnUrl = null)
    {
        $ipnUrl = $ipnUrl ?: $this->ipnUrl;

        try {
            $token = $this->authenticate();

            $payload = [
                'url' => $ipnUrl,
                'ipn_notification_type' => 'POST'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/api/URLSetup/RegisterIPN');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['ipn_id'])) {
                Log::info('Pesapal Subscription: IPN registered successfully', [
                    'ipn_id' => $data['ipn_id'],
                    'url' => $ipnUrl
                ]);
                
                return $data['ipn_id'];
            }

            // If already registered, Pesapal returns the existing IPN ID
            if (isset($data['ipn_id'])) {
                return $data['ipn_id'];
            }

            throw new \Exception('IPN registration failed: ' . ($data['error']['message'] ?? 'HTTP ' . $httpCode));

        } catch (\Exception $e) {
            Log::error('Pesapal Subscription: IPN registration failed', [
                'error' => $e->getMessage(),
                'ipn_url' => $ipnUrl
            ]);
            throw $e;
        }
    }

    /**
     * STEP 3: Initialize payment
     * Creates payment request and returns redirect URL
     * 
     * @param Subscription $subscription
     * @param string|null $notificationId
     * @param string|null $callbackUrl
     * @return array
     */
    public function initializePayment($subscription, $notificationId = null, $callbackUrl = null)
    {
        try {
            $token = $this->authenticate();
            $callbackUrl = $callbackUrl ?: $this->callbackUrl;

            // Get or register IPN
            if (!$notificationId) {
                $notificationId = $this->registerIpnUrl();
            }

            $user = $subscription->user;
            $plan = $subscription->plan;

            $payload = [
                'id' => $subscription->pesapal_merchant_reference,
                'currency' => $subscription->currency,
                'amount' => (float) $subscription->amount_paid,
                'description' => "Subscription: {$plan->name} - {$plan->duration_days} days",
                'callback_url' => $callbackUrl,
                'notification_id' => $notificationId,
                'billing_address' => [
                    'email_address' => $user->email ?? '',
                    'phone_number' => $user->phone_number ?? $user->phone_number_1 ?? '',
                    'country_code' => 'UG',
                    'first_name' => $user->first_name ?? $user->name ?? 'User',
                    'last_name' => $user->last_name ?? '',
                    'line_1' => '',
                    'line_2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'zip_code' => ''
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/api/Transactions/SubmitOrderRequest');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['order_tracking_id'])) {
                // Update subscription with Pesapal details
                $subscription->pesapal_tracking_id = $data['order_tracking_id'];
                $subscription->pesapal_response = $data;
                $subscription->payment_url = $data['redirect_url'] ?? null;
                $subscription->payment_status = 'Processing';
                $subscription->save();

                // Create transaction record
                SubscriptionTransaction::create([
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id,
                    'transaction_type' => $subscription->is_extension ? 'Renewal' : 'Initial',
                    'amount' => $subscription->amount_paid,
                    'currency' => $subscription->currency,
                    'status' => 'Pending',
                    'pesapal_tracking_id' => $data['order_tracking_id'],
                    'merchant_reference' => $subscription->pesapal_merchant_reference,
                    'request_payload' => $payload,
                    'response_payload' => $data,
                ]);

                Log::info('Pesapal Subscription: Payment initialized successfully', [
                    'subscription_id' => $subscription->id,
                    'tracking_id' => $data['order_tracking_id'],
                    'merchant_reference' => $subscription->pesapal_merchant_reference,
                ]);

                return [
                    'success' => true,
                    'order_tracking_id' => $data['order_tracking_id'],
                    'merchant_reference' => $subscription->pesapal_merchant_reference,
                    'redirect_url' => $data['redirect_url'],
                    'status' => '200'
                ];
            }

            throw new \Exception('Payment initialization failed: ' . ($data['error']['message'] ?? 'HTTP ' . $httpCode));

        } catch (\Exception $e) {
            Log::error('Pesapal Subscription: Payment initialization failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * STEP 4: Check transaction status
     * 
     * @param string $orderTrackingId
     * @return array
     */
    public function getTransactionStatus($orderTrackingId)
    {
        try {
            $token = $this->authenticate();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . $orderTrackingId);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200) {
                Log::info('Pesapal Subscription: Transaction status retrieved', [
                    'tracking_id' => $orderTrackingId,
                    'status' => $data['status_code'] ?? 'unknown'
                ]);

                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            throw new \Exception('Status check failed: HTTP ' . $httpCode);

        } catch (\Exception $e) {
            Log::error('Pesapal Subscription: Transaction status check failed', [
                'tracking_id' => $orderTrackingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription status after payment
     * COMPREHENSIVE IMPLEMENTATION - Handles all fields carefully
     * 
     * @param string $orderTrackingId
     * @param array $statusData
     * @return array
     */
    public function updateSubscriptionStatus($orderTrackingId, $statusData)
    {
        try {
            Log::info('🔄 Pesapal: Starting subscription status update', [
                'tracking_id' => $orderTrackingId,
                'status_data' => $statusData,
            ]);

            $subscription = Subscription::where('pesapal_tracking_id', $orderTrackingId)->first();

            if (!$subscription) {
                Log::error('❌ Pesapal: Subscription not found', [
                    'tracking_id' => $orderTrackingId,
                ]);
                throw new \Exception('Subscription not found for tracking ID: ' . $orderTrackingId);
            }

            Log::info('📦 Pesapal: Found subscription', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'current_status' => $subscription->status,
                'current_payment_status' => $subscription->payment_status,
                'plan_id' => $subscription->plan_id,
            ]);

            $transaction = $subscription->transactions()
                ->where('pesapal_tracking_id', $orderTrackingId)
                ->orderBy('created_at', 'DESC')
                ->first();

            if (!$transaction) {
                Log::warning('⚠️ Pesapal: Transaction record not found, will create one', [
                    'subscription_id' => $subscription->id,
                ]);
            }

            $paymentStatus = $statusData['payment_status_description'] ?? $statusData['status'] ?? null;
            $statusCode = $statusData['status_code'] ?? $statusData['payment_status_code'] ?? null;

            Log::info('🔍 Pesapal: Analyzing payment status', [
                'payment_status' => $paymentStatus,
                'status_code' => $statusCode,
                'full_data' => $statusData,
            ]);

            // ==================== PAYMENT SUCCESS ====================
            if ($statusCode == 1 || strtolower($paymentStatus ?? '') === 'completed') {
                
                Log::info('✅ Pesapal: Payment SUCCESSFUL - Starting activation process', [
                    'subscription_id' => $subscription->id,
                    'tracking_id' => $orderTrackingId,
                ]);

                // Check if subscription is already active
                $isAlreadyActive = ($subscription->status === 'Active' && $subscription->payment_status === 'Completed');
                
                if ($isAlreadyActive) {
                    Log::info('ℹ️ Pesapal: Subscription ALREADY ACTIVE - Will not update start_date_time', [
                        'subscription_id' => $subscription->id,
                        'existing_start_date' => $subscription->start_date_time,
                        'existing_end_date' => $subscription->end_date_time,
                    ]);
                }

                // Update subscription - CAREFULLY
                $subscription->status = 'Active';
                $subscription->payment_status = 'Completed';
                
                // Only set start_date_time if NOT already active
                if (!$isAlreadyActive && !$subscription->start_date_time) {
                    $subscription->start_date_time = now();
                    Log::info('📅 Pesapal: Setting start_date_time', [
                        'start_date_time' => $subscription->start_date_time,
                    ]);
                } else {
                    Log::info('📅 Pesapal: Keeping existing start_date_time', [
                        'start_date_time' => $subscription->start_date_time,
                    ]);
                }

                // Always calculate/update end_date_time
                if (!$subscription->end_date_time) {
                    $startDate = $subscription->start_date_time ?? now();
                    $subscription->end_date_time = \Carbon\Carbon::parse($startDate)->addDays($subscription->days);
                    Log::info('📅 Pesapal: Calculated end_date_time', [
                        'days' => $subscription->days,
                        'end_date_time' => $subscription->end_date_time,
                    ]);
                }

                // Set payment confirmed timestamp
                if (!$subscription->payment_confirmed_at) {
                    $subscription->payment_confirmed_at = now();
                    Log::info('✅ Pesapal: Setting payment_confirmed_at', [
                        'payment_confirmed_at' => $subscription->payment_confirmed_at,
                    ]);
                }

                // Clear failed_at if it was set
                if ($subscription->failed_at) {
                    $subscription->failed_at = null;
                    Log::info('🔄 Pesapal: Clearing failed_at timestamp');
                }

                // Update pesapal response data
                $subscription->pesapal_response = array_merge(
                    $subscription->pesapal_response ?? [],
                    ['status_check' => $statusData]
                );

                $subscription->save();

                Log::info('💾 Pesapal: Subscription SAVED successfully', [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'payment_status' => $subscription->payment_status,
                    'start_date_time' => $subscription->start_date_time,
                    'end_date_time' => $subscription->end_date_time,
                    'payment_confirmed_at' => $subscription->payment_confirmed_at,
                ]);

                // Update or create transaction record
                if ($transaction) {
                    Log::info('📝 Pesapal: Updating existing transaction', [
                        'transaction_id' => $transaction->id,
                    ]);

                    $transaction->status = 'Completed';
                    $transaction->payment_method = $statusData['payment_method'] ?? $transaction->payment_method;
                    $transaction->confirmation_code = $statusData['confirmation_code'] ?? $transaction->confirmation_code;
                    $transaction->payment_account = $statusData['payment_account'] ?? $statusData['account_number'] ?? null;
                    $transaction->response_payload = array_merge(
                        $transaction->response_payload ?? [],
                        ['status_check' => $statusData]
                    );
                    $transaction->error_message = null; // Clear any errors
                    $transaction->save();

                    Log::info('💾 Pesapal: Transaction updated successfully', [
                        'transaction_id' => $transaction->id,
                        'status' => $transaction->status,
                        'payment_method' => $transaction->payment_method,
                        'confirmation_code' => $transaction->confirmation_code,
                    ]);
                } else {
                    // Create transaction if it doesn't exist
                    Log::info('📝 Pesapal: Creating new transaction record');

                    $transaction = SubscriptionTransaction::create([
                        'subscription_id' => $subscription->id,
                        'user_id' => $subscription->user_id,
                        'transaction_type' => $subscription->is_extension ? 'Renewal' : 'Initial',
                        'amount' => $subscription->amount_paid,
                        'currency' => $subscription->currency,
                        'status' => 'Completed',
                        'pesapal_tracking_id' => $orderTrackingId,
                        'merchant_reference' => $subscription->pesapal_merchant_reference,
                        'payment_method' => $statusData['payment_method'] ?? null,
                        'confirmation_code' => $statusData['confirmation_code'] ?? null,
                        'payment_account' => $statusData['payment_account'] ?? $statusData['account_number'] ?? null,
                        'request_payload' => null,
                        'response_payload' => ['status_check' => $statusData],
                    ]);

                    Log::info('💾 Pesapal: Transaction created successfully', [
                        'transaction_id' => $transaction->id,
                    ]);
                }

                Log::info('🎉 Pesapal: Subscription ACTIVATED successfully', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'plan_id' => $subscription->plan_id,
                    'tracking_id' => $orderTrackingId,
                    'start_date' => $subscription->start_date_time,
                    'end_date' => $subscription->end_date_time,
                    'duration_days' => $subscription->days,
                ]);

                return [
                    'status' => 'success',
                    'subscription' => $subscription->fresh(['plan', 'transactions']),
                    'transaction' => $transaction,
                    'message' => 'Payment successful! Your subscription is now active.',
                ];

            } 
            // ==================== PAYMENT FAILED ====================
            elseif ($statusCode == 2 || in_array(strtolower($paymentStatus ?? ''), ['failed', 'invalid'])) {
                
                Log::warning('❌ Pesapal: Payment FAILED', [
                    'subscription_id' => $subscription->id,
                    'tracking_id' => $orderTrackingId,
                    'reason' => $paymentStatus,
                    'status_code' => $statusCode,
                ]);

                // Update subscription
                $subscription->status = 'Failed';
                $subscription->payment_status = 'Failed';
                $subscription->failed_at = now();
                $subscription->cancelled_reason = 'Payment failed: ' . $paymentStatus;
                $subscription->pesapal_response = array_merge(
                    $subscription->pesapal_response ?? [],
                    ['status_check' => $statusData]
                );
                $subscription->save();

                Log::info('💾 Pesapal: Subscription marked as FAILED', [
                    'subscription_id' => $subscription->id,
                    'failed_at' => $subscription->failed_at,
                ]);

                // Update transaction
                if ($transaction) {
                    $transaction->status = 'Failed';
                    $transaction->error_message = 'Payment failed: ' . $paymentStatus;
                    $transaction->response_payload = array_merge(
                        $transaction->response_payload ?? [],
                        ['status_check' => $statusData]
                    );
                    $transaction->save();

                    Log::info('💾 Pesapal: Transaction marked as FAILED', [
                        'transaction_id' => $transaction->id,
                    ]);
                }

                return [
                    'status' => 'failed',
                    'subscription' => $subscription->fresh(['plan', 'transactions']),
                    'transaction' => $transaction,
                    'message' => 'Payment failed: ' . $paymentStatus,
                ];

            } 
            // ==================== PAYMENT PENDING ====================
            else {
                
                Log::info('⏳ Pesapal: Payment still PENDING', [
                    'subscription_id' => $subscription->id,
                    'tracking_id' => $orderTrackingId,
                    'payment_status' => $paymentStatus,
                    'status_code' => $statusCode,
                ]);

                // Update payment status to Processing if it was Pending
                if ($subscription->payment_status === 'Pending') {
                    $subscription->payment_status = 'Processing';
                    $subscription->pesapal_response = array_merge(
                        $subscription->pesapal_response ?? [],
                        ['status_check' => $statusData]
                    );
                    $subscription->save();

                    Log::info('💾 Pesapal: Updated payment status to Processing', [
                        'subscription_id' => $subscription->id,
                    ]);
                }

                return [
                    'status' => 'pending',
                    'subscription' => $subscription->fresh(['plan', 'transactions']),
                    'transaction' => $transaction,
                    'message' => 'Payment is being processed. Please check back shortly.',
                ];
            }

        } catch (\Exception $e) {
            Log::error('💥 Pesapal: CRITICAL ERROR in subscription status update', [
                'tracking_id' => $orderTrackingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Process IPN callback
     * 
     * @param string $orderTrackingId
     * @param string|null $merchantReference
     * @return array
     */
    public function processIpnCallback($orderTrackingId, $merchantReference = null)
    {
        try {
            Log::info('Pesapal Subscription: Processing IPN callback', [
                'tracking_id' => $orderTrackingId,
                'merchant_reference' => $merchantReference,
            ]);

            // Get latest transaction status from Pesapal
            $statusResult = $this->getTransactionStatus($orderTrackingId);

            if (!$statusResult['success']) {
                throw new \Exception('Failed to get transaction status');
            }

            // Update subscription status
            $result = $this->updateSubscriptionStatus($orderTrackingId, $statusResult['data']);

            Log::info('Pesapal Subscription: IPN callback processed successfully', [
                'tracking_id' => $orderTrackingId,
                'status' => $result['status'],
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Pesapal Subscription: IPN callback processing failed', [
                'tracking_id' => $orderTrackingId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
