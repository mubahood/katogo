# Payment Confirmation - Quick Testing Guide

## 🧪 Quick Test Checklist

### Prerequisites
✅ Backend running on `http://localhost:8000`  
✅ Frontend running on `http://localhost:5173`  
✅ Pesapal sandbox credentials configured  
✅ Database migrations applied  

---

## Test 1: Successful Payment Flow ✅

### Steps:
1. Navigate to: `http://localhost:5173/subscription/plans`
2. Click "Subscribe" on any plan
3. Payment page opens in new tab
4. Use Pesapal test card:
   - Card: `4111 1111 1111 1111`
   - CVV: `123`
   - Expiry: Any future date
5. Complete payment
6. Should redirect to: `/subscription/callback?status=success&tracking_id={id}`

### Expected Behavior:
- Shows "Verifying Payment..." loader
- Shows "Payment Successful!" with green checkmark
- Displays subscription details:
  - Plan name
  - Status: Active
  - Payment Status: Completed
  - Amount paid
  - Valid from/until dates
- Auto-redirects to My Subscriptions after 5 seconds

### Backend Logs to Check:
```bash
tail -f /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log | grep "Pesapal"
```

Look for:
```
🔔 Pesapal Callback: Received callback
📦 Pesapal: Found subscription
✅ Pesapal: Payment SUCCESSFUL
💾 Pesapal: Subscription SAVED successfully
🎉 Pesapal: Subscription ACTIVATED successfully
```

### Database Verification:
```sql
SELECT id, status, payment_status, start_date_time, end_date_time, payment_confirmed_at
FROM subscriptions
ORDER BY id DESC LIMIT 1;

-- Expected:
-- status: Active
-- payment_status: Completed
-- start_date_time: NOT NULL
-- end_date_time: NOT NULL
-- payment_confirmed_at: NOT NULL
```

### Auto-Ticket Verification:
```sql
-- Latest payment success ticket
SELECT id, user_id, ticket_type, status, resolution_state, subject, last_reply_at
FROM customer_tickets
WHERE ticket_type = 'payment_thanks'
ORDER BY id DESC LIMIT 1;

-- Latest auto-generated payment success record
SELECT ctr.id, ctr.customer_ticket_id, ctr.sender_type, ctr.action_description, ctr.created_at
FROM customer_ticket_records ctr
WHERE ctr.action_description LIKE 'AUTO_PAYMENT_TICKET|subscription=%|trigger=payment_success'
ORDER BY ctr.id DESC LIMIT 1;
```

---

## Test 2: Failed Payment ❌

### Steps:
1-3. Same as Test 1
4. Use declined card or click "Cancel Payment"
5. Should redirect to: `/subscription/callback?status=failed&tracking_id={id}`

### Expected Behavior:
- Shows "Payment Failed" with red X icon
- Displays error message
- Shows "Try Again" button
- Shows WhatsApp support link

### Backend Logs:
```
❌ Pesapal: Payment FAILED
💾 Pesapal: Subscription marked as FAILED
```

### Database Verification:
```sql
-- status: Failed
-- payment_status: Failed
-- failed_at: NOT NULL
```

### Auto-Ticket Verification:
```sql
SELECT id, user_id, ticket_type, status, resolution_state, subject
FROM customer_tickets
WHERE ticket_type = 'payment_fail'
ORDER BY id DESC LIMIT 1;

SELECT ctr.id, ctr.customer_ticket_id, ctr.action_description, ctr.created_at
FROM customer_ticket_records ctr
WHERE ctr.action_description LIKE 'AUTO_PAYMENT_TICKET|subscription=%|trigger=payment_failed'
ORDER BY ctr.id DESC LIMIT 1;
```

---

## Test 3: Pending Payment (Mobile Money) ⏳

### Steps:
1-3. Same as Test 1
4. Select "Mobile Money" option (if available in sandbox)
5. Don't complete payment immediately
6. Should redirect to: `/subscription/callback?status=pending&tracking_id={id}`

### Expected Behavior:
- Shows "Payment Processing" with spinner
- Displays auto-check message: "Auto-checking status every 10 seconds... (Check 1/20)"
- Shows "Check Status Now" button
- Shows info: "You can safely close this page..."

### What Happens:
- Frontend calls `/api/subscriptions/payment-status/{trackingId}` every 10 seconds
- If you complete payment on mobile, status automatically updates to success
- Max 20 auto-checks (3.3 minutes total)

### Backend Logs:
```
⏳ Pesapal: Payment still PENDING
🔍 Payment Status Check: Starting
🔄 Payment Status Check: Querying Pesapal
```

### Auto-Ticket Verification (Pending > 15 Minutes):
1. Keep the payment pending for at least 15 minutes.
2. Trigger either endpoint:
   - `GET /api/subscriptions/payment-status/{trackingId}`
   - `GET /api/subscriptions/pending`
3. Verify billing issue auto-ticket was created:

```sql
SELECT id, user_id, ticket_type, status, resolution_state, subject
FROM customer_tickets
WHERE ticket_type = 'billing_issue'
  AND subject LIKE 'Payment pending over 15 minutes for subscription #%'
ORDER BY id DESC LIMIT 1;

SELECT ctr.id, ctr.customer_ticket_id, ctr.action_description, ctr.created_at
FROM customer_ticket_records ctr
WHERE ctr.action_description LIKE 'AUTO_PAYMENT_TICKET|subscription=%|trigger=payment_pending_15m'
ORDER BY ctr.id DESC LIMIT 1;
```

### Idempotency Verification:
Run the same status endpoint 2-3 times. Confirm only one record exists per trigger signature.

```sql
SELECT action_description, COUNT(*) AS total
FROM customer_ticket_records
WHERE action_description LIKE 'AUTO_PAYMENT_TICKET|subscription=%'
GROUP BY action_description
HAVING COUNT(*) > 1;
```

Expected: zero rows.

---

## Test 4: Network Timeout 🌐

### Steps:
1. Start payment flow
2. Complete payment
3. **Disconnect internet** before callback page loads
4. Page shows "Connection Timeout" error
5. **Reconnect internet**
6. Click "Retry Verification"

### Expected Behavior:
- Shows timeout error message
- Provides retry button
- After reconnecting, retry works and shows success

---

## Test 5: Browser Close During Payment 🔄

### Steps:
1. Start payment flow
2. Complete payment on Pesapal
3. **Close browser** before callback page loads
4. **Reopen browser**
5. Navigate to: `/subscription/my-subscriptions`

### Expected Behavior:
- Subscription shows as "Active" (updated by IPN)
- All fields populated correctly
- Can access premium content

---

## Quick Debug Commands

### Check Latest Subscription:
```sql
SELECT * FROM subscriptions ORDER BY id DESC LIMIT 1 \G
```

### Check Latest Transaction:
```sql
SELECT * FROM subscription_transactions ORDER BY id DESC LIMIT 1 \G
```

### Check Pending Payments:
```sql
SELECT id, user_id, status, payment_status, created_at
FROM subscriptions
WHERE payment_status IN ('Pending', 'Processing')
ORDER BY created_at DESC;
```

### Check Pesapal Logs:
```bash
# All Pesapal logs
grep "Pesapal" /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log | tail -50

# Only errors
grep "Pesapal.*ERROR" /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log | tail -20

# Only successes
grep "Pesapal.*ACTIVATED" /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log | tail -10
```

---

## Frontend Console Debugging

### Open Browser DevTools (F12):

**Console Tab** - Look for:
```
🔔 PaymentResult: Component mounted
📋 URL Params: {status: "success", tracking_id: "..."}
🔍 Starting payment verification
📦 Backend Response: {...}
🎉 Payment CONFIRMED as COMPLETED by backend
```

**Network Tab** - Check:
- Request to: `/api/subscriptions/payment-status/{trackingId}`
- Status: 200 OK
- Response contains `subscription` and `manifest` objects

---

## Common Issues & Solutions

### Issue: "Subscription not found"
**Solution**: Check if subscription was created. Query:
```sql
SELECT * FROM subscriptions WHERE pesapal_tracking_id = 'YOUR_TRACKING_ID';
```

### Issue: Stuck on "Verifying Payment..."
**Solution**: 
1. Check browser console for errors
2. Check network tab for failed API calls
3. Verify backend is running
4. Check CORS settings

### Issue: Shows success but subscription not active
**Solution**: Check logs for activation errors:
```bash
grep "Subscription.*activate" /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log | tail -10
```

### Issue: IPN not working
**Solution**:
1. Check IPN URL registered with Pesapal
2. Verify server accessible from internet (use ngrok for local testing)
3. Check IPN endpoint logs:
```bash
grep "Pesapal IPN" /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log | tail -20
```

---

## Testing Checklist

- [ ] Test 1: Successful payment ✅
- [ ] Test 2: Failed payment ❌
- [ ] Test 3: Pending payment ⏳
- [ ] Test 4: Network timeout 🌐
- [ ] Test 5: Browser close 🔄
- [ ] Verify backend logs
- [ ] Verify database updates
- [ ] Verify transaction records
- [ ] Test IPN callback
- [ ] Test auto-check for pending
- [ ] Test manual check button
- [ ] Verify auto payment success ticket (`payment_thanks`)
- [ ] Verify auto payment failed ticket (`payment_fail`)
- [ ] Verify auto pending >15 minutes ticket (`billing_issue`)
- [ ] Verify idempotent signatures (no duplicates)
- [ ] Verify redirect after success
- [ ] Verify error messages
- [ ] Verify support links work

---

## Success Criteria

✅ **Payment Flow**: User can complete payment and subscription activates  
✅ **Database**: All fields populated correctly  
✅ **Transactions**: Transaction record created/updated  
✅ **Logging**: All steps logged with emojis  
✅ **Error Handling**: Graceful failure with retry options  
✅ **Edge Cases**: Handles timeout, browser close, network issues  
✅ **User Experience**: Clear feedback at each step  

---

## Next Steps After Testing

1. If all tests pass → Deploy to production
2. If any test fails → Check logs and debug
3. Document any issues found
4. Update this guide with new findings

---

**Quick Start Command**:
```bash
# Start backend
cd /Applications/MAMP/htdocs/katogo
php artisan serve

# Start frontend
cd /Users/mac/Desktop/github/katogo-react
npm run dev

# Watch logs in separate terminal
tail -f /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log
```

**Ready to Test!** 🚀

Last Updated: October 4, 2025
