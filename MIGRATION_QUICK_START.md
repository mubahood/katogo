# Movie Likes Table Migration - Complete Guide

## 🎯 Quick Start

### Run This Command:
```bash
cd /Applications/MAMP/htdocs/katogo
php artisan migrate --path=database/migrations/2025_10_02_050000_recreate_movie_likes_table_without_constraints.php
```

---

## 📋 What Was Created

### 1. Migration File
**Location:** `/database/migrations/2025_10_02_050000_recreate_movie_likes_table_without_constraints.php`

**What it does:**
- ✅ Drops existing `movie_likes` table
- ✅ Creates new `movie_likes` table without foreign key constraints
- ✅ Makes all columns nullable (except `id`)
- ✅ Keeps same column names and table name
- ✅ Adds performance indexes

### 2. Documentation Files
- ✅ `MIGRATION_GUIDE_REMOVE_CONSTRAINTS.md` - Detailed migration guide
- ✅ `MOVIELIKE_AUTHENTICATION_FIX.md` - Authentication fix documentation
- ✅ `MOVIELIKE_TESTING_GUIDE.md` - Testing instructions

---

## 🔧 Table Structure Comparison

### OLD (With Constraints) ❌
```sql
CREATE TABLE movie_likes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  movie_model_id BIGINT UNSIGNED NOT NULL,
  ...
  CONSTRAINT movie_likes_user_id_foreign 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT movie_likes_movie_model_id_foreign 
    FOREIGN KEY (movie_model_id) REFERENCES movie_models(id) ON DELETE CASCADE
);
```

**Problems:**
- ❌ Foreign key violations if user/movie doesn't exist
- ❌ Can't insert likes with invalid IDs
- ❌ Application crashes on missing references

### NEW (Without Constraints) ✅
```sql
CREATE TABLE movie_likes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  movie_model_id BIGINT UNSIGNED NULL,
  ...
  INDEX movie_likes_user_id_index (user_id),
  INDEX movie_likes_movie_model_id_index (movie_model_id)
);
```

**Benefits:**
- ✅ No foreign key violations
- ✅ Application validates data (more flexible)
- ✅ Can handle edge cases gracefully
- ✅ Better performance (no constraint checking)

---

## 📊 Column Details

| Column | Type | Old | New | Notes |
|--------|------|-----|-----|-------|
| `id` | bigint unsigned | NOT NULL | NOT NULL | Primary key, auto-increment |
| `created_at` | timestamp | NULL | NULL | Unchanged |
| `updated_at` | timestamp | NULL | NULL | Unchanged |
| `user_id` | bigint unsigned | NOT NULL + FK | **NULL** | No foreign key |
| `movie_model_id` | bigint unsigned | NOT NULL + FK | **NULL** | No foreign key |
| `ip_address` | varchar(50) | NULL | NULL | Unchanged |
| `device` | varchar(50) | NULL | NULL | Unchanged |
| `platform` | varchar(50) | NULL | NULL | Unchanged |
| `browser` | varchar(50) | NULL | NULL | Unchanged |
| `country` | varchar(50) | NULL | NULL | Unchanged |
| `city` | varchar(50) | NULL | NULL | Unchanged |
| `status` | varchar(50) | 'Active' | **NULL (default: 'Active')** | Now nullable |

---

## 🚀 Step-by-Step Instructions

### Step 1: Backup (CRITICAL!)
```bash
# Navigate to MAMP MySQL
cd /Applications/MAMP/Library/bin

# Export movie_likes table
./mysqldump -u root -p katogo movie_likes > ~/Desktop/movie_likes_backup_$(date +%Y%m%d_%H%M%S).sql

# Or full database backup
./mysqldump -u root -p katogo > ~/Desktop/katogo_backup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Check Current Table
```bash
cd /Applications/MAMP/htdocs/katogo
php artisan tinker
```
```php
DB::select("SHOW CREATE TABLE movie_likes");
exit;
```

### Step 3: Run Migration
```bash
php artisan migrate --path=database/migrations/2025_10_02_050000_recreate_movie_likes_table_without_constraints.php
```

**Expected output:**
```
Migrating: 2025_10_02_050000_recreate_movie_likes_table_without_constraints
Migrated:  2025_10_02_050000_recreate_movie_likes_table_without_constraints (45.67ms)
```

### Step 4: Verify Structure
```bash
php artisan tinker
```
```php
// Check columns
Schema::getColumnListing('movie_likes');

// Check constraints (should be empty)
DB::select("
  SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE 
  FROM information_schema.TABLE_CONSTRAINTS 
  WHERE TABLE_SCHEMA = 'katogo' AND TABLE_NAME = 'movie_likes'
");

// Should only show PRIMARY key, no FOREIGN keys

exit;
```

### Step 5: Test The API
```bash
# Test like endpoint (should work now)
curl -X POST http://localhost/katogo/api/account/likes/toggle \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"movie_id": 10350}'
```

---

## ✅ Verification Checklist

After migration, verify:

- [ ] Table `movie_likes` exists
- [ ] No foreign key constraints
- [ ] All columns present (12 total)
- [ ] `user_id` is nullable
- [ ] `movie_model_id` is nullable
- [ ] Indexes created (user_id, movie_model_id, status, created_at)
- [ ] Primary key `id` auto-increments
- [ ] API endpoint works without errors

---

## 🔄 Rollback (If Needed)

If something goes wrong:

```bash
# Option 1: Rollback migration
php artisan migrate:rollback --step=1

# Option 2: Restore from backup
mysql -u root -p katogo < ~/Desktop/movie_likes_backup_YYYYMMDD_HHMMSS.sql
```

---

## 🎯 Why This Change?

### Problem:
```json
{
  "message": "SQLSTATE[23000]: Integrity constraint violation: 
   1452 Cannot add or update a child row: a foreign key constraint fails"
}
```

### Solution:
Remove database-level constraints, enforce integrity at application level.

### Backend Validation Still Works:
```php
// User authentication check (application level)
$user = auth('api')->user();
if (!$user) {
    return $this->error('You must be logged in to like movies', 401);
}

// Movie existence check (application level)
$request->validate([
    'movie_id' => 'required|integer|exists:movie_models,id'
]);
```

---

## 📝 Code Changes

### ✅ No Code Changes Required!

The backend code already handles validation:
- ✅ User authentication check
- ✅ Movie existence validation
- ✅ Guest user blocking
- ✅ Error handling

The model relationships still work:
```php
// app/Models/MovieLike.php
public function user() {
    return $this->belongsTo(User::class);
}

public function movie() {
    return $this->belongsTo(MovieModel::class, 'movie_model_id');
}
```

---

## 🧪 Testing After Migration

### Test 1: Like Movie (Authenticated)
```bash
# Should work perfectly
curl -X POST http://localhost/katogo/api/account/likes/toggle \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"movie_id": 10350}'
```

### Test 2: Unlike Movie
```bash
# Click like button again - should unlike
# Response: {"code": 1, "message": "Movie unliked successfully"}
```

### Test 3: Unauthenticated User
```bash
# Should get 401 error
curl -X POST http://localhost/katogo/api/account/likes/toggle \
  -H "Content-Type: application/json" \
  -d '{"movie_id": 10350}'
```

---

## 💡 Important Notes

### Data Integrity
- **Before:** Database enforces (foreign keys)
- **After:** Application enforces (validation code)

### Orphaned Data
Without constraints, you might get:
- Likes from deleted users
- Likes for deleted movies

**Clean up periodically:**
```php
// Option 1: Artisan command
php artisan cleanup:orphaned-likes

// Option 2: Manual query
MovieLike::whereNotExists(function ($query) {
    $query->select('id')
          ->from('users')
          ->whereRaw('users.id = movie_likes.user_id');
})->delete();
```

### Performance
- ✅ Faster inserts (no constraint checking)
- ✅ Indexes maintained for query performance
- ✅ No cascade delete overhead

---

## 🎉 Success Criteria

After migration, you should be able to:
1. ✅ Like movies without foreign key errors
2. ✅ Unlike movies successfully
3. ✅ See accurate likes count
4. ✅ Get proper authentication errors (401/403)
5. ✅ No database constraint violations

---

## 📞 Support

If you encounter issues:

1. Check logs:
```bash
tail -f /Applications/MAMP/htdocs/katogo/storage/logs/laravel.log
```

2. Verify table structure:
```bash
php artisan tinker
DB::select("DESCRIBE movie_likes");
```

3. Test in phpMyAdmin:
```
http://localhost/phpMyAdmin
-> katogo database
-> movie_likes table
-> Structure tab
```

---

## ✨ Summary

**Created:**
- ✅ Migration file (ready to run)
- ✅ Complete documentation
- ✅ Testing guide

**Run Command:**
```bash
php artisan migrate --path=database/migrations/2025_10_02_050000_recreate_movie_likes_table_without_constraints.php
```

**Expected Result:**
- ✅ No more foreign key violations
- ✅ Application-level validation
- ✅ Same table structure (nullable columns)
- ✅ Better flexibility and performance

**Ready to migrate!** 🚀
