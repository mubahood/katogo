# Katogo Platform — Server & Backend Plan

> **Scope:** Laravel 10 backend + Namecheap production + Hetzner VPS infrastructure
> **Companion doc:** `MOBILE_PLAN.md` for Flutter app work, `IMPROVEMENT_PLAN.md` for full task list
> **Audit date:** June 2026

---

## ⚠ PRODUCTION WARNING

`movies.mruodel.com` (`209.74.87.69`) is the **live production server** with real users,
real payments, and real data. Do not run schema changes, migrations, queue restarts, or
data modifications against it without scheduled maintenance.

All experiments happen on **Hetzner VPS** (`munoapp.store` / `91.98.42.156`).

---

## 1. Server Architecture Map

```
┌─────────────────────────────────────────────────────────────────────┐
│  LOCAL DEV (Mac, MAMP)                                              │
│  /Applications/MAMP/htdocs/katogo                                   │
│  MySQL: katogo_3 @ localhost:8889                                    │
│  URL: http://localhost:8888/katogo                                   │
└────────────────────────────┬────────────────────────────────────────┘
                             │ git push / FTP / rsync
                ┌────────────┴────────────┐
                ▼                         ▼
┌───────────────────────────┐  ┌─────────────────────────────────────┐
│  NAMECHEAP (PRODUCTION)   │  │  HETZNER VPS (STAGING / TESTING)    │
│  movies.mruodel.com       │  │  munoapp.store / 91.98.42.156       │
│  209.74.87.69             │  │  ubuntu-8gb-fsn1-1                  │
│  AlmaLinux 9              │  │  Ubuntu 26.04 LTS                   │
│  PHP 8.x, Apache/cPanel   │  │  Nginx 1.28 + PHP 8.5 FPM          │
│  MySQL (cPanel managed)   │  │  MySQL 8.4 (root-managed)           │
│  Queue: cron-kept worker  │  │  Redis 7.x on localhost:6379        │
│  No Supervisor            │  │  Supervisor (2 queue workers)       │
│  SSL: Namecheap SSL       │  │  SSL: Let's Encrypt (auto-renew)    │
│  Storage: local disk +    │  │  Storage: 80GB SSD + 20GB volume    │
│           Firebase        │  │           Hetzner StorageShare      │
└───────────────────────────┘  └─────────────────────────────────────┘
         ▲                              ▲
         │  Hetzner pulls via SSH tunnel│
         └──────────────────────────────┘
              DB Sync (SyncPullService)

             ▲ both served by ▲
┌─────────────────────────────────────────┐
│  Hetzner StorageShare (Nextcloud 32)    │
│  storage.bunnycdn.net → CDN delivery    │
│  WebDAV + OCS API                       │
│  Unlimited quota, direct /s/{token}/download URLs │
└─────────────────────────────────────────┘
```

---

## 2. Current State: What Is Installed on Each Server

### 2.1 Namecheap (Production)

| Component | Status |
| --------- | ------ |
| Laravel 10 app | Running, production |
| MySQL katogo_3 | Live data, read-only from Hetzner perspective |
| PHP | cPanel managed, version confirmed |
| Queue worker | Running as PID 3171050, cron-kept (fragile) |
| `SolveFLWCaptchaJob` | HTTP solver working — confirmed HTTP 200 on test |
| `TransferMovieToHetzner` | Active, transferring movies to StorageShare |
| `SyncExportService` | Deployed and running — provides export API for Hetzner pull |
| SSL | Namecheap SSL on `movies.mruodel.com` |

### 2.2 Hetzner VPS (Testing/Staging)

| Component | Status |
| --------- | ------ |
| LEMP stack | Installed (Nginx 1.28, PHP 8.5 FPM, MySQL 8.4) |
| Redis | Running on localhost:6379 |
| Supervisor | Installed, queue worker config in place |
| Certbot | SSL issued — `munoapp.store` cert expires 2026-09-10 |
| Laravel app | **NOT YET DEPLOYED** |
| `.env` | **NOT CONFIGURED** |
| Migrations | **NOT RUN** |
| DNS | `munoapp.store` → `91.98.42.156` (working) |
| SSH key auth | Enforced, password disabled |
| UFW firewall | Active (ports 22, 80, 443) |
| fail2ban | Active (SSH brute-force protection) |

### 2.3 Remaining Steps to Activate Hetzner

```bash
# 1. SSH in
ssh hetzner-katogo  # alias for: ssh root@91.98.42.156

# 2. Deploy app (first time)
cd /var/www
git clone <repo-url> katogo    # or rsync from local
cd katogo
composer install --no-dev --optimize-autoloader

# 3. Configure .env
cp .env.example .env
# Edit: APP_URL, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD,
#       JWT_SECRET, PESAPAL_*, FLUTTERWAVE_*, ONESIGNAL_*,
#       HETZNER_STORAGE_*, SYNC_SOURCE_HOST, SYNC_SSH_USER, SYNC_DB_*
php artisan key:generate
php artisan jwt:secret

# 4. Run migrations
php artisan migrate --force

# 5. Seed sync cursors
php artisan sync:pull --seed-only   # or: php artisan tinker → app(SyncPullService::class)->seedCursors()

# 6. First sync pull
php artisan sync:pull --force

# 7. Set permissions
chown -R www-data:www-data /var/www/katogo
chmod -R 755 /var/www/katogo/storage

# 8. Restart PHP-FPM + Nginx
systemctl restart php8.5-fpm nginx

# 9. Start queue workers via Supervisor
supervisorctl start katogo-worker:*
```

---

## 3. API Route Audit

### 3.1 Route Groups Overview

| Group | Auth Required | Prefix | Count |
| ----- | ------------- | ------ | ----- |
| Public auth | No | `/api/auth/` | 5 |
| iOS Review | No (+ JWT for delete) | `/api/ios/` | 5 |
| Subscription (public) | No | `/api/subscriptions/` | 9 |
| Subscription (authenticated) | JWT | `/api/subscriptions/` | 10 |
| Profile & Account | JWT | `/api/me`, `/api/account/` | 15 |
| Movies & Content | JWT | (dynamic) | 10+ |
| Support Tickets | JWT | `/api/support/` | 8 |
| Moderation | JWT/Public | `/api/moderation/` | 11 |
| Admin (in-app) | JWT | `/api/admin/` | 10 |
| Video Transfers | Mixed | `/api/video-transfers/` | 4 |
| Movie URL Sync | Shared secret | `/api/movie-url-sync` | 2 |
| Games | JWT | `/api/game/`, `/api/ludo/`, `/api/checkers/` | **ALL DISABLED (503)** |
| V2 (versioned) | JWT | `/api/v2/` | 40+ |
| Catch-all dynamic | JWT | `/api/api/{model}` | 2 |

### 3.2 Issues Found in Routes

**Security Risk — Remove Immediately:**
```php
Route::post('run-migration', [MigrationController::class, 'runMigration']);
// ^^ No auth, executes migrations. Must be deleted from api.php.
```

**Test Routes in Production:**
```php
Route::get('test-free-trial/{user_id?}', ...);
Route::get('test-auto-assignment/{user_id?}', ...);
Route::get('test-free-trial-plan', ...);
Route::get('test-free-trial-stats', ...);
Route::delete('test-free-trial-cleanup/{user_id?}', ...);
// ^^ All unauthenticated. Can expose user data. Delete in production.
```

**Inconsistency:**
- `POST /api/moderation/report-content` AND `POST /api/moderation/report` are aliases doing the same thing — remove the alias after confirming all app versions use the canonical route
- `stock-items` and `stock-sub-categories` routes are leftover from a different business context (stock management) — review whether these are still needed

**Missing Routes:**
- No `GET /api/movies` or `GET /api/movies/{id}` in the explicit route list — handled via `DynamicCrudController` catch-all. Should be a named, typed V2 route for clarity and caching
- No `POST /api/movies/{id}/rate` — needed for rating system (Section 5.2 in IMPROVEMENT_PLAN.md)
- No `GET /api/me/recommendations` — needed for personalization
- No `GET /api/subtitle-files/{movie_id}` — needed for subtitle support

---

## 4. Database Schema Deep-Dive

### 4.1 Core Tables

| Table | Rows (est.) | Key Columns | Notes |
| ----- | ----------- | ----------- | ----- |
| `admin_users` | 10k–50k | id, name, username, email, app_type, phone | Primary user table |
| `movie_models` | 20k+ | id, title, url, status, content_type, is_premium | Core content table |
| `series_movies` | 50k+ | id, movie_id, title, url, season, episode | Episodes/series |
| `subscriptions` | 5k+ | id, user_id, plan_id, payment_status, start/end dates | Payment records |
| `subscription_plans` | ~10 | id, name, days, amount, app_type | Plan definitions |
| `movie_views` | 100k+ | id, user_id, movie_id, progress | Watch history |
| `movie_likes` | 50k+ | id, user_id, movie_id | Like records |
| `game_stats` | varies | user_id, game_type, wins/losses | Offline game stats |
| `video_transfers` | varies | movie_id, status, dest_url, file_size | Hetzner transfer queue |
| `db_sync_cursors` | ~50 | table_name, last_synced_id, status | Sync state per table |

### 4.2 Missing Indexes (Found During Audit)

```sql
-- These queries appear in controllers but may lack covering indexes:

-- movie_views: frequent lookup by user + movie (check-if-watched)
ALTER TABLE movie_views ADD INDEX IF NOT EXISTS idx_user_movie (user_id, movie_id);

-- subscriptions: user's active subscription (checked on every API call)
ALTER TABLE subscriptions ADD INDEX IF NOT EXISTS idx_user_status (user_id, status, end_date_time);

-- movie_models: listing by content_type + status (home screen queries)
ALTER TABLE movie_models ADD INDEX IF NOT EXISTS idx_type_status (content_type, status, is_premium);

-- movie_searches: recent searches per user
ALTER TABLE movie_searches ADD INDEX IF NOT EXISTS idx_user_created (user_id, created_at);

-- admin_users: lookup by app_type (multi-app filtering)
ALTER TABLE admin_users ADD INDEX IF NOT EXISTS idx_app_type (app_type);
```

### 4.3 Schema Concerns

- `admin_users` is both the admin panel user AND the regular app user. This dual role creates confusion — the `role` and `permissions` columns are for admin access but the same table holds 50k regular app users. Consider separating in the long run, but do not migrate now (breaking change).
- `movie_models.url` length: current migrations changed it to `TEXT` — good, but ensure all query paths that filter by `url` use prefix index or full-text, not `=` comparison on TEXT.
- `subscriptions` has many nullable date columns — some should be NOT NULL with DEFAULT values to avoid null-handling bugs in PHP.

---

## 5. Queue System Analysis

### 5.1 Current Jobs

| Job | Trigger | Retries | Timeout | Risk |
| --- | ------- | ------- | ------- | ---- |
| `SolveFLWCaptchaJob` | After FLW MoMo payment init | 3 (10s, 30s) | 90s | Low — HTTP solver confirmed working |
| `TransferMovieToHetzner` | Admin triggers transfer | 3 | 300s | Medium — file transfer can timeout |
| `AutoFixMovie` | Admin batch action | 1 | 120s | Low |
| `PushUrlChangeToOrigin` | After URL sync received | 3 | 60s | Low |
| `SendChatNotification` | On new chat message | 2 | 30s | Low |

### 5.2 Namecheap Queue Worker Problem

The queue worker on Namecheap runs via cron:
```
* * * * * cd /path && php artisan queue:work --stop-when-empty
```

**Problem:** If a job takes longer than 60s, the next cron tick starts a second worker instance. Multiple workers on the same queue with competing locks can cause duplicate job processing. Also, if the server load is high, cron may miss cycles, leaving `SolveFLWCaptchaJob` sitting queued (no USSD push fires).

**Fix:** Use `--max-time=55` flag so the worker exits before the next cron tick, OR (better) switch to a long-running worker with proper process management:

```bash
# Add to crontab (runs every 5 minutes, only starts if not already running):
*/5 * * * * flock -n /tmp/katogo-queue.lock php artisan queue:work --timeout=90 --tries=3 --max-time=290 >> /tmp/katogo-queue.log 2>&1
```

### 5.3 Horizon on Hetzner

Laravel Horizon provides a real-time dashboard at `/horizon` for the Hetzner server:

```bash
ssh hetzner-katogo
cd /var/www/katogo
composer require laravel/horizon
php artisan horizon:install
php artisan migrate  # creates horizon tables
# Add to supervisor instead of queue:work:
# command=php /var/www/katogo/artisan horizon
```

Config `config/horizon.php`:
```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'transfers', 'notifications'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 5,
            'tries' => 3,
            'timeout' => 300,
        ],
    ],
],
```

---

## 6. DB Sync System — Current State & What Remains

### 6.1 What Is Built

| Component | Location | Status |
| --------- | -------- | ------ |
| `SyncExportService` | `app/Services/SyncExportService.php` | Complete — 50+ table export with cursor CDC |
| `SyncPullService` | `app/Services/SyncPullService.php` | Complete — SSH tunnel, upsert, pivot table handling |
| `SyncDashboardController` | `app/Admin/Controllers/SyncDashboardController.php` | Complete — HTML dashboard with run button |
| `DbSyncCursor` | `app/Models/DbSyncCursor.php` | Model for per-table sync state |
| `DbSyncLog` | `app/Models/DbSyncLog.php` | Model for per-run history |
| Migration | `2026_06_12_300000_create_db_sync_tables.php` | Created, needs running on Hetzner |
| `SyncPull` command | `app/Console/Commands/SyncPull.php` | Created, needs testing |

### 6.2 How It Works (Architecture)

```
Namecheap (Source)                      Hetzner (Destination)
─────────────────                       ─────────────────────
SyncExportService                       SyncPullService
  ↑ reads DB → JSON export API          ↓
  (provides /api/sync-export endpoint)  1. Opens SSH tunnel (port 13306)
                                        2. Connects to Namecheap MySQL via tunnel
                                        3. Per table: fetches rows WHERE id > last_cursor
                                        4. Also fetches rows WHERE updated_at > last_ts
                                        5. Upserts via INSERT ... ON DUPLICATE KEY UPDATE
                                        6. After sync: reapplies Hetzner CDN URLs for
                                           movies that were transferred to StorageShare
                                        7. Updates db_sync_cursors with new position
```

### 6.3 What Remains to Activate Sync

1. Run migration `2026_06_12_300000_create_db_sync_tables.php` on Hetzner
2. Configure `.env` on Hetzner with:
   ```
   SYNC_ENABLED=true
   SYNC_SOURCE_HOST=209.74.87.69
   SYNC_SSH_USER=muhindo          # or whichever SSH user can reach Namecheap MySQL
   SYNC_DB_USER=katogo
   SYNC_DB_PASS=<production DB password>
   SYNC_DB_NAME=katogo_3
   SYNC_TUNNEL_PORT=13306
   SYNC_BATCH_SIZE=500
   SYNC_MAX_PAGES=30
   ```
3. Ensure SSH key from Hetzner (`/root/.ssh/id_rsa`) is authorized on Namecheap
4. Register `/sync-dashboard` route in Laravel-Admin routes
5. Run first sync: `php artisan sync:pull --force`
6. Set up scheduled pull: in `app/Console/Kernel.php`:
   ```php
   $schedule->command('sync:pull')->everyFiveMinutes();
   ```

---

## 7. Caching Strategy

### 7.1 What Should Be Cached

| Data | Cache Key | TTL | Invalidation Trigger |
| ---- | --------- | --- | -------------------- |
| Subscription plans list | `subscription_plans` | 1 hour | Any plan update in admin |
| Movie listing by genre | `movies.genre.{genre}.page.{n}` | 15 min | Movie status change |
| User's active subscription | `user.{id}.subscription` | 5 min | Subscription update |
| System config values | `system_config.{key}` | 30 min | Config change in admin |
| Trending movies | `trending.movies.{date}` | 1 hour | Nightly job |
| Movie detail | `movie.{id}` | 30 min | Movie edit in admin |

### 7.2 Response Cache for Heavy Endpoints

With `spatie/laravel-responsecache`:

```php
// In a service provider or middleware:
// Cache GET /api/subscription-plans for 1 hour
// Cache GET /api/v2/movies for 15 minutes per user
// Do NOT cache: /api/me, /api/subscriptions/my-subscription, any POST
```

---

## 8. Security Audit

### 8.1 Immediate Actions

| Issue | Severity | Fix |
| ----- | -------- | --- |
| `run-migration` route open (no auth) | CRITICAL | Delete from `api.php` immediately |
| Test routes open in production | HIGH | Delete 6 test routes from `api.php` |
| Queue worker has no supervisor | HIGH | Add flock-based cron or Supervisor |
| FLW webhook `verif-hash` — verify implementation | HIGH | Confirm in `SubscriptionApiController::flutterwaveWebhook()` |
| Hetzner SSH password auth | LOW | Already disabled — confirm with `sshd -T \| grep PasswordAuthentication` |

### 8.2 Rate Limiting Per app_type

Currently all apps share the same `throttle:auth` bucket. A misbehaving Muno installation
could exhaust the auth limit and lock out LugaFlix users.

Add app-type-aware rate limiting in `app/Http/Middleware/`:
```php
// In JwtMiddleware or a new AppTypeRateLimiter middleware:
$key = 'api.' . ($request->input('app_type') ?? 'unknown') . '.' . $request->ip();
RateLimiter::attempt($key, 120, fn() => null, 60);
```

### 8.3 JWT Rotation

JWT tokens have a 5-year TTL (`config/jwt.php`). This is intentional (persistent login).
But tokens are never invalidated server-side on logout. Add a `token_blacklist` table or
use Redis to track invalidated tokens:

```php
// On logout:
$token = JWTAuth::getToken();
JWTAuth::invalidate($token);
// Requires JWT_BLACKLIST_ENABLED=true in .env
```

---

## 9. Storage Architecture

### 9.1 Storage Layers

```
┌──────────────────────────────────────────────────────────────────────┐
│ Layer 1: Movie Source Files                                          │
│ Namecheap local disk → VideoTransfer job → Hetzner StorageShare     │
│ Status per movie: tracked in movie_file_transfers table              │
│ After transfer: movie_models.url updated to Hetzner CDN URL         │
└──────────────────────────────────────────────────────────────────────┘
┌──────────────────────────────────────────────────────────────────────┐
│ Layer 2: Images / Thumbnails                                         │
│ Stored on Namecheap /storage/app/public → served via /storage/      │
│ Firebase Storage: used for some user-uploaded content                │
└──────────────────────────────────────────────────────────────────────┘
┌──────────────────────────────────────────────────────────────────────┐
│ Layer 3: App Database                                                │
│ MySQL katogo_3 on Namecheap (primary)                                │
│ Replicated to Hetzner via SyncPullService (secondary, read-mostly)   │
└──────────────────────────────────────────────────────────────────────┘
```

### 9.2 Hetzner StorageShare Integration

- Protocol: WebDAV + OCS API (Nextcloud 32)
- Direct download URL format: `https://{share-host}/s/{token}/download`
- Unlimited quota
- `HetznerStorageService.php` handles upload, URL generation, deletion
- `TransferMovieToHetzner` job uses this service

**Remaining:** Move MySQL data directory to the attached volume so DB survives server rebuild:
```bash
ssh hetzner-katogo
systemctl stop mysql
rsync -av /var/lib/mysql/ /mnt/HC_Volume_105999006/mysql/
# Update /etc/mysql/mysql.conf.d/mysqld.cnf: datadir = /mnt/HC_Volume_105999006/mysql
systemctl start mysql
```

---

## 10. Server-Specific Task List

Cross-reference with `IMPROVEMENT_PLAN.md` — same task numbers.

### Priority Queue

```
CRITICAL (do before anything else):
  [ ] 1.1  Remove dangerous routes from api.php
  [ ] 1.2  Configure Hetzner .env
  [ ] 1.3  Run migrations on Hetzner
  [ ] 8.5  Verify SSH password auth disabled on Hetzner

HIGH (this week):
  [ ] 1.4  Seed sync cursors + run first DB sync pull
  [ ] 1.5  Fix Namecheap queue worker (flock-based cron)
  [ ] 1.6  Verify FLW webhook signature
  [ ] 8.1  Install Laravel Horizon on Hetzner
  [ ] 8.7  Set up nightly DB backup on Hetzner
  [ ] 8.8  Fix Namecheap queue worker supervision

MEDIUM (next two weeks):
  [ ] 1.7  Move MySQL data to Hetzner volume
  [ ] 6.1  Install Meilisearch on Hetzner
  [ ] 6.2  Index movie_models into Meilisearch
  [ ] 8.2  Install Telescope on Hetzner
  [ ] 8.3  Add /health endpoint
  [ ] 8.4  Set up uptime monitoring
```

---

## 11. Nightly Backup Strategy

```bash
# /etc/cron.d/katogo-backup on Hetzner:
0 2 * * * root /var/www/katogo/scripts/backup.sh

# backup.sh:
#!/bin/bash
DATE=$(date +%Y%m%d)
DEST="/mnt/HC_Volume_105999006/backups"
mkdir -p "$DEST"

# DB backup
mysqldump -u katogo -p"$DB_PASS" katogo_3 \
  | gzip > "$DEST/katogo_3_$DATE.sql.gz"

# Keep last 14 days
find "$DEST" -name "*.sql.gz" -mtime +14 -delete

echo "Backup completed: $DATE"
```
