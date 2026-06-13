# Flutterwave Direct Mobile Money Charge — Technical Report

> **Scope:** Uganda MTN + Airtel direct charge via the Flutterwave v3 (and v4) API.
> Applicable to the Katogo Laravel 10 backend serving LugaFlix, UGFlix, and Muno apps.

---

## 1. What Is This?

Instead of generating a Flutterwave payment link and redirecting the user to a hosted checkout page, you can POST directly to Flutterwave's charge endpoint with the customer's **phone number** and **network (MTN or Airtel)**. Flutterwave sends a **USSD push notification** to the customer's phone — a PIN prompt appears directly on their handset. The customer enters their mobile money PIN and the debit happens automatically. No checkout link, no browser tab opening to Flutterwave's site.

There are two API generations available:

| API Version | Flow | Uganda Support |
|-------------|------|----------------|
| **v3** (current, recommended) | 1 API call → redirect URL returned → customer visits URL → USSD pushed | ✅ Full support |
| **v4** (newer orchestrator) | 3 API calls (customer → payment method → charge) → pure USSD push, no redirect needed | ✅ Available |

For Uganda, **v3 is the simplest** and most documented path. v4 allows a truly link-free pure push flow but requires three sequential API calls.

---

## 2. API Endpoints

### v3 (Recommended)

| Operation | Method | URL |
|-----------|--------|-----|
| Initiate charge | POST | `https://api.flutterwave.com/v3/charges?type=mobile_money_uganda` |
| Verify by transaction ID | GET | `https://api.flutterwave.com/v3/transactions/{id}/verify` |
| Query by your tx_ref | GET | `https://api.flutterwave.com/v3/transactions?tx_ref={tx_ref}` |

**All v3 requests require:**
```
Authorization: Bearer FLWSECK_LIVE-xxxx
Content-Type: application/json
```

### v4 (Pure Push, Link-Free)

| Operation | Method | URL |
|-----------|--------|-----|
| Create customer | POST | `https://api.flutterwave.com/customers` |
| Create payment method | POST | `https://api.flutterwave.com/payment-methods` |
| Initiate charge | POST | `https://api.flutterwave.com/charges` |
| Get charge status | GET | `https://api.flutterwave.com/charges/{id}` |

**Additional v4 headers required:**
```
X-Trace-Id: {unique-uuid-per-request}
X-Idempotency-Key: {unique-uuid-per-charge}
```

---

## 3. Supported Countries & Networks

Uganda is fully supported for direct mobile money charges.

| Country | Currency | Networks |
|---------|----------|----------|
| **Uganda** | **UGX** | **MTN, AIRTEL** |
| Ghana | GHS | MTN, AIRTELTIGO, VODAFONE |
| Kenya | KES | M-Pesa (MPS) |
| Rwanda | RWF | MTN, MPS |
| Tanzania | TZS | AIRTEL, MTN, TIGO, HALOPESA, VODACOM |
| Zambia | ZMW | MPS |
| Cameroon | XAF | MTN, ORANGEMONEY |
| Côte d'Ivoire | XOF | MTN, MOOV, ORANGE, WAVE |
| Senegal | XOF | ORANGEMONEY, WAVE |

For Uganda the `network` value must be **exactly** `"MTN"` or `"AIRTEL"` (uppercase string).

---

## 4. Charge Request Payload (v3 Uganda)

### Endpoint
```
POST https://api.flutterwave.com/v3/charges?type=mobile_money_uganda
```

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `phone_number` | string | Customer's number with country code: `"256701234567"` |
| `network` | string | `"MTN"` or `"AIRTEL"` |
| `amount` | integer | Amount in UGX (e.g. `5000`) |
| `currency` | string | Must be `"UGX"` |
| `email` | string | Customer email |
| `tx_ref` | string | Your unique transaction reference — store this before calling |

### Optional Fields

| Field | Type | Description |
|-------|------|-------------|
| `fullname` | string | Customer full name |
| `redirect_url` | string | Where customer is sent after authorization |
| `meta` | object | Arbitrary key-value metadata (e.g. `user_id`, `plan_id`) |
| `client_ip` | string | Customer's IP (fraud prevention) |
| `device_fingerprint` | string | Device fingerprint |

### Example Request

```bash
curl --request POST \
  'https://api.flutterwave.com/v3/charges?type=mobile_money_uganda' \
  --header 'Authorization: Bearer FLWSECK_LIVE-xxxx' \
  --header 'Content-Type: application/json' \
  --data '{
    "phone_number": "256701234567",
    "network": "MTN",
    "amount": 5000,
    "currency": "UGX",
    "email": "customer@example.com",
    "tx_ref": "KTG-ABC123-1718273645",
    "fullname": "John Doe",
    "redirect_url": "https://api.mruodel.com/payments/flutterwave/callback",
    "meta": {
      "user_id": 1234,
      "plan_id": 2,
      "app_type": "lugaflix"
    }
  }'
```

---

## 5. Response Flow After the API Call

### Step 1 — Immediate Response

The endpoint returns **immediately** with a redirect URL. The `"success"` status means Flutterwave accepted the request — not that the customer has paid.

```json
{
  "status": "success",
  "message": "Charge initiated",
  "meta": {
    "authorization": {
      "redirect": "https://ravemodal-dev.herokuapp.com/captcha/verify/lang-en/97639:...",
      "mode": "redirect"
    }
  }
}
```

Key points:
- `meta.authorization.redirect` — send the customer here
- `meta.authorization.mode` — always `"redirect"` for Uganda v3
- No transaction ID in this response — your `tx_ref` is your only reference until the webhook arrives

### Step 2 — Customer Authorization Page

The customer is directed (via WebView in the Flutter app) to `meta.authorization.redirect`. On this page:
- Customer sees confirmation of the amount and phone number
- Flutterwave triggers a **USSD push** to the customer's MTN/Airtel number
- Customer's phone shows a USSD menu/popup from their network
- Customer enters their **mobile money PIN on the handset** — never touches your server
- Network confirms the debit and notifies Flutterwave

The customer has **~10 minutes** to complete authorization before the session expires. In production, the USSD push arrives on the customer's phone within 5–30 seconds.

### Step 3 — Webhook (Async)

Once authorization completes (or fails), Flutterwave POSTs a webhook to your configured webhook URL. Your server processes the webhook independently of the WebView flow.

---

## 6. OTP / PIN Handling

**For Uganda MTN and Airtel — the PIN never touches your server.**

1. Your Laravel backend calls the charge endpoint
2. Flutter app opens the returned redirect URL in a WebView
3. Flutterwave sends USSD push to the customer's phone
4. Customer enters PIN on their physical handset via the USSD menu
5. Network (MTN/Airtel) authorizes and debits
6. Flutterwave receives confirmation and fires your webhook

You never handle the PIN. The only exception is Vodafone Ghana's `voucher` field — not applicable for Uganda.

---

## 7. Webhook Configuration & Payload

### Setup
In Flutterwave Dashboard → Settings → Webhooks:
- **Webhook URL:** `https://api.mruodel.com/webhooks/flutterwave`
- **Secret Hash:** A strong random string you generate (store in `.env`)
- Enable all event types

### Delivery Specs
- Method: POST, Content-Type: application/json
- Your endpoint must respond within **60 seconds** with HTTP 200
- Retries: 3 attempts, 30 minutes apart on failure
- Signature header: `flutterwave-signature` (contains your raw secret hash)

### Webhook Payload (charge.completed)

```json
{
  "webhook_id": "wbk_ZlH8y4R45J6IeqHmmIbD",
  "type": "charge.completed",
  "timestamp": 1718273645000,
  "data": {
    "id": 1163068,
    "tx_ref": "KTG-ABC123-1718273645",
    "flw_ref": "flwm3s4m0c1620380894041",
    "amount": 5000,
    "charged_amount": 5000,
    "amount_settled": 4750,
    "currency": "UGX",
    "status": "successful",
    "payment_type": "mobilemoneyuganda",
    "auth_model": "MOBILEMONEY",
    "customer": {
      "id": 252759,
      "name": "John Doe",
      "email": "customer@example.com",
      "phone_number": "256701234567"
    }
  }
}
```

Key fields:
- `type` — `"charge.completed"` for all payment results
- `data.status` — `"successful"` (paid) or `"failed"` (not paid)
- `data.payment_type` — `"mobilemoneyuganda"` for Uganda mobile money
- `data.id` — Flutterwave transaction ID (use for verification)
- `data.tx_ref` — your stored reference (use to match to your order)
- `data.amount_settled` — what you receive after fees (typically ~5% less)

---

## 8. Laravel Implementation

### .env Variables

```env
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-xxxx
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-xxxx
FLUTTERWAVE_SECRET_HASH=your_webhook_secret_hash_here
```

### config/services.php

```php
'flutterwave' => [
    'public_key'  => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secret_key'  => env('FLUTTERWAVE_SECRET_KEY'),
    'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
],
```

### FlutterwaveDirectChargeService

```php
// app/Services/FlutterwaveDirectChargeService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FlutterwaveDirectChargeService
{
    private string $secretKey;
    private string $baseUrl = 'https://api.flutterwave.com/v3';

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
    }

    /**
     * Initiate a Uganda mobile money direct charge.
     *
     * Returns ['success', 'tx_ref', 'redirect_url'] on success
     * or ['success' => false, 'error'] on failure.
     */
    public function chargeMobileMoneyUganda(
        string $phoneNumber,
        string $network,         // "MTN" or "AIRTEL"
        float  $amount,
        string $email,
        string $fullname = '',
        string $redirectUrl = '',
        array  $meta = []
    ): array {
        $txRef = 'KTG-' . strtoupper(Str::random(8)) . '-' . time();

        $payload = [
            'phone_number' => $phoneNumber,
            'network'      => strtoupper($network),
            'amount'       => (int) $amount,
            'currency'     => 'UGX',
            'email'        => $email,
            'tx_ref'       => $txRef,
        ];

        if ($fullname)    $payload['fullname']     = $fullname;
        if ($redirectUrl) $payload['redirect_url'] = $redirectUrl;
        if ($meta)        $payload['meta']         = $meta;

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/charges?type=mobile_money_uganda", $payload);

        if (!$response->successful()) {
            return [
                'success' => false,
                'tx_ref'  => $txRef,
                'error'   => $response->json('message') ?? 'HTTP ' . $response->status(),
            ];
        }

        $body = $response->json();

        if (($body['status'] ?? '') !== 'success') {
            return [
                'success' => false,
                'tx_ref'  => $txRef,
                'error'   => $body['message'] ?? 'Charge initiation failed',
            ];
        }

        return [
            'success'      => true,
            'tx_ref'       => $txRef,
            'redirect_url' => $body['meta']['authorization']['redirect'] ?? null,
            'mode'         => $body['meta']['authorization']['mode'] ?? 'redirect',
        ];
    }

    /**
     * Verify a completed transaction server-side before granting value.
     * ALL four conditions must pass.
     */
    public function verifyTransaction(int $flwTransactionId, float $expectedAmount, string $expectedTxRef): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transactions/{$flwTransactionId}/verify");

        if (!$response->ok()) {
            return ['verified' => false, 'error' => 'Flutterwave API unavailable'];
        }

        $data = $response->json('data');

        if (!$data) {
            return ['verified' => false, 'error' => 'Empty response from Flutterwave'];
        }

        $verified = $data['status'] === 'successful'
            && $data['tx_ref'] === $expectedTxRef
            && $data['currency'] === 'UGX'
            && floatval($data['amount']) >= floatval($expectedAmount);

        return [
            'verified'     => $verified,
            'status'       => $data['status'],
            'amount'       => $data['amount'],
            'amount_settled' => $data['amount_settled'] ?? null,
            'payment_type' => $data['payment_type'] ?? null,
            'flw_ref'      => $data['flw_ref'] ?? null,
            'data'         => $data,
        ];
    }

    /**
     * Fall-back: find a transaction by your tx_ref (useful when you lose the FLW transaction ID).
     */
    public function findByTxRef(string $txRef): ?array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transactions", ['tx_ref' => $txRef]);

        if (!$response->ok()) return null;

        $transactions = $response->json('data');
        return is_array($transactions) && count($transactions) > 0 ? $transactions[0] : null;
    }
}
```

### Payment Controller

```php
// app/Http/Controllers/Api/MobileMoneyController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlutterwaveDirectChargeService;
use Illuminate\Http\Request;

class MobileMoneyController extends Controller
{
    public function initiate(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|min:10',
            'network'      => 'required|in:MTN,AIRTEL',
            'amount'       => 'required|numeric|min:500',
            'plan_id'      => 'required|integer|exists:subscription_plans,id',
        ]);

        $user    = auth()->user();
        $service = new FlutterwaveDirectChargeService();

        $result = $service->chargeMobileMoneyUganda(
            phoneNumber: $request->phone_number,
            network:     $request->network,
            amount:      $request->amount,
            email:       $user->email,
            fullname:    $user->name,
            redirectUrl: config('app.url') . '/payments/flutterwave/callback',
            meta: [
                'user_id'  => $user->id,
                'plan_id'  => $request->plan_id,
                'app_type' => $request->header('app-type', 'lugaflix'),
            ]
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        // Store pending payment record
        \DB::table('payment_transactions')->insert([
            'user_id'    => $user->id,
            'tx_ref'     => $result['tx_ref'],
            'amount'     => $request->amount,
            'currency'   => 'UGX',
            'network'    => $request->network,
            'phone'      => $request->phone_number,
            'plan_id'    => $request->plan_id,
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return redirect_url to Flutter app
        // Flutter opens this in a WebView
        return response()->json([
            'tx_ref'       => $result['tx_ref'],
            'redirect_url' => $result['redirect_url'],
            'message'      => 'Check your phone for a payment prompt and enter your PIN.',
        ]);
    }
}
```

### Webhook Controller

```php
// app/Http/Controllers/FlutterwaveWebhookController.php

namespace App\Http\Controllers;

use App\Services\FlutterwaveDirectChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlutterwaveWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. Verify signature — compare header to your stored secret hash
        $secretHash = config('services.flutterwave.secret_hash');
        $signature  = $request->header('flutterwave-signature');

        if (!$signature || $signature !== $secretHash) {
            Log::warning('FLW webhook: invalid signature', ['received' => $signature]);
            return response('Unauthorized', 401);
        }

        $payload = $request->all();

        // 2. Only handle completed charges
        if (($payload['type'] ?? '') !== 'charge.completed') {
            return response('OK', 200);
        }

        $data = $payload['data'] ?? [];

        // 3. Dispatch async job — return 200 immediately to prevent webhook retry
        \App\Jobs\ProcessFlutterwavePayment::dispatch($data);

        return response('OK', 200);
    }
}
```

### Payment Processing Job

```php
// app/Jobs/ProcessFlutterwavePayment.php

namespace App\Jobs;

use App\Services\FlutterwaveDirectChargeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessFlutterwavePayment implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(public array $data) {}

    public function handle(): void
    {
        $txRef = $this->data['tx_ref'] ?? null;
        $flwId = $this->data['id'] ?? null;

        if (!$txRef || !$flwId) {
            Log::warning('FLW job: missing tx_ref or id', $this->data);
            return;
        }

        // Idempotency guard — never double-process
        $tx = DB::table('payment_transactions')
            ->where('tx_ref', $txRef)
            ->first();

        if (!$tx || $tx->status === 'successful') {
            return;
        }

        // Only process successful payments
        if (($this->data['status'] ?? '') !== 'successful') {
            DB::table('payment_transactions')
                ->where('tx_ref', $txRef)
                ->update(['status' => 'failed', 'updated_at' => now()]);
            return;
        }

        // Verify independently against the API — never trust the webhook alone
        $service  = new FlutterwaveDirectChargeService();
        $verified = $service->verifyTransaction($flwId, $tx->amount, $txRef);

        if (!$verified['verified']) {
            Log::error('FLW: verification failed', ['tx_ref' => $txRef, 'flwId' => $flwId]);
            return;
        }

        // Mark payment successful
        DB::table('payment_transactions')
            ->where('tx_ref', $txRef)
            ->update([
                'status'     => 'successful',
                'flw_txn_id' => $flwId,
                'flw_ref'    => $this->data['flw_ref'] ?? null,
                'settled'    => $verified['amount_settled'],
                'updated_at' => now(),
            ]);

        // Grant subscription to the user
        // (wire into your existing SubscriptionService here)
        $plan = \App\Models\SubscriptionPlan::find($tx->plan_id);
        if ($plan && $tx->user_id) {
            app(\App\Services\SubscriptionService::class)->grant($tx->user_id, $plan);
        }

        Log::info('FLW: payment successful', ['tx_ref' => $txRef, 'user' => $tx->user_id]);
    }
}
```

### Routes

```php
// routes/api.php
Route::middleware('auth:api')->group(function () {
    Route::post('/payments/mobile-money/initiate', [MobileMoneyController::class, 'initiate']);
});

// Webhook route — no auth middleware, no CSRF
Route::post('/webhooks/flutterwave', [FlutterwaveWebhookController::class, 'handle']);
```

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'webhooks/flutterwave',
];
```

---

## 9. Transaction Verification

Always verify the transaction server-side before granting a subscription. Verification checklist — **all four must pass**:

1. `data.status === "successful"`
2. `data.tx_ref === your_stored_tx_ref`
3. `data.currency === "UGX"`
4. `data.amount >= expected_amount` (use `>=`, not strict equality — float rounding)

```
GET https://api.flutterwave.com/v3/transactions/{flw_id}/verify
Authorization: Bearer FLWSECK_LIVE-xxxx
```

Response:
```json
{
  "status": "success",
  "data": {
    "id": 1163068,
    "tx_ref": "KTG-ABC123-1718273645",
    "amount": 5000,
    "currency": "UGX",
    "charged_amount": 5000,
    "amount_settled": 4750,
    "status": "successful",
    "payment_type": "mobilemoneyuganda",
    "customer": { ... }
  }
}
```

---

## 10. Error Codes & Edge Cases

### HTTP 400 Errors

```json
{
  "status": "failed",
  "error": {
    "code": "10400",
    "message": "Currency not supported for UG Mobile Money."
  }
}
```

### Mobile Money Network Errors (arrive via webhook or redirect)

| Error | Cause | Fix |
|-------|-------|-----|
| `Activity timed out` | Customer didn't enter PIN within 10 min | Retry with new tx_ref |
| `The balance is insufficient` | Customer has no funds | Customer tops up |
| `Invalid account` | Number not registered for mobile money | Customer registers with MTN/Airtel |
| `Msisdn cannot be empty or less than 10 digits` | Bad phone number format | Validate number before charge |
| `Unauthorized network Number` | Not activated for MoMo | Customer contacts network |
| `Would exceed daily transfer limit` | MTN/Airtel daily cap | Customer increases limit or waits |
| `You have entered an invalid PIN` | Wrong PIN entered | Customer retries |
| `Duplicate transaction reference` | Same tx_ref reused | Always generate fresh tx_ref per attempt |
| `Network is temporarily down` | MTN/Airtel outage | Retry after a few minutes |

### Critical Operational Edge Cases

1. **Webhook arrives before redirect callback** — the async webhook can fire before the WebView flow completes. Your DB record must be independent of the WebView flow (don't wait for callback to set status).

2. **Lost transaction ID** — the v3 charge response contains no `data.id`. Your `tx_ref` is your only reference until the webhook. If the webhook is missed, use `GET /v3/transactions?tx_ref={tx_ref}` to recover.

3. **Duplicate webhooks** — Flutterwave can send the same `charge.completed` more than once. Use `data.id` (FLW transaction ID) as idempotency key. The job above already guards this via the `status === 'successful'` check.

4. **"Customer says money was deducted but status is failed"** — query by tx_ref to check: `GET /v3/transactions?tx_ref=KTG-xxx`. If found as `successful`, it was a webhook delivery failure — process it manually.

---

## 11. Uganda-Specific Notes

### Phone Number Format
```
Format:  256 + local number without leading zero
MTN:     25677XXXXXXX  or  25678XXXXXXX  or  25639XXXXXXX
Airtel:  25670XXXXXXX  or  25675XXXXXXX
Example: 256772345678 (MTN)
         256701234567 (Airtel)
```

### Currency & Limits
- Currency must be `"UGX"` — USD and other currencies are not supported for Uganda mobile money charges
- Minimum per transaction: **UGX 500**
- Maximum per transaction: **UGX 5,000,000**
- Customer's daily maximum depends on their MTN/Airtel account tier
- Maximum account balance: UGX 20,000,000

### MTN vs Airtel
- Both use the same Flutterwave endpoint — only the `network` field differs
- Both use USSD push — no API behavioral difference
- MTN MoMo has higher adoption in Uganda
- Airtel Money has a formal partnership with Flutterwave (April 2024)
- Flutterwave received an official Uganda Payment Systems License (August 2024)

### Test Mode
- In sandbox mode, Uganda mobile money payments **auto-authorize within seconds** — no actual USSD interaction needed
- You still receive the redirect URL in test mode — skip visiting it in automated tests
- Webhook fires automatically after the test auto-authorization

---

## 12. Flutter App Integration

After your Laravel API returns the `redirect_url`, the Flutter app opens it:

```dart
// Open the Flutterwave authorization page in a WebView
import 'package:webview_flutter/webview_flutter.dart';

class MobileMoneyAuthScreen extends StatelessWidget {
  final String redirectUrl;
  final String txRef;

  const MobileMoneyAuthScreen({required this.redirectUrl, required this.txRef});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Complete Payment')),
      body: WebViewWidget(
        controller: WebViewController()
          ..setJavaScriptMode(JavaScriptMode.unrestricted)
          ..setNavigationDelegate(NavigationDelegate(
            onNavigationRequest: (request) {
              // Detect when Flutterwave redirects back to your callback URL
              if (request.url.contains('/payments/flutterwave/callback')) {
                Navigator.pop(context);
                // Poll your backend for payment status by tx_ref
                _checkPaymentStatus(txRef);
                return NavigationDecision.prevent;
              }
              return NavigationDecision.navigate;
            },
          ))
          ..loadRequest(Uri.parse(redirectUrl)),
      ),
    );
  }
}
```

**The customer's experience:**
1. WebView opens Flutterwave's confirmation page
2. After a few seconds, a USSD popup appears on their home screen (native OS prompt from MTN/Airtel)
3. Customer taps their mobile money PIN on the USSD menu
4. USSD popup closes, WebView shows a success/failure message
5. Flutterwave redirects the WebView to your `redirect_url`
6. App detects the redirect, closes WebView, polls backend for confirmation

---

## 13. Complete Flow Diagram

```
[Flutter App]              [Your Laravel API]           [Flutterwave]         [MTN/Airtel]    [Customer Phone]
     |                            |                           |                     |                |
     |--POST /payments/initiate-->|                           |                     |                |
     |  {phone, network, amount}  |                           |                     |                |
     |                            |--POST /charges?type=ug -->|                     |                |
     |                            |<--{redirect_url}----------|                     |                |
     |                            |--Store tx_ref (pending)   |                     |                |
     |<--{redirect_url, tx_ref}---|                           |                     |                |
     |                            |                           |                     |                |
     |--Open WebView(redirect_url)------------------------------->                  |                |
     |                            |                           |--USSD push -------->|                |
     |                            |                           |                     |--USSD menu --->|
     |                            |                           |                     |<--PIN entered--|
     |                            |                           |<--Auth confirmed----|                |
     |                            |<--POST /webhooks/flutterwave-------------------|                |
     |                            |  {type:"charge.completed",                     |                |
     |                            |   data.status:"successful",                    |                |
     |                            |   data.tx_ref:"KTG-xxx",                       |                |
     |                            |   data.id:1163068}                             |                |
     |                            |--Verify signature         |                     |                |
     |                            |--Dispatch job             |                     |                |
     |                            |--Return HTTP 200          |                     |                |
     |                            |                           |                     |                |
     |                            | [Job runs async]          |                     |                |
     |                            |--GET /transactions/1163068/verify->            |                |
     |                            |<--{status:"successful"}---|                     |                |
     |                            |--Update DB: successful    |                     |                |
     |                            |--Grant subscription       |                     |                |
     |                            |                           |                     |                |
     |--WebView detects callback URL                          |                     |                |
     |--Poll /payments/status/{tx_ref}-->                     |                     |                |
     |<--{status:"successful"}-------                         |                     |                |
     |--Show success screen                                   |                     |                |
```

---

## 14. Key Differences vs. Current Payment Link Flow

| Aspect | Payment Link (current) | Direct Charge (new) |
|--------|----------------------|---------------------|
| What opens | Full Flutterwave checkout page in browser | Minimal confirmation + USSD push |
| Customer experience | Chooses payment method on FLW site | USSD popup on phone (feels native) |
| Phone number | Customer enters on FLW site | You provide — pre-filled |
| Network selection | Customer selects on FLW site | You provide — pre-selected |
| UX friction | High (leaves your app) | Low (USSD feels like a system prompt) |
| Requires WebView | No | Yes (for v3) — or No for v4 |
| API calls | 1 (generate link) | 1 charge + 1 verify (v3) |

---

## 15. Recommendation for Katogo

**Use v3 for now.** It's one API call, fully documented, Uganda-supported, and battle-tested. The WebView is a minor UX cost but customers are very familiar with USSD flows.

**Future upgrade to v4 pure push** — when you want to eliminate the WebView entirely: POST to create a customer, POST to create a payment method, POST to create a charge. The charge response returns `next_action.type: "payment_instruction"` instead of a redirect URL — the USSD push is fired directly with no browser step. This is the cleanest UX but requires implementing three sequential API calls.

---

## Sources

- [Flutterwave v3 Uganda Mobile Money Docs](https://developer.flutterwave.com/v3.0/docs/uganda)
- [Charge via Uganda Mobile Money (v3 reference)](https://developer.flutterwave.com/v3.0/reference/charge-via-uganda-mobile-money)
- [v4 Mobile Money Docs](https://developer.flutterwave.com/docs/mobile-money)
- [Transaction Verification](https://developer.flutterwave.com/v3.0/docs/transaction-verification)
- [Webhooks Guide](https://developer.flutterwave.com/docs/webhooks)
- [Common Mobile Money Errors](https://flutterwave.com/ng/support/general/common-mobile-money-transaction-errors)
- [PHP SDK v3 (GitHub)](https://github.com/Flutterwave/PHP-v3)
- [How to Collect Payments via v4 APIs (DEV Community)](https://dev.to/flutterwaveeng/how-to-collect-payments-using-the-v4-apis-4lhp)
- [Flutterwave Uganda License Announcement](https://flutterwave.com/us/blog/flutterwave-expands-african-footprint-with-payment-systems-license-in-uganda)
