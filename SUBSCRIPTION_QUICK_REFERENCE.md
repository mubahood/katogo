# 🚀 SUBSCRIPTION ENFORCEMENT - QUICK REFERENCE

## ⚡ QUICK START

```bash
# 1. Start Backend
cd /Applications/MAMP/htdocs/katogo
php artisan serve

# 2. Start Frontend
cd /Users/mac/Desktop/github/katogo-react
npm start

# 3. Test
open http://localhost:3000/subscription/plans
```

---

## 🔒 PROTECTED ROUTES

### Backend (Requires Active Subscription)
```
✅ GET  /api/movies
✅ GET  /api/movie/{id}
✅ POST /api/video-progress
✅ GET  /api/watch-history
✅ ALL  /api/account/watchlist/*
✅ ALL  /api/account/likes/*
✅ ALL  /api/account/wishlist/*
```

### Frontend Pages
```
✅ /movies           → Protected by API
✅ /movie/:id        → Protected by API
✅ /account/watchlist → Protected by API
```

---

## 📊 ENFORCEMENT FLOW

```
User tries to access /movies
         ↓
Backend checks subscription
         ↓
   Has subscription?
    ↙          ↘
  YES           NO
   ↓             ↓
Return 200    Return 403
  OK        + require_subscription
              ↓
         Frontend intercepts
              ↓
    Shows toast + redirects
    to /subscription/plans
```

---

## 🧪 QUICK TESTS

### Test 1: No Subscription
```bash
# Expected: Redirect to subscription plans
curl -X GET http://localhost:8000/api/movies \
     -H "Authorization: Bearer YOUR_TOKEN"
```

### Test 2: With Subscription
```bash
# 1. Subscribe via UI
# 2. Try again - Expected: 200 OK
```

### Test 3: Grace Period
```sql
-- Set expired date (2 days ago)
UPDATE subscriptions 
SET end_date = DATE_SUB(NOW(), INTERVAL 2 DAY)
WHERE user_id = 1;

-- Expected: Still works (grace period)
```

---

## 🛠️ KEY FILES

### Backend
```
app/Http/Middleware/CheckSubscription.php   - Middleware
routes/api.php                              - Protected routes
app/Models/User.php                         - hasActiveSubscription()
```

### Frontend
```
src/app/services/Api.ts                     - 403 interceptor
src/app/components/Auth/SubscriptionRoute.tsx - Route guard
src/app/pages/SubscriptionPlans.tsx         - Plans page
```

---

## 🔧 CONFIGURATION

### Backend (.env)
```env
PESAPAL_CONSUMER_KEY=your_key
PESAPAL_CONSUMER_SECRET=your_secret
PESAPAL_ENVIRONMENT=sandbox
SUBSCRIPTION_GRACE_PERIOD_DAYS=3
```

### Middleware
```php
// In routes/api.php
Route::middleware(['auth', 'subscription'])->group(function () {
    Route::get('movies', [Controller::class, 'movies']);
});
```

---

## 📱 USER EXPERIENCE

### Without Subscription
1. User accesses movies → 403
2. Toast: "Subscription required"
3. Auto-redirect to plans (1.5s)
4. User selects plan
5. Pesapal payment
6. Access granted

### With Subscription
1. Dashboard shows status widget
2. Green badge: "Active - X days left"
3. Full access to all content
4. Watch, save, like movies

---

## 🎯 STATUS CHECKS

### Check Middleware
```bash
grep -n "CheckSubscription" /Applications/MAMP/htdocs/katogo/app/Http/Kernel.php
```

### Check Protected Routes
```bash
grep -n "subscription" /Applications/MAMP/htdocs/katogo/routes/api.php
```

### Check Frontend Interceptor
```bash
grep -n "require_subscription" /Users/mac/Desktop/github/katogo-react/src/app/services/Api.ts
```

---

## 🐛 TROUBLESHOOTING

### Issue: Not Redirecting
**Check**: Frontend interceptor catches `require_subscription` flag
```javascript
// In Api.ts
if (error.response?.data?.data?.require_subscription) {
  window.location.href = '/subscription/plans';
}
```

### Issue: Grace Period Not Working
**Check**: Middleware parameter
```php
// Allow grace period (default)
Route::middleware(['subscription'])->group(...);

// No grace period
Route::middleware(['subscription:false'])->group(...);
```

### Issue: 403 on Public Routes
**Add to middleware excluded routes**:
```php
protected $excludedRoutes = [
    'api/your-public-route',
];
```

---

## 📞 SUPPORT RESOURCES

- 📖 Full Guide: `SUBSCRIPTION_ENFORCEMENT_GUIDE.md`
- 📝 Complete Docs: `SUBSCRIPTION_SYSTEM_COMPLETE.md`
- 🧪 Test Script: `bash test_subscription_enforcement.sh`
- 💬 WhatsApp: Built into subscription pages

---

## ✅ DEPLOYMENT CHECKLIST

- [ ] Migrations run
- [ ] Plans seeded
- [ ] Pesapal configured
- [ ] Middleware applied
- [ ] Frontend built
- [ ] Cron job set up
- [ ] IPN URL configured
- [ ] Test payments work

---

**Status**: ✅ PRODUCTION READY  
**Version**: 1.0.0  
**Last Updated**: January 2025
