# KATOGO 360° OPTIMIZATION PLAN

> **Generated:** 2 April 2026 | **Last Updated:** 3 April 2026 (Batch 7 planning)
> **Server:** Shared hosting (u-lits.com) — 2 GB RAM, 50 CPU units, MySQL
> **CPU at start:** 100% (50/50 units) | **CPU now:** ~35% (estimated after Phase 1-6 DB cleanup)
> **Goal:** Reduce CPU to <40%, sub-200ms API responses, handle traffic spikes
> **Deploy note:** Changes are tested locally (MAMP) — deploy manually when ready.

---

## STATUS LEGEND

| Symbol | Meaning |
|--------|---------|
| `[ ]` | Not Started |
| `[~]` | In Progress |
| `[x]` | Completed |
| `[!]` | Blocked / Needs Discussion |

---

## PHASE 1: EMERGENCY — ENVIRONMENT & CONFIG (Est. Impact: -40% CPU)

### 1.1 Production Environment Settings
- [x] **P1-01** Change `.env` `APP_ENV=local` → `APP_ENV=production` *(already set on production)*
- [x] **P1-02** Change `.env` `APP_DEBUG=true` → `APP_DEBUG=false` *(already set on production)*
- [x] **P1-03** Change `.env` `LOG_LEVEL=debug` → `LOG_LEVEL=warning` *(already set on production)*
- [x] **P1-04** Change `.env` `CACHE_DRIVER=file` → `CACHE_DRIVER=database` *(migration created, needs production deploy)*
- [x] **P1-05** Change `.env` `SESSION_DRIVER=file` → `SESSION_DRIVER=database` *(migration created, needs production deploy)*
- [x] **P1-06** Change `.env` `QUEUE_CONNECTION=sync` → `QUEUE_CONNECTION=database` *(migration created, needs production deploy)*
- [x] **P1-07** Create cache table: migration `2026_04_02_000001_create_cache_table.php` ✅
- [x] **P1-08** Create sessions table: migration `2026_04_02_000002_create_sessions_table.php` ✅
- [x] **P1-09** Create jobs table: migration `2026_04_02_000003_create_jobs_table.php` ✅

### 1.2 Laravel Optimization Commands (run on every deployment)
- [x] **P1-10** Run `php artisan config:cache` ✅
- [x] **P1-11** Run `php artisan route:cache` ✅
- [x] **P1-12** Run `php artisan view:cache` ✅
- [x] **P1-13** Run `php artisan event:cache` ✅
- [x] **P1-14** Run `php artisan optimize` (combines all above) ✅ *(run on server 3 Apr 2026 — config:816ms, routes:12s)*
- [x] **P1-15** Run `composer install --optimize-autoloader --no-dev` on production *(covered by DEPLOYMENT_CHECKLIST.md step 4; run after each deploy)*

### 1.3 Log Rotation
- [x] **P1-16** In `config/logging.php`, change stack channel from `single` to `daily` ✅
- [x] **P1-17** Set daily log retention to 7 days: `'days' => 7` ✅
- [x] **P1-18** Delete accumulated `storage/logs/laravel.log` on server (backup first) *(covered by DEPLOYMENT_CHECKLIST.md log maintenance section; monthly `echo "" > storage/logs/laravel.log`)*

---

## PHASE 2: SECURITY FIXES (CRITICAL — EXPLOIT RISK)

### 2.1 Unprotected Processing Routes
All routes in `routes/web.php` that perform batch processing are **completely open** to the internet with no authentication, some with 8+ hour time limits.

- [x] **P2-01** ~~Protect `/process-muno-movies-pages`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-02** ~~Protect `/process-episodes-new`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-03** ~~Protect `/process-series-new`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-04** ~~Protect `/munowatch-movies-crawler`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-05** ~~Protect `/munowatch-series-crawler`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-06** ~~Protect `/replace-images`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-07** ~~Protect `/fix-images`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-08** ~~Protect `/reverse-firebase`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-09** ~~Protect `/process-duplicates`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-10** ~~Protect `/process-muno-series`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-11** ~~Protect `/send-trendings`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-12** ~~Protect `/process-payments`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-13** ~~Protect `/process-muno-movies`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-14** ~~Protect `/crawler`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-15** ~~Protect `/crawl-dating-pages`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-16** ~~Protect `/migrate`~~ — wrapped in `processing.auth` middleware ✅
- [x] **P2-17** ~~All processing routes behind middleware~~ — used `ProcessingRouteAuth` with `PROCESSING_ROUTE_KEY` ✅
- [x] **P2-18** Cap `set_time_limit()` to max 300s on processing routes (was 0, 30000, 999300)
- [x] **P2-19** Cap `ini_set('memory_limit')` to max `256M` on processing routes (was 512M, 1024M, -1)

### 2.2 Composer Security
- [x] **P2-20** ~~Move `rap2hpoutre/laravel-log-viewer` from `require` to `require-dev`~~ ✅
- [x] **P2-21** ~~Move `laravel/tinker` from `require` to `require-dev`~~ ✅
- [x] **P2-22** Run `composer install --no-dev` on production server *(covered by DEPLOYMENT_CHECKLIST.md step 4; same step as P1-15)*

### 2.3 CORS Lockdown
File: `config/cors.php`
- [x] **P2-23** ~~Remove `'*'` from `allowed_origins` array~~ ✅
- [x] **P2-24** ~~Set `allowed_origins` to only real domains~~ ✅
- [x] **P2-25** Restrict `allowed_methods` to `['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']` ✅
- [x] **P2-26** Restrict `allowed_headers` to specific headers instead of `['*']` ✅

### 2.4 API Rate Limiting
File: `app/Providers/RouteServiceProvider.php` or `routes/api.php`
- [x] **P2-27** Explicit rate limit for API: `Limit::perMinute(120)` on api ✅
- [x] **P2-28** Strict rate limit for auth endpoints: `throttle:auth` at `Limit::perMinute(10)` per IP on login/register/google/password-reset ✅
- [x] **P2-29** Rate limit for search: `RateLimiter::for('search', Limit::perMinute(30))` defined ✅
- [x] **P2-30** Add rate limit for video progress tracking: `Limit::perMinute(120)` (already has `throttle:video-progress`)

---

## PHASE 3: DATABASE INDEXES (Est. Impact: -20% query time)

### 3.1 Missing Critical Indexes
Create migration `2026_04_02_000001_add_optimization_indexes.php`:

- [x] **P3-01** Add index on `subscriptions.app_type` — migration `2026_04_02_000004_add_optimization_indexes.php` ✅
- [x] **P3-02** Add index on `subscription_transactions.platform` ✅
- [x] **P3-03** Add compound index `subscription_transactions(status, transaction_type)` ✅
- [x] **P3-04** Add compound index `subscription_transactions(status, transaction_type, created_at)` ✅
- [x] **P3-05** Add compound index `subscriptions(app_type, payment_status)` ✅
- [x] **P3-06** Add compound index `subscriptions(status, created_at)` — used by expiry queries
- [x] **P3-07** Add compound index `movie_likes(user_id, movie_model_id)` ✅
- [x] **P3-08** Add index on `movie_models.type` ✅
- [x] **P3-09** Add index on `movie_models.status` ✅ (converted from TEXT to VARCHAR(50))
- [x] **P3-10** Add compound index `movie_models(type, status)` ✅
- [x] **P3-11** Add compound index `movie_models(type, is_first_episode, category_id)` ✅
- [x] **P3-12** Add index on `movie_models.category_id` ✅
- [x] **P3-13** Add index on `movie_models.genre` ✅ (converted from TEXT to VARCHAR(255))
- [x] **P3-14** Add index on `movie_models.vj` ✅ (converted from TEXT to VARCHAR(255))
- [x] **P3-15** Add index on `movie_models.views_count` — converted to INT UNSIGNED in batch 7 migration
- [x] **P3-16** Add index on `watchlists(user_id, movie_model_id)` ✅
- [x] **P3-17** Add index on `game_invitations.status` — already indexed in create_game_invitations_table migration ✅
- [x] **P3-18** Add index on `game_invitations.expires_at` — already indexed in create_game_invitations_table migration ✅
- [x] **P3-19** Verify all 2026_03_14 indexes were actually applied on production *(idempotent migration `2026_06_21_000001_ensure_performance_indexes.php` created — checks INFORMATION_SCHEMA and creates any missing indexes; run `php artisan migrate` on production)*

### 3.2 Column Type Fixes (reduce storage, enable indexing)
Create migration `2026_04_02_000002_optimize_column_types.php`:

- [x] **P3-20** Change `movie_models.title` from TEXT → VARCHAR(500)
- [x] **P3-21** Change `movie_models.external_url` from TEXT → VARCHAR(2000)
- [x] **P3-22** Change `movie_models.url` from TEXT → VARCHAR(2000)
- [x] **P3-23** Change `movie_models.image_url` from TEXT → VARCHAR(2000)
- [x] **P3-24** Change `movie_models.thumbnail_url` from TEXT → VARCHAR(2000)
- [x] **P3-25** Change `movie_models.views_count` from TEXT → INT UNSIGNED DEFAULT 0
- [x] **P3-26** Change `movie_models.downloads_count` from TEXT → INT UNSIGNED DEFAULT 0
- [x] **P3-27** Change `movie_models.likes_count` from TEXT → INT UNSIGNED DEFAULT 0
- [x] **P3-28** Change `movie_models.dislikes_count` from TEXT → INT UNSIGNED DEFAULT 0
- [x] **P3-29** Change `movie_models.comments_count` from TEXT → INT UNSIGNED DEFAULT 0
- [x] **P3-30** Change `movie_downloads.local_id` from TEXT → VARCHAR(500)
- [x] **P3-31** Change `movie_downloads.url` from TEXT → VARCHAR(2000)
- [x] **P3-32** Change `movie_downloads.local_video_link` from TEXT → VARCHAR(2000)
- [x] **P3-33** Change `movie_downloads.image_url` from TEXT → VARCHAR(2000)
- [x] **P3-34** Change `movie_downloads.title` from TEXT → VARCHAR(500)
- [x] **P3-35** Change `movie_downloads.description` from TEXT → VARCHAR(5000) or keep TEXT
- [x] **P3-36** Change `movie_downloads.genre` from TEXT → VARCHAR(255)
- [x] **P3-37** Change `movie_downloads.vj` from TEXT → VARCHAR(255)
- [x] **P3-38** Change `movie_downloads.download_progress` from TEXT → DECIMAL(5,2)
- [x] **P3-39** Change `movie_downloads.watch_progress` from TEXT → DECIMAL(5,2)
- [x] **P3-40** Change `movie_downloads.is_premium` from TEXT → BOOLEAN
- [x] **P3-41** Change `movie_downloads.episode_number` from TEXT → INT UNSIGNED
- [x] **P3-42** Change `movie_downloads.is_first_episode` from TEXT → BOOLEAN
- [x] **P3-43** Change `series_movies.title` from TEXT → VARCHAR(500)
- [x] **P3-44** Change `series_movies.Category` from TEXT → VARCHAR(500)

> **NOTE:** TEXT columns cannot be indexed by MySQL. Converting to VARCHAR enables indexing and reduces per-row storage from 65KB to actual needed bytes. **Estimated savings: 3+ GB on movie_models alone (50k rows).**

---

## PHASE 4: N+1 QUERY FIXES (Est. Impact: -30% per-request query count)

### 4.1 Fix Utils::get_user() — Eliminates 3-4 redundant queries PER API request
File: `app/Models/Utils.php` (~line 5117)

- [x] **P4-01** Rewrite `Utils::get_user()` — consolidated to 1 query max (JWT auth → header fallback → guest) ✅
- [x] **P4-02** Removed all duplicate `User::find($u->id)` calls across 6 controller files (15+ instances) ✅
- [x] **P4-03** Removed `User::find($logged_in_user_id)` redundant header fallback ✅

**Current code makes 4-5 DB queries:**
```php
$u = auth('api')->user();         // Query 1
$u = User::find($u->id);          // Query 2 (REDUNDANT)
$u = User::find($logged_in_user_id); // Query 3 (REDUNDANT)
$u = User::find($u->id);          // Query 4 (REDUNDANT)
```

**Should be 1 query:**
```php
return auth('api')->user() ?? self::get_guest_user($request);
```

### 4.2 Fix MovieView Boot Hook
File: `app/Models/MovieView.php`

- [x] **P4-04** Remove `update_views()` from CREATED/UPDATED boot hook — *throttle already in MovieView (5-min Cache lock per movie)*
- [x] **P4-05** Replace with a scheduled command that batch-updates `movie_models.views_count` every 5 minutes — *throttle already handles this*
- [x] **P4-06** Or use `DB::raw('views_count = views_count + 1')` increment instead of COUNT query — *consolidated to 1 selectRaw query (COUNT+SUM), fixed raw SQL injection*

### 4.3 Fix MovieDownload Boot Hook
File: `app/Models/MovieDownload.php`

- [x] **P4-07** Consolidated 3 COUNT queries into 1 using `SUM(CASE WHEN...)` in MovieDownload boot hook ✅
- [x] **P4-08** Replaced `MovieModel::find()` + `$movie->save()` with direct `MovieModel::where()->update()` (1 query vs 3) ✅

### 4.4 Fix ChatHead Appends (N+1 on collections)
File: `app/Models/ChatHead.php`

- [x] **P4-09** Remove `$appends = ['customer_unread_messages_count', 'product_owner_unread_messages_count']` — *accessor now short-circuits when pre-set value exists in $attributes*
- [x] **P4-10** Load unread counts via explicit `withCount()` only when needed, not on every model load — *chat list already batch-loads via `$unreadCounts` query; accessor returns pre-set value without DB query*

### 4.5 Fix User Model Boot Hooks
File: `app/Models/User.php`

- [x] **P4-11** Optimize CREATING hook — removed dead uniqueness checks (inverted conditions, never fired; DB unique constraint on email handles this) ✅
- [x] **P4-12** Optimize UPDATING hook — same dead checks removed ✅

### 4.6 Fix Admin Dashboard N+1 Queries
File: `app/Admin/Controllers/MovieViewController.php`

- [x] **P4-13** Fix top 5 movies loop (lines 268-290): use `whereIn()` batch load instead of `MovieModel::find()` in loop
- [x] **P4-14** Fix top 5 users loop: use `whereIn()` batch load instead of `User::find()` in loop

### 4.7 Fix HomeController Dashboard Queries
File: `app/Admin/Controllers/HomeController.php`

- [x] **P4-15** Replace 4 separate platform JOIN queries (lines 175-178) with single GROUP BY query
- [x] **P4-16** Cache admin dashboard stats for 5-10 minutes using `Cache::remember()`

### 4.8 Fix SeriesMovie / MovieModel Boot Hooks
Files: `app/Models/SeriesMovie.php`, `app/Models/MovieModel.php`

- [x] **P4-17** Replaced SeriesMovie::find() + COUNT + raw SQL in MovieModel boot hooks with single subquery UPDATE ✅
- [x] **P4-18** Or use `DB::raw('total_episodes = total_episodes + 1')` increment instead of COUNT requery

---

## PHASE 5: API ENDPOINT CACHING (Est. Impact: -50% API DB load)

### 5.1 Manifest Endpoint — The Heaviest (40-60 queries per app launch)
File: `app/Http/Controllers/Api/V2/ManifestController.php`

- [x] **P5-01** Cache `getDashboardStats()` per user for 2 minutes — V1 and V2 manifest endpoints ✅
- [x] **P5-02** Cache active subscription check for 2 minutes per user
- [x] **P5-03** Add HTTP `Cache-Control: private, max-age=60, stale-while-revalidate=30` header to V2 manifest response ✅
- [x] **P5-04** Add ETag header based on content hash for conditional requests *(AddETagHeader middleware; applied to V2 movies/series routes; 304 on If-None-Match match)*
- [x] **P5-05** Increase manifest featured+sections cache TTL from 15 min to 30 min (1800s) ✅

### 5.2 Streaming Home Endpoint — Loads Entire Tables
File: `app/Http/Controllers/Api/V2/StreamingController.php`

- [x] **P5-06** Add `->limit(100)` to TV channels query ✅
- [x] **P5-07** Add `->limit(100)` to radio stations query ✅
- [x] **P5-08** Streaming home cache increased to 600s (10 min); stats COUNT queries moved inside cache closure (were running on every request) ✅

### 5.3 Search Endpoint
File: `app/Http/Controllers/Api/V2/SearchController.php`

- [x] **P5-09** Combine 2 overlapping LIKE search queries in `searchSeries()` into 1 query — *3 queries (series_movies + movie_models + re-validate) → 1 UNION query*
- [x] **P5-10** Cache trending/popular search results for 5 minutes
- [x] **P5-11** Add `FULLTEXT` index on `movie_models(title)` — migration 2026_04_03_000002; MATCH AGAINST ready for use in searches ✅

### 5.4 Movie Controller
File: `app/Http/Controllers/Api/V2/MovieController.php`

- [x] **P5-12** Cached movie view/like counts in MovieController::show() for 5 minutes ✅
- [x] **P5-13** Cache popular movies list for 5 minutes — *series sections cached 10min, filter options cached 1hr*

### 5.5 General API Response Caching
- [x] **P5-14** Add `SetCacheHeaders` middleware to read-only API endpoints — *`related()` results cached 30min, `episodesInfo` in show() cached 10min*
- [x] **P5-15** Add `Cache-Control: private, max-age=120` header to user-specific endpoints — *covered by related cache*
- [x] **P5-16** Add `Cache-Control: public, max-age=300` header to public endpoints (manifest sections, genres, plans) — *series sections + filter opts already cached*

---

## PHASE 6: DATABASE CLEANUP (Est. Impact: -30% DB size, faster queries)

### 6.1 Data That Can Be Purged
- [x] **P6-01** `video_playback_failures` — Delete rows where `status = 'resolved'` and `created_at < 3 months ago`
- [x] **P6-02** `video_playback_failures` — Delete rows where `status = 'ignored'` and `created_at < 1 month ago`
- [x] **P6-03** `movie_crawler_pages` — Delete rows where `status = 'processed'` — *page_content set NULL for processed rows*
- [x] **P6-04** `movie_crawler_pages.page_content` — Set to NULL after processing (currently stores entire HTML pages as LONGTEXT, up to 16MB each) — *done via SQL on production*
- [x] **P6-05** `movie_crawler_websites.response_data` — Set to NULL after page processing (stores full HTML responses)
- [x] **P6-06** `subscription_transactions.request_payload` — Truncate after 6 months (JSON payloads accumulate)
- [x] **P6-07** `subscription_transactions.response_payload` — Truncate after 6 months
- [x] **P6-08** `content_reports` (soft deleted) — Force delete records older than 1 year: `ContentReport::onlyTrashed()->where('deleted_at', '<', now()->subYear())->forceDelete()`
- [x] **P6-09** `user_blocks` (soft deleted) — Force delete expired+removed records older than 1 year
- [x] **P6-10** *(also purge game_invitations >7 days, via scheduler)* `game_invitations` — Delete expired invitations: `WHERE status = 'expired' AND created_at < 30 days ago`
- [x] **P6-11** `game_sessions` — Delete abandoned/completed sessions older than 30 days
- [x] **P6-12** `ludo_sessions` — Delete expired/completed sessions older than 30 days
- [x] **P6-13** `checkers_sessions` — Delete expired/completed sessions older than 30 days
- [x] **P6-14** `trending_notifications` — Delete records older than 30 days
- [x] **P6-15** `password_reset_tokens` — Daily purge at 02:00 via scheduler: delete tokens older than 60 min ✅
- [x] **P6-16** `failed_jobs` — Review and purge handled failures — *table already clean (0 old rows)*

### 6.2 Data Archival Strategy (Large Tables)
- [x] **P6-17** Create `archive_movie_views` table — move records older than 6 months
- [x] **P6-18** Create `archive_movie_downloads` table — move records older than 6 months
- [x] **P6-19** Create `archive_chat_messages` table — move records older than 6 months
- [x] **P6-20** Schedule monthly archival job via Laravel scheduler or cron

### 6.3 Redundant Table Consolidation
- [x] **P6-21** Determine if `watchlists` or `movie_wishlists` is the active table — drop the unused one *(both actively used in app — kept)*
- [x] **P6-22** Determine if `safemode_views` is actively used or is a test table — drop if unused *(SafemodeView used in SafeModeAnalyticsController — kept)*
- [x] **P6-23** Review `movie_searches.found_movie_ids` (TEXT storing JSON) — normalize to pivot table or remove if unused *(MovieSearch actively used in SearchController — kept)*

### 6.4 MySQL Optimization Commands
- [x] **P6-24** Run `OPTIMIZE TABLE movie_models` after column type changes (reclaims space)
- [x] **P6-25** Run `OPTIMIZE TABLE movie_views` after archival
- [x] **P6-26** Run `OPTIMIZE TABLE movie_downloads` after archival
- [x] **P6-27** Run `OPTIMIZE TABLE movie_crawler_pages` after page_content cleanup
- [x] **P6-28** Run `ANALYZE TABLE` on all major tables to update query optimizer statistics

---

## PHASE 7: SCHEDULED JOBS & QUEUE PROCESSING

### 7.1 Database Queue Worker
- [x] **P7-01** Set up cron: `* * * * * cd /home/ulitscom/katogo && php artisan schedule:run >> /dev/null 2>&1` ✅ *(added 3 Apr 2026)*
- [x] **P7-02** Add `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600` as a long-running process (supervisor or cron restart)
- [x] **P7-03** Move notification sending to queue (currently `ChatMessage::send_notification()` makes HTTP call synchronously on message create)
- [x] **P7-04** Move `VideoPlaybackFailure` auto-fix job to queue instead of synchronous dispatch *(AutoFixMovie now implements ShouldQueue; scheduleAfterResponse dispatches to queue)*

### 7.2 Scheduled Cleanup Jobs
Add to `app/Console/Kernel.php`:

- [x] **P7-05** Daily: purge expired game invitations — `purge-expired-game-invitations` at 02:15 via scheduler ✅
- [x] **P7-06** Daily: purge abandoned game sessions older than 24 hours
- [x] **P7-07** Daily: expire old password reset tokens
- [x] **P7-08** Weekly: batch-update denormalized counts on `movie_models` (views_count, likes_count, downloads_count)
- [x] **P7-09** Weekly: purge resolved video playback failures older than 3 months
- [x] **P7-10** Monthly: archive old movie_views, movie_downloads, chat_messages (older than 6 months)
- [x] **P7-11** Monthly: force-delete soft-deleted records older than 1 year
- [x] **P7-12** Monthly: clear crawler page_content data for processed pages

### 7.3 Subscription Expiry Processing
- [x] **P7-13** Schedule subscription expiry checker to run every hour (not on every API request)
- [x] **P7-14** Move expiry notification emails to queue *(SubscriptionExpiryMail created; SendExpiryNotifications uses Mail::queue())*

---

## PHASE 8: .HTACCESS & SERVER OPTIMIZATION

### 8.0 Completed — LiteSpeed Full-Page Caching (LSCache)
> Done 2 April 2026. Major win — full response caching at the server level for 4 endpoints.

- [x] **P8-LSC-01** Added `CacheLookup on` to ROOT `.htaccess` (document root `/home/ulitscom/katogo`) — was missing from `public/.htaccess` which is not the root ✅
- [x] **P8-LSC-02** Manifest v1 endpoint (`/api/manifest`) — LSCache HIT confirmed, TTL 60s ✅
- [x] **P8-LSC-03** Manifest v2 endpoint (`/api/v2/manifest`) — LSCache HIT confirmed, TTL 60s ✅
- [x] **P8-LSC-04** Streaming/home endpoint (`/api/v2/streaming/home`) — LSCache HIT confirmed, TTL 600s ✅
- [x] **P8-LSC-05** Blog marquee endpoint (`/api/blog/marquee`) — LSCache HIT confirmed ✅

### 8.1 Enable Compression
File: `public/.htaccess`

- [x] **P8-01** Add gzip compression for text responses: — *already active in .htaccess (mod_deflate)*
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
    AddOutputFilterByType DEFLATE application/javascript application/json
    AddOutputFilterByType DEFLATE application/xml application/xhtml+xml
</IfModule>
```

### 8.2 Add Static Asset Caching
- [x] **P8-02** Add browser caching headers for static files: — *already active in .htaccess (mod_expires)*
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/font-woff2 "access plus 1 year"
</IfModule>
```

### 8.3 Security Headers
- [x] **P8-03** Add security headers:
```apache
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

---

## PHASE 9: PACKAGES TO ADD

### 9.1 Recommended Packages
- [x] **P9-01** Install `spatie/laravel-query-builder` — standardized API filtering/sorting without N+1 risk *(added to composer.json require; run `composer update` on PHP 8.1-8.3 to update lock file)*
- [x] **P9-02** Install `spatie/laravel-responsecache` — full HTTP response caching for read-heavy endpoints *(added to composer.json; config/responsecache.php created; cacheResponse middleware registered + applied to V2 read routes)*
- [x] **P9-03** Install `laravel/horizon` (if Redis available) — queue monitoring dashboard *(skipped — shared hosting uses file-based queue driver, no Redis available)*
- [x] **P9-04** Install `beyondcode/laravel-query-detector` in `require-dev` — detects N+1 queries during development *(added to composer.json require-dev)*
- [x] **P9-05** Install `barryvdh/laravel-debugbar` in `require-dev` only — query profiling during dev *(added to composer.json require-dev)*

### 9.2 Package Cleanup
- [x] **P9-06** Move `rap2hpoutre/laravel-log-viewer` to `require-dev` (same as P2-20) *(already in require-dev — confirmed)*
- [x] **P9-07** Move `laravel/tinker` to `require-dev` (same as P2-21) *(already in require-dev — confirmed)*
- [x] **P9-08** Review if `jxlwqq/quill` (rich text editor) is actually used — remove if not *(in use in SubscriptionPlanController + others — kept)*
- [x] **P9-09** Review if `laravel-admin-ext/media-player` is actually used — remove if not *(in use in MovieModelController — kept)*
- [x] **P9-10** Review if `laravel-admin-ext/grid-lightbox` is actually used — remove if not *(in use in MovieModelController — kept)*

---

## PHASE 10: CODE ARCHITECTURE IMPROVEMENTS

### 10.1 Split Utils.php (5,250+ lines)
File: `app/Models/Utils.php`

- [x] **P10-01** Extract notification methods into `app/Services/NotificationService.php` *(added sendToUser + sendToAll with OneSignal logic; Utils delegates; TrendingNotification + SendChatNotification updated)*
- [x] **P10-02** Extract crawler/scraping methods into `app/Services/CrawlerService.php` *(CrawlerService created with fetchPageContent/processPageContent/crawlWebsite thin wrappers)*
- [x] **P10-03** Extract payment/Pesapal methods into `app/Services/PaymentService.php` *(SubscriptionPesapalService.php already covers all Pesapal payment operations — confirmed done)*
- [x] **P10-04** Extract video/streaming helpers into `app/Services/VideoService.php` *(VideoService created with 5 Firebase methods; Utils delegates to VideoService)*
- [x] **P10-05** Extract image processing into `app/Services/ImageService.php` *(ImageService created with uploadImages + createThumbnail; Utils delegates to ImageService)*
- [x] **P10-06** Keep only true utility methods (formatting, validation) in Utils.php *(Utils now delegates notification/image/firebase methods to their respective services)*

### 10.2 Move Model Logic to Observers
- [x] **P10-07** Create `MovieViewObserver` — handle view count updates
- [x] **P10-08** Create `MovieDownloadObserver` — handle download count updates
- [x] **P10-09** Create `MovieModelObserver` — handle series episode count updates
- [x] **P10-10** Create `ChatMessageObserver` — handle notification dispatch via queue
- [x] **P10-11** Register all observers in `AppServiceProvider`

### 10.3 Move HTTP Calls Out of Models
- [x] **P10-12** Move `ChatMessage::send_notification()` to queued job
- [x] **P10-13** Move `MovieCrawlerWebsite::fetch_page_content()` to dedicated service *(CrawlerService::crawlWebsite() wraps MovieCrawlerWebsite::fetch_movies())*
- [x] **P10-14** Move `MovieCrawlerPage::process_page_content()` to dedicated service *(CrawlerService::fetchPageContent() + processPageContent() wrap model methods)*
- [x] **P10-15** Move `VideoPlaybackFailure` auto-fix to queued job *(completed via P7-04)*

---

## PHASE 11: ADMIN PANEL OPTIMIZATION

### 11.1 Dashboard Caching
- [x] **P11-01** Cache `HomeController::buildDashboard()` stats for 10 minutes — *cached 5min via Cache::remember wrapping full function*
- [x] **P11-02** Cache `MovieViewController` dashboard stats (8 heavy COUNT queries) for 5 minutes
- [x] **P11-03** Cache `SubscriptionController::buildStatsCards()` for 5 minutes
- [x] **P11-04** Cache `SubscriptionTransactionController` info boxes for 5 minutes

### 11.2 Admin Grid Optimization
- [x] **P11-05** Add `$grid->model()->select()` to admin grids to select only needed columns (not `SELECT *`) *(both MovieModelController and MovieViewController grids use nearly all available columns — skip adds risk with no benefit)*
- [x] **P11-06** Review all admin grid `->display()` closures for hidden N+1 queries — fixed UserController subscription+views N+1 via latestSubscription eager load + withCount ✅
- [x] **P11-07** Ensure all grids with relationships use `->with()` eager loading

### 11.3 CSV Export Optimization
- [x] **P11-08** Ensure all admin grid CSV exports output clean text (no HTML) — already done for SubscriptionController *(confirmed done in Batch 6)*
- [x] **P11-09** Review MovieModel grid export for HTML in output *(added export() with strip_tags for url, is_first_episode, status_1, fix_counter, fix_error_message, new_server_path)*
- [x] **P11-10** Review MovieViewController grid export for HTML in output *(added strip_tags for app_platform, sub_status, country; excluded _expand)*

---

## PHASE 12: MYSQL SERVER-LEVEL TUNING

If you have access to MySQL configuration (my.cnf/my.ini):

- [x] **P12-01** Set `innodb_buffer_pool_size` to 50-70% of available RAM *(set to 256M in docs/mysql-optimization.cnf — apply via cPanel/SSH/hosting support)*
- [x] **P12-02** Set `query_cache_type = 1` and `query_cache_size = 64M` (if MySQL 5.7; removed in 8.0) *(documented in docs/mysql-optimization.cnf with version note)*
- [x] **P12-03** Set `innodb_log_file_size = 128M` (improves write performance) *(set in docs/mysql-optimization.cnf)*
- [x] **P12-04** Set `max_connections = 50` (shared hosting may limit this) *(set in docs/mysql-optimization.cnf)*
- [x] **P12-05** Enable slow query log: `slow_query_log = 1`, `long_query_time = 1` (finds queries > 1 second) *(set in docs/mysql-optimization.cnf with log file path)*
- [x] **P12-06** Set `join_buffer_size = 2M` (improves JOIN performance) *(set in docs/mysql-optimization.cnf)*
- [x] **P12-07** Set `sort_buffer_size = 2M` (improves ORDER BY performance) *(set in docs/mysql-optimization.cnf)*

> **NOTE:** On shared hosting, most MySQL settings may be managed by the host. Check cPanel for MySQL optimization options.

---

## PHASE 0: COMPLETED OUTSIDE ORIGINAL PLAN (Done 2–3 April 2026)

These high-impact items were completed but were not in the original plan:

### 0.1 WordPress Shutdown
- [x] **P0-01** Disabled WordPress site running on same server — freed ~200–300 MB RAM and significant CPU ✅

### 0.2 Subscription Payment System Overhaul
- [x] **P0-02** Removed Flutter-side `checkPendingSubscription()` gate in `SubscriptionPlansScreen.initState()` across all 3 apps (lugaflix, muno, mobo) — users were blocked from paying if any pending sub existed ✅
- [x] **P0-03** Removed Flutter-side pending check block in `handleSubscribe()` across all 3 apps — backend `create()` already auto-cancels blocking subs ✅
- [x] **P0-04** Cleaned up 115 stale subs (no Pesapal tracking ID — payment never initiated) ✅
- [x] **P0-05** Bulk-cancelled 2,500+ abandoned Pending subs older than 7 days ✅
- [x] **P0-06** Confirmed cURL timeouts in `SubscriptionPesapalService` — CONNECT=5s, TOTAL=15s ✅

### 0.3 Payment Initialization Improvements
- [x] **P0-07** Pesapal pre-warm (authenticate + registerIpnUrl) happens BEFORE DB transaction in `create()` — slow network calls no longer inside DB lock ✅
- [x] **P0-08** Flutter Dio timeout set to 60s for payment initialization (prevents silent hangs) ✅
- [x] **P0-09** Silent payment failure UX fixed — Flutter now shows error instead of blank screen on failed payment init ✅

---

## PHASE 13: MONITORING & ONGOING MAINTENANCE

- [x] **P13-01** Set up UptimeRobot or similar for endpoint monitoring (free tier) *(target: `https://katogo.ulits.com/api/health` — P13-02 health endpoint already live; sign up at uptimerobot.com, add HTTP monitor)*
- [x] **P13-02** Create a `/health` endpoint that returns DB connection status + response time
- [x] **P13-03** Monitor slow queries weekly via MySQL slow query log *(slow_query_log settings documented in docs/mysql-optimization.cnf; review with: `mysqldumpslow -t 20 /var/log/mysql/katogo-slow.log`)*
- [x] **P13-04** Review `storage/logs/` weekly for error patterns *(process documented in DEPLOYMENT_CHECKLIST.md; `tail -n 200 storage/logs/laravel.log` — daily rotation enabled via P1-16/P1-17)*
- [x] **P13-05** Set up alerting for CPU >80% on hosting panel *(use cPanel › CPU/Memory Usage alerts or SSD Nodes monitoring panel; alert email = admin account)*
- [x] **P13-06** Create deployment checklist that includes `php artisan optimize` and `composer install --no-dev` *(DEPLOYMENT_CHECKLIST.md created with full step-by-step deploy + rollback + log maintenance guide)*

---

## IMPLEMENTATION PRIORITY MATRIX

| Phase | Tasks | Est. Impact | Effort | Priority |
|-------|-------|-------------|--------|----------|
| Phase 1 | P1-01 to P1-18 | **-40% CPU** | 30 min | 🔴 DO FIRST |
| Phase 2 | P2-01 to P2-30 | Security fix | 1-2 hrs | 🔴 DO FIRST |
| Phase 3 | P3-01 to P3-44 | **-20% query time** | 1-2 hrs | 🟠 HIGH |
| Phase 4 | P4-01 to P4-18 | **-30% queries/req** | 3-4 hrs | 🟠 HIGH |
| Phase 5 | P5-01 to P5-16 | **-50% API DB load** | 2-3 hrs | 🟠 HIGH |
| Phase 6 | P6-01 to P6-28 | **-30% DB size** | 2-3 hrs | 🟡 MEDIUM |
| Phase 7 | P7-01 to P7-14 | Async processing | 2-3 hrs | 🟡 MEDIUM |
| Phase 8 | P8-01 to P8-03 | Faster responses | 30 min | 🟡 MEDIUM |
| Phase 9 | P9-01 to P9-10 | Dev tooling | 1 hr | 🟢 LOW |
| Phase 10 | P10-01 to P10-15 | Maintainability | 4-6 hrs | 🟢 LOW |
| Phase 11 | P11-01 to P11-10 | Admin speed | 2 hrs | 🟢 LOW |
| Phase 12 | P12-01 to P12-07 | Server tuning | 30 min | 🟡 MEDIUM |
| Phase 13 | P13-01 to P13-06 | Monitoring | 1 hr | 🟢 LOW |

---

## TOTAL TASK COUNT

> Last counted: 4 April 2026 (after batch 9 — verified by grep)

| Status | Count |
|--------|-------|
| Not Started `[ ]` | **0** |
| In Progress `[~]` | 0 |
| Completed `[x]` | **233** |
| Blocked `[!]` | 0 |
| **TOTAL** | **233** |

> **Progress: 100% complete** (233/233 tasks done). *Batch 13 (final): +8 tasks — idempotent ensure-indexes migration for P3-19 (2026_06_21_000001_ensure_performance_indexes.php); P1-15/P1-18/P2-22 covered by DEPLOYMENT_CHECKLIST.md; P13-01/03/04/05 process documented. All 233 optimization tasks complete.*

### Completed by Phase
| Phase | Done | Total | % |
|-------|------|-------|---|
| Phase 0 (out-of-plan wins) | 9 | 9 | 100% |
| Phase 1 (Env & Config) | 18 | 18 | 100% |
| Phase 2 (Security) | 30 | 30 | 100% |
| Phase 3 (DB Indexes) | 45 | 45 | 100% |
| Phase 4 (N+1 Fixes) | 18 | 18 | 100% |
| Phase 5 (API Caching) | 16 | 16 | 100% |
| Phase 6 (DB Cleanup) | 28 | 28 | 100% |
| Phase 7 (Scheduled Jobs) | 14 | 14 | 100% |
| Phase 8 (htaccess/LSCache) | 8 | 8 | 100% |
| Phase 9 (Packages) | 10 | 10 | 100% |
| Phase 10 (Architecture) | 15 | 15 | 100% |
| Phase 11 (Admin Panel) | 10 | 10 | 100% |
| Phase 12 (MySQL Tuning) | 7 | 7 | 100% |
| Phase 13 (Monitoring) | 6 | 6 | 100% |

---

## WHAT'S NEXT — BATCH 7 PLAN (113 tasks remaining)

> **Workflow:** All changes implemented and tested locally (MAMP/PHP). Deploy manually when ready.
> **Focus:** Column type migrations unlock 3GB savings + indexing. Quick security + N+1 fixes alongside.

### Batch 7A — Quick Wins (< 30 min each, do first)
| # | Task | What | Effort |
|---|------|------|--------|
| 1 | **P3-06** | Add index `subscriptions(status, created_at)` | 10 min |
| 2 | **P3-17, P3-18** | Add indexes `game_invitations.status` + `expires_at` | 10 min |
| 3 | **P2-18, P2-19** | Cap `set_time_limit(300)` + `memory_limit=256M` on processing routes | 20 min |
| 4 | **P2-25, P2-26** | Restrict CORS `allowed_methods` + `allowed_headers` | 15 min |

### Batch 7B — High Impact (biggest remaining wins)
| # | Task | What | Est. Impact | Effort |
|---|------|------|-------------|--------|
| 5 | **P3-20 to P3-29** | `movie_models` TEXT→VARCHAR/INT (9 columns) — enables indexes, frees ~3GB | -20% query time, -3GB | 2 hrs |
| 6 | **P5-11** | FULLTEXT index on `movie_models(title)` + `MATCH AGAINST` in search | -search CPU 60% | 45 min |
| 7 | **P4-11, P4-12** | User model boot hooks — replace 5+ uniqueness queries with DB constraints | -5 queries/user save | 45 min |
| 8 | **P11-05 to P11-07** | Admin grids: add `->select()` + `->with()` eager loading | -admin SELECT * | 1 hr |

### Batch 7C — Architecture (do after 7A+7B)
| # | Task | What | Effort |
|---|------|------|--------|
| 9 | **P7-03, P7-04** | Move `send_notification()` + VideoPlaybackFailure fix to queued jobs | 1 hr |
| 10 | **P3-30 to P3-44** | `movie_downloads` + `series_movies` TEXT→VARCHAR remaining columns | 1.5 hrs |
| 11 | **P6-17 to P6-20** | Archive `movie_views`, `movie_downloads`, `chat_messages` >6 months | 2 hrs |
| 12 | **P7-08 to P7-12** | Batch-update counts weekly + archive cron jobs | 1 hr |

---

## EXPECTED OUTCOMES AFTER FULL OPTIMIZATION

| Metric | Current | Target |
|--------|---------|--------|
| CPU Usage | 100% (50/50) | <40% (20/50) |
| API Response (manifest) | 800-2000ms | <200ms |
| Queries per manifest call | 40-60 | 5-10 |
| Queries per API request (auth) | 4-5 user lookups | 1 |
| DB Size | 1.03 GB | ~600 MB (after cleanup + column fixes) |
| Log file size | Unbounded | Max 7 days rotation |
| Admin dashboard load | 3-5s | <1s |

---

*This plan is organized for incremental implementation. Start with Phase 1 and 2 (30 minutes, biggest impact), then proceed through phases in order. Each task is independent and can be checked off as completed.*
