# MovieLike Testing Guide

## Quick Test Instructions

### Prerequisites
1. Backend server running (MAMP)
2. Frontend server running (npm run dev)
3. Valid user account (not guest)

---

## Test 1: Authenticated User Like/Unlike ✅

### Steps:
1. Log in to the application with valid credentials
2. Navigate to any movie watch page
3. Click the heart/like button
4. **Expected Result:**
   - Button animates and becomes active (filled heart)
   - Toast shows: "Added to your liked movies! ❤️"
   - Likes count increments by 1
   - Page remains responsive

5. Click the heart button again (unlike)
6. **Expected Result:**
   - Button becomes inactive (outline heart)
   - Toast shows: "Removed from liked movies"
   - Likes count decrements by 1

7. Refresh the page
8. **Expected Result:**
   - Like state is preserved (button shows correct state)
   - Likes count is accurate

---

## Test 2: Unauthenticated User ❌

### Steps:
1. Log out or clear authentication token
2. Navigate to any movie watch page
3. Click the heart/like button
4. **Expected Result:**
   - Error toast shows: "Please log in to like movies"
   - Button reverts to previous state (optimistic update rollback)
   - No like is created in database

**Backend Response:**
```json
{
  "code": 0,
  "message": "You must be logged in to like movies",
  "data": ""
}
```

---

## Test 3: Guest User 🚫

### Steps:
1. Access the app as guest (if guest mode available)
2. Navigate to any movie watch page
3. Click the heart/like button
4. **Expected Result:**
   - Warning toast shows: "Guest users cannot like movies. Please create an account."
   - Button reverts to previous state
   - No like is created in database

**Backend Response:**
```json
{
  "code": 0,
  "message": "Guest users cannot like movies. Please create an account.",
  "data": ""
}
```

---

## Test 4: Network Error 🌐

### Steps:
1. Log in with valid account
2. Stop the backend server (simulate network failure)
3. Click the heart/like button
4. **Expected Result:**
   - Error toast shows: "Failed to toggle like: [error message]"
   - Button reverts to previous state (optimistic update rollback)
   - Likes count reverts to previous value

---

## Test 5: Invalid Movie ID ⚠️

### Manual API Test:
```bash
curl -X POST http://localhost/katogo/api/account/likes/toggle \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"movie_id": 99999999}'
```

**Expected Response:**
```json
{
  "code": 0,
  "message": "Invalid movie ID: {...}",
  "data": ""
}
```

---

## Test 6: Rapid Clicking (Race Condition) 🏃

### Steps:
1. Log in with valid account
2. Navigate to movie watch page
3. Click the like button 5 times rapidly
4. **Expected Result:**
   - Button is disabled during API call (`isLiking` state)
   - Only one request is processed
   - Final state is correct (liked or unliked)
   - No duplicate likes in database

---

## Test 7: Database Verification 🗄️

### Check Database:
```sql
-- View all likes for a movie
SELECT * FROM movie_likes 
WHERE movie_model_id = 10350 
ORDER BY created_at DESC;

-- Check likes count
SELECT COUNT(*) as total_likes 
FROM movie_likes 
WHERE movie_model_id = 10350 
AND status = 'Active';

-- Verify device tracking
SELECT id, user_id, movie_model_id, ip_address, device, platform, browser, country, city, status 
FROM movie_likes 
WHERE user_id = YOUR_USER_ID 
ORDER BY created_at DESC 
LIMIT 5;
```

**Expected:**
- ✅ user_id matches authenticated user
- ✅ movie_model_id matches current movie
- ✅ ip_address captured (e.g., "::1" for localhost)
- ✅ device captured (e.g., "Mobile", "Desktop")
- ✅ platform captured (e.g., "Linux", "Windows", "MacOS")
- ✅ browser captured (e.g., "Chrome", "Safari", "Firefox")
- ✅ country/city may be null (optional)
- ✅ status is "Active"

---

## Test 8: Browser Console Check 🔍

### Open Browser DevTools:
1. Go to Console tab
2. Like/unlike a movie
3. **Expected Console Output:**
   - No errors or warnings
   - API request logged (if enabled)
   - Success response from backend

### Check Network Tab:
1. Go to Network tab
2. Like a movie
3. **Expected:**
   - POST request to `/api/account/likes/toggle`
   - Status: 200 OK
   - Response body contains `code: 1` and like data

---

## Test 9: Backend Logs 📋

### Check Laravel Logs:
```bash
tail -f /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log
```

### Like a movie and check logs:

**Success Case:**
- No errors logged
- Like created successfully

**Failure Cases:**
- `[WARNING] Like attempt without authentication` (unauthenticated)
- `[ERROR] Authenticated user not found in database: X` (invalid user)
- `[ERROR] Like toggle error: ...` (general errors with stack trace)

---

## Test 10: Edge Cases 🎯

### A. Deleted Movie
1. Try to like a movie that was deleted
2. **Expected:** "Invalid movie ID" error

### B. Expired Token
1. Use an expired authentication token
2. **Expected:** 401 error + "Please log in to like movies"

### C. Same Movie Multiple Times
1. Like movie A
2. Navigate to movie B
3. Like movie B
4. Go back to movie A
5. **Expected:** Movie A still shows as liked

---

## Success Criteria ✅

All tests should pass with:
- ✅ No foreign key constraint violations
- ✅ Correct authentication enforcement
- ✅ Accurate likes count
- ✅ Proper error handling and recovery
- ✅ Device info captured (when available)
- ✅ UI state matches database state
- ✅ Optimistic updates with rollback on error
- ✅ Clear, user-friendly error messages

---

## Common Issues & Solutions

### Issue: "Foreign key constraint violation"
**Solution:** User must be properly authenticated. Check token validity.

### Issue: Likes count not updating
**Solution:** 
- Check if MovieLike::getMovieLikesCount() is called
- Verify backend returns likes_count in response
- Check frontend updates likesCount state

### Issue: Like state not persisting on refresh
**Solution:**
- Verify has_liked is correctly returned in movie() endpoint
- Check if MovieLike::hasUserLikedMovie() includes status check
- Ensure frontend updates liked state on page load

### Issue: Guest user can like
**Solution:**
- Verify toggle_movie_like uses auth('api')->user() not Utils::get_user()
- Check guest user block (is_guest === 'Yes' check)

---

## Quick Debug Commands

### Check Current User:
```bash
curl http://localhost/katogo/api/user \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Check Movie Details:
```bash
curl http://localhost/katogo/api/movie/10350 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Toggle Like:
```bash
curl -X POST http://localhost/katogo/api/account/likes/toggle \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"movie_id": 10350}'
```

---

## Performance Test

### Load Testing:
1. Like 50+ different movies rapidly
2. **Expected:**
   - All requests complete successfully
   - No duplicate likes
   - Consistent response times (<500ms)
   - Database remains consistent

---

**Ready to Test!** 🚀

Start with Test 1 (authenticated user) and work through the list. If you encounter any issues, check the logs and refer to the troubleshooting section.
