# 🔔 Trending Notification System - Quick Reference

## 🚀 QUICK START

### Check System Health
```bash
php verify_notification_fix.php
```

**Expected Output**:
```
✅ Today's notifications are diverse (4 unique movies)
✅ Last 7 days show good rotation
🎉 FIX IS WORKING CORRECTLY!
```

---

## 📋 AVAILABLE COMMANDS

### 1. Health Check (Daily Use)
```bash
php verify_notification_fix.php
```
**Use when**: Daily monitoring, quick status check

### 2. Comprehensive Analysis
```bash
php test_trending_notification_fix.php
```
**Use when**: Investigating issues, detailed diagnostics

### 3. Force Create New Notifications
```bash
php force_create_trending_notifications.php
```
**Use when**: Need to reset today's notifications, ensure diversity

### 4. Test Sending Process
```bash
echo "yes" | php simulate_notification_sending.php
```
**Use when**: Testing notification sending, marking as sent

---

## ⚡ COMMON TASKS

### Reset Today's Notifications
```bash
# Delete and create fresh diverse notifications
php force_create_trending_notifications.php
```

### Check What Will Be Sent
```bash
mysql -uroot -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock katogo_2 \
  -e "SELECT day_time, title, is_sent FROM trending_notifications WHERE DATE(created_at) = CURDATE();"
```

### View Recent History
```bash
mysql -uroot -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock katogo_2 \
  -e "SELECT DATE(created_at) as date, COUNT(*) as total, COUNT(DISTINCT movie_model_id) as unique_movies FROM trending_notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at);"
```

---

## 🔍 TROUBLESHOOTING

### Same Movie Appearing Multiple Times

**Check**:
```bash
php verify_notification_fix.php
```

**Fix**:
```bash
php force_create_trending_notifications.php
```

---

### No Notifications Being Created

**Check**:
```sql
-- Are there any active movies?
SELECT COUNT(*) FROM movie_models WHERE status = 'Active' AND type = 'Movie';

-- Recent notifications?
SELECT * FROM trending_notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY);
```

**Fix**:
```bash
php force_create_trending_notifications.php
```

---

### All Notifications Showing Same Movie

**This is the bug that was fixed!**

**Verify fix is applied**:
```bash
# Check if fix is working
php verify_notification_fix.php

# Should show 4 different movies
```

**If still broken**:
```bash
# Re-apply fix by creating new notifications
php force_create_trending_notifications.php
```

---

## 📊 KEY METRICS TO MONITOR

### Daily
- **Diversity Ratio**: Should be close to 100% (different movie per time period)
- **Unique Movies**: Should have 4 unique movies for today

### Weekly
- **Rotation**: Should have 10+ unique movies in 7 days
- **Repetition**: No movie should appear more than 2-3 times per week

### Monthly
- **Coverage**: Should cycle through 40+ different movies
- **No Excessive Repetition**: No movie should appear 10+ times

---

## 🎯 EXPECTED BEHAVIOR

### Perfect Day
```
MORNING   : Sex And Death 101 - Vj Kriss Sweet ✅
AFTERNOON : Good Luck Chuck ✅
EVENING   : The Shadow's Edge ✅
NIGHT     : Sex Tape - Vj Junior ✅

Result: 4 different movies = 100% diversity ✅
```

### Acceptable Day
```
MORNING   : Movie A ✅
AFTERNOON : Movie B ✅
EVENING   : Movie C ✅
NIGHT     : Movie A ⚠️ (repeated from morning)

Result: 3 different movies = 75% diversity ⚠️
```

### Problem Day (BUG)
```
MORNING   : Movie A ❌
AFTERNOON : Movie A ❌
EVENING   : Movie A ❌
NIGHT     : Movie A ❌

Result: 1 movie = 0% diversity ❌ FIX NEEDED!
```

---

## 📁 FILE LOCATIONS

**Main Code**: `/Applications/MAMP/htdocs/katogo/app/Models/TrendingNotification.php`

**Tools**:
- `verify_notification_fix.php` - Quick health check ⭐
- `test_trending_notification_fix.php` - Detailed analysis
- `force_create_trending_notifications.php` - Reset & create
- `simulate_notification_sending.php` - Test sending

**Documentation**:
- `NOTIFICATION_FIX_SUMMARY.md` - Executive summary
- `TRENDING_NOTIFICATION_FIX_COMPLETE.md` - Technical details
- `README_NOTIFICATIONS.md` - This file

**Database**:
- Table: `trending_notifications`
- Movies: `movie_models`

---

## 🔧 CONFIGURATION

### Current Settings
- **Exclusion Period**: 7 days (movies won't repeat for 7 days)
- **Time Periods**: 4 (morning, afternoon, evening, night)
- **Min Watch Time**: 30 minutes (for fallback selection)

### Adjust Exclusion Period
**File**: `app/Models/TrendingNotification.php`  
**Line**: ~68

```php
// Current: 7 days
Carbon::now()->subDays(7)

// For more variety: 3 days
Carbon::now()->subDays(3)

// For less repetition: 14 days
Carbon::now()->subDays(14)
```

---

## 🎯 SUCCESS CRITERIA

✅ **Today**: 4 different movies (one per time period)  
✅ **This Week**: 10+ unique movies  
✅ **No Movie**: Sent more than 2-3 times per week  
✅ **All Notifications**: Successfully sent (is_sent = 'Yes')

---

## 📞 EMERGENCY PROCEDURES

### If System Breaks
```bash
# Step 1: Verify the issue
php verify_notification_fix.php

# Step 2: Force create new notifications
php force_create_trending_notifications.php

# Step 3: Verify fix worked
php verify_notification_fix.php

# Should now show:
# ✅ Today's notifications are diverse
# 🎉 FIX IS WORKING CORRECTLY!
```

---

## 📈 MONITORING SCHEDULE

### Daily (2 minutes)
```bash
php verify_notification_fix.php
```

### Weekly (5 minutes)
```bash
php test_trending_notification_fix.php
```

### Monthly (10 minutes)
```bash
# Run full analysis
php test_trending_notification_fix.php

# Check database stats
mysql -uroot -proot --socket=/Applications/MAMP/tmp/mysql/mysql.sock katogo_2 \
  -e "SELECT COUNT(*) as total, COUNT(DISTINCT movie_model_id) as unique FROM trending_notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);"
```

---

## 🎊 RESULTS

**Before Fix**: Same movie 229 times ❌  
**After Fix**: 4 different movies daily ✅  
**Status**: 🟢 Working Perfectly

---

**Last Updated**: December 13, 2025  
**Status**: Production Ready ✅  
**Version**: 1.0
