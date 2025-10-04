# 🎯 Subscription System - Comprehensive Implementation Plan

**Date:** October 3, 2025  
**Project:** Katogo Subscription Module  
**Author:** AI Assistant  
**Status:** Planning Phase

---

## 📋 Table of Contents
1. [Executive Summary](#executive-summary)
2. [System Architecture](#system-architecture)
3. [Database Design](#database-design)
4. [Security Considerations](#security-considerations)
5. [Edge Cases & Solutions](#edge-cases--solutions)
6. [Payment Flow](#payment-flow)
7. [Implementation Phases](#implementation-phases)
8. [Testing Strategy](#testing-strategy)

---

## 🎯 Executive Summary

### Core Requirements
- **User Restriction**: No system access without active subscription
- **Payment Integration**: Pesapal payment gateway (following blitxpress implementation)
- **Subscription Types**: Multiple plans with flexible durations
- **Auto-Renewal**: Optional automatic renewal capability
- **Grace Period**: Consider grace period for expired subscriptions
- **Notifications**: Email/SMS alerts for expiring subscriptions

### Success Metrics
- ✅ 100% payment success rate for valid transactions
- ✅ Zero unauthorized access to protected content
- ✅ Seamless user experience during subscription flow
- ✅ Real-time subscription status checking
- ✅ Automated expiration handling

---

## 🏗️ System Architecture

### Models & Relationships

```
User (admin_users table)
├── subscriptions (hasMany)
└── activeSubscription (hasOne)

SubscriptionPlan
├── subscriptions (hasMany)
└── features (JSON/Text)

Subscription
├── user (belongsTo)
├── plan (belongsTo)
└── transactions (hasMany)

SubscriptionTransaction (extends PesapalTransaction concept)
├── subscription (belongsTo)
├── pesapal_order_tracking_id
├── pesapal_merchant_reference
└── payment_status
```

### Middleware Chain
```
Request → JwtMiddleware → CheckSubscription → Controller
```

---

## 🗄️ Database Design

### Table: `subscription_plans`

```sql
id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
created_at              TIMESTAMP NULL
updated_at              TIMESTAMP NULL

-- Plan Details
name                    VARCHAR(191) NOT NULL
name_luganda           VARCHAR(191) NULL (e.g., "Ssente Ntono", "Ssente Nene")
name_swahili           VARCHAR(191) NULL (e.g., "Bei Ndogo", "Bei Kubwa")
slug                    VARCHAR(191) UNIQUE NOT NULL
description            TEXT NULL

-- Pricing
price                   DECIMAL(15, 2) NOT NULL
currency               VARCHAR(10) DEFAULT 'UGX'
duration_days          INT NOT NULL (3, 14, 30, 90, 365)

-- Features (HTML formatted)
features               TEXT NULL
features_luganda       TEXT NULL
features_swahili       TEXT NULL

-- Status & Sorting
status                 ENUM('Active', 'Inactive') DEFAULT 'Active'
is_featured            BOOLEAN DEFAULT FALSE
sort_order             INT DEFAULT 0

-- Discount & Promotions
discount_percentage    DECIMAL(5, 2) DEFAULT 0.00
is_trial               BOOLEAN DEFAULT FALSE

-- Limits (for feature gating)
max_downloads          INT NULL (NULL = unlimited)
max_watchlist          INT NULL
ad_free                BOOLEAN DEFAULT TRUE

-- Metadata
created_by             BIGINT UNSIGNED NULL
updated_by             BIGINT UNSIGNED NULL
```

**Indexes:**
- `idx_status` ON (status)
- `idx_sort_order` ON (sort_order, status)
- `idx_slug` ON (slug)

**Default Plans:**
```php
1. "Ssente Ntono" (Small Money) - 3 days - UGX 1,000
2. "Wiiki Bbiri" (Two Weeks) - 14 days - UGX 5,000
3. "Mwezi" (One Month) - 30 days - UGX 8,000
```

---

### Table: `subscriptions`

```sql
id                          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
created_at                  TIMESTAMP NULL
updated_at                  TIMESTAMP NULL

-- Foreign Keys
user_id                     BIGINT UNSIGNED NOT NULL
plan_id                     BIGINT UNSIGNED NOT NULL

-- Subscription Period
days                        INT NOT NULL
start_date_time            DATETIME NOT NULL
end_date_time              DATETIME NOT NULL
grace_period_end           DATETIME NULL (3 days after end)

-- Status
status                      ENUM('Pending', 'Active', 'Expired', 'Cancelled', 'Failed') DEFAULT 'Pending'
auto_renew                  BOOLEAN DEFAULT FALSE

-- Payment Details
payment_method              VARCHAR(50) DEFAULT 'pesapal'
payment_status              ENUM('Pending', 'Processing', 'Completed', 'Failed', 'Refunded') DEFAULT 'Pending'

-- Pesapal Integration
pesapal_transaction_id      VARCHAR(191) NULL
pesapal_tracking_id         VARCHAR(191) NULL (order_tracking_id)
pesapal_merchant_reference  VARCHAR(191) NULL UNIQUE
pesapal_signature          TEXT NULL
pesapal_response           JSON NULL

-- Amount & Currency
amount_paid                DECIMAL(15, 2) NOT NULL
currency                   VARCHAR(10) DEFAULT 'UGX'

-- Extension Tracking (for renewals)
is_extension               BOOLEAN DEFAULT FALSE
extended_from_id           BIGINT UNSIGNED NULL (previous subscription)

-- Metadata
cancelled_at               DATETIME NULL
cancelled_reason           TEXT NULL
cancelled_by               BIGINT UNSIGNED NULL
ip_address                 VARCHAR(45) NULL
user_agent                 TEXT NULL

-- Notifications
expiry_notification_sent   BOOLEAN DEFAULT FALSE
expiry_notification_at     DATETIME NULL

FOREIGN KEY (user_id) REFERENCES admin_users(id)
FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
FOREIGN KEY (extended_from_id) REFERENCES subscriptions(id)
```

**Indexes:**
- `idx_user_status` ON (user_id, status)
- `idx_status_end_date` ON (status, end_date_time)
- `idx_pesapal_tracking` ON (pesapal_tracking_id)
- `idx_merchant_reference` ON (pesapal_merchant_reference)
- `idx_payment_status` ON (payment_status)
- `idx_expiry_check` ON (status, end_date_time, expiry_notification_sent)

---

### Table: `subscription_transactions` (Payment audit trail)

```sql
id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT
created_at              TIMESTAMP NULL
updated_at              TIMESTAMP NULL

-- Relations
subscription_id         BIGINT UNSIGNED NOT NULL
user_id                BIGINT UNSIGNED NOT NULL

-- Transaction Details
transaction_type        ENUM('Initial', 'Renewal', 'Upgrade', 'Refund') DEFAULT 'Initial'
amount                 DECIMAL(15, 2) NOT NULL
currency               VARCHAR(10) DEFAULT 'UGX'
status                 ENUM('Pending', 'Completed', 'Failed', 'Refunded') DEFAULT 'Pending'

-- Pesapal Details
pesapal_tracking_id     VARCHAR(191) NULL
merchant_reference      VARCHAR(191) NULL
payment_method          VARCHAR(50) NULL
confirmation_code       VARCHAR(191) NULL

-- Audit
request_payload         JSON NULL
response_payload        JSON NULL
error_message          TEXT NULL
ip_address             VARCHAR(45) NULL

FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
FOREIGN KEY (user_id) REFERENCES admin_users(id)
```

**Indexes:**
- `idx_subscription` ON (subscription_id)
- `idx_user` ON (user_id)
- `idx_status` ON (status)
- `idx_pesapal` ON (pesapal_tracking_id)

---

## 🔒 Security Considerations

### 1. Payment Security
- ✅ **Never store credit card details** - handled by Pesapal
- ✅ **Webhook verification** - Verify Pesapal signatures
- ✅ **HTTPS only** - All payment endpoints must use SSL
- ✅ **Idempotency** - Prevent double charging using merchant_reference
- ✅ **Transaction logging** - Comprehensive audit trail

### 2. Subscription Validation
- ✅ **Real-time checks** - Query database on every protected request
- ✅ **Grace period** - 3-day grace period after expiration
- ✅ **Token invalidation** - Optional: invalidate JWT on expiration
- ✅ **Rate limiting** - Prevent subscription status abuse

### 3. Data Protection
- ✅ **Soft deletes** - Never hard delete subscriptions
- ✅ **Encryption** - Encrypt sensitive payment data
- ✅ **Access control** - Only admins can view all subscriptions
- ✅ **GDPR compliance** - User data export/deletion

### 4. Attack Prevention
- ✅ **SQL Injection** - Use Eloquent ORM exclusively
- ✅ **CSRF** - Laravel's built-in CSRF protection
- ✅ **XSS** - Sanitize all HTML content
- ✅ **Brute force** - Rate limit payment attempts
- ✅ **Replay attacks** - Check transaction timestamps

---

## 🚨 Edge Cases & Solutions

### 1. **Concurrent Subscription Purchase**
**Problem:** User buys subscription twice before first completes  
**Solution:** 
```php
// Use database transaction with row locking
DB::transaction(function() use ($user, $plan) {
    $user->lockForUpdate(); // Row-level lock
    $pendingCount = $user->subscriptions()
        ->where('payment_status', 'Pending')
        ->count();
    
    if ($pendingCount > 0) {
        throw new Exception('You have a pending subscription payment');
    }
    
    // Create subscription
});
```

### 2. **Payment Success but Callback Fails**
**Problem:** Pesapal charges but IPN callback doesn't reach server  
**Solution:**
- Implement **manual status check** command
- Add "Check Payment Status" button in user dashboard
- Cronjob to auto-check pending payments older than 15 minutes

```php
// Command: php artisan subscriptions:check-pending
class CheckPendingSubscriptions extends Command
{
    public function handle()
    {
        $pending = Subscription::where('payment_status', 'Pending')
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();
            
        foreach ($pending as $subscription) {
            $status = $this->pesapalService->getTransactionStatus(
                $subscription->pesapal_tracking_id
            );
            
            if ($status['status'] === 'COMPLETED') {
                $subscription->activate();
            }
        }
    }
}
```

### 3. **User Has Active Subscription, Buys Another**
**Problem:** Should we extend or create new?  
**Solution:**
```php
public function createSubscription($user, $plan)
{
    $activeSubscription = $user->activeSubscription();
    
    if ($activeSubscription && $activeSubscription->end_date_time > now()) {
        // EXTEND: Add days to existing subscription
        $newEndDate = Carbon::parse($activeSubscription->end_date_time)
            ->addDays($plan->duration_days);
        
        $subscription = new Subscription([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'days' => $plan->duration_days,
            'start_date_time' => $activeSubscription->end_date_time, // Start after current ends
            'end_date_time' => $newEndDate,
            'is_extension' => true,
            'extended_from_id' => $activeSubscription->id
        ]);
    } else {
        // NEW: Create fresh subscription
        $subscription = new Subscription([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'days' => $plan->duration_days,
            'start_date_time' => now(),
            'end_date_time' => now()->addDays($plan->duration_days)
        ]);
    }
    
    return $subscription;
}
```

### 4. **Subscription Expires During Active Session**
**Problem:** User is watching movie, subscription expires  
**Solution:**
- **Grace period**: Allow 5-10 minute grace during active session
- **Soft warning**: Show notification "Subscription expiring soon"
- **Middleware check**: Only enforce on new requests, not ongoing streams

```php
public function hasActiveSubscription($gracePeriod = false)
{
    $subscription = $this->activeSubscription();
    
    if (!$subscription) {
        return false;
    }
    
    $now = now();
    $endDate = Carbon::parse($subscription->end_date_time);
    
    if ($gracePeriod) {
        // Allow 10 minutes grace for ongoing sessions
        $endDate = $endDate->addMinutes(10);
    }
    
    return $now->lte($endDate);
}
```

### 5. **User Attempts Payment Retry After Failed Transaction**
**Problem:** Multiple failed transactions cluttering database  
**Solution:**
- Mark old failed transactions as 'Abandoned'
- Allow max 3 retry attempts per subscription
- Auto-clean up abandoned subscriptions after 24 hours

### 6. **Timezone Issues**
**Problem:** User in different timezone, subscription expires early/late  
**Solution:**
```php
// ALWAYS use server timezone (UTC)
// Convert for display only
config(['app.timezone' => 'UTC']);

// In model
protected $casts = [
    'start_date_time' => 'datetime:Y-m-d H:i:s',
    'end_date_time' => 'datetime:Y-m-d H:i:s',
];

// In API response
'end_date_time' => $subscription->end_date_time->timezone($user->timezone ?? 'UTC')
```

### 7. **Pesapal Duplicate IPN Notifications**
**Problem:** Pesapal might send IPN multiple times  
**Solution:**
```php
public function handleIPN(Request $request)
{
    $trackingId = $request->OrderTrackingId;
    
    // Check if already processed
    $existing = SubscriptionTransaction::where('pesapal_tracking_id', $trackingId)
        ->where('status', 'Completed')
        ->first();
        
    if ($existing) {
        Log::info('IPN already processed', ['tracking_id' => $trackingId]);
        return response()->json(['status' => 'duplicate'], 200);
    }
    
    // Process IPN
}
```

### 8. **Refund Scenarios**
**Problem:** User requests refund, how to handle?  
**Solution:**
- Create `refund_requested_at` field
- Admin approves/rejects refund
- On approval: Set status to 'Refunded', subtract days from end_date
- Keep transaction history for audit

---

## 💳 Payment Flow (Pesapal Integration)

### Phase 1: Initialization
```
User → Selects Plan → API: POST /api/subscriptions/create
    ↓
Backend:
    1. Validate user (authenticated)
    2. Check for pending subscriptions
    3. Create subscription record (status: Pending)
    4. Generate merchant_reference: SUB-{user_id}-{timestamp}
    5. Call Pesapal: Initialize Payment
    6. Store pesapal_tracking_id
    7. Return redirect_url to frontend
    ↓
Frontend:
    1. Redirect user to Pesapal payment page
    2. User completes payment
```

### Phase 2: Callback
```
Pesapal → Callback URL: GET /api/subscriptions/pesapal/callback
    ↓
Backend:
    1. Extract OrderTrackingId, MerchantReference
    2. Query Pesapal for transaction status
    3. Update subscription:
        - payment_status: Completed/Failed
        - status: Active (if paid) / Failed
        - start_date_time: now()
    4. Send confirmation email
    5. Redirect to success page
    ↓
Frontend:
    1. Show success message
    2. Redirect to dashboard/content
```

### Phase 3: IPN (Instant Payment Notification)
```
Pesapal → IPN URL: POST /api/subscriptions/pesapal/ipn
    ↓
Backend:
    1. Log IPN request
    2. Verify signature
    3. Query transaction status
    4. Update subscription if needed
    5. Create transaction log
    6. Return acknowledgment to Pesapal
```

### Phase 4: Status Check (Fallback)
```
User/System → GET /api/subscriptions/check-status/{subscription_id}
    ↓
Backend:
    1. Find subscription
    2. If status still Pending:
        - Query Pesapal API
        - Update subscription
    3. Return current status
```

---

## 🔄 Subscription Lifecycle

```
┌─────────────┐
│   Created   │ (User initiates purchase)
└──────┬──────┘
       ↓
┌─────────────┐
│   Pending   │ (Awaiting payment)
└──────┬──────┘
       ↓
   ┌───┴────┐
   ↓        ↓
┌────────┐ ┌────────┐
│ Active │ │ Failed │
└───┬────┘ └────────┘
    ↓
┌─────────┐
│ Expired │ (auto-detected by cron)
└─────────┘
    ↓
┌───────────┐
│ Cancelled │ (user/admin action)
└───────────┘
```

---

## 📅 Implementation Phases

### Phase 1: Database & Models (Day 1) ✅
- [x] Create migrations (subscription_plans, subscriptions, subscription_transactions)
- [x] Create models with relationships
- [x] Add User model methods (hasActiveSubscription, activeSubscription)
- [x] Create default plans seeder
- [x] Model hooks for cascading operations

### Phase 2: Middleware & Commands (Day 1) ✅
- [x] CheckSubscription middleware
- [x] Command: subscriptions:check-expired
- [x] Command: subscriptions:send-expiry-notifications
- [x] Command: subscriptions:check-pending-payments
- [x] Schedule commands in Kernel

### Phase 3: Pesapal Integration (Day 2) ✅
- [x] Create SubscriptionPesapalService (based on blitxpress)
- [x] IPN callback handler
- [x] Payment status checker
- [x] Transaction logger

### Phase 4: Admin Controllers (Day 2) ✅
- [x] SubscriptionPlanController (CRUD)
- [x] SubscriptionController (view, cancel, refund)
- [x] Admin routes
- [x] Authorization policies

### Phase 5: API Endpoints (Day 3) ✅
- [x] GET /api/subscription-plans (list available plans)
- [x] POST /api/subscriptions/create (initiate subscription)
- [x] GET /api/subscriptions/my-subscription (current status)
- [x] GET /api/subscriptions/history (past subscriptions)
- [x] POST /api/subscriptions/retry-payment (retry failed payment)
- [x] GET /api/subscriptions/pesapal/callback
- [x] POST /api/subscriptions/pesapal/ipn
- [x] POST /api/subscriptions/check-payment-status

### Phase 6: Frontend Layout (Day 4) ✅
- [x] Create SubscriptionLayout component
- [x] Step-by-step wizard (Select Plan → Payment → Confirmation)
- [x] Protected route wrapper
- [x] Subscription status checker

### Phase 7: Frontend UI (Day 5) ✅
- [x] Plan selection cards
- [x] Price comparison table
- [x] Payment processing screen
- [x] Success/failure pages
- [x] WhatsApp support button

### Phase 8: Dashboard Integration (Day 5) ✅
- [x] Subscription status widget
- [x] Renewal button
- [x] Payment history table
- [x] Expiration countdown

### Phase 9: Enforcement (Day 6) ✅
- [x] API call interceptor (check subscription on each request)
- [x] Route guards (redirect to subscription page)
- [x] Graceful expiration handling
- [x] Error messages

### Phase 10: Testing & Documentation (Day 7) ✅
- [x] Unit tests for models
- [x] Integration tests for payment flow
- [x] E2E tests for user journey
- [x] API documentation
- [x] User guide
- [x] Admin manual

---

## 🧪 Testing Strategy

### Unit Tests
```php
// tests/Unit/SubscriptionTest.php
test('user can have active subscription')
test('subscription expires correctly')
test('subscription extension adds days')
test('expired subscription returns false')
test('grace period allows access')
```

### Integration Tests
```php
// tests/Feature/SubscriptionFlowTest.php
test('user can create subscription')
test('payment callback activates subscription')
test('failed payment marks subscription as failed')
test('ipn notification updates subscription')
test('pending payment can be checked manually')
```

### E2E Tests (Frontend)
```typescript
describe('Subscription Flow', () => {
  it('should allow user to select plan')
  it('should redirect to Pesapal')
  it('should show success after payment')
  it('should block access without subscription')
  it('should allow renewal before expiry')
})
```

---

## 📊 Monitoring & Analytics

### Key Metrics to Track
1. **Conversion Rate**: Users who view plans vs. users who purchase
2. **Payment Success Rate**: Successful payments / Total attempts
3. **Churn Rate**: Expired subscriptions / Total active
4. **Average Subscription Duration**: Days users stay subscribed
5. **Revenue Metrics**: MRR (Monthly Recurring Revenue), ARPU (Average Revenue Per User)

### Database Queries for Reports
```sql
-- Active subscriptions
SELECT COUNT(*) FROM subscriptions WHERE status = 'Active';

-- Revenue this month
SELECT SUM(amount_paid) FROM subscriptions 
WHERE payment_status = 'Completed' 
AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH);

-- Expiring soon (next 7 days)
SELECT * FROM subscriptions 
WHERE status = 'Active' 
AND end_date_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY);

-- Conversion funnel
SELECT 
    COUNT(DISTINCT user_id) as started,
    SUM(CASE WHEN payment_status = 'Completed' THEN 1 ELSE 0 END) as completed,
    (SUM(CASE WHEN payment_status = 'Completed' THEN 1 ELSE 0 END) / COUNT(DISTINCT user_id) * 100) as conversion_rate
FROM subscriptions
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH);
```

---

## 🎨 UI/UX Considerations

### Mobile-First Design
- Large, tappable buttons
- Clear pricing display
- Progress indicators
- Simplified forms

### Accessibility
- ARIA labels on all interactive elements
- Keyboard navigation support
- Screen reader compatible
- Color contrast compliance (WCAG AA)

### Performance
- Lazy load plan images
- Cache plan data
- Minimize API calls
- Optimistic UI updates

### Error Handling
- User-friendly error messages
- Retry mechanisms
- Fallback content
- Support contact always visible

---

## 📞 Support Integration

### WhatsApp Support Button
```tsx
<WhatsAppButton 
  phoneNumber="+16479686445"
  message="Hello! I need help with my subscription"
  position="bottom-right"
/>
```

### Help Resources
- FAQ section (common questions)
- Video tutorials (how to subscribe)
- Contact form (for complex issues)
- Live chat (business hours)

---

## 🚀 Deployment Checklist

### Before Launch
- [ ] Environment variables configured (.env)
- [ ] Database migrations run
- [ ] Default plans seeded
- [ ] Pesapal credentials verified (production)
- [ ] IPN URL registered with Pesapal
- [ ] SSL certificate active
- [ ] Backup strategy in place
- [ ] Monitoring tools configured
- [ ] Error logging enabled
- [ ] Rate limiting configured

### Post-Launch Monitoring
- [ ] Monitor payment success rate (first 24 hours)
- [ ] Check IPN logs for errors
- [ ] Review user feedback
- [ ] Monitor server performance
- [ ] Check email delivery
- [ ] Review analytics data

---

## 📝 API Endpoints Summary

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/subscription-plans` | List available plans | No |
| POST | `/api/subscriptions/create` | Create new subscription | Yes |
| GET | `/api/subscriptions/my-subscription` | Get current subscription | Yes |
| GET | `/api/subscriptions/history` | View subscription history | Yes |
| POST | `/api/subscriptions/retry-payment` | Retry failed payment | Yes |
| POST | `/api/subscriptions/cancel` | Cancel subscription | Yes |
| GET | `/api/subscriptions/pesapal/callback` | Pesapal callback | No |
| POST | `/api/subscriptions/pesapal/ipn` | Pesapal IPN | No |
| POST | `/api/subscriptions/check-status` | Manual status check | Yes |

### Admin Endpoints
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/admin/subscription-plans` | Manage plans | Admin |
| POST | `/admin/subscription-plans` | Create plan | Admin |
| PUT | `/admin/subscription-plans/{id}` | Update plan | Admin |
| DELETE | `/admin/subscription-plans/{id}` | Delete plan | Admin |
| GET | `/admin/subscriptions` | View all subscriptions | Admin |
| POST | `/admin/subscriptions/{id}/cancel` | Cancel user subscription | Admin |
| POST | `/admin/subscriptions/{id}/refund` | Process refund | Admin |

---

## 🎯 Success Criteria

### Technical
- ✅ Zero unauthorized access to protected content
- ✅ 99.9% payment processing uptime
- ✅ < 2 second subscription status check
- ✅ 100% IPN delivery acknowledgment
- ✅ Automated daily expiration checks

### Business
- ✅ Clear, transparent pricing
- ✅ Easy subscription purchase process
- ✅ < 3 clicks to subscribe
- ✅ Automated renewal reminders
- ✅ < 5% failed payment rate

### User Experience
- ✅ Mobile-responsive design
- ✅ Multiple payment methods (Pesapal supports)
- ✅ Clear subscription status display
- ✅ Easy access to support
- ✅ Graceful error handling

---

## 🔮 Future Enhancements

### Phase 2 Features
1. **Promo Codes** - Discount codes for special offers
2. **Referral Program** - Get free days for referring friends
3. **Family Plans** - Shared subscriptions for multiple users
4. **Annual Plans** - Discounted yearly subscriptions
5. **Payment Methods** - MTN Mobile Money, Airtel Money
6. **Auto-Renewal** - Optional automatic subscription renewal
7. **Subscription Pause** - Pause subscription for vacation
8. **Gifting** - Buy subscription as gift for others

### Phase 3 Features
1. **Analytics Dashboard** - Detailed subscription analytics
2. **A/B Testing** - Test different pricing strategies
3. **Dynamic Pricing** - Adjust prices based on market
4. **Loyalty Rewards** - Benefits for long-term subscribers
5. **Enterprise Plans** - Custom plans for businesses

---

## 📚 References

- Pesapal API Documentation: https://developer.pesapal.com
- Laravel Subscriptions Best Practices
- Stripe Billing (inspiration)
- Laravel Cashier (concept reference)

---

**End of Planning Document**

*This plan will be executed phase by phase, with comprehensive testing at each stage.*
