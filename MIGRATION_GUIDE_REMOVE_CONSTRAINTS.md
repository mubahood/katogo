# Movie Likes Table Migration - Remove Constraints

**Date:** October 2, 2025  
**Purpose:** Recreate `movie_likes` table without foreign key constraints and make all columns nullable  
**Status:** Ready to run

---

## 🎯 What This Migration Does

### Before (With Constraints):
```sql
CREATE TABLE `movie_likes` (
  `id` bigint unsigned PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `movie_model_id` bigint unsigned NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  ...
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`movie_model_id`) REFERENCES `movie_models` (`id`) ON DELETE CASCADE
);
```

### After (Without Constraints):
```sql
CREATE TABLE `movie_likes` (
  `id` bigint unsigned PRIMARY KEY AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `movie_model_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  ...
  -- No foreign key constraints
  -- All columns nullable
  INDEX `movie_likes_user_id_index` (`user_id`),
  INDEX `movie_likes_movie_model_id_index` (`movie_model_id`)
);
```

---

## 📋 Table Structure

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary key |
| `created_at` | timestamp | YES | NULL | Record creation time |
| `updated_at` | timestamp | YES | NULL | Record update time |
| `user_id` | bigint unsigned | YES | NULL | User who liked (no FK) |
| `movie_model_id` | bigint unsigned | YES | NULL | Movie that was liked (no FK) |
| `ip_address` | varchar(50) | YES | NULL | User IP address |
| `device` | varchar(50) | YES | NULL | Device type |
| `platform` | varchar(50) | YES | NULL | Operating system |
| `browser` | varchar(50) | YES | NULL | Browser used |
| `country` | varchar(50) | YES | NULL | Country code |
| `city` | varchar(50) | YES | NULL | City name |
| `status` | varchar(50) | YES | 'Active' | Like status |

### Indexes:
- ✅ `movie_likes_user_id_index` - Fast user lookup
- ✅ `movie_likes_movie_model_id_index` - Fast movie lookup
- ✅ `movie_likes_status_index` - Fast status filtering
- ✅ `movie_likes_created_at_index` - Fast date sorting

---

## ⚠️ IMPORTANT: Backup First!

Before running this migration, **BACKUP YOUR DATABASE**:

```bash
# Option 1: Export movie_likes table
mysqldump -u root -p katogo movie_likes > movie_likes_backup_$(date +%Y%m%d_%H%M%S).sql

# Option 2: Full database backup
mysqldump -u root -p katogo > katogo_backup_$(date +%Y%m%d_%H%M%S).sql

# Option 3: MAMP phpMyAdmin
# Go to: http://localhost/phpMyAdmin
# Select 'katogo' database
# Select 'movie_likes' table
# Click 'Export' tab
# Click 'Go' button
```

---

## 🚀 Running The Migration

### Step 1: Check Current Status
```bash
cd /Applications/MAMP/htdocs/katogo
php artisan migrate:status
```

### Step 2: Run The Migration
```bash
php artisan migrate --path=database/migrations/2025_10_02_050000_recreate_movie_likes_table_without_constraints.php
```

### Expected Output:
```
Migrating: 2025_10_02_050000_recreate_movie_likes_table_without_constraints
Migrated:  2025_10_02_050000_recreate_movie_likes_table_without_constraints (XX.XXms)
```

### Step 3: Verify Table Structure
```bash
php artisan tinker
```

Then in Tinker:
```php
DB::select("SHOW CREATE TABLE movie_likes");
Schema::getColumnListing('movie_likes');
exit;
```

---

## 🔄 Rolling Back (If Needed)

If you need to undo this migration:

```bash
php artisan migrate:rollback --step=1
```

This will:
1. Drop the new table
2. Recreate the old table **with** foreign key constraints

⚠️ **Note:** Rolling back will restore the constraints, which means you'll need valid user_id and movie_model_id values.

---

## ✅ Verification Checklist

After running the migration, verify:

### 1. Table Exists
```sql
SHOW TABLES LIKE 'movie_likes';
```

### 2. No Foreign Key Constraints
```sql
SELECT 
    CONSTRAINT_NAME, 
    CONSTRAINT_TYPE 
FROM 
    information_schema.TABLE_CONSTRAINTS 
WHERE 
    TABLE_SCHEMA = 'katogo' 
    AND TABLE_NAME = 'movie_likes';
```
**Expected:** No foreign key constraints (only PRIMARY KEY)

### 3. All Columns Nullable
```sql
SELECT 
    COLUMN_NAME, 
    IS_NULLABLE, 
    COLUMN_DEFAULT 
FROM 
    information_schema.COLUMNS 
WHERE 
    TABLE_SCHEMA = 'katogo' 
    AND TABLE_NAME = 'movie_likes';
```
**Expected:** All columns (except `id`) should have `IS_NULLABLE = 'YES'`

### 4. Indexes Created
```sql
SHOW INDEX FROM movie_likes;
```
**Expected:** Indexes on `user_id`, `movie_model_id`, `status`, `created_at`

---

## 🧪 Testing After Migration

### Test 1: Create Like Without Valid User
```php
// In Tinker
use App\Models\MovieLike;

$like = MovieLike::create([
    'user_id' => 99999, // Non-existent user
    'movie_model_id' => 10350,
    'status' => 'Active'
]);

// Should work now (no foreign key constraint)
```

### Test 2: Create Like With Null Values
```php
$like = MovieLike::create([
    'user_id' => null,
    'movie_model_id' => null,
    'ip_address' => '127.0.0.1'
]);

// Should work (all columns nullable)
```

### Test 3: Backend API Test
```bash
curl -X POST http://localhost/katogo/api/account/likes/toggle \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"movie_id": 10350}'
```
**Expected:** Like created successfully (no foreign key error)

---

## 📝 Code Changes Required

### ✅ No Backend Code Changes Needed!

The backend code in `DynamicCrudController.php` already has proper validation:

```php
// Validates that user exists (application-level check)
$user = auth('api')->user();
if (!$user) {
    return $this->error('You must be logged in to like movies', 401);
}

// Validates that movie exists
$request->validate([
    'movie_id' => 'required|integer|exists:movie_models,id'
]);
```

This provides **application-level integrity** without database constraints.

### ✅ Model Stays The Same

`MovieLike.php` relationships still work:
```php
public function user() {
    return $this->belongsTo(User::class);
}

public function movie() {
    return $this->belongsTo(MovieModel::class, 'movie_model_id');
}
```

---

## 🎯 Benefits of Removing Constraints

1. ✅ **No Foreign Key Violations** - Invalid IDs won't crash the app
2. ✅ **Flexible Data** - Can store likes from deleted users/movies for analytics
3. ✅ **Better Performance** - No constraint checking on INSERT/UPDATE
4. ✅ **Easier Testing** - Can create test data without worrying about references
5. ✅ **Application-Level Control** - Backend validates data before saving

---

## ⚠️ Important Considerations

### Data Integrity
- **Before:** Database enforced integrity (foreign keys)
- **After:** Application enforces integrity (validation in code)

### Orphaned Records
Without constraints, you can have:
- Likes from deleted users
- Likes for deleted movies

**Solution:** Clean up periodically:
```php
// Delete likes from non-existent users
MovieLike::whereNotIn('user_id', User::pluck('id'))->delete();

// Delete likes for non-existent movies
MovieLike::whereNotIn('movie_model_id', MovieModel::pluck('id'))->delete();
```

Or create a scheduled cleanup job:
```php
// app/Console/Commands/CleanupOrphanedLikes.php
MovieLike::whereRaw('user_id NOT IN (SELECT id FROM users)')->delete();
MovieLike::whereRaw('movie_model_id NOT IN (SELECT id FROM movie_models)')->delete();
```

---

## 🔧 Troubleshooting

### Migration Fails: "Table doesn't exist"
**Solution:** Table is already dropped, just re-run the migration.

### Migration Fails: "Foreign key constraint"
**Solution:** Drop constraints first:
```sql
ALTER TABLE movie_likes DROP FOREIGN KEY movie_likes_user_id_foreign;
ALTER TABLE movie_likes DROP FOREIGN KEY movie_likes_movie_model_id_foreign;
```

### Data Lost After Migration
**Solution:** Restore from backup:
```bash
mysql -u root -p katogo < movie_likes_backup_YYYYMMDD_HHMMSS.sql
```

---

## 📊 Migration Timeline

1. ✅ **Create backup** (5 minutes)
2. ✅ **Run migration** (< 1 second)
3. ✅ **Verify structure** (2 minutes)
4. ✅ **Test API** (5 minutes)
5. ✅ **Monitor for issues** (24 hours)

---

## ✨ Summary

**What Changes:**
- 🗑️ Foreign key constraints removed
- ✅ All columns now nullable (except `id`)
- ✅ Indexes added for performance
- ✅ Same column names and table name

**What Stays:**
- ✅ Table name: `movie_likes`
- ✅ Column names: identical
- ✅ Primary key: `id` with auto-increment
- ✅ Model code: no changes
- ✅ API code: no changes

**Ready to migrate!** 🚀

```bash
# One command to rule them all
php artisan migrate --path=database/migrations/2025_10_02_050000_recreate_movie_likes_table_without_constraints.php
```
