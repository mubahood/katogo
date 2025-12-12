# Payment System Fix - Quick Reference

## 🚀 Quick Start

### Run Tests
```bash
php test_payment_status_checker.php
```

### Process Stuck Payments
```bash
php artisan subscriptions:check-pending-payments
```

### Check Specific Payment
```php
$statusChecker = app(App\Services\PaymentStatusChecker::class);
$subscription = Subscription::find(123);
$result = $statusChecker->checkPaymentStatus($subscription);
```

## 🔧 Key Fixes

| Issue | Solution | File |
|-------|----------|------|
| Weak comparisons | Strict `===` checks | `SubscriptionPesapalService.php` |
| No retries | 3-attempt exponential backoff | `PaymentStatusChecker.php` |
| Race conditions | Transaction + cache locks | `PaymentStatusChecker.php` |
| Token expiration | 270s cache (was 240s) | `SubscriptionPesapalService.php` |
| IPN failures | Retry mechanism in callback | `SubscriptionApiController.php` |
| Stuck payments | Force verify endpoint | `SubscriptionApiController.php` |

## 📊 New Features

### 1. PaymentStatusChecker Service
```php
// Standard check
$statusChecker->checkPaymentStatus($subscription, [
    'max_retries' => 3,
    'retry_delay' => 2,
    'exponential_backoff' => true,
]);

// Bulk check
$results = $statusChecker->checkPendingPayments([
    'age_minutes' => 15,
    'limit' => 50,
]);

// Force verify (support/admin)
$result = $statusChecker->forceVerifyPayment($subscription);
```

### 2. Enhanced API Endpoints

**Check Status** (with retries)
```bash
POST /api/subscriptions/check-status
{"subscription_id": 123}
```

**Force Verify** (manual recovery)
```bash
POST /api/subscriptions/force-verify
{"subscription_id": 123}
```

### 3. Improved Command
```bash
# Standard run
php artisan subscriptions:check-pending-payments

# Custom options
php artisan subscriptions:check-pending-payments --age=30 --limit=100

# Dry run
php artisan subscriptions:check-pending-payments --dry-run
```

## 🔍 Troubleshooting

### Payment Stuck Pending?

**Option 1: API**
```bash
curl -X POST https://api.munowatch.com/api/subscriptions/force-verify \
  -H "Authorization: Bearer TOKEN" \
  -d '{"subscription_id": 123}'
```

**Option 2: Artisan**
```bash
php artisan subscriptions:check-pending-payments --age=1
```

**Option 3: Tinker**
```bash
php artisan tinker
>>> $sub = Subscription::find(123);
>>> app(App\Services\PaymentStatusChecker::class)->forceVerifyPayment($sub);
```

### Check Current Status
```sql
-- Pending payments count
SELECT COUNT(*) FROM subscriptions 
WHERE status = 'Pending' AND payment_status IN ('Pending', 'Processing');

-- Recent pending
SELECT id, user_id, created_at, payment_status 
FROM subscriptions 
WHERE status = 'Pending' 
  AND created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
ORDER BY created_at DESC;
```

### Monitor Logs
```bash
# Watch payment logs
tail -f storage/logs/laravel.log | grep -i "payment\|pesapal"

# Count errors
grep -i "payment.*error" storage/logs/laravel.log | wc -l
```

## ⚙️ Configuration

### Cron Schedule (Recommended)
```bash
# /etc/crontab or Laravel scheduler
*/15 * * * * cd /path/to/katogo && php artisan subscriptions:check-pending-payments
```

### Environment
```env
CACHE_DRIVER=redis  # Recommended for production
LOG_LEVEL=info      # For detailed payment logging
```

## 📝 Common Tasks

### Fix Single Stuck Payment
```php
$subscription = Subscription::find(123);
$checker = app(App\Services\PaymentStatusChecker::class);
$result = $checker->forceVerifyPayment($subscription);

if ($result['success']) {
    echo "Payment verified: " . $result['status'];
}
```

### Fix All Stuck Payments
```bash
php artisan subscriptions:check-pending-payments --age=1 --limit=500
```

### Check System Health
```bash
php test_payment_status_checker.php
```

## 📈 Success Metrics

### Expected Results
- **Pending:** < 5% (resolved within 15 min)
- **Failed:** < 2% (genuine failures only)
- **Successful:** > 93%

### Before vs After
```sql
-- Run this query before and after deployment
SELECT 
    status,
    payment_status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM subscriptions
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY status, payment_status
ORDER BY count DESC;
```

## 🎯 Best Practices

### ✅ DO
- Run CheckPendingPayments every 15 minutes
- Use force verify for user support
- Monitor logs for payment errors
- Check Pesapal dashboard for discrepancies

### ❌ DON'T
- Don't trust IPN as source of truth
- Don't manually update payment status without verification
- Don't skip the retry mechanism
- Don't cache payment status on frontend

## 🆘 Emergency Recovery

### Mass Payment Verification
```bash
# Check all pending payments from last 7 days
php artisan subscriptions:check-pending-payments --age=1 --limit=1000
```

### Clear Stuck Cache Locks
```bash
php artisan cache:clear
```

### Database Transaction Deadlock
```sql
-- Show running transactions
SHOW PROCESSLIST;

-- Kill stuck transaction (use carefully)
KILL <process_id>;
```

## 📞 Support Contact

If you encounter issues:
1. Run test script: `php test_payment_status_checker.php`
2. Check logs: `tail -f storage/logs/laravel.log`
3. Verify Pesapal status in dashboard
4. Use force verify API endpoint
5. Contact Pesapal support if API issues persist

## 📚 Related Files

- `app/Services/PaymentStatusChecker.php` - Main retry logic
- `app/Services/SubscriptionPesapalService.php` - Pesapal API integration
- `app/Http/Controllers/SubscriptionApiController.php` - API endpoints
- `app/Console/Commands/CheckPendingPayments.php` - Cron command
- `PAYMENT_SYSTEM_FIX_COMPLETE.md` - Full documentation

---
**Last Updated:** 2024  
**Version:** 1.0
