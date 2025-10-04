# 📘 Subscription System API Documentation

**Version:** 1.0  
**Base URL:** `https://your-domain.com/api`  
**Date:** October 3, 2025

---

## 🔐 Authentication

Most endpoints require JWT authentication. Include the token in the Authorization header:

```
Authorization: Bearer {your_jwt_token}
```

---

## 📋 Table of Contents

1. [Get Subscription Plans](#1-get-subscription-plans)
2. [Create Subscription](#2-create-subscription)
3. [Get My Subscription](#3-get-my-subscription)
4. [Get Subscription History](#4-get-subscription-history)
5. [Retry Payment](#5-retry-payment)
6. [Check Payment Status](#6-check-payment-status)
7. [Payment Callback](#7-payment-callback)
8. [Payment IPN](#8-payment-ipn)

---

## 1. Get Subscription Plans

Get all available subscription plans with pricing and features.

**Endpoint:** `GET /api/subscription-plans`

**Authentication:** Not required

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `lang` | string | No | Language code (`en`, `lg` for Luganda, `sw` for Swahili). Default: `en` |

**Example Request:**
```bash
curl -X GET "https://your-domain.com/api/subscription-plans?lang=lg"
```

**Example Response:**
```json
{
  "code": 1,
  "status": 200,
  "message": "Subscription plans retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Ssente Ntono",
      "slug": "quick-start-3-days",
      "description": "Kikugwanira okugezaako...",
      "price": 1000.00,
      "actual_price": 1000.00,
      "formatted_price": "UGX 1,000",
      "currency": "UGX",
      "duration_days": 3,
      "duration_text": "3 Days",
      "daily_cost": 333.33,
      "features": "<ul><li>Laba firimu ezitali za makalu</li>...</ul>",
      "features_array": [
        "Laba firimu ezitali za makalu",
        "Obulungi bw'omutindo gwa HD",
        "Tolaba langi za byokulunda"
      ],
      "is_featured": false,
      "is_popular": false,
      "is_trial": true,
      "discount_percentage": 0,
      "max_downloads": 5,
      "max_watchlist": 10,
      "ad_free": true,
      "hd_streaming": true,
      "status": "Active",
      "active_subscriptions": 245
    },
    {
      "id": 2,
      "name": "Wiiki Bbiri",
      "slug": "two-weeks-special",
      "price": 5000.00,
      "duration_days": 14,
      "is_featured": false,
      "...": "..."
    },
    {
      "id": 3,
      "name": "Omwezi Omulungi",
      "slug": "monthly-premium",
      "price": 8000.00,
      "duration_days": 30,
      "is_featured": true,
      "is_popular": true,
      "...": "..."
    }
  ]
}
```

---

## 2. Create Subscription

Create a new subscription and initialize payment.

**Endpoint:** `POST /api/subscriptions/create`

**Authentication:** Required (JWT)

**Request Body:**
```json
{
  "plan_id": 3,
  "callback_url": "https://your-frontend.com/payment-result" // Optional
}
```

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `plan_id` | integer | Yes | ID of the subscription plan |
| `callback_url` | string | No | Custom callback URL after payment |

**Example Request:**
```bash
curl -X POST "https://your-domain.com/api/subscriptions/create" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 3,
    "callback_url": "https://myapp.com/subscription-success"
  }'
```

**Example Response (Success):**
```json
{
  "code": 1,
  "status": 200,
  "message": "Subscription created successfully. Please complete payment.",
  "data": {
    "subscription_id": 456,
    "order_tracking_id": "4a8a5956-1ae4-4b6f-8b0b-0e8d4e8e8e8e",
    "merchant_reference": "SUB-123-1696329600",
    "redirect_url": "https://pay.pesapal.com/iframe/PesapalIframe3/Index/?OrderTrackingId=4a8a5956...",
    "amount": 8000.00,
    "currency": "UGX"
  }
}
```

**Next Steps:**
1. Redirect user to `redirect_url`
2. User completes payment on Pesapal
3. User is redirected back to your callback URL
4. Your backend receives IPN notification

**Error Responses:**

Pending subscription exists:
```json
{
  "code": 0,
  "status": 500,
  "message": "You have a pending subscription payment. Please complete it or wait for it to expire.",
  "data": null
}
```

---

## 3. Get My Subscription

Get current user's subscription status.

**Endpoint:** `GET /api/subscriptions/my-subscription`

**Authentication:** Required (JWT)

**Example Request:**
```bash
curl -X GET "https://your-domain.com/api/subscriptions/my-subscription" \
  -H "Authorization: Bearer {token}"
```

**Example Response (Active Subscription):**
```json
{
  "code": 1,
  "status": 200,
  "message": "Subscription status retrieved successfully",
  "data": {
    "has_subscription": true,
    "status": "Active",
    "is_active": true,
    "days_remaining": 25,
    "hours_remaining": 600,
    "end_date": "2025-10-28T10:30:00.000000Z",
    "formatted_end_date": "Oct 28, 2025 10:30 AM",
    "is_in_grace_period": false,
    "plan": {
      "id": 3,
      "name": "Monthly Premium",
      "price": 8000.00,
      "duration_days": 30,
      "...": "..."
    },
    "subscription": {
      "id": 456,
      "user_id": 123,
      "status": "Active",
      "payment_status": "Completed",
      "start_date": "2025-09-28T10:30:00.000000Z",
      "end_date": "2025-10-28T10:30:00.000000Z",
      "amount_paid": 8000.00,
      "currency": "UGX",
      "...": "..."
    }
  }
}
```

**Example Response (No Subscription):**
```json
{
  "code": 1,
  "status": 200,
  "message": "Subscription status retrieved successfully",
  "data": {
    "has_subscription": false,
    "status": "No Subscription",
    "is_active": false,
    "days_remaining": 0,
    "end_date": null,
    "is_in_grace_period": false
  }
}
```

---

## 4. Get Subscription History

Get user's past subscriptions.

**Endpoint:** `GET /api/subscriptions/history`

**Authentication:** Required (JWT)

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Number of subscriptions to return. Default: `10` |

**Example Request:**
```bash
curl -X GET "https://your-domain.com/api/subscriptions/history?limit=5" \
  -H "Authorization: Bearer {token}"
```

**Example Response:**
```json
{
  "code": 1,
  "status": 200,
  "message": "Subscription history retrieved successfully",
  "data": [
    {
      "id": 456,
      "user_id": 123,
      "plan": {
        "id": 3,
        "name": "Monthly Premium",
        "price": 8000.00
      },
      "status": "Active",
      "payment_status": "Completed",
      "start_date": "2025-09-28T10:30:00.000000Z",
      "end_date": "2025-10-28T10:30:00.000000Z",
      "days_remaining": 25,
      "is_active": true,
      "amount_paid": 8000.00,
      "currency": "UGX",
      "created_at": "2025-09-28T09:15:00.000000Z"
    },
    {
      "id": 234,
      "status": "Expired",
      "start_date": "2025-08-15T10:30:00.000000Z",
      "end_date": "2025-09-15T10:30:00.000000Z",
      "is_active": false,
      "...": "..."
    }
  ]
}
```

---

## 5. Retry Payment

Retry payment for a failed or pending subscription.

**Endpoint:** `POST /api/subscriptions/retry-payment`

**Authentication:** Required (JWT)

**Request Body:**
```json
{
  "subscription_id": 456,
  "callback_url": "https://your-frontend.com/payment-result" // Optional
}
```

**Example Request:**
```bash
curl -X POST "https://your-domain.com/api/subscriptions/retry-payment" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "subscription_id": 456
  }'
```

**Example Response:**
```json
{
  "code": 1,
  "status": 200,
  "message": "Payment retry initiated. Please complete payment.",
  "data": {
    "subscription_id": 456,
    "order_tracking_id": "new-tracking-id-here",
    "merchant_reference": "SUB-123-1696329700",
    "redirect_url": "https://pay.pesapal.com/iframe/..."
  }
}
```

**Error Response (Cannot Retry):**
```json
{
  "code": 0,
  "status": 400,
  "message": "This subscription cannot be retried",
  "data": null
}
```

---

## 6. Check Payment Status

Manually check payment status for a pending subscription.

**Endpoint:** `POST /api/subscriptions/check-status`

**Authentication:** Required (JWT)

**Request Body:**
```json
{
  "subscription_id": 456
}
```

**Example Request:**
```bash
curl -X POST "https://your-domain.com/api/subscriptions/check-status" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "subscription_id": 456
  }'
```

**Example Response (Payment Completed):**
```json
{
  "code": 1,
  "status": 200,
  "message": "Payment status checked successfully",
  "data": {
    "id": 456,
    "status": "Active",
    "payment_status": "Completed",
    "is_active": true,
    "...": "..."
  }
}
```

---

## 7. Payment Callback

Handles payment callback from Pesapal after user completes payment.

**Endpoint:** `GET /api/subscriptions/pesapal/callback`

**Authentication:** Not required (called by Pesapal)

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `OrderTrackingId` | string | Pesapal order tracking ID |
| `OrderMerchantReference` | string | Merchant reference |
| `OrderNotificationType` | string | Notification type (COMPLETED, FAILED, etc) |

**This endpoint is called automatically by Pesapal.** It processes the payment and:
1. Checks payment status with Pesapal
2. Activates subscription if payment successful
3. Marks as failed if payment failed
4. Redirects user to frontend with status

**JSON Response (for API requests):**
```json
{
  "code": 1,
  "status": 200,
  "message": "Payment completed successfully. Your subscription is now active!",
  "data": {
    "subscription_id": 456,
    "status": "Active",
    "payment_status": "Completed",
    "order_tracking_id": "4a8a5956..."
  }
}
```

**Redirect (for web requests):**
User is redirected to: `{frontend_url}/subscription-result?status={success|failed|pending}&subscription_id={id}`

---

## 8. Payment IPN (Instant Payment Notification)

Receives instant payment notifications from Pesapal.

**Endpoint:** `POST /api/subscriptions/pesapal/ipn`

**Authentication:** Not required (called by Pesapal)

**Request Body (from Pesapal):**
```json
{
  "OrderTrackingId": "4a8a5956-1ae4-4b6f-8b0b-0e8d4e8e8e8e",
  "OrderMerchantReference": "SUB-123-1696329600",
  "OrderNotificationType": "IPNCHANGE"
}
```

**Response to Pesapal:**
```json
{
  "orderNotificationType": "IPNCHANGE",
  "orderTrackingId": "4a8a5956...",
  "orderMerchantReference": "SUB-123-1696329600",
  "status": 200
}
```

**This endpoint is called automatically by Pesapal** to notify about payment status changes.

---

## 🔒 Subscription Middleware

Protected routes can use the `subscription` middleware to ensure users have active subscriptions.

**Usage in routes:**
```php
Route::middleware(['auth', 'subscription'])->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
});
```

**Error Response (No Active Subscription):**
```json
{
  "code": 0,
  "status": 403,
  "message": "Active subscription required to access this content",
  "data": {
    "subscription_required": true,
    "subscription_status": {
      "has_subscription": false,
      "status": "No Subscription",
      "is_active": false
    },
    "pending_subscription": false,
    "action_url": "https://your-domain.com/api/subscription-plans"
  }
}
```

---

## 📊 Subscription Status Values

### Subscription Status
- `Pending` - Awaiting payment
- `Active` - Currently active
- `Expired` - Subscription has ended
- `Cancelled` - Manually cancelled
- `Failed` - Payment failed

### Payment Status
- `Pending` - Payment not yet completed
- `Processing` - Payment being processed
- `Completed` - Payment successful
- `Failed` - Payment failed
- `Refunded` - Payment refunded

---

## 🛠️ Integration Guide

### Frontend Integration Example

```javascript
// 1. Fetch available plans
const plans = await fetch('/api/subscription-plans?lang=lg', {
  method: 'GET'
}).then(res => res.json());

// 2. User selects a plan, create subscription
const subscription = await fetch('/api/subscriptions/create', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${userToken}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    plan_id: selectedPlanId,
    callback_url: `${window.location.origin}/subscription-result`
  })
}).then(res => res.json());

// 3. Redirect to Pesapal
if (subscription.code === 1) {
  window.location.href = subscription.data.redirect_url;
}

// 4. Handle callback (on subscription-result page)
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status'); // success, failed, pending

if (status === 'success') {
  // Show success message, redirect to content
} else if (status === 'failed') {
  // Show error, offer retry
}

// 5. Check subscription status
const mySubscription = await fetch('/api/subscriptions/my-subscription', {
  headers: { 'Authorization': `Bearer ${userToken}` }
}).then(res => res.json());

if (mySubscription.data.has_subscription) {
  // Allow access to content
} else {
  // Redirect to subscription page
}
```

---

## 🎯 Best Practices

1. **Always check subscription status** before allowing access to protected content
2. **Handle payment callbacks gracefully** - users might refresh or navigate away
3. **Implement retry logic** for failed payments
4. **Show clear error messages** - guide users on what to do next
5. **Cache plan data** - reduce API calls for frequently accessed plan information
6. **Monitor pending payments** - implement UI to check payment status manually
7. **Grace period** - Consider showing a warning before blocking access

---

## 📞 Support

For integration help, contact:
- **WhatsApp:** +1 (647) 968-6445
- **Email:** support@katogo.com

---

## 🔄 Changelog

### Version 1.0 (October 3, 2025)
- Initial release
- 8 API endpoints
- Pesapal payment integration
- Subscription middleware
- Automated expiration checking
- Email notifications

---

**End of API Documentation**
