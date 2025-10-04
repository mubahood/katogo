# 🚀 Subscription System - Backend Deployment Guide

**Version:** 1.0  
**Date:** October 3, 2025

---

## ✅ Pre-Deployment Checklist

### 1. Environment Configuration

Add these variables to your `.env` file:

```bash
# Pesapal Configuration
PESAPAL_CONSUMER_KEY=your_consumer_key_here
PESAPAL_CONSUMER_SECRET=your_consumer_secret_here
PESAPAL_PRODUCTION_URL=https://pay.pesapal.com/v3
PESAPAL_IPN_URL=https://yourdomain.com/api/subscriptions/pesapal/ipn
PESAPAL_CALLBACK_URL=https://yourdomain.com/api/subscriptions/pesapal/callback

# Frontend URL (for redirects)
APP_FRONTEND_URL=https://your-frontend-domain.com

# Subscription Settings (optional)
SUBSCRIPTION_LOG_ACCESS=false
```

### 2. Database Migrations

Run the migrations to create subscription tables:

```bash
cd /Applications/MAMP/htdocs/katogo

# Run migrations
php artisan migrate

# Seed default subscription plans
php artisan db:seed --class=SubscriptionPlanSeeder
```

**Expected Output:**
```
Migration table created successfully.
Migrating: 2025_10_03_000001_create_subscription_plans_table
Migrated:  2025_10_03_000001_create_subscription_plans_table
Migrating: 2025_10_03_000002_create_subscriptions_table
Migrated:  2025_10_03_000002_create_subscriptions_table
Migrating: 2025_10_03_000003_create_subscription_transactions_table
Migrated:  2025_10_03_000003_create_subscription_transactions_table

Seeding: SubscriptionPlanSeeder
Created plan: Quick Start (Ssente Ntono)
Created plan: Two Weeks Special (Wiiki Bbiri)
Created plan: Monthly Premium (Omwezi Omulungi)
✅ Successfully created 3 subscription plans!
Seeded:  SubscriptionPlanSeeder
```

### 3. Verify Tables

Check that tables were created:

```bash
php artisan tinker
```

```php
// In tinker
\App\Models\SubscriptionPlan::count(); // Should return 3
\App\Models\SubscriptionPlan::all(); // View all plans
exit
```

---

## 📝 Testing the Backend

### Test 1: List Subscription Plans

```bash
curl -X GET "http://localhost/katogo/api/subscription-plans" | json_pp
```

**Expected:** JSON response with 3 subscription plans

### Test 2: Check User Subscription Status

```bash
# First, login to get token
curl -X POST "http://localhost/katogo/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' | json_pp

# Use the token
TOKEN="your_token_here"

curl -X GET "http://localhost/katogo/api/subscriptions/my-subscription" \
  -H "Authorization: Bearer $TOKEN" | json_pp
```

**Expected:** Subscription status (no subscription initially)

### Test 3: Create Subscription

```bash
curl -X POST "http://localhost/katogo/api/subscriptions/create" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 1,
    "callback_url": "http://localhost:3000/subscription-result"
  }' | json_pp
```

**Expected:** JSON with `redirect_url` to Pesapal

### Test 4: Test Middleware

Try accessing a protected route without subscription:

```bash
curl -X GET "http://localhost/katogo/api/movies" \
  -H "Authorization: Bearer $TOKEN" | json_pp
```

**Expected:** 403 error if subscription middleware is applied

---

## 🔧 Command Testing

### Test Commands Manually

```bash
# Check for expired subscriptions
php artisan subscriptions:check-expired --dry-run

# Send expiry notifications
php artisan subscriptions:send-expiry-notifications --dry-run

# Check pending payments
php artisan subscriptions:check-pending-payments --dry-run
```

### View Scheduled Tasks

```bash
php artisan schedule:list
```

**Expected Output:**
```
  0 1 * * * php artisan subscriptions:check-expired ......... Next Due: 19 hours from now
  0 9 * * * php artisan subscriptions:send-expiry-notifi ... Next Due: 11 hours from now
  */15 * * * * php artisan subscriptions:check-pending-pa ... Next Due: 5 minutes from now
```

---

## 🗄️ Database Verification

### Check Tables

```sql
-- Show subscription plans
SELECT id, name, name_luganda, price, duration_days, status FROM subscription_plans;

-- Show subscriptions
SELECT id, user_id, plan_id, status, payment_status, start_date_time, end_date_time 
FROM subscriptions 
ORDER BY created_at DESC 
LIMIT 10;

-- Show transactions
SELECT id, subscription_id, transaction_type, amount, status, created_at 
FROM subscription_transactions 
ORDER BY created_at DESC 
LIMIT 10;
```

---

## 🔄 Cron Job Setup

For production, set up cron jobs to run scheduled tasks:

```bash
# Edit crontab
crontab -e

# Add this line
* * * * * cd /path/to/katogo && php artisan schedule:run >> /dev/null 2>&1
```

This will run Laravel's scheduler every minute, which will then execute your subscription commands at their scheduled times.

---

## 🐛 Debugging

### Enable Debug Mode

In `.env`:
```bash
APP_DEBUG=true
LOG_LEVEL=debug
```

### View Logs

```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/subscriptions-check-expired.log
tail -f storage/logs/subscriptions-expiry-notifications.log
tail -f storage/logs/subscriptions-check-pending.log
```

### Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## 🔍 Common Issues & Solutions

### Issue 1: "Class not found" Error

**Solution:**
```bash
composer dump-autoload
```

### Issue 2: Middleware Not Working

**Solution:**
```bash
# Clear config and route cache
php artisan config:clear
php artisan route:clear

# Check if middleware is registered
php artisan route:list | grep subscription
```

### Issue 3: Pesapal Authentication Fails

**Solution:**
- Verify `PESAPAL_CONSUMER_KEY` and `PESAPAL_CONSUMER_SECRET` in `.env`
- Check if you're using production or sandbox credentials
- Ensure your IP is whitelisted in Pesapal dashboard

### Issue 4: IPN Not Receiving Callbacks

**Solution:**
- Ensure `PESAPAL_IPN_URL` is publicly accessible (not localhost)
- Use ngrok for local testing: `ngrok http 80`
- Check Pesapal dashboard for IPN registration status
- Verify SSL certificate is valid

### Issue 5: Subscription Not Activating

**Solution:**
```bash
# Manually check payment status
php artisan tinker
```
```php
$subscription = \App\Models\Subscription::find(1);
$service = app(\App\Services\SubscriptionPesapalService::class);
$status = $service->getTransactionStatus($subscription->pesapal_tracking_id);
print_r($status);
```

---

## 📊 Performance Optimization

### Index Optimization

Indexes are already added in migrations. Verify:

```sql
SHOW INDEX FROM subscriptions;
SHOW INDEX FROM subscription_plans;
SHOW INDEX FROM subscription_transactions;
```

### Cache Plans

Add caching for frequently accessed plans:

```php
// In your controller
$plans = Cache::remember('subscription_plans', 3600, function () {
    return SubscriptionPlan::active()->ordered()->get();
});
```

### Queue Jobs

For production, consider queuing email notifications:

```bash
# In .env
QUEUE_CONNECTION=database

# Run queue worker
php artisan queue:work --tries=3
```

---

## 🔐 Security Checklist

- ✅ Environment variables not committed to git
- ✅ JWT tokens expire after reasonable time
- ✅ Pesapal IPN URL validates signatures
- ✅ User can only access their own subscriptions
- ✅ Database uses row-level locking for concurrent requests
- ✅ Rate limiting enabled on API endpoints
- ✅ HTTPS enforced in production
- ✅ SQL injection prevented by Eloquent ORM
- ✅ CSRF protection enabled
- ✅ Logs don't expose sensitive data

---

## 📈 Monitoring

### Key Metrics to Track

1. **Active Subscriptions Count**
```sql
SELECT COUNT(*) FROM subscriptions WHERE status = 'Active';
```

2. **Revenue This Month**
```sql
SELECT SUM(amount_paid) as revenue 
FROM subscriptions 
WHERE payment_status = 'Completed' 
AND MONTH(created_at) = MONTH(CURRENT_DATE());
```

3. **Conversion Rate**
```sql
SELECT 
  COUNT(*) as total_subscriptions,
  SUM(CASE WHEN payment_status = 'Completed' THEN 1 ELSE 0 END) as paid,
  (SUM(CASE WHEN payment_status = 'Completed' THEN 1 ELSE 0 END) / COUNT(*) * 100) as conversion_rate
FROM subscriptions
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

4. **Expiring Soon (Next 7 Days)**
```sql
SELECT COUNT(*) 
FROM subscriptions 
WHERE status = 'Active' 
AND end_date_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY);
```

---

## 🎯 Next Steps

### Backend Complete ✅

You've successfully deployed:
- ✅ 3 Database tables
- ✅ 4 Models with relationships
- ✅ 1 Middleware
- ✅ 3 Scheduled commands
- ✅ 1 Pesapal service
- ✅ 8 API endpoints
- ✅ Default subscription plans

### Ready for Frontend Integration

Now you can proceed with the frontend implementation:
1. Create subscription layout and wizard
2. Build plan selection UI
3. Implement payment flow
4. Add subscription enforcement
5. Create user dashboard widgets

---

## 📞 Support

If you encounter any issues:

**WhatsApp:** +1 (647) 968-6445  
**Email:** support@katogo.com

---

## 📚 Documentation Files

- `SUBSCRIPTION_SYSTEM_PLAN.md` - Comprehensive planning document
- `SUBSCRIPTION_API_DOCUMENTATION.md` - API endpoints reference
- `SUBSCRIPTION_BACKEND_DEPLOYMENT.md` - This file

---

**Backend deployment complete! Ready for frontend integration.** 🎉
