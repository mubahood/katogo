# Katogo Payment System — Complete Documentation

> Last updated: 2026-06-13  
> Author: Muhindo Mubaraka  
> Status: **Production (Hetzner VPS — munoapp.store)**

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Payment Flow — Flutterwave Direct Charge (USSD Push)](#payment-flow--flutterwave-direct-charge-ussd-push)
3. [Captcha Auto-Solver (Puppeteer)](#captcha-auto-solver-puppeteer)
4. [Backward Compatibility — Old App Versions](#backward-compatibility--old-app-versions)
5. [Payment States Lifecycle](#payment-states-lifecycle)
6. [Mobile App Integration](#mobile-app-integration)
7. [Webhook Handling](#webhook-handling)
8. [Activation Guarantee — Money Never Lost](#activation-guarantee--money-never-lost)
9. [Admin Fix Lab](#admin-fix-lab)
10. [Pesapal → Flutterwave Migration](#pesapal--flutterwave-migration)
11. [Server Infrastructure](#server-infrastructure)
12. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

```
Mobile App (LugaFlix / UGFlix / Muno)
        │
        │  POST /api/subscriptions/create
        │  { plan_id, mobile_money_phone?, payment_gateway }
        ▼
SubscriptionApiController (Laravel)
        │
        │  Always routes to Flutterwave (Pesapal disabled for new payments)
        ▼
SubscriptionFlutterwaveService::initializePayment()
        │
        │  POST /v3/charges?type=mobile_money_uganda
        ▼
Flutterwave API
        │
        │  Returns: mode=redirect, captcha_url, tx_ref
        ▼
dispatchAutoSolveIfNeeded()
        │
        │  Detects captcha URL → dispatches SolveFLWCaptchaJob to queue
        │  Returns redirect_url to app (backward compat) + auto_push:true (new apps)
        ▼
SolveFLWCaptchaJob (Laravel Queue Worker)
        │
        │  Runs scripts/flw-solve-captcha.mjs via Puppeteer/Node.js
        │  Solves math captcha → FLW sends USSD push to customer phone
        │  Updates subscription.payment_status = 'AwaitingPIN'
        ▼
Customer receives USSD prompt → enters PIN
        │
        ▼
Flutterwave Webhook → /api/subscriptions/flutterwave/webhook
        │
        │  processCallbackWithFallback(tx_ref)
        │  Verifies transaction → activates subscription
        ▼
Subscription Status: Active / Completed
```

---

## Payment Flow — Flutterwave Direct Charge (USSD Push)

### Step 1 — App creates subscription

```
POST /api/subscriptions/create
{
  "plan_id": 1,
  "mobile_money_phone": "0772123456",  // optional — pulled from profile if omitted
  "payment_gateway": "flutterwave"     // always overridden to flutterwave server-side
}
```

**Response (new app — auto_push mode):**
```json
{
  "code": 1,
  "message": "Payment request sent to your phone. Enter your PIN when prompted.",
  "data": {
    "subscription_id": 12345,
    "auto_push": true,
    "redirect_url": "https://checkout.flutterwave.com/captcha/verify/...",
    "payment_gateway": "flutterwave",
    "order_tracking_id": "LUG-1-1780830017"
  }
}
```

**Response (old app — backward compat):**
Same response. Old app opens `redirect_url` in WebView, sees captcha already solved,
page transitions automatically. No functionality lost.

### Step 2 — Backend solves captcha

`SolveFLWCaptchaJob` runs headless Chrome via Puppeteer:
1. Opens the captcha URL
2. Waits for math question (e.g., "9 - 8 = ?")
3. Parses and answers it via DOM injection
4. Submits form
5. FLW sends USSD push to customer phone
6. Marks subscription `AwaitingPIN`

### Step 3 — Customer enters PIN on phone

Customer receives USSD prompt and enters Mobile Money PIN.

### Step 4 — Flutterwave confirms payment

FLW sends webhook to `/api/subscriptions/flutterwave/webhook`.
Backend verifies and activates the subscription.

### Step 5 — App polls for confirmation

New apps poll `GET /api/subscriptions/payment-status/{tx_ref}` every 5 seconds.
Old apps open the redirect_url WebView — the page auto-redirects to the callback URL after payment.

---

## Captcha Auto-Solver (Puppeteer)

### Files

| File | Purpose |
|------|---------|
| `scripts/flw-solve-captcha.mjs` | Node.js/Puppeteer script that solves the math captcha |
| `app/Jobs/SolveFLWCaptchaJob.php` | Laravel queued job that spawns the script |

### Configuration

- Chrome binary: `/var/cache/puppeteer/chrome/linux-149.0.7827.22/chrome-linux64/chrome`
- Owned by: `www-data` (the web/queue worker user)
- Temp dir: `/var/www/chrome-tmp` (owned by `www-data`, `chmod 755`)
- Queue: `default` (2 workers via supervisor `katogo-worker`)

### Process environment (required for headless Chrome as www-data)

```
HOME=/var/www
PUPPETEER_CACHE_DIR=/var/cache/puppeteer
XDG_CONFIG_HOME=/var/www/chrome-tmp
XDG_CACHE_HOME=/var/www/chrome-tmp
TMPDIR=/var/www/chrome-tmp
```

### Job configuration

```php
public int $tries   = 3;       // max 3 attempts
public int $timeout = 90;      // 90-second job timeout
public function backoff(): array { return [10, 30]; } // retry after 10s, 30s
```

### Idempotency

At start of `handle()`, job checks if subscription is already `Completed`, `AwaitingPIN`, or `Active` — if so, exits cleanly without re-running. Safe to dispatch multiple times.

### Failure handling

- Attempts 1–2: release back to queue (retry after backoff)
- Attempt 3 (final): marks subscription `CaptchaFailed`
- App fallback: when `CaptchaFailed`, old apps already have `redirect_url` → user opens WebView manually

---

## Backward Compatibility — Old App Versions

**Rule: old apps NEVER break, even without an update.**

| Scenario | Old App Behavior | New App Behavior |
|----------|-----------------|-----------------|
| `auto_push: true` | Ignores flag, opens `redirect_url` in WebView | Polls payment-status, shows "Check your phone" |
| `auto_push: false` | Opens `redirect_url` as normal payment link | Opens payment link |
| `redirect_url: null` | NEVER happens — always returned | Same |
| Pesapal response | Still works if subscription has pesapal tracking ID | Same |
| Webhook activation | Works independently of app version | Same |

**Key guarantee:** `redirect_url` is ALWAYS returned, even when `auto_push: true`. Old apps
open it, new apps use it as fallback only if auto-push fails.

---

## Payment States Lifecycle

```
[Pending] 
    │
    ├─ Payment init failed → [Failed]
    │
    └─ FLW init OK + captcha URL → [Processing]
            │
            ├─ Captcha solve OK → USSD push → [AwaitingPIN]
            │       │
            │       ├─ Customer enters PIN → FLW webhook → [Completed] → [Active]
            │       │
            │       └─ Customer ignores PIN → stays [AwaitingPIN] → expires
            │
            ├─ Captcha solve fails (3 tries) → [CaptchaFailed]
            │       └─ App shows redirect_url fallback
            │
            └─ No captcha URL (card payment, etc.) → [Processing]
                    └─ FLW webhook → [Completed] → [Active]
```

### Database fields tracking payment

| Field | Purpose |
|-------|---------|
| `subscriptions.payment_status` | `Pending`, `Processing`, `AwaitingPIN`, `CaptchaFailed`, `Completed`, `Failed` |
| `subscriptions.status` | `Pending`, `Active`, `Expired`, `Cancelled`, `Failed` |
| `subscriptions.payment_url` | The FLW captcha/checkout URL (backup fallback) |
| `subscriptions.flutterwave_reference` | FLW tx_ref (matches `pesapal_merchant_reference`) |
| `subscriptions.flutterwave_transaction_id` | FLW internal transaction ID |
| `subscriptions.flutterwave_response` | Full raw FLW API response (JSON) |
| `subscription_transactions.*` | Audit trail — every payment attempt logged here |

---

## Mobile App Integration

### API endpoints used by apps

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/subscriptions/create` | POST | Create new subscription + initiate payment |
| `/api/subscriptions/retry-payment` | POST | Retry failed payment |
| `/api/subscriptions/regenerate-link/{id}` | POST | Generate fresh payment link |
| `/api/subscriptions/payment-status/{tx_ref}` | GET | Poll payment status (new apps) |
| `/api/subscriptions/check-pending-payment/{id}` | GET | Auto-heal pending subscriptions |

### New app polling (auto_push mode)

When `auto_push: true` in response:
1. Show "Payment request sent to phone. Enter your PIN."
2. Poll `GET /api/subscriptions/payment-status/{tx_ref}` every 5 seconds
3. When `data.is_active == true` OR `data.subscription.payment_status == 'Completed'` → show success
4. When `data.subscription.payment_status == 'CaptchaFailed'` → open `redirect_url` in WebView

### Old app behavior (no auto_push handling)

1. Receives `redirect_url` (always present)
2. Opens URL in InAppWebView
3. FLW page may show captcha already solved (blank flash) → redirects to callback
4. OR captcha not yet solved → user solves manually (still works)

---

## Webhook Handling

### Flutterwave Webhook

**Endpoint:** `POST /api/subscriptions/flutterwave/webhook`  
**Header:** `verif-hash: {secret_hash}` (configured in `FLUTTERWAVE_SECRET_HASH`)

Flow:
1. Validates signature via HMAC-SHA256
2. Extracts `tx_ref` from payload
3. Calls `flutterwaveService->processCallbackWithFallback(tx_ref)`
4. FLW verifies transaction via `/v3/transactions/verify_by_reference`
5. On success → `finalizeSuccessfulSubscriptionState()` → `status=Active`, `payment_status=Completed`
6. Returns `200 OK` always (even on error) to prevent FLW retry storms

### Pesapal IPN (legacy — for existing subscriptions only)

**Endpoint:** `POST /api/subscriptions/pesapal/ipn`  
Still active for existing Pesapal transactions. No new Pesapal payments are created.

---

## Activation Guarantee — Money Never Lost

Three independent layers ensure subscription activation even if one fails:

### Layer 1 — Flutterwave Webhook (primary)
FLW pushes webhook as soon as customer confirms payment. Activates subscription immediately.

### Layer 2 — Payment Status Polling (app)
App polls `payment-status/{tx_ref}` every 5 seconds. Backend calls FLW verify API on each poll.
If webhook was missed but payment completed on FLW, this catches it.

### Layer 3 — Check Pending Payment (auto-heal)
`GET /api/subscriptions/check-pending-payment/{id}` — app calls this on startup for any pending sub.
Backend calls FLW verify, activates if paid. Also auto-re-initializes if no payment URL exists.

### Audit trail
Every payment attempt creates a `subscription_transactions` row, even if the gateway call fails.
This ensures money is always traceable via the admin panel, even if webhook + polling both fail.

---

## Admin Fix Lab

Located at: `https://munoapp.store/subscriptions` (Hetzner) — load any subscription → "Fix Lab"

### Initiate New Payment modal

| Mode | When | Backend Action |
|------|------|---------------|
| FLW + phone | Phone entered, FLW selected | Direct charge → dispatch `SolveFLWCaptchaJob` → USSD push |
| FLW + no phone | Phone blank, FLW selected | `POST /v3/payments` → hosted checkout URL |
| Pesapal (any) | Pesapal selected | **Silently redirected to FLW** — Pesapal disabled for new payments |

### Live status in modal (auto_push mode)

After clicking "Initiate Payment" with a phone:
- Immediately: "USSD push dispatched!"
- Polls `/admin/api/subscriptions/debug/inspect` every 5 seconds
- Status updates: `Waiting → AwaitingPIN (USSD sent) → Payment confirmed / CaptchaFailed`
- If `CaptchaFailed`: shows fallback "Open Captcha Page" link

### Admin Fix Lab endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST api/subscriptions/debug/initiate-payment` | Initiate new payment for a subscription |
| `POST api/subscriptions/debug/inspect` | Inspect subscription + gateway status |
| `POST api/subscriptions/debug/apply-fix` | Apply a specific fix action |
| `POST api/subscriptions/debug/batch-fix-single` | Run batch fix on one subscription |

---

## Pesapal → Flutterwave Migration

### What changed

| Area | Before | After |
|------|--------|-------|
| `create()` | Used pesapal or FLW based on user preference | Always FLW |
| `retryPayment()` | Used gateway from subscription | Always FLW |
| `regeneratePaymentLink()` | Used gateway from subscription | Always FLW |
| `checkPendingPayment()` auto-heal | Used subscription's gateway | Always FLW |
| `initializePaymentWithFallback()` | Could call pesapal service | Always FLW |
| Admin fix lab | Could use Pesapal | Remaps pesapal → FLW with log |
| `paymentGateways` API | Showed both | Shows FLW only |

### What still uses Pesapal (intentionally preserved)

| Area | Why kept |
|------|---------|
| `pesapalCallback()` | Existing Pesapal subscriptions still get callback IPN |
| `pesapalIpn()` | Existing Pesapal subscriptions still get IPN |
| `getPaymentStatus()` verify path | Old subscriptions with `pesapal_tracking_id` still verifiable |
| `resolveGateway()` | Returns 'pesapal' for existing subscriptions so verify calls go to right service |
| `SubscriptionPesapalService` | Not deleted — used for existing order verification |

### Backward compatibility guarantee

- Old apps sending `payment_gateway: "pesapal"` → server ignores it, uses FLW
- Old apps with pending Pesapal orders → webhook still activates them
- Old apps polling Pesapal tracking IDs → still verified correctly

---

## Server Infrastructure

### Production (Hetzner VPS)

| Item | Value |
|------|-------|
| URL | https://munoapp.store |
| IP | 91.98.42.156 |
| SSH | `ssh hetzner-katogo` |
| App path | /var/www/katogo |
| PHP | 8.5.4 (FPM) |
| Queue workers | 2× katogo-worker (supervisor) |
| Node.js | /usr/bin/node |
| Chrome | /var/cache/puppeteer/chrome/... |
| Chrome tmp | /var/www/chrome-tmp (www-data owned) |

### Key supervisor services

```
katogo-worker:katogo-worker_00   RUNNING  # queue worker 1
katogo-worker:katogo-worker_01   RUNNING  # queue worker 2
```

**Restart queue workers after code deploy:**
```bash
ssh hetzner-katogo "cd /var/www/katogo && php artisan queue:restart"
```

### Deploy procedure

```bash
# From local Mac:
git push origin main
ssh hetzner-katogo "cd /var/www/katogo && git pull origin main && php artisan config:cache && php artisan queue:restart"
```

---

## Troubleshooting

### USSD not received by customer

1. Check supervisor queue workers are running: `supervisorctl status`
2. Check Laravel logs: `tail -100 /var/www/katogo/storage/logs/laravel.log | grep SolveFLW`
3. Check subscription `payment_status` in admin Fix Lab
4. If `CaptchaFailed`: open the `redirect_url` manually in browser to check FLW side
5. If `AwaitingPIN`: customer was prompted but may not have entered PIN — resend via "Initiate New Payment" in Fix Lab

### Webhook not received / subscription not activating

1. Verify webhook URL configured in FLW dashboard: `https://munoapp.store/api/subscriptions/flutterwave/webhook`
2. Verify `FLUTTERWAVE_SECRET_HASH` in `/var/www/katogo/.env` matches FLW dashboard
3. Check Laravel logs for webhook events
4. Use admin Fix Lab → Re-Inspect to trigger manual verification

### Payment link opening to blank page / captcha

Old app behavior — backend may still be solving captcha. User can wait 10-15 seconds and refresh. The captcha page auto-submits once the backend solves it.

### Chrome crashes / SolveFLWCaptchaJob fails immediately

Check permissions:
```bash
ls -la /var/www/chrome-tmp       # must be owned by www-data
ls -la /var/cache/puppeteer      # must be readable by www-data
```

Fix if needed:
```bash
chown -R www-data:www-data /var/www/chrome-tmp
chmod -R 755 /var/cache/puppeteer
```

### Money paid but subscription not active

1. Admin Fix Lab → load subscription → Re-Inspect Selected Tx
2. If FLW shows "successful" but DB shows "Processing": click "Mark Sub Active" or use Apply Fix
3. Every paid transaction has an audit row in `subscription_transactions` — money is never truly lost

---

*For server credentials and infrastructure setup, see: `HETZNER_VPS_GUIDE.md`*  
*For Flutterwave API reference, see: `docs/flutterwave-mobile-money-direct-charge.md`*
