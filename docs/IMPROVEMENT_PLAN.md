# Katogo Platform — Master Improvement Plan

> **Author:** System audit, June 2026
> **Backend:** `/Applications/MAMP/htdocs/katogo` (local) · `movies.mruodel.com` (Namecheap production)
> **Testing server:** Hetzner VPS `munoapp.store` / `91.98.42.156`

---

## ⚠ PRODUCTION SYSTEM — READ BEFORE TOUCHING ANYTHING

This platform has **real users, real subscriptions, and real payment transactions** processed
daily through Pesapal and Flutterwave. The Namecheap server (`movies.mruodel.com`, `209.74.87.69`)
is live production. **Never run destructive operations, schema changes, or bulk data writes
against production.**

**All testing, staging, and experimentation runs on the Hetzner VPS only:**
- Host: `munoapp.store` / `ssh hetzner-katogo` / `91.98.42.156`
- DB: `katogo_3` on Hetzner MySQL (separate from production)

**Dummy test data rules:**
- Every test record must be prefixed: username/name/title → `TEST_`
- Add `is_test = 1` flag wherever the schema allows
- Minimum **50 records per entity** for any meaningful test
- All test records must be bulk-deletable without touching real rows:
  ```sql
  DELETE FROM admin_users WHERE username LIKE 'TEST_%';
  DELETE FROM subscriptions WHERE ip_address = '127.0.0.1' AND user_id IN (SELECT id FROM admin_users WHERE username LIKE 'TEST_%');
  ```

---

## 1. System Architecture — What Exists Today

### The 5-App Family

| App | Server | app_type | Status |
| --- | ------ | -------- | ------ |
| LugaFlix | `movies.mruodel.com` | `lugaflix` | Live, production |
| UGFlix | `movies.mruodel.com` | `ugflix` | Live, production |
| Muno | `movies.mruodel.com` | `muno_app` | Live, production |
| Katogo | `movies.mruodel.com` | `katogo` | Built, needs Firebase/logo/signing |
| VJ Junior | `movies.mruodel.com` | `vjjunior` | Built, needs Firebase/logo/signing |

All 5 apps share **one backend, one database, one API**. `app_type` is the differentiator.

### Backend Stack

- Laravel 10, PHP 8.x, MySQL (`katogo_3`)
- JWT auth (5-year TTL) via `tymon/jwt-auth`
- Admin panel via `encore/laravel-admin` at `/admin`
- Queue: Laravel queue worker (Namecheap: cron-kept, no supervisor)
- 387 Dart files, 195 Flutter screens, 140+ migrations
- Video storage: Hetzner StorageShare (WebDAV) + Firebase
- Payments: Pesapal (card/bank) + Flutterwave MoMo (auto captcha solve working)
- Jobs: `SolveFLWCaptchaJob`, `TransferMovieToHetzner`, `AutoFixMovie`, `PushUrlChangeToOrigin`
- Sync: `SyncExportService` (runs on Namecheap) + `SyncPullService` (runs on Hetzner) — both fully built

### What Is Working Well

- Payment flow end-to-end (Pesapal + FLW MoMo captcha auto-solve)
- Movie crawler pipeline (MunoWatch + NamzEntertainment)
- Video transfer system (Namecheap → Hetzner StorageShare)
- Support ticket system
- Movie moderation/reporting system
- Games (offline: Ludo, Checkers, Chess, Matatu)
- Safe mode (parental controls)
- Dating/social profile layer (screens exist)
- DB sync system (fully coded, needs Hetzner .env + migration)

### What Is Incomplete or Broken

- Hetzner `.env` not configured → migrations not run → sync not live
- Online multiplayer games disabled server-side (503 for all game routes)
- Katogo + VJ Junior apps need logos, Firebase registration, OneSignal, signing keys
- UGFlix + Muno missing 12 synced files from LugaFlix (listed in Section 7)
- No rate limiting per `app_type` (all apps hit same throttle bucket)
- No queue supervisor on Namecheap (cron-kept, fragile)
- `FreeTrialTestController` and test routes still live in `api.php` (should be removed)
- `MigrationController` route still live in `api.php` (security risk — remove immediately)

---

## 2. Immediate Security Fixes (Do These First)

### 2.1 Remove Dangerous Open Routes

In `routes/api.php`, two routes expose destructive functionality with no auth:

```php
// REMOVE THESE — they exist in api.php and must be deleted:
Route::post('run-migration', [MigrationController::class, 'runMigration']);
Route::get('test-free-trial/{user_id?}', ...);
Route::get('test-auto-assignment/{user_id?}', ...);
Route::get('test-free-trial-plan', ...);
Route::get('test-free-trial-stats', ...);
Route::delete('test-free-trial-cleanup/{user_id?}', ...);
```

### 2.2 Webhook Signature Verification

Flutterwave webhook at `POST /api/subscriptions/flutterwave/webhook` must verify the
`verif-hash` header. Confirm this is implemented in `SubscriptionApiController::flutterwaveWebhook()`.
Pesapal IPN similarly must validate the `OrderMerchantReference` before trusting the payload.

### 2.3 Namecheap Queue Worker — Add Supervisor

The current worker is kept alive by cron (`php artisan queue:work --stop-when-empty`).
If the cron misses a cycle, `SolveFLWCaptchaJob` will sit queued and MoMo payments
will not auto-push. Add a proper process manager or use a long-running worker with
`--max-time=3600` in the cron to ensure restarts.

---

## 3. Payment & Money UX Improvements

### 3.1 UGX Amount Display

**Current:** Amount shown as `50000` (raw number).
**Fix:** Format consistently across all screens as `UGX 50,000` or `UGX 50K`.

Dart utility to add to `lib/utils/AppConfig.dart`:
```dart
static String formatUGX(dynamic amount) {
  final n = double.tryParse(amount.toString()) ?? 0;
  if (n >= 1000000) return 'UGX ${(n / 1000000).toStringAsFixed(1)}M';
  if (n >= 1000) return 'UGX ${(n / 1000).toStringAsFixed(0)}K';
  return 'UGX ${n.toStringAsFixed(0)}';
}
```

### 3.2 Payment Journey Friction Points

| Step | Current State | Improvement |
| ---- | ------------- | ----------- |
| Plan selection | Plans listed, no comparison UI | Add side-by-side plan card with feature checklist |
| Payment method | Text dropdown | Visual card with MTN/Airtel/Visa logos |
| MoMo initiation | Shows "processing" | Show countdown timer "USSD push sent, check your phone (60s)" |
| USSD timeout | No UX | Auto-retry button + support chat shortcut |
| Pesapal redirect | Opens webview | Add loading shimmer before webview loads |
| Post-payment | User lands back on app | Show animated "Subscription Activated!" celebration screen |
| Receipt | None | Send push notification + in-app receipt screen |

### 3.3 Subscription Grace Period UX

Currently grace period logic exists in the backend but is not communicated clearly in the app.

**Add to Flutter:** A persistent banner that appears during the grace period:
```
⏰ Your subscription expired 2 days ago.
   You have 1 day left to renew without losing access.
   [Renew Now]
```

### 3.4 Failed Payment Recovery

When `SolveFLWCaptchaJob` exhausts retries and marks `CaptchaFailed`, the user
currently sees no actionable UI. Add a "Retry Payment" button on the subscription
status screen that calls `POST /api/subscriptions/{id}/regenerate-link`.

---

## 4. UI/UX — Consistency, Polish, and Hand-Crafted Feel

### 4.1 Current Inconsistencies Found

| Issue | Location | Fix |
| ----- | -------- | --- |
| Mixed loading states | Various screens | Standardize on `shimmer` package skeleton loaders |
| Inconsistent error messages | API error handlers | Create a single `ErrorWidget` component |
| No empty state illustrations | Watchlist, Downloads, History | Add illustrated empty states per screen |
| Abrupt screen transitions | Navigation throughout | Add hero transitions and slide animations |
| Status bar color varies | Auth vs main screens | Standardize overlay style in `app_theme.dart` |
| Button styles inconsistent | Auth vs subscription vs settings | Create `AppButton` widget with variants |
| No haptic feedback | Like, subscribe, play actions | Add `HapticFeedback.mediumImpact()` on key actions |
| Font inconsistency | Some screens use system font | Lock to one Google Font (suggest Nunito or Poppins) |

### 4.2 Screen-by-Screen Polish Plan

**Home Screen (`full_app.dart`):**
- Add featured content hero banner with auto-scroll every 5s
- Add "Continue Watching" rail showing in-progress movies with progress bar
- Add "Trending Today" section (backend already has `trending_notifications`)
- Add personalized "Because You Watched X" rail (query by genre)

**Movie Detail Screen (`MovieDetailScreen.dart`):**
- Add a sticky bottom bar with [Watch] [Download] [Add to List] buttons
- Show cast/actors from `actor` field in `MovieModel`
- Add related movies horizontal scroll at bottom
- Add user rating (1-5 stars) — needs new `movie_ratings` table

**Video Player:**
- Add double-tap to seek ±10s with visual overlay
- Add long-press to enter 1.5x/2x speed
- Add subtitle support (needs subtitle URL field in `MovieModel`)
- Add "Next Episode" overlay that auto-plays after 5s for series
- Lock orientation to landscape on play

**Subscription Plans Screen (`SubscriptionPlansScreen.dart`):**
- Replace text list with feature comparison cards
- Highlight most popular plan with a badge
- Show savings badge on annual plans ("Save 40%")

**Profile Screen:**
- Show progress ring for profile completeness (like LinkedIn)
- Show subscription status badge prominently
- Add quick-access buttons for Download History, Ratings, Watchlist

**Auth Screens:**
- Add "Continue with Google" as first/primary option
- Add social proof below ("Join 10,000+ Ugandan movie lovers")
- Remove fields not needed for auto-accounts (streamline the flow)

### 4.3 New Flutter Packages to Add

| Package | Purpose | Justification |
| ------- | ------- | ------------- |
| `shimmer` | Skeleton loading states | Replace all CircularProgressIndicator spinners |
| `lottie` | Animated illustrations (empty states, success) | Add personality and polish |
| `google_fonts` | Consistent typography | Lock to Poppins or Nunito across all screens |
| `flutter_animate` | Micro-animations on cards, transitions | Professional feel |
| `cached_network_image` (already in?) | Optimized image loading with cache | Reduce image load flicker |
| `share_plus` | Share movie link to WhatsApp, Telegram | Viral growth for Ugandan market |
| `flutter_rating_bar` | Star ratings for movies | User engagement + data collection |
| `flutter_local_notifications` | Scheduled local notifications | Subscription expiry reminders |
| `connectivity_plus` | Detect offline/weak connection | Show quality warning before streaming |
| `in_app_review` | Google Play review prompt | Prompt after positive events (first movie completed) |
| `flutter_staggered_animations` | List item entry animations | Polished grid/list reveals |
| `video_player` + `chewie` | Enhanced player controls | If current player lacks quality control |
| `hive` or `drift` | Offline-first local DB | Cache movie list, watchlist for offline browsing |

---

## 5. Database Schema Enhancements — 360-Degree User Profiling

The `admin_users` table is the primary user table (User extends Administrator).
The app already has dating screens (`lib/screens/dating/`). The schema needs to
catch up to what the frontend has already built.

### 5.1 Professional Profile Fields

```sql
-- Migration: 2026_07_01_000001_add_professional_profile_to_admin_users.php
ALTER TABLE `admin_users`
  ADD COLUMN `job_title`        VARCHAR(120)  NULL AFTER `bio`,
  ADD COLUMN `employer`         VARCHAR(150)  NULL,
  ADD COLUMN `industry`         VARCHAR(100)  NULL,
  ADD COLUMN `linkedin_url`     VARCHAR(255)  NULL,
  ADD COLUMN `professional_bio` TEXT          NULL,
  ADD COLUMN `skills`           JSON          NULL   COMMENT 'Array of skill strings',
  ADD COLUMN `experience_years` TINYINT       NULL,
  ADD COLUMN `education_level`  VARCHAR(60)   NULL   COMMENT 'High School/Certificate/Diploma/Degree/Masters/PhD';
```

### 5.2 Social & Dating Layer Fields

```sql
-- Migration: 2026_07_01_000002_add_dating_profile_to_admin_users.php
ALTER TABLE `admin_users`
  ADD COLUMN `relationship_status`  ENUM('Single','Taken','Married','Divorced','Widowed','Complicated') NULL,
  ADD COLUMN `date_of_birth`        DATE          NULL,
  ADD COLUMN `age`                  TINYINT       NULL  COMMENT 'Computed from dob, cached',
  ADD COLUMN `district`             VARCHAR(80)   NULL  COMMENT 'Uganda district (Kampala, Wakiso, etc.)',
  ADD COLUMN `region`               VARCHAR(60)   NULL  COMMENT 'Central, Eastern, Northern, Western',
  ADD COLUMN `nationality`          VARCHAR(60)   NULL  DEFAULT 'Ugandan',
  ADD COLUMN `ethnicity`            VARCHAR(60)   NULL,
  ADD COLUMN `religion`             VARCHAR(60)   NULL,
  ADD COLUMN `height_cm`            SMALLINT      NULL,
  ADD COLUMN `body_type`            VARCHAR(40)   NULL,
  ADD COLUMN `short_bio`            VARCHAR(300)  NULL  COMMENT 'Public-facing dating bio',
  ADD COLUMN `interests`            JSON          NULL  COMMENT 'Array: [\"Football\",\"Movies\",\"Travel\"]',
  ADD COLUMN `preferred_genres`     JSON          NULL  COMMENT 'Favourite movie genres',
  ADD COLUMN `preferred_languages`  JSON          NULL  COMMENT '[\"Luganda\",\"English\"]',
  ADD COLUMN `looking_for`          VARCHAR(60)   NULL  COMMENT 'Friendship/Dating/Networking',
  ADD COLUMN `age_preference_min`   TINYINT       NULL,
  ADD COLUMN `age_preference_max`   TINYINT       NULL,
  ADD COLUMN `profile_photo_url`    VARCHAR(500)  NULL  COMMENT 'Public profile photo (distinct from avatar)',
  ADD COLUMN `profile_visible`      TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '0 = private profile',
  ADD COLUMN `profile_complete_pct` TINYINT       NOT NULL DEFAULT 0 COMMENT '0-100 completion %';
```

### 5.3 Engagement Analytics Fields

```sql
-- Migration: 2026_07_01_000003_add_engagement_analytics_to_admin_users.php
ALTER TABLE `admin_users`
  ADD COLUMN `total_watch_minutes`    INT          NOT NULL DEFAULT 0,
  ADD COLUMN `total_movies_watched`   INT          NOT NULL DEFAULT 0,
  ADD COLUMN `total_series_completed` INT          NOT NULL DEFAULT 0,
  ADD COLUMN `average_completion_pct` DECIMAL(5,2) NOT NULL DEFAULT 0,
  ADD COLUMN `favourite_genre`        VARCHAR(80)  NULL,
  ADD COLUMN `favourite_vj`           VARCHAR(80)  NULL,
  ADD COLUMN `last_watched_movie_id`  INT          NULL,
  ADD COLUMN `watch_streak_days`      INT          NOT NULL DEFAULT 0,
  ADD COLUMN `last_active_at`         TIMESTAMP    NULL,
  ADD COLUMN `total_coins`            INT          NOT NULL DEFAULT 0 COMMENT 'Game coins balance, redundant with game_coins_balance for quick access';
```

### 5.4 Device & UX Preference Fields

```sql
-- Migration: 2026_07_01_000004_add_ux_preferences_to_admin_users.php
ALTER TABLE `admin_users`
  ADD COLUMN `preferred_quality`      ENUM('auto','240p','480p','720p','1080p') NOT NULL DEFAULT 'auto',
  ADD COLUMN `data_saver_mode`        TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN `parental_pin`           VARCHAR(6)   NULL    COMMENT 'Hashed 4-digit PIN for safe mode',
  ADD COLUMN `subtitle_language`      VARCHAR(10)  NULL    DEFAULT 'en',
  ADD COLUMN `autoplay_next`          TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN `push_subscriptions`     TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN `push_new_movies`        TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN `push_game_invites`      TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN `push_expiry_reminders`  TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN `preferred_payment`      VARCHAR(30)  NULL    COMMENT 'pesapal|flutterwave|mtn|airtel',
  ADD COLUMN `content_language`       VARCHAR(10)  NULL    DEFAULT 'lg' COMMENT 'lg=Luganda en=English';
```

### 5.5 New Table: `movie_ratings`

```sql
-- Migration: 2026_07_01_000005_create_movie_ratings_table.php
CREATE TABLE `movie_ratings` (
  `id`         INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT            NOT NULL,
  `movie_id`   INT            NOT NULL,
  `rating`     TINYINT        NOT NULL COMMENT '1-5 stars',
  `review`     TEXT           NULL,
  `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `user_movie` (`user_id`, `movie_id`),
  KEY `movie_id` (`movie_id`),
  KEY `rating` (`rating`)
);
```

### 5.6 New Table: `user_activity_logs`

For granular engagement analytics (not the same as `admin_operation_log`):

```sql
-- Migration: 2026_07_01_000006_create_user_activity_logs_table.php
CREATE TABLE `user_activity_logs` (
  `id`         BIGINT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT            NOT NULL,
  `action`     VARCHAR(50)    NOT NULL COMMENT 'movie_play|movie_complete|search|download|like|rate',
  `entity_type`VARCHAR(30)    NULL     COMMENT 'movie|series|game',
  `entity_id`  INT            NULL,
  `duration_s` INT            NULL     COMMENT 'Seconds watched (for play events)',
  `app_type`   VARCHAR(30)    NULL,
  `meta`       JSON           NULL     COMMENT 'Extra context (quality, position, etc.)',
  `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB ROW_FORMAT=COMPRESSED;
```

### 5.7 New Table: `subtitle_files`

```sql
CREATE TABLE `subtitle_files` (
  `id`         INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `movie_id`   INT            NOT NULL,
  `language`   VARCHAR(10)    NOT NULL DEFAULT 'en',
  `label`      VARCHAR(50)    NULL     COMMENT 'English, Luganda, etc.',
  `url`        VARCHAR(500)   NOT NULL COMMENT 'VTT/SRT file URL',
  `is_default` TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `movie_id` (`movie_id`)
);
```

---

## 6. New Backend Packages to Add

| Package | Install | Purpose |
| ------- | ------- | ------- |
| `laravel/horizon` | `composer require laravel/horizon` | Queue monitoring dashboard at `/horizon` — see job throughput, failures, retry queue in real time |
| `spatie/laravel-query-builder` | `composer require spatie/laravel-query-builder` | Clean API filtering, sorting, including — replaces ad-hoc query params |
| `spatie/laravel-activitylog` | `composer require spatie/laravel-activitylog` | Track model changes with who/when/what — great for admin audit trail |
| `spatie/laravel-responsecache` | `composer require spatie/laravel-responsecache` | Cache entire API responses (movie listing, plans) — cuts DB load dramatically |
| `meilisearch/meilisearch-php` + `laravel/scout` | `composer require laravel/scout` | Full-text movie search — far better than `LIKE %q%` |
| `barryvdh/laravel-telescope` | `composer require barryvdh/laravel-telescope --dev` | Request/response inspector for debugging API issues on Hetzner |
| `spatie/laravel-health` | `composer require spatie/laravel-health` | `/health` endpoint showing queue, DB, cache, disk status — connect to monitoring |
| `stevebauman/location` | `composer require stevebauman/location` | IP → Uganda district/region auto-detection on registration |

---

## 7. Pending Sync: LugaFlix → UGFlix + Muno

These 12 files were updated in LugaFlix but not yet synced to the child apps.
UGFlix and Muno use the same Dart package name (`ugflix`), so no import rewriting is needed.

```bash
PARENT="/Users/mac/Desktop/github/lugaflix"
UGFLIX="/Users/mac/Desktop/github/luganda-translated-movies-mobo"
MUNO="/Users/mac/Desktop/github/muno-app-free-luganda-movies"

FILES=(
  "lib/services/auto_account_service.dart"
  "lib/services/download_service.dart"
  "lib/utils/Utilities.dart"
  "lib/utils/app_theme.dart"
  "lib/screens/auth/login_screen.dart"
  "lib/services/google_auth_service.dart"
  "lib/screens/splash_screen.dart"
  "lib/screens/streaming/TVPlayerScreen.dart"
  "lib/screens/games/ludo/services/ludo_multiplayer_service.dart"
  "lib/services/unified_game_invitation_service.dart"
  "lib/screens/auth/profile_completion_wizard_screen.dart"
  "lib/models/MovieModel.dart"
)

for f in "${FILES[@]}"; do
  cp "$PARENT/$f" "$UGFLIX/$f" && echo "✓ UGFlix: $f"
  cp "$PARENT/$f" "$MUNO/$f"   && echo "✓ Muno: $f"
done
```

---

## 8. Future Planned Work — Detailed Approach

### 8.1 Hetzner VPS — Remaining Steps

Two steps remain before the Hetzner server can serve live traffic:

**Step 1: Configure `.env` on Hetzner**
```bash
ssh hetzner-katogo
cp /var/www/katogo/.env.example /var/www/katogo/.env
nano /var/www/katogo/.env
# Fill in: DB_PASSWORD, JWT_SECRET, PESAPAL_*, FLUTTERWAVE_*, ONESIGNAL_*, HETZNER_STORAGE_*
php artisan key:generate
php artisan jwt:secret
```

**Step 2: Run migrations**
```bash
ssh hetzner-katogo
cd /var/www/katogo
php artisan migrate --force
php artisan db:seed --class=SyncCursorSeeder  # seeds db_sync_cursors
```

**Step 3: Start DB sync** (after `.env` and migrations)
```bash
php artisan sync:pull --force  # First full sync from Namecheap
```

### 8.2 Online Multiplayer Games — Re-enable

Games are currently returning 503 for all routes (resource optimization).
The backend code is complete. To re-enable:

1. Remove the 503 stub block in `routes/api.php` (the `$gameDisabled` closure)
2. Uncomment the original game routes below it
3. Add `throttle:game` middleware rate limit in `config/app.php`
4. Test on Hetzner first (game sessions, ludo, checkers)

### 8.3 Katogo + VJ Junior — Launch Checklist

For each app:

```
[ ] Replace assets/images/logo.png with actual brand logo
[ ] Register Firebase app (Android + iOS) in project ugnews24-bd189
[ ] Download and replace google-services.json + GoogleService-Info.plist
[ ] Create OneSignal app → update ONESIGNAL_APP_ID in AppConfig.dart
[ ] Generate Android signing keystore → create android/key.properties
[ ] Register app on Google Play Console with correct package name
[ ] Configure backend: ensure katogo/vjjunior app_type returns correct subscription plans
[ ] Test full flow on Hetzner before Play Store submission
```

### 8.4 Subtitle System

1. Create `subtitle_files` migration (Section 5.7)
2. Add `subtitle_url` field to `MovieModel` (for single-subtitle movies)
3. Add subtitle admin panel controller to upload VTT files
4. In Flutter player, load subtitle track from API response
5. Add subtitle language picker in video player controls

### 8.5 Rating System

1. Create `movie_ratings` migration (Section 5.5)
2. Add `avg_rating` and `rating_count` columns to `movie_models`
3. Add `POST /api/movies/{id}/rate` endpoint
4. In Flutter `MovieDetailScreen`, show stars + write review
5. Show average rating on movie cards

### 8.6 Push Notification Improvements

Current: OneSignal sends trending notifications from `TrendingNotification` model.

**Add:**
- Subscription expiry reminder (3 days, 1 day before)
- "New movie added in your favourite genre"
- "Your friend rated X movie" (when social connections are added)
- Payment confirmation push on successful subscription activation
- Deep link on notification tap → go directly to the movie/subscription screen

---

## 9. Phased Execution Task List

### Phase 1 — Foundation & Security

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 1.1 | Remove `run-migration` and test routes from `api.php` | CRITICAL | `[ ]` | 30 min | Backend |
| 1.2 | Configure Hetzner `.env` with production values | CRITICAL | `[ ]` | 1h | Hetzner |
| 1.3 | Run `php artisan migrate --force` on Hetzner | CRITICAL | `[ ]` | 30 min | Hetzner |
| 1.4 | Seed `db_sync_cursors` and run first sync pull | HIGH | `[ ]` | 2h | Hetzner |
| 1.5 | Add proper queue supervisor on Namecheap | HIGH | `[ ]` | 1h | Namecheap |
| 1.6 | Verify FLW webhook signature verification is in place | HIGH | `[ ]` | 1h | Backend |
| 1.7 | Move MySQL data dir to Hetzner volume (optional, safe) | MEDIUM | `[ ]` | 2h | Hetzner |

### Phase 2 — Payments & Subscriptions

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 2.1 | Add `formatUGX()` utility to AppConfig + apply throughout | HIGH | `[ ]` | 2h | LugaFlix |
| 2.2 | Build plan comparison cards on SubscriptionPlansScreen | HIGH | `[ ]` | 4h | LugaFlix |
| 2.3 | Add MoMo countdown timer "Check your phone" screen | HIGH | `[ ]` | 3h | LugaFlix |
| 2.4 | Add grace period banner widget | HIGH | `[ ]` | 2h | LugaFlix |
| 2.5 | Add post-payment celebration/receipt screen | MEDIUM | `[ ]` | 3h | LugaFlix |
| 2.6 | Add payment method visual cards (MTN/Airtel/Visa logos) | MEDIUM | `[ ]` | 2h | LugaFlix |
| 2.7 | Push notification on subscription activation | MEDIUM | `[ ]` | 2h | Backend |
| 2.8 | Add subscription expiry reminder push (3d, 1d) | MEDIUM | `[ ]` | 3h | Backend |

### Phase 3 — Mobile Sync

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 3.1 | Sync 12 files from LugaFlix → UGFlix + Muno | HIGH | `[ ]` | 2h | UGFlix, Muno |
| 3.2 | Commit UGFlix after sync | HIGH | `[ ]` | 30 min | UGFlix |
| 3.3 | Commit Muno after sync | HIGH | `[ ]` | 30 min | Muno |
| 3.4 | Replace Katogo logo, Firebase, OneSignal, signing key | HIGH | `[ ]` | 3h | Katogo |
| 3.5 | Replace VJ Junior logo, Firebase, OneSignal, signing key | HIGH | `[ ]` | 3h | VJJunior |
| 3.6 | Test Katogo full flow on Hetzner | HIGH | `[ ]` | 2h | Katogo |
| 3.7 | Test VJ Junior full flow on Hetzner | HIGH | `[ ]` | 2h | VJJunior |

### Phase 4 — UI/UX Polish

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 4.1 | Add `shimmer`, `lottie`, `flutter_animate` packages | HIGH | `[ ]` | 1h | LugaFlix |
| 4.2 | Replace all spinners with shimmer skeleton loaders | HIGH | `[ ]` | 4h | LugaFlix |
| 4.3 | Add hero transitions between movie card → detail | MEDIUM | `[ ]` | 3h | LugaFlix |
| 4.4 | Lock typography to Poppins (add `google_fonts`) | MEDIUM | `[ ]` | 2h | LugaFlix |
| 4.5 | Add illustrated empty states (watchlist, downloads, history) | MEDIUM | `[ ]` | 4h | LugaFlix |
| 4.6 | Add "Continue Watching" rail to home screen | HIGH | `[ ]` | 4h | LugaFlix |
| 4.7 | Add featured hero banner with auto-scroll | MEDIUM | `[ ]` | 3h | LugaFlix |
| 4.8 | Add double-tap seek ±10s in video player | HIGH | `[ ]` | 2h | LugaFlix |
| 4.9 | Add "Next Episode" auto-play overlay for series | HIGH | `[ ]` | 3h | LugaFlix |
| 4.10 | Add profile completeness ring to profile screen | MEDIUM | `[ ]` | 2h | LugaFlix |
| 4.11 | Add haptic feedback on key actions | LOW | `[ ]` | 1h | LugaFlix |
| 4.12 | Standardize status bar colour across all screens | MEDIUM | `[ ]` | 2h | LugaFlix |
| 4.13 | Sync all Phase 4 changes → UGFlix, Muno, Katogo, VJ Junior | HIGH | `[ ]` | 3h | All Apps |

### Phase 5 — User Profiling & Engagement

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 5.1 | Run 360-degree profile migrations (Sections 5.1–5.4) | HIGH | `[ ]` | 2h | DB |
| 5.2 | Create `movie_ratings` table and API endpoints | HIGH | `[ ]` | 3h | Backend |
| 5.3 | Add star rating widget to MovieDetailScreen | HIGH | `[ ]` | 2h | LugaFlix |
| 5.4 | Add rating count + average to movie cards | MEDIUM | `[ ]` | 2h | LugaFlix |
| 5.5 | Create `user_activity_logs` table (Section 5.6) | MEDIUM | `[ ]` | 1h | DB |
| 5.6 | Log watch events (play, pause, complete) to activity log | MEDIUM | `[ ]` | 3h | Backend |
| 5.7 | Build `GET /api/me/recommendations` endpoint (genre-based) | MEDIUM | `[ ]` | 4h | Backend |
| 5.8 | Add "Because You Watched" personalised rail to home | MEDIUM | `[ ]` | 2h | LugaFlix |
| 5.9 | Add professional profile fields to profile wizard | LOW | `[ ]` | 3h | LugaFlix |
| 5.10 | Connect Uganda district dropdown to stevebauman/location | LOW | `[ ]` | 2h | Backend |

### Phase 6 — Content, Search & Discovery

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 6.1 | Install Laravel Scout + Meilisearch on Hetzner | HIGH | `[ ]` | 3h | Hetzner |
| 6.2 | Index `movie_models` into Meilisearch | HIGH | `[ ]` | 2h | Backend |
| 6.3 | Replace `LIKE %q%` search with Scout search | HIGH | `[ ]` | 2h | Backend |
| 6.4 | Add search suggestions/autocomplete in Flutter | MEDIUM | `[ ]` | 3h | LugaFlix |
| 6.5 | Create `subtitle_files` table and upload admin UI | MEDIUM | `[ ]` | 4h | Backend |
| 6.6 | Add subtitle track support in Flutter video player | MEDIUM | `[ ]` | 4h | LugaFlix |
| 6.7 | Add `share_plus` — share movie link to WhatsApp | MEDIUM | `[ ]` | 2h | LugaFlix |
| 6.8 | Add in-app review prompt after first movie completed | LOW | `[ ]` | 1h | LugaFlix |

### Phase 7 — Games & Community

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 7.1 | Re-enable online multiplayer game routes (remove 503 stub) | MEDIUM | `[ ]` | 1h | Backend |
| 7.2 | Test online Ludo on Hetzner with 50 dummy game sessions | MEDIUM | `[ ]` | 3h | Hetzner |
| 7.3 | Add game coin purchase via subscription (reward model) | LOW | `[ ]` | 4h | Backend |
| 7.4 | Add leaderboard screen to Flutter games section | LOW | `[ ]` | 3h | LugaFlix |
| 7.5 | Add dating profile screens to profile wizard | LOW | `[ ]` | 5h | LugaFlix |
| 7.6 | Connect UsersListScreen to real user discovery API | LOW | `[ ]` | 4h | Backend |

### Phase 8 — Ops, DevOps & Monitoring

| # | Task | Priority | Status | Effort | Affects |
| - | ---- | -------- | ------ | ------ | ------- |
| 8.1 | Install Laravel Horizon on Hetzner | HIGH | `[ ]` | 2h | Hetzner |
| 8.2 | Install Laravel Telescope on Hetzner (dev-only) | MEDIUM | `[ ]` | 1h | Hetzner |
| 8.3 | Add `spatie/laravel-health` `/health` endpoint | MEDIUM | `[ ]` | 2h | Backend |
| 8.4 | Set up UptimeRobot or BetterUptime monitoring | MEDIUM | `[ ]` | 1h | Ops |
| 8.5 | Disable password SSH auth on Hetzner (key-only) | HIGH | `[ ]` | 30 min | Hetzner |
| 8.6 | Add `spatie/laravel-responsecache` for listing endpoints | MEDIUM | `[ ]` | 2h | Backend |
| 8.7 | Set up nightly DB backup on Hetzner → StorageShare | HIGH | `[ ]` | 2h | Hetzner |
| 8.8 | Configure Namecheap queue supervisor (not cron-based) | HIGH | `[ ]` | 1h | Namecheap |

---

## 10. Testing Protocol

For every task above, before marking `[x]`:

1. **On Hetzner only** — never test destructive operations on `movies.mruodel.com`
2. **Create 50+ test records** for any feature that involves data:
   ```sql
   -- Example: 50 test users
   INSERT INTO admin_users (name, username, email, password, app_type created_at)
   SELECT
     CONCAT('TEST_User_', n),
     CONCAT('TEST_user_', n),
     CONCAT('test_', n, '@test.katogo.internal'),
     '$2y$12$dummyhashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
     'lugaflix',
     NOW()
   FROM (SELECT 1 n UNION SELECT 2 UNION ... UNION SELECT 50) t;
   ```
3. **Test both happy path and edge cases:**
   - Payment: successful, failed, timed out, webhook delayed
   - Player: slow network, network drop, back navigation mid-play
   - Subscription: active, expired, grace period, cancelled
4. **Bulk delete test data after test:**
   ```sql
   DELETE FROM admin_users WHERE username LIKE 'TEST_%';
   -- Delete cascading related records before or use ON DELETE CASCADE
   ```
