# KATOGO 360° OPTIMIZATION PLAN

> **Generated:** 2 April 2026
> **Server:** Shared hosting (u-lits.com) — 2 GB RAM, 50 CPU units, MySQL
> **Current CPU:** 100% (50/50 units consumed)
> **Goal:** Reduce CPU to <40%, sub-200ms API responses, handle traffic spikes

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
- [ ] **P1-10** Run `php artisan config:cache` (caches 50+ config files into one)
- [ ] **P1-11** Run `php artisan route:cache` (caches 120+ routes)
- [ ] **P1-12** Run `php artisan view:cache` (pre-compiles Blade templates)
- [ ] **P1-13** Run `php artisan event:cache` (caches event-listener map)
- [ ] **P1-14** Run `php artisan optimize` (combines all above)
- [ ] **P1-15** Run `composer install --optimize-autoloader --no-dev` on production

### 1.3 Log Rotation
- [x] **P1-16** In `config/logging.php`, change stack channel from `single` to `daily` ✅
- [x] **P1-17** Set daily log retention to 7 days: `'days' => 7` ✅
- [ ] **P1-18** Delete accumulated `storage/logs/laravel.log` on server (backup first) *(needs manual action on server)*

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
- [ ] **P2-18** Cap `set_time_limit()` to max 300s on processing routes (not 30,000s)
- [ ] **P2-19** Cap `ini_set('memory_limit')` to max `256M` on processing routes (not `512M` or `-1`)

### 2.2 Composer Security
- [x] **P2-20** ~~Move `rap2hpoutre/laravel-log-viewer` from `require` to `require-dev`~~ ✅
- [x] **P2-21** ~~Move `laravel/tinker` from `require` to `require-dev`~~ ✅
- [ ] **P2-22** Run `composer install --no-dev` on production server

### 2.3 CORS Lockdown
File: `config/cors.php`
- [x] **P2-23** ~~Remove `'*'` from `allowed_origins` array~~ ✅
- [x] **P2-24** ~~Set `allowed_origins` to only real domains~~ ✅
- [ ] **P2-25** Restrict `allowed_methods` to `['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']` instead of `['*']`
- [ ] **P2-26** Restrict `allowed_headers` to specific headers instead of `['*']`

### 2.4 API Rate Limiting
File: `app/Providers/RouteServiceProvider.php` or `routes/api.php`
- [ ] **P2-27** Define explicit rate limits: `RateLimiter::for('api', fn() => Limit::perMinute(60))`
- [ ] **P2-28** Add stricter rate limit for auth endpoints: `Limit::perMinute(10)` on login/register
- [ ] **P2-29** Add stricter rate limit for search: `Limit::perMinute(30)` on search endpoints
- [ ] **P2-30** Add rate limit for video progress tracking: `Limit::perMinute(120)` (already has `throttle:video-progress`)

---

## PHASE 3: DATABASE INDEXES (Est. Impact: -20% query time)

### 3.1 Missing Critical Indexes
Create migration `2026_04_02_000001_add_optimization_indexes.php`:

- [x] **P3-01** Add index on `subscriptions.app_type` — migration `2026_04_02_000004_add_optimization_indexes.php` ✅
- [x] **P3-02** Add index on `subscription_transactions.platform` ✅
- [x] **P3-03** Add compound index `subscription_transactions(status, transaction_type)` ✅
- [x] **P3-04** Add compound index `subscription_transactions(status, transaction_type, created_at)` ✅
- [x] **P3-05** Add compound index `subscriptions(app_type, payment_status)` ✅
- [ ] **P3-06** Add compound index `subscriptions(status, created_at)` — used by expiry queries
- [x] **P3-07** Add compound index `movie_likes(user_id, movie_model_id)` ✅
- [x] **P3-08** Add index on `movie_models.type` ✅
- [x] **P3-09** Add index on `movie_models.status` ✅ (converted from TEXT to VARCHAR(50))
- [x] **P3-10** Add compound index `movie_models(type, status)` ✅
- [x] **P3-11** Add compound index `movie_models(type, is_first_episode, category_id)` ✅
- [x] **P3-12** Add index on `movie_models.category_id` ✅
- [x] **P3-13** Add index on `movie_models.genre` ✅ (converted from TEXT to VARCHAR(255))
- [x] **P3-14** Add index on `movie_models.vj` ✅ (converted from TEXT to VARCHAR(255))
- [ ] **P3-15** Add index on `movie_models.views_count` — needs type change first
- [x] **P3-16** Add index on `watchlists(user_id, movie_model_id)` ✅
- [ ] **P3-17** Add index on `game_invitations.status` — for filtering pending invitations
- [ ] **P3-18** Add index on `game_invitations.expires_at` — for expiry cleanup
- [ ] **P3-19** Verify all 2026_03_14 indexes were actually applied on production

### 3.2 Column Type Fixes (reduce storage, enable indexing)
Create migration `2026_04_02_000002_optimize_column_types.php`:

- [ ] **P3-20** Change `movie_models.title` from TEXT → VARCHAR(500)
- [ ] **P3-21** Change `movie_models.external_url` from TEXT → VARCHAR(2000)
- [ ] **P3-22** Change `movie_models.url` from TEXT → VARCHAR(2000)
- [ ] **P3-23** Change `movie_models.image_url` from TEXT → VARCHAR(2000)
- [ ] **P3-24** Change `movie_models.thumbnail_url` from TEXT → VARCHAR(2000)
- [ ] **P3-25** Change `movie_models.views_count` from TEXT → INT UNSIGNED DEFAULT 0
- [ ] **P3-26** Change `movie_models.downloads_count` from TEXT → INT UNSIGNED DEFAULT 0
- [ ] **P3-27** Change `movie_models.likes_count` from TEXT → INT UNSIGNED DEFAULT 0
- [ ] **P3-28** Change `movie_models.dislikes_count` from TEXT → INT UNSIGNED DEFAULT 0
- [ ] **P3-29** Change `movie_models.comments_count` from TEXT → INT UNSIGNED DEFAULT 0
- [ ] **P3-30** Change `movie_downloads.local_id` from TEXT → VARCHAR(500)
- [ ] **P3-31** Change `movie_downloads.url` from TEXT → VARCHAR(2000)
- [ ] **P3-32** Change `movie_downloads.local_video_link` from TEXT → VARCHAR(2000)
- [ ] **P3-33** Change `movie_downloads.image_url` from TEXT → VARCHAR(2000)
- [ ] **P3-34** Change `movie_downloads.title` from TEXT → VARCHAR(500)
- [ ] **P3-35** Change `movie_downloads.description` from TEXT → VARCHAR(5000) or keep TEXT
- [ ] **P3-36** Change `movie_downloads.genre` from TEXT → VARCHAR(255)
- [ ] **P3-37** Change `movie_downloads.vj` from TEXT → VARCHAR(255)
- [ ] **P3-38** Change `movie_downloads.download_progress` from TEXT → DECIMAL(5,2)
- [ ] **P3-39** Change `movie_downloads.watch_progress` from TEXT → DECIMAL(5,2)
- [ ] **P3-40** Change `movie_downloads.is_premium` from TEXT → BOOLEAN
- [ ] **P3-41** Change `movie_downloads.episode_number` from TEXT → INT UNSIGNED
- [ ] **P3-42** Change `movie_downloads.is_first_episode` from TEXT → BOOLEAN
- [ ] **P3-43** Change `series_movies.title` from TEXT → VARCHAR(500)
- [ ] **P3-44** Change `series_movies.Category` from TEXT → VARCHAR(500)

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

- [ ] **P4-04** Remove `update_views()` from CREATED/UPDATED boot hook
- [ ] **P4-05** Replace with a scheduled command that batch-updates `movie_models.views_count` every 5 minutes
- [ ] **P4-06** Or use `DB::raw('views_count = views_count + 1')` increment instead of COUNT query

### 4.3 Fix MovieDownload Boot Hook
File: `app/Models/MovieDownload.php`

- [x] **P4-07** Consolidated 3 COUNT queries into 1 using `SUM(CASE WHEN...)` in MovieDownload boot hook ✅
- [x] **P4-08** Replaced `MovieModel::find()` + `$movie->save()` with direct `MovieModel::where()->update()` (1 query vs 3) ✅

### 4.4 Fix ChatHead Appends (N+1 on collections)
File: `app/Models/ChatHead.php`

- [ ] **P4-09** Remove `$appends = ['customer_unread_messages_count', 'product_owner_unread_messages_count']`
- [ ] **P4-10** Load unread counts via explicit `withCount()` only when needed, not on every model load

### 4.5 Fix User Model Boot Hooks
File: `app/Models/User.php`

- [ ] **P4-11** Optimize CREATING hook — currently runs 5+ validation (uniqueness) queries; use DB unique constraints instead
- [ ] **P4-12** Optimize UPDATING hook — same redundant validation queries

### 4.6 Fix Admin Dashboard N+1 Queries
File: `app/Admin/Controllers/MovieViewController.php`

- [ ] **P4-13** Fix top 5 movies loop (lines 268-290): use `whereIn()` batch load instead of `MovieModel::find()` in loop
- [ ] **P4-14** Fix top 5 users loop: use `whereIn()` batch load instead of `User::find()` in loop

### 4.7 Fix HomeController Dashboard Queries
File: `app/Admin/Controllers/HomeController.php`

- [ ] **P4-15** Replace 4 separate platform JOIN queries (lines 175-178) with single GROUP BY query
- [ ] **P4-16** Cache admin dashboard stats for 5-10 minutes using `Cache::remember()`

### 4.8 Fix SeriesMovie / MovieModel Boot Hooks
Files: `app/Models/SeriesMovie.php`, `app/Models/MovieModel.php`

- [x] **P4-17** Replaced SeriesMovie::find() + COUNT + raw SQL in MovieModel boot hooks with single subquery UPDATE ✅
- [ ] **P4-18** Or use `DB::raw('total_episodes = total_episodes + 1')` increment instead of COUNT requery

---

## PHASE 5: API ENDPOINT CACHING (Est. Impact: -50% API DB load)

### 5.1 Manifest Endpoint — The Heaviest (40-60 queries per app launch)
File: `app/Http/Controllers/Api/V2/ManifestController.php`

- [x] **P5-01** Cache `getDashboardStats()` per user for 2 minutes — V1 and V2 manifest endpoints ✅
- [ ] **P5-02** Cache active subscription check for 2 minutes per user
- [ ] **P5-03** Add HTTP `Cache-Control: public, max-age=60` header to manifest response (allows CDN/device caching)
- [ ] **P5-04** Add ETag header based on content hash for conditional requests
- [ ] **P5-05** Increase section cache TTL from 15 min to 30 min: `Cache::remember("v2_manifest_sections_...", 1800, ...)`

### 5.2 Streaming Home Endpoint — Loads Entire Tables
File: `app/Http/Controllers/Api/V2/StreamingController.php`

- [ ] **P5-06** Add `->limit(50)` to TV channels query (currently loads ALL)
- [ ] **P5-07** Add `->limit(50)` to radio stations query (currently loads ALL)
- [ ] **P5-08** Cache streaming home response for 10 minutes

### 5.3 Search Endpoint
File: `app/Http/Controllers/Api/V2/SearchController.php`

- [ ] **P5-09** Combine 2 overlapping LIKE search queries in `searchSeries()` into 1 query
- [ ] **P5-10** Cache trending/popular search results for 5 minutes
- [ ] **P5-11** Add `FULLTEXT` index on `movie_models(title, description)` and use `MATCH AGAINST` instead of `LIKE '%term%'` (prevents full table scans)

### 5.4 Movie Controller
File: `app/Http/Controllers/Api/V2/MovieController.php`

- [x] **P5-12** Cached movie view/like counts in MovieController::show() for 5 minutes ✅
- [ ] **P5-13** Cache popular movies list for 5 minutes

### 5.5 General API Response Caching
- [ ] **P5-14** Add `SetCacheHeaders` middleware to read-only API endpoints
- [ ] **P5-15** Add `Cache-Control: private, max-age=120` header to user-specific endpoints
- [ ] **P5-16** Add `Cache-Control: public, max-age=300` header to public endpoints (manifest sections, genres, plans)

---

## PHASE 6: DATABASE CLEANUP (Est. Impact: -30% DB size, faster queries)

### 6.1 Data That Can Be Purged
- [ ] **P6-01** `video_playback_failures` — Delete rows where `status = 'resolved'` and `created_at < 3 months ago`
- [ ] **P6-02** `video_playback_failures` — Delete rows where `status = 'ignored'` and `created_at < 1 month ago`
- [ ] **P6-03** `movie_crawler_pages` — Delete rows where `status = 'processed'` (page_content LONGTEXT data no longer needed)
- [ ] **P6-04** `movie_crawler_pages.page_content` — Set to NULL after processing (currently stores entire HTML pages as LONGTEXT, up to 16MB each)
- [ ] **P6-05** `movie_crawler_websites.response_data` — Set to NULL after page processing (stores full HTML responses)
- [ ] **P6-06** `subscription_transactions.request_payload` — Truncate after 6 months (JSON payloads accumulate)
- [ ] **P6-07** `subscription_transactions.response_payload` — Truncate after 6 months
- [ ] **P6-08** `content_reports` (soft deleted) — Force delete records older than 1 year: `ContentReport::onlyTrashed()->where('deleted_at', '<', now()->subYear())->forceDelete()`
- [ ] **P6-09** `user_blocks` (soft deleted) — Force delete expired+removed records older than 1 year
- [ ] **P6-10** `game_invitations` — Delete expired invitations: `WHERE status = 'expired' AND created_at < 30 days ago`
- [ ] **P6-11** `game_sessions` — Delete abandoned/completed sessions older than 30 days
- [ ] **P6-12** `ludo_sessions` — Delete expired/completed sessions older than 30 days
- [ ] **P6-13** `checkers_sessions` — Delete expired/completed sessions older than 30 days
- [ ] **P6-14** `trending_notifications` — Delete records older than 30 days
- [ ] **P6-15** `password_reset_tokens` — Delete expired tokens (older than 60 minutes)
- [ ] **P6-16** `failed_jobs` — Review and purge handled failures

### 6.2 Data Archival Strategy (Large Tables)
- [ ] **P6-17** Create `archive_movie_views` table — move records older than 6 months
- [ ] **P6-18** Create `archive_movie_downloads` table — move records older than 6 months
- [ ] **P6-19** Create `archive_chat_messages` table — move records older than 6 months
- [ ] **P6-20** Schedule monthly archival job via Laravel scheduler or cron

### 6.3 Redundant Table Consolidation
- [ ] **P6-21** Determine if `watchlists` or `movie_wishlists` is the active table — drop the unused one
- [ ] **P6-22** Determine if `safemode_views` is actively used or is a test table — drop if unused
- [ ] **P6-23** Review `movie_searches.found_movie_ids` (TEXT storing JSON) — normalize to pivot table or remove if unused

### 6.4 MySQL Optimization Commands
- [ ] **P6-24** Run `OPTIMIZE TABLE movie_models` after column type changes (reclaims space)
- [ ] **P6-25** Run `OPTIMIZE TABLE movie_views` after archival
- [ ] **P6-26** Run `OPTIMIZE TABLE movie_downloads` after archival
- [ ] **P6-27** Run `OPTIMIZE TABLE movie_crawler_pages` after page_content cleanup
- [ ] **P6-28** Run `ANALYZE TABLE` on all major tables to update query optimizer statistics

---

## PHASE 7: SCHEDULED JOBS & QUEUE PROCESSING

### 7.1 Database Queue Worker
- [ ] **P7-01** Set up cron: `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
- [ ] **P7-02** Add `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600` as a long-running process (supervisor or cron restart)
- [ ] **P7-03** Move notification sending to queue (currently `ChatMessage::send_notification()` makes HTTP call synchronously on message create)
- [ ] **P7-04** Move `VideoPlaybackFailure` auto-fix job to queue instead of synchronous dispatch

### 7.2 Scheduled Cleanup Jobs
Add to `app/Console/Kernel.php`:

- [ ] **P7-05** Daily: purge expired game invitations (`game_invitations WHERE expires_at < now() AND status != 'accepted'`)
- [ ] **P7-06** Daily: purge abandoned game sessions older than 24 hours
- [ ] **P7-07** Daily: expire old password reset tokens
- [ ] **P7-08** Weekly: batch-update denormalized counts on `movie_models` (views_count, likes_count, downloads_count)
- [ ] **P7-09** Weekly: purge resolved video playback failures older than 3 months
- [ ] **P7-10** Monthly: archive old movie_views, movie_downloads, chat_messages (older than 6 months)
- [ ] **P7-11** Monthly: force-delete soft-deleted records older than 1 year
- [ ] **P7-12** Monthly: clear crawler page_content data for processed pages

### 7.3 Subscription Expiry Processing
- [ ] **P7-13** Schedule subscription expiry checker to run every hour (not on every API request)
- [ ] **P7-14** Move expiry notification emails to queue

---

## PHASE 8: .HTACCESS & SERVER OPTIMIZATION

### 8.1 Enable Compression
File: `public/.htaccess`

- [ ] **P8-01** Add gzip compression for text responses:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
    AddOutputFilterByType DEFLATE application/javascript application/json
    AddOutputFilterByType DEFLATE application/xml application/xhtml+xml
</IfModule>
```

### 8.2 Add Static Asset Caching
- [ ] **P8-02** Add browser caching headers for static files:
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
- [ ] **P8-03** Add security headers:
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
- [ ] **P9-01** Install `spatie/laravel-query-builder` — standardized API filtering/sorting without N+1 risk
- [ ] **P9-02** Install `spatie/laravel-responsecache` — full HTTP response caching for read-heavy endpoints
- [ ] **P9-03** Install `laravel/horizon` (if Redis available) — queue monitoring dashboard
- [ ] **P9-04** Install `beyondcode/laravel-query-detector` in `require-dev` — detects N+1 queries during development
- [ ] **P9-05** Install `barryvdh/laravel-debugbar` in `require-dev` only — query profiling during dev

### 9.2 Package Cleanup
- [ ] **P9-06** Move `rap2hpoutre/laravel-log-viewer` to `require-dev` (same as P2-20)
- [ ] **P9-07** Move `laravel/tinker` to `require-dev` (same as P2-21)
- [ ] **P9-08** Review if `jxlwqq/quill` (rich text editor) is actually used — remove if not
- [ ] **P9-09** Review if `laravel-admin-ext/media-player` is actually used — remove if not
- [ ] **P9-10** Review if `laravel-admin-ext/grid-lightbox` is actually used — remove if not

---

## PHASE 10: CODE ARCHITECTURE IMPROVEMENTS

### 10.1 Split Utils.php (5,250+ lines)
File: `app/Models/Utils.php`

- [ ] **P10-01** Extract notification methods into `app/Services/NotificationService.php`
- [ ] **P10-02** Extract crawler/scraping methods into `app/Services/CrawlerService.php`
- [ ] **P10-03** Extract payment/Pesapal methods into `app/Services/PaymentService.php`
- [ ] **P10-04** Extract video/streaming helpers into `app/Services/VideoService.php`
- [ ] **P10-05** Extract image processing into `app/Services/ImageService.php`
- [ ] **P10-06** Keep only true utility methods (formatting, validation) in Utils.php

### 10.2 Move Model Logic to Observers
- [ ] **P10-07** Create `MovieViewObserver` — handle view count updates
- [ ] **P10-08** Create `MovieDownloadObserver` — handle download count updates
- [ ] **P10-09** Create `MovieModelObserver` — handle series episode count updates
- [ ] **P10-10** Create `ChatMessageObserver` — handle notification dispatch via queue
- [ ] **P10-11** Register all observers in `AppServiceProvider`

### 10.3 Move HTTP Calls Out of Models
- [ ] **P10-12** Move `ChatMessage::send_notification()` to queued job
- [ ] **P10-13** Move `MovieCrawlerWebsite::fetch_page_content()` to dedicated service
- [ ] **P10-14** Move `MovieCrawlerPage::process_page_content()` to dedicated service
- [ ] **P10-15** Move `VideoPlaybackFailure` auto-fix to queued job

---

## PHASE 11: ADMIN PANEL OPTIMIZATION

### 11.1 Dashboard Caching
- [ ] **P11-01** Cache `HomeController::buildDashboard()` stats for 10 minutes
- [ ] **P11-02** Cache `MovieViewController` dashboard stats (8 heavy COUNT queries) for 5 minutes
- [ ] **P11-03** Cache `SubscriptionController::buildStatsCards()` for 5 minutes
- [ ] **P11-04** Cache `SubscriptionTransactionController` info boxes for 5 minutes

### 11.2 Admin Grid Optimization
- [ ] **P11-05** Add `$grid->model()->select()` to admin grids to select only needed columns (not `SELECT *`)
- [ ] **P11-06** Review all admin grid `->display()` closures for hidden N+1 queries
- [ ] **P11-07** Ensure all grids with relationships use `->with()` eager loading

### 11.3 CSV Export Optimization
- [ ] **P11-08** Ensure all admin grid CSV exports output clean text (no HTML) — already done for SubscriptionController
- [ ] **P11-09** Review MovieModel grid export for HTML in output
- [ ] **P11-10** Review MovieViewController grid export for HTML in output

---

## PHASE 12: MYSQL SERVER-LEVEL TUNING

If you have access to MySQL configuration (my.cnf/my.ini):

- [ ] **P12-01** Set `innodb_buffer_pool_size` to 50-70% of available RAM (currently ~1GB available, set to 512M-700M)
- [ ] **P12-02** Set `query_cache_type = 1` and `query_cache_size = 64M` (if MySQL 5.7; removed in 8.0)
- [ ] **P12-03** Set `innodb_log_file_size = 128M` (improves write performance)
- [ ] **P12-04** Set `max_connections = 50` (shared hosting may limit this)
- [ ] **P12-05** Enable slow query log: `slow_query_log = 1`, `long_query_time = 1` (finds queries > 1 second)
- [ ] **P12-06** Set `join_buffer_size = 2M` (improves JOIN performance)
- [ ] **P12-07** Set `sort_buffer_size = 2M` (improves ORDER BY performance)

> **NOTE:** On shared hosting, most MySQL settings may be managed by the host. Check cPanel for MySQL optimization options.

---

## PHASE 13: MONITORING & ONGOING MAINTENANCE

- [ ] **P13-01** Set up UptimeRobot or similar for endpoint monitoring (free tier)
- [ ] **P13-02** Create a `/health` endpoint that returns DB connection status + response time
- [ ] **P13-03** Monitor slow queries weekly via MySQL slow query log
- [ ] **P13-04** Review `storage/logs/` weekly for error patterns
- [ ] **P13-05** Set up alerting for CPU >80% on hosting panel
- [ ] **P13-06** Create deployment checklist that includes `php artisan optimize` and `composer install --no-dev`

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

| Status | Count |
|--------|-------|
| Not Started `[ ]` | **176** |
| In Progress `[~]` | 0 |
| Completed `[x]` | 11 |
| Blocked `[!]` | 0 |
| **TOTAL** | **187** |

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
