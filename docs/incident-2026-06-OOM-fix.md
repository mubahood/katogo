# Incident Report: Server-Wide OOM Crash (June 2026)

**Severity:** Critical — complete service outage  
**Duration:** ~48 hours of instability before full resolution  
**Affected:** All API endpoints (manifest, movies, auth), homepage, admin panel  
**Root cause:** PHP-FPM workers consuming 960 MB each, exhausting 8 GB RAM and OOM-killing MariaDB  

---

## What Happened

The live Namecheap server (`movies.mruodel.com`, `209.74.87.69`) became unstable, returning HTTP 500/503 on all endpoints. MariaDB was repeatedly killed by the Linux OOM-killer. After each manual restart it would die again within minutes.

**Chain of failure:**

1. A cold-cache request to `/api/manifest` triggered a PHP closure that loaded ~87,025 users into memory with **all 80+ columns** each. Peak PHP-FPM worker memory: **960 MB per worker**.
2. With `pm.max_children = 25` (temporarily raised during previous debugging), 25 workers × 960 MB = **24 GB of virtual memory demand** on an 8 GB server.
3. The Linux OOM-killer killed MariaDB to reclaim memory.
4. After MariaDB restarted, the next cold-cache manifest request triggered the same explosion, killing it again.
5. All 256 Apache workers (`MaxRequestWorkers`) queued behind hung PHP-FPM sockets, making HTTPS appear completely down (port 443 timed out; port 80 returned 301 but nothing more).

---

## Root Causes (5 total)

### 1. `NotificationService` loaded 87,025 users with all columns

```php
// BEFORE (broke the server):
$users = User::where('status', 'Active')
    ->orWhereNull('status')
    ->get();  // ALL 80+ columns × 87k rows ≈ 4.8 GB in a single PHP variable
```

```php
// AFTER (fixed):
$notifColumns = [
    'id', 'email', 'push_notifications', 'notification_preferences',
    'last_trending_notification_date', 'trending_notifications_today',
    'max_trending_notifications_per_day', 'last_trending_notification_period',
    'last_trending_notification_sent',
];
$users = User::where('status', 'Active')
    ->orWhereNull('status')
    ->get($notifColumns);  // 9 columns only ≈ 45 MB
```

**File:** `app/Services/NotificationService.php:sendTrendingNotificationToEligibleUsers()`

---

### 2. `sendTrendingNotification()` was called from the manifest HTTP path

`TrendingNotification::getTrendingMovie()` silently called `self::sendTrendingNotification()`, which triggered the 87k-user loop **on every cold-cache manifest request** — a synchronous HTTP request bearing the full cost.

```php
// BEFORE (called inline):
public static function getTrendingMovie() {
    self::sendTrendingNotification();  // ← 4.8 GB allocation during HTTP request
    ...
}

// AFTER (removed from HTTP path):
public static function getTrendingMovie() {
    // Notification sending moved to scheduled cron only. NEVER call from HTTP.
    // self::sendTrendingNotification();
    ...
}
```

**File:** `app/Models/TrendingNotification.php:getTrendingMovie()`

---

### 3. V1 manifest loaded all 80+ movie columns for every query

The `ApiController::getManifest()` method ran three unconstrained `->get()` calls on `movie_models`, loading every column including large text fields for hundreds of movies per worker.

```php
// BEFORE:
$to_stamp = MovieModel::where([...])->limit(20)->get();
$extra    = MovieModel::where([...])->limit(20)->get();
$iosMovies = Cache::remember('v1_ios_movies', 600, fn() =>
    MovieModel::where(['platform_type' => 'ios'])->limit(100)->get()
);

// AFTER:
$take_only = ['id', 'title', 'url', 'thumbnail_url', 'description', 'genre',
              'type', 'vj', 'is_premium', 'category_id', 'category'];
$to_stamp = MovieModel::where([...])->limit(20)->get($take_only);
$extra    = MovieModel::where([...])->limit(20)->get($take_only);
$iosMovies = Cache::remember('v1_ios_movies', 600, function () use ($take_only) {
    return MovieModel::where(['platform_type' => 'ios'])->limit(100)->get($take_only);
});
```

**File:** `app/Http/Controllers/ApiController.php`

---

### 4. `pm.max_children` set too high during previous debugging

The PHP-FPM pool had been temporarily raised to `pm.max_children = 25` while diagnosing an earlier slowness issue. With worker memory at 960 MB, 25 workers = 24 GB demand, far exceeding the 8 GB server.

**Fix:** Reduced to `pm.max_children = 8` on live server  
**File:** `/usr/local/apps/php82/etc/php-fpm.conf` on `209.74.87.69`

---

### 5. `innodb_buffer_pool_size` too large

MariaDB was configured with `innodb_buffer_pool_size = 512M`, consuming half a GB before PHP even started.

**Fix:** Reduced to `innodb_buffer_pool_size = 256M`  
**File:** `/etc/my.cnf` on `209.74.87.69`

---

## Full Fix List

| # | What | File | Impact |
|---|------|------|--------|
| 1 | Column-restrict user query (87k users → 9 cols) | `NotificationService.php` | 4.8 GB → 45 MB per cold-cache call |
| 2 | Remove notification send from manifest HTTP path | `TrendingNotification.php` | Eliminated cold-cache memory spike |
| 3 | Column-restrict V1 manifest movie queries | `ApiController.php` | 80+ cols → 11 cols per movie |
| 4 | `pm.max_children = 8` (was 25) | php-fpm.conf (live) | Max 8 concurrent workers, not 25 |
| 5 | `innodb_buffer_pool_size = 256M` (was 512M) | /etc/my.cnf (live) | Freed 256 MB for PHP |
| 6 | `end_date_time` null check in subscription logic | `User.php:695` | Prevents null > now() coercion |
| 7 | `resolveUser()` DB write in try-catch | `MovieController.php` | Non-fatal `last_online_at` can't crash endpoints |
| 8 | ManifestController bookkeeping in try-catch | `ManifestController.php` | Platform/app_type save can't crash manifest |
| 9 | `me/recommendations` fallback for missing table | `MovieController.php:recommendations()` | Graceful fallback if user_activity_logs missing |
| 10 | `memory_limit = 2G` explicit ceiling | `ApiController.php` | Prevents silent 128M default cap |
| 11 | `$guarded = []` on User model | `User.php` | Unblocked admin panel save (prior session) |
| 12 | Login dead-code removed | `ApiController.php` | Username/phone login fallback now reachable |
| 13 | Admin auto-password fill | `Admin/bootstrap.php` | Admin panel password field no longer blank |

---

## Recovery Steps Taken on Live Server

When the server was in full OOM-kill loop:

```bash
# 1. Kill all hung PHP-FPM workers (workers, not master)
kill $(ps aux | grep php-fpm | grep worker | awk '{print $2}') 2>/dev/null

# 2. Remove stale FPM socket if it exists
rm -f /usr/local/apps/php82/var/fpm-muhindo.sock

# 3. Kill MariaDB forcefully (systemctl restart sometimes fails with lock files)
pkill -9 -x mariadbd

# 4. Start MariaDB fresh
systemctl start mariadb

# 5. Restart PHP-FPM master
systemctl restart php82-fpm-muhindo   # or the correct service name

# 6. Verify memory is healthy
free -m
ps aux | grep php-fpm | awk '{print $6/1024 "MB"}' | sort -n
```

---

## Before vs After

| Metric | Before fixes | After fixes |
|--------|-------------|-------------|
| PHP worker peak memory | 960 MB | ~45 MB |
| Server free RAM | 0 MB (OOM) | 3,883 MB |
| MariaDB status | Killed every ~2 min | Stable (active) |
| `/api/manifest` | 500 (OOM crash) | 200 (2.6s) |
| `/api/v2/manifest` | 500 (OOM crash) | 200 (1.9s) |
| `/api/v2/me/recommendations` | 404 (route missing) | 200 |
| Homepage | 500 (missing variables) | 200 |

---

## Lessons & Rules for Future

### Rule 1: Never call `->get()` without column restriction on large tables

`movie_models` has 80+ columns. `admin_users` has 90+ columns. Always specify which columns you need:

```php
// Bad — loads everything:
User::where('status', 'Active')->get()

// Good — loads only what you need:
User::where('status', 'Active')->get(['id', 'email', 'push_notifications'])
```

### Rule 2: Never run heavy background work synchronously inside HTTP requests

Notification dispatch, email sending, or any loop over thousands of records must go in:
- A scheduled Artisan command (`app/Console/Commands/`)
- A queued job (`app/Jobs/`)
- Never inline in a controller or model called from an HTTP path

### Rule 3: `pm.max_children` must fit in RAM with a safety margin

Formula: `pm.max_children = floor((RAM - OS_reserve - DB_reserve) / peak_worker_MB)`

For this server (8 GB): `floor((8192 - 1024 - 512) / 100) = 66` theoretical max. Use 8-10 conservatively.

### Rule 4: MariaDB OOM-kill recovery

If `systemctl restart mariadb` fails with `Can't lock aria control file`:
```bash
pkill -9 -x mariadbd   # force kill lingering process
systemctl start mariadb # then start clean
```

### Rule 5: Shell escaping corrupts bcrypt hashes

When setting passwords via SSH, never pass `$2y$10$...` hashes through shell variables — `$` chars are interpolated. Use PHP directly:

```bash
php -r "echo password_hash('NewPassword@2026', PASSWORD_BCRYPT);"
# Then use PHP PDO to write it, not shell echo
```

---

## Monitoring Checklist (run after any deploy)

```bash
# Memory health
free -m

# PHP-FPM worker count and per-worker memory
ps aux | grep php-fpm | grep -v grep | awk '{print $6/1024 "MB"}'

# MariaDB running
systemctl is-active mariadb

# Key endpoint smoke test (replace TOKEN)
TOKEN=$(curl -s -X POST https://movies.mruodel.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"testuserx@gmail.com","password":"NewTest@2026","app_type":"lugaflix"}' \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('token',''))")

curl -s -o /dev/null -w "%{http_code}  V1 manifest\n"         -H "Authorization: Bearer $TOKEN" https://movies.mruodel.com/api/manifest
curl -s -o /dev/null -w "%{http_code}  V2 manifest\n"         -H "Authorization: Bearer $TOKEN" https://movies.mruodel.com/api/v2/manifest
curl -s -o /dev/null -w "%{http_code}  V2 movies\n"           -H "Authorization: Bearer $TOKEN" https://movies.mruodel.com/api/v2/movies
curl -s -o /dev/null -w "%{http_code}  V2 recommendations\n"  -H "Authorization: Bearer $TOKEN" https://movies.mruodel.com/api/v2/me/recommendations
```

Expected: all `200`.

---

## Files Modified (all deployed to live server)

- `app/Http/Controllers/ApiController.php`
- `app/Http/Controllers/Api/V2/MovieController.php`
- `app/Http/Controllers/Api/V2/ManifestController.php`
- `app/Http/Controllers/LandingController.php`
- `app/Models/TrendingNotification.php`
- `app/Models/User.php`
- `app/Services/NotificationService.php`
- `app/Admin/bootstrap.php`
- Live server: `/usr/local/apps/php82/etc/php-fpm.conf` (`pm.max_children = 8`)
- Live server: `/etc/my.cnf` (`innodb_buffer_pool_size = 256M`)
- Live server: `routes/api.php` (`me/recommendations` route added)
