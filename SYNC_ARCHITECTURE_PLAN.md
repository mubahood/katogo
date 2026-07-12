# Katogo Two-Server Sync Architecture
## Complete Design, Implementation & Deployment Plan

**Document version:** 2026-07-12  
**Author:** Engineering  
**Status:** Plan — pending implementation sign-off

---

## 1. System Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         LIVE PRODUCTION                                 │
│           movies.mruodel.com  ·  209.74.87.69  (Namecheap VPS)         │
│                                                                         │
│  Laravel app  •  MySQL katogo_3  •  APP_ENV=production                  │
│  SYNC_ROLE=source   SYNC_ENABLED=false  (never pulls, only serves)      │
└──────────────────────────────┬──────────────────────────────────────────┘
                               │
                ① SSH tunnel (port 13306)
                   Hetzner → Namecheap MySQL
                   (read-only pull, every 5 min)
                               │
                ② URL-change push (HTTP POST)
                   munoapp.store → movies.mruodel.com/api/movie-url-sync
                   (when Hetzner transfers finish)
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       BACKUP / DEBUG SERVER                             │
│             munoapp.store  ·  91.98.42.156  (Hetzner VPS)              │
│                                                                         │
│  Laravel app  •  MySQL katogo_3  •  APP_ENV=production                  │
│  SYNC_ROLE=replica  SYNC_ENABLED=true   (pulls every 5 min)             │
│  30 Supervisor workers  •  Cron scheduler  •  Transfer pipeline         │
└─────────────────────────────────────────────────────────────────────────┘
                               │
                               ▼
                ③ Hetzner Storage (Nextcloud 32)
                   nx100800.your-storageshare.de
                   MP4 files  •  public CDN URLs
```

### Non-negotiable rules

| Rule | Enforcement |
|------|------------|
| Live → Backup only. Never reverse. | `SYNC_ENABLED=false` on live; observer creates no reverse writes |
| Hetzner video URLs are never overwritten by sync | `reapplyHetznerUrls()` runs at end of every `sync:pull` cycle |
| Backup-specific tables are never synced to live | Strict `ALLOWED_TABLES` allowlist in `SyncExportService` |
| No credentials in git | `.env` and `.vps-credentials` in `.gitignore` |

---

## 2. Current State Audit

### 2.1 What Is Already Built (do NOT rebuild)

| Component | File | Status |
|-----------|------|--------|
| Incremental pull engine | `app/Services/SyncPullService.php` | ✅ Complete |
| Export API service | `app/Services/SyncExportService.php` | ✅ Complete |
| Artisan `sync:pull` command | `app/Console/Commands/SyncPull.php` | ✅ Complete |
| Admin dashboard UI | `app/Admin/Controllers/SyncDashboardController.php` | ✅ Complete |
| Admin dashboard routes | `app/Admin/routes.php` lines 220–223 | ✅ Registered |
| DB tracking models | `app/Models/DbSyncCursor.php` + `DbSyncLog.php` | ✅ Complete |
| Tracking tables migration | `database/migrations/2026_06_12_300000_create_db_sync_tables.php` | ✅ Migrated on live |
| Scheduler entry `sync:pull` | `app/Console/Kernel.php` (guarded by `SYNC_ENABLED`) | ✅ Present |
| Anti-reversal `reapplyHetznerUrls` | `SyncPullService::reapplyHetznerUrls()` | ✅ Complete |
| URL-push endpoint | `app/Http/Controllers/Api/MovieUrlSyncController.php` | ✅ Complete |

### 2.2 What Is Missing (must be added)

| Gap | Priority | Work needed |
|-----|----------|-------------|
| SSH key from Hetzner added to mruodel's `authorized_keys` | CRITICAL | 1 command |
| `SYNC_ENABLED=true` + source config in Hetzner `.env` | CRITICAL | `.env` edit |
| `db_sync_cursors` + `db_sync_logs` tables on Hetzner | CRITICAL | `php artisan migrate` + `sync:pull --seed` |
| HTTP export API endpoint on live server | HIGH | New route + controller |
| Initial full sync (close the data gap) | HIGH | `sync:pull --force` run |
| GitHub Actions CI/CD pipelines | MEDIUM | Two workflow files |
| `SYNC_ROLE` guard in `sync:pull` (belt-and-suspenders) | MEDIUM | Small code addition |

### 2.3 Data Gap Between Servers (as of 2026-07-12)

| Table | Live (source) rows | Backup rows | Gap |
|-------|-------------------|-------------|-----|
| `movie_models` | 64,881 | 57,535 | **7,346** |
| `admin_users` | 89,628 | 82,213 | **7,415** |
| `subscriptions` | 52,240 | 44,662 | **7,578** |
| `subscription_transactions` | 61,548 | 42,840 | **18,708** |
| `movie_views` | 87,752 | 83,198 | **4,554** |
| `customer_tickets` | 67,706 | 64,348 | **3,358** |
| `customer_ticket_records` | 69,187 | 64,190 | **4,997** |

The initial sync will close all these gaps. After that, incremental sync keeps them < 5 minutes behind.

---

## 3. Table Priority Classification

Tables are grouped into four sync tiers. Within each tier, rows are synced incrementally by `id` (new rows) and `updated_at` (modified rows). Pivot tables use full-replace.

### Tier 1 — Users & Payments (every 5 minutes)
These directly affect revenue and user access. Any delay here means a subscriber can't log in or access content they paid for.

| Table | Reason |
|-------|--------|
| `admin_users` | All user accounts (subscribers, admins) |
| `subscriptions` | Current subscription status |
| `subscription_transactions` | Payment records — audit trail |
| `subscription_plans` | Plan definitions (prices, durations) |
| `coin_transactions` | In-app currency records |

### Tier 2 — Content & Engagement (every 5 minutes)
Core movie data and user behaviour. Required for the debug server to behave identically to live.

| Table | Reason |
|-------|--------|
| `movie_models` | All 65K movies — primary content table |
| `series_movies` | Episode records for series |
| `movie_views` | View history — drives recommendations |
| `movie_likes` | Favourites / watchlist signals |
| `movie_downloads` | Download records |
| `movie_wishlists` | User wishlists |
| `movie_requests` | User content requests |
| `movie_searches` | Search logs — analytics |

### Tier 3 — Support & Operations (every 15 minutes)
Support and moderation data. Important but slightly less time-sensitive.

| Table | Reason |
|-------|--------|
| `customer_tickets` | Support ticket headers |
| `customer_ticket_records` | Individual ticket messages |
| `support_audit_logs` | Agent actions audit trail |
| `video_playback_failures` | Playback error reports |
| `content_reports` | User content reports |
| `content_moderation_logs` | Moderation actions |
| `chat_heads` | Direct message threads |
| `chat_messages` | DM messages |
| `movie_crawler_pages` | Crawler state |
| `movie_crawler_websites` | Crawler source config |
| `movie_pics` | Extra movie images |
| `munowatch_categories` | MunoWatch category data |
| `munowatch_movie_categories` | Movie↔category pivot |
| `safemode_views` | Safe-mode view records |

### Tier 4 — Config & Reference (every 60 minutes)
Rarely changes. Low cost to be slightly stale.

| Table | Reason |
|-------|--------|
| `admin_roles` | Permission roles |
| `admin_permissions` | Permission definitions |
| `admin_menu` | Admin nav config |
| `admin_operation_log` | Admin action log |
| `admin_role_users` | Role assignments (pivot) |
| `admin_role_permissions` | Role permissions (pivot) |
| `admin_user_permissions` | Per-user permissions (pivot) |
| `streaming_stations` | Live streaming channels |
| `streaming_urls` | Stream source URLs |
| `pages` | Static content pages |
| `links` | Curated links |
| `schools` | Schools directory |
| `blog_posts` | Blog content |
| `blog_comments` | Blog comments |
| `blog_likes` | Blog reaction pivot |
| `system_configs` | App config overrides |
| `scraper_models` | Scraper config |
| `game_stats` | Game leaderboard stats |
| `trending_notifications` | Push notification records |
| `user_blocks` | Block list |
| `merged_accounts` | Merged account records |
| `page_visits` | Page view analytics |
| `learning_material_categories` | Learning categories |
| `learning_material_posts` | Learning posts |
| `trivia_questions` | Trivia question bank |
| `companies` + `financial_periods` | Financial metadata |
| `stock_*` | Stock management tables |

### Tables Explicitly Excluded (never synced to backup)

| Table | Reason not synced |
|-------|------------------|
| `movie_file_transfers` | Backup-server-only pipeline state |
| `movie_video_url_changes` | URL sync tracking (Hetzner-side) |
| `db_sync_cursors` | Sync bookkeeping itself |
| `db_sync_logs` | Sync log on replica |
| `jobs` / `failed_jobs` | Queue is environment-specific |
| `sessions` / `cache` / `cache_locks` | Server-local runtime state |
| `password_reset_tokens` | Security — never replicate |
| `personal_access_tokens` | Security |
| `health_check_result_history_items` | Local health checks |
| `migrations` | Each server manages its own |

---

## 4. Sync Engine Design

### 4.1 SSH Tunnel Method (Primary)

```
Hetzner (replica)
└─ sync:pull fires
   └─ SyncPullService::openTunnel()
      └─ ssh -N -L 127.0.0.1:13306:127.0.0.1:3306 muhindo@209.74.87.69
         └─ Binds local port 13306 → Namecheap MySQL port 3306
   └─ Configure Laravel connection 'namecheap' → 127.0.0.1:13306
   └─ For each table (ordered by priority):
      ├─ New rows: SELECT * WHERE id > cursor_id ORDER BY id LIMIT 500
      ├─ Updated rows: SELECT * WHERE updated_at > cursor_ts AND id <= cursor_id
      ├─ Upsert: INSERT ... ON DUPLICATE KEY UPDATE all columns except id
      └─ Update cursor: last_synced_id, last_updated_ts
   └─ After movie_models sync: reapplyHetznerUrls()
   └─ closeTunnel()
```

**Why SSH tunnel instead of HTTP API?**
- Direct MySQL access — no Laravel overhead on source
- Reads are atomic at MySQL level
- No auth tokens exposed publicly
- Can read any table including tables without REST endpoints

### 4.2 HTTP Export API (Secondary / Fallback)

For cases where the SSH tunnel is blocked (firewall change, SSH key issue), a read-only HTTP export endpoint serves data from the live server. Authentication uses a pre-shared token (`SYNC_EXPORT_SECRET`).

```
GET /api/internal/sync/export?table=movie_models&cursor_id=50000&limit=500
X-Sync-Export-Secret: {token}

Response:
{
  "rows": [...],
  "next_cursor_id": 50500,
  "next_updated_ts": "2026-07-12 14:30:00",
  "has_more": true,
  "total_rows": 64881
}
```

### 4.3 Dual-Cursor Incremental Sync (anti-miss guarantee)

Each table uses two cursors simultaneously:

1. **ID cursor** (`last_synced_id`): Catches all NEW rows (`id > cursor`)
2. **Timestamp cursor** (`last_updated_ts`): Catches all MODIFIED rows (`updated_at > cursor AND id <= last_synced_id`)

This ensures:
- New rows are always caught
- Updates to already-synced rows are caught
- Deletes on live are NOT propagated (by design — backup is append-only for safety)

### 4.4 Conflict Prevention (anti-reversal)

The most critical conflict: `sync:pull` copies `movie_models.url` from live (which has BunnyCDN/Firebase URLs) and overwrites Hetzner's own Hetzner CDN URLs for transferred movies.

**Resolution (already implemented):** `reapplyHetznerUrls()` runs at the end of every sync cycle:
```sql
UPDATE movie_models mm
INNER JOIN movie_file_transfers mft ON mft.movie_id = mm.id
SET mm.url = mft.dest_url
WHERE mft.status = 'done'
  AND mft.dest_url IS NOT NULL
```

This guarantees that Hetzner CDN URLs are ALWAYS preserved even after a sync overwrites them.

---

## 5. Real-Time Event Push (Lossless Mode)

For Tier 1 tables, a 5-minute sync lag could mean:
- A user subscribes → can't access content for 5 minutes on backup
- A subscription expires → user still has access on backup for 5 minutes

**Solution:** Event observers push critical changes to the backup instantly via HTTP.

### Tables that get real-time push:
- `subscriptions` (created, updated)
- `subscription_transactions` (created)
- `admin_users` (created, updated — for new registrations)

### How it works:
```
Live server: SubscriptionObserver
└─ fires on created/updated
   └─ POST https://munoapp.store/api/internal/sync/receive-event
      Headers: X-Sync-Event-Secret: {token}
      Body: { table: "subscriptions", action: "upsert", row: {...} }
      └─ Backup server: InternalSyncController::receiveEvent()
         └─ INSERT ... ON DUPLICATE KEY UPDATE
         └─ Returns { ok: true, id: 12345 }
```

This is fire-and-forget (dispatched via a queued job so live server is never blocked).

### Anti-reversal on event push:
- The event push endpoint on backup **only accepts** incoming POST (never initiates outbound)
- The endpoint checks `SYNC_ROLE=replica` — returns 403 if called on live server
- The live server only POSTs outbound, never accepts inbound sync

---

## 6. GitHub Deployment Strategy

### 6.1 Repository Setup

```
GitHub: [your-username]/katogo (public repo)
├─ main branch         → triggers deploy to BOTH servers
├─ .gitignore          → .env, .vps-credentials, storage/, vendor/
└─ .github/workflows/
   ├─ deploy-live.yml  → deploys to movies.mruodel.com
   └─ deploy-backup.yml → deploys to munoapp.store
```

### 6.2 What Goes in Git vs Not

```
IN GIT ✅                       NOT IN GIT ❌
─────────────────────────────   ──────────────────────────────
app/                            .env (all servers)
bootstrap/                      .vps-credentials
config/                         storage/logs/
database/migrations/            storage/app/
public/                         vendor/
resources/                      node_modules/
routes/                         storage/framework/cache/
tests/                          .env.example (YES — in git)
```

**`.env.example`** must be kept up-to-date with all variables (values can be empty or defaults — no real credentials).

### 6.3 GitHub Actions: Deploy to Live Server

```yaml
# .github/workflows/deploy-live.yml
name: Deploy to Live (mruodel)
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Deploy to movies.mruodel.com
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: 209.74.87.69
          username: muhindo
          password: ${{ secrets.MRUODEL_SSH_PASSWORD }}
          script: |
            set -e
            cd /home/muhindo/movies

            # Pull latest code
            git fetch origin main
            git diff --name-only HEAD origin/main

            # Put site in maintenance mode
            php artisan down --retry=30

            # Update code (NEVER overwrite .env)
            git reset --hard origin/main

            # Install/update dependencies (no dev)
            composer install --no-dev --no-interaction --optimize-autoloader

            # Run migrations (safe — only additive)
            php artisan migrate --force --no-interaction

            # Clear all caches and rebuild
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache

            # Restore site
            php artisan up

            echo "Deploy to mruodel complete"
```

### 6.4 GitHub Actions: Deploy to Backup Server

```yaml
# .github/workflows/deploy-backup.yml
name: Deploy to Backup (munoapp)
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Deploy to munoapp.store
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: 91.98.42.156
          username: root
          key: ${{ secrets.HETZNER_SSH_PRIVATE_KEY }}
          script: |
            set -e
            cd /var/www/katogo

            # Pull latest code
            git fetch origin main

            # No maintenance mode needed on backup (not live traffic)
            git reset --hard origin/main

            composer install --no-dev --no-interaction --optimize-autoloader

            php artisan migrate --force --no-interaction

            php artisan config:cache
            php artisan route:cache
            php artisan view:cache

            # Restart workers to pick up code changes
            supervisorctl restart katogo-worker:*

            echo "Deploy to munoapp complete"
```

### 6.5 Secrets to Add to GitHub Repository

| Secret name | Value | Where |
|-------------|-------|-------|
| `MRUODEL_SSH_PASSWORD` | `5TOb9k62dOXnKt80yT` | GitHub Secrets |
| `HETZNER_SSH_PRIVATE_KEY` | contents of `~/.ssh/hetzner_katogo` | GitHub Secrets |

### 6.6 First-Time Git Setup on Each Server

**On munoapp.store (Hetzner):**
```bash
cd /var/www/katogo
git remote set-url origin https://github.com/[username]/katogo.git
git fetch origin
git branch -u origin/main main
```

**On movies.mruodel.com (Namecheap):**
```bash
cd /home/muhindo/movies
git remote set-url origin https://github.com/[username]/katogo.git
git fetch origin
git branch -u origin/main main
```

### 6.7 Handling the Current Diverged State

Both servers have local modifications not yet committed. Before setting up CI/CD:

1. **On local machine:** commit all current changes
2. **On each server:** `git stash` → `git pull` → `git stash pop` → resolve conflicts
3. The `.env` files are never touched by git (in `.gitignore`)

---

## 7. Implementation Phases

### Phase 0 — Prerequisites (do once, 30 minutes)
1. Add Hetzner's RSA public key to mruodel's `authorized_keys`
2. Test SSH from Hetzner → mruodel without password
3. Run `php artisan migrate` on Hetzner to create sync tables
4. Configure Hetzner `.env` with sync settings
5. Run `php artisan sync:pull --seed` to register all tables

### Phase 1 — Initial Full Sync (2–4 hours, run once)
- Run `php artisan sync:pull --force` on Hetzner
- This closes all data gaps (18K+ subscription_transactions, etc.)
- `reapplyHetznerUrls()` preserves Hetzner CDN URLs automatically
- Verify counts: `sync:pull --status` shows all tables in sync

### Phase 2 — Continuous Incremental Sync (permanent, already scheduled)
- `SYNC_ENABLED=true` activates the `sync:pull` entry in Kernel.php
- Runs every 5 minutes → stays within 5 minutes of live
- Dashboard at `/admin/sync-dashboard` shows live status

### Phase 3 — HTTP Export API (safety net)
- Add `GET /api/internal/sync/export` endpoint on live server
- Uses `SyncExportService` (already built)
- Protected by `X-Sync-Export-Secret` header
- Provides HTTP fallback if SSH tunnel is ever disrupted

### Phase 4 — Real-Time Event Push (live server outbound)
- Add model observers on live server for Tier 1 tables
- Observers push changes to `POST /api/internal/sync/receive-event` on backup
- Implemented as queued jobs to never block live request path
- Backup endpoint creates `InternalSyncController::receiveEvent()`

### Phase 5 — GitHub CI/CD
- Create GitHub Actions workflows
- Add required secrets to repository
- Test: push a small change, verify both servers update automatically

### Phase 6 — Monitoring & Alerting
- Sync dashboard already built at `/admin/sync-dashboard`
- Add email/push alert when any table goes > 30 minutes without sync
- Add `sync:health-check` artisan command for cron-based monitoring

---

## 8. Configuration Reference

### 8.1 Hetzner `.env` additions (munoapp.store)
```dotenv
# ── DB Sync (replica pulls from live) ────────────────────
SYNC_ENABLED=true
SYNC_ROLE=replica

# SSH tunnel target
SYNC_SOURCE_HOST=209.74.87.69
SYNC_SSH_USER=muhindo
SYNC_DB_USER=katogo
SYNC_DB_PASS=Kat0g0_2026!Sec
SYNC_DB_NAME=katogo_3
SYNC_TUNNEL_PORT=13306

# Tuning
SYNC_BATCH_SIZE=500
SYNC_MAX_PAGES=50

# HTTP export fallback secret (same value on both servers)
SYNC_EXPORT_SECRET=generate-a-64-char-random-hex-here

# Real-time event receive secret (same on both servers)
SYNC_EVENT_SECRET=generate-another-64-char-random-hex-here
```

### 8.2 Live Server `.env` additions (movies.mruodel.com)
```dotenv
# ── DB Sync (source, never pulls) ─────────────────────────
SYNC_ENABLED=false
SYNC_ROLE=source

# HTTP export endpoint protection
SYNC_EXPORT_SECRET=same-value-as-hetzner

# Event push target (where to send real-time updates)
SYNC_REPLICA_URL=https://munoapp.store
SYNC_EVENT_SECRET=same-value-as-hetzner
```

### 8.3 Laravel config/services.php additions
```php
'sync' => [
    'enabled'     => env('SYNC_ENABLED', false),
    'role'        => env('SYNC_ROLE', 'replica'),
    'source_host' => env('SYNC_SOURCE_HOST', '209.74.87.69'),
    'ssh_user'    => env('SYNC_SSH_USER', 'muhindo'),
    'db_user'     => env('SYNC_DB_USER', 'katogo'),
    'db_pass'     => env('SYNC_DB_PASS', ''),
    'db_name'     => env('SYNC_DB_NAME', 'katogo_3'),
    'tunnel_port' => env('SYNC_TUNNEL_PORT', 13306),
    'batch_size'  => env('SYNC_BATCH_SIZE', 500),
    'max_pages'   => env('SYNC_MAX_PAGES', 50),
    'export_secret' => env('SYNC_EXPORT_SECRET', ''),
    'event_secret'  => env('SYNC_EVENT_SECRET', ''),
    'replica_url'   => env('SYNC_REPLICA_URL', ''),
],
```

---

## 9. New Code to Write

### 9.1 HTTP Export API Endpoint (live server)

**Route** (`routes/api.php`):
```php
Route::middleware('throttle:sync-export')->group(function () {
    Route::get('internal/sync/export',    [SyncExportController::class, 'export']);
    Route::get('internal/sync/handshake', [SyncExportController::class, 'handshake']);
});
```

**Controller** (`app/Http/Controllers/Api/SyncExportController.php`):
```php
class SyncExportController extends Controller
{
    public function export(Request $request, SyncExportService $svc): JsonResponse
    {
        // Auth check
        $secret = config('services.sync.export_secret');
        if (!$secret || $request->header('X-Sync-Export-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $table    = $request->query('table', '');
        $cursorId = (int) $request->query('cursor_id', 0);
        $updatedTs = $request->query('updated_ts');
        $limit    = (int) $request->query('limit', 500);
        $offset   = (int) $request->query('offset', 0);

        if (!$svc->isAllowed($table)) {
            return response()->json(['error' => 'Table not allowed'], 403);
        }

        return response()->json($svc->export($table, $cursorId, $updatedTs, $limit, $offset));
    }

    public function handshake(Request $request, SyncExportService $svc): JsonResponse
    {
        $secret = config('services.sync.export_secret');
        if (!$secret || $request->header('X-Sync-Export-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return response()->json([
            'ok'           => true,
            'server'       => config('app.url'),
            'role'         => config('services.sync.role', 'source'),
            'table_counts' => $svc->tableSummary(),
            'ts'           => now()->toISOString(),
        ]);
    }
}
```

### 9.2 Real-Time Event Receive Endpoint (backup server only)

**Route** (`routes/api.php`, guarded by `SYNC_ROLE=replica`):
```php
Route::middleware('throttle:sync-events')->group(function () {
    Route::post('internal/sync/receive-event', [InternalSyncController::class, 'receiveEvent']);
});
```

**Controller** (`app/Http/Controllers/Api/InternalSyncController.php`):
```php
class InternalSyncController extends Controller
{
    // Allowed tables for event push — same as Tier 1
    private const EVENT_TABLES = [
        'admin_users', 'subscriptions', 'subscription_transactions',
        'subscription_plans', 'coin_transactions',
    ];

    public function receiveEvent(Request $request): JsonResponse
    {
        // Guard: only accept on replica
        if (config('services.sync.role') !== 'replica') {
            return response()->json(['error' => 'Not a replica'], 403);
        }

        // Auth
        $secret = config('services.sync.event_secret');
        if (!$secret || $request->header('X-Sync-Event-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $table  = $request->json('table', '');
        $action = $request->json('action', 'upsert');
        $rows   = $request->json('rows', []);

        if (!in_array($table, self::EVENT_TABLES, true)) {
            return response()->json(['error' => 'Table not allowed'], 403);
        }
        if (empty($rows)) {
            return response()->json(['error' => 'No rows provided'], 422);
        }

        // Upsert each row
        $upserted = 0;
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (array_chunk($rows, 100) as $chunk) {
                $columns = array_keys($chunk[0]);
                $colList = '`' . implode('`,`', $columns) . '`';
                $ph      = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
                $updateCols = array_filter($columns, fn($c) => $c !== 'id');
                $updateClause = implode(', ', array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $updateCols));

                $allPh = implode(',', array_fill(0, count($chunk), $ph));
                $values = array_merge(...array_map(fn($r) => array_values($r), $chunk));

                DB::statement(
                    "INSERT INTO `{$table}` ({$colList}) VALUES {$allPh} ON DUPLICATE KEY UPDATE {$updateClause}",
                    $values
                );
                $upserted += count($chunk);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Log::info("[InternalSync] Received {$upserted} rows for {$table} via event push.");
        return response()->json(['ok' => true, 'upserted' => $upserted]);
    }
}
```

### 9.3 Outbound Event Push Job (live server)

**Job** (`app/Jobs/PushSyncEvent.php`):
```php
class PushSyncEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly string $table,
        private readonly array  $rows,
    ) {}

    public function handle(): void
    {
        $replicaUrl = rtrim(config('services.sync.replica_url', ''), '/');
        $secret     = config('services.sync.event_secret', '');

        if (!$replicaUrl || !$secret) return;

        Http::withHeaders([
            'X-Sync-Event-Secret' => $secret,
            'Content-Type'        => 'application/json',
        ])->timeout(10)->post("{$replicaUrl}/api/internal/sync/receive-event", [
            'table'  => $this->table,
            'action' => 'upsert',
            'rows'   => $this->rows,
        ]);
    }
}
```

**Observer** (example for `subscriptions` on live server):
```php
class SubscriptionSyncObserver
{
    private static bool $enabled;

    public function created(Subscription $subscription): void  { $this->push($subscription); }
    public function updated(Subscription $subscription): void  { $this->push($subscription); }

    private function push(Subscription $subscription): void
    {
        if (config('services.sync.role') !== 'source') return;
        if (!config('services.sync.replica_url')) return;

        PushSyncEvent::dispatch('subscriptions', [$subscription->toArray()])
            ->onQueue('url-sync'); // low-priority queue, never blocks main
    }
}
```

### 9.4 SYNC_ROLE Guard in SyncPull Command
```php
// At the top of SyncPull::handle()
if (config('services.sync.role') === 'source') {
    $this->error('SAFETY: This server is SYNC_ROLE=source — sync:pull is disabled. Exiting.');
    return self::FAILURE;
}
```

---

## 10. Testing Plan

### 10.1 Unit Tests

| Test | What to verify |
|------|---------------|
| `SyncExportService::isAllowed()` | Rejects excluded tables, accepts allowed ones |
| `SyncExportService::export()` — new rows | Returns only rows with `id > cursor_id` |
| `SyncExportService::export()` — updated rows | Returns rows with `updated_at > cursor_ts` |
| `SyncPullService::upsertRows()` | DUPLICATE KEY UPDATE preserves existing rows |
| `reapplyHetznerUrls()` | Hetzner URLs survive a sync cycle |

### 10.2 Integration Tests (SSH tunnel)

```bash
# 1. Test SSH tunnel opens successfully
php artisan sync:pull --dry-run --table=subscription_plans
# Expected: "Tunnel active on 127.0.0.1:13306" → "subscription_plans ✓ N rows fetched"

# 2. Test cursor advancement
php artisan sync:pull --status
# Expected: shows last_synced_id > 0 for synced tables

# 3. Test incremental (only new rows)
php artisan sync:pull --table=admin_users
php artisan sync:pull --table=admin_users
# Second run should fetch 0 rows (nothing new in < 1 second)

# 4. Test force re-sync
php artisan sync:pull --reset=subscription_plans --force --table=subscription_plans
# Expected: re-fetches all rows for subscription_plans
```

### 10.3 HTTP Export API Tests

```bash
# On backup server, hit live's export endpoint
curl -H "X-Sync-Export-Secret: {secret}" \
  "https://movies.mruodel.com/api/internal/sync/handshake"
# Expected: {"ok":true,"role":"source","table_counts":{...}}

curl -H "X-Sync-Export-Secret: {secret}" \
  "https://movies.mruodel.com/api/internal/sync/export?table=subscription_plans&cursor_id=0&limit=10"
# Expected: {"rows":[...],"next_cursor_id":8,"has_more":false}

# Test auth rejection
curl "https://movies.mruodel.com/api/internal/sync/export?table=subscription_plans"
# Expected: 401 Unauthorized

# Test table allowlist
curl -H "X-Sync-Export-Secret: {secret}" \
  "https://movies.mruodel.com/api/internal/sync/export?table=movie_file_transfers"
# Expected: 403 Forbidden
```

### 10.4 Real-Time Event Push Tests

```bash
# Manually push a test event to backup
curl -X POST https://munoapp.store/api/internal/sync/receive-event \
  -H "X-Sync-Event-Secret: {secret}" \
  -H "Content-Type: application/json" \
  -d '{"table":"subscription_plans","action":"upsert","rows":[{"id":1,"name":"Test"}]}'
# Expected: {"ok":true,"upserted":1}

# Verify anti-reversal: should reject on live server
curl -X POST https://movies.mruodel.com/api/internal/sync/receive-event \
  -H "X-Sync-Event-Secret: {secret}" \
  -d '{"table":"admin_users","action":"upsert","rows":[]}'
# Expected: 403 {"error":"Not a replica"}
```

### 10.5 Anti-Reversal Tests

```bash
# 1. On backup: verify sync:pull rejects on live server
# (Add SYNC_ROLE guard to command — test it)
ssh muhindo@209.74.87.69 "cd /home/muhindo/movies && php artisan sync:pull"
# Expected: "SAFETY: This server is SYNC_ROLE=source — sync:pull is disabled."

# 2. On backup: manually set a Hetzner URL, run sync, verify URL preserved
mysql katogo_3 -e "UPDATE movie_models SET url='https://nx100800.your-storageshare.de/s/TEST/download' WHERE id=1234"
php artisan sync:pull --table=movie_models --force
mysql katogo_3 -e "SELECT url FROM movie_models WHERE id=1234"
# Expected: URL is still the Hetzner URL (not overwritten by live's BunnyCDN URL)

# 3. Verify event push only works one-way
# (Already covered by SYNC_ROLE guard in InternalSyncController)
```

### 10.6 GitHub Actions Tests

```bash
# 1. Make a trivial change (e.g., add a comment to a PHP file)
git add -A && git commit -m "test: verify CI/CD pipeline"
git push origin main

# 2. Watch GitHub Actions tab — both workflows should pass
# 3. SSH to each server and verify the change appears
```

### 10.7 Load & Timing Tests

```bash
# Measure time for full sync:pull cycle
time php artisan sync:pull --force 2>&1 | tail -5
# Target: < 5 minutes for all tables

# Measure time for incremental sync (normal run)
time php artisan sync:pull 2>&1 | tail -5
# Target: < 30 seconds for a normal incremental run
```

### 10.8 Monitoring Dashboard Tests

```bash
# Access admin dashboard on backup server
curl -c /tmp/cookies.txt -b /tmp/cookies.txt https://munoapp.store/admin/sync-dashboard
# Expected: HTML with table list, status badges, stat row

# Test live JSON endpoint (polled by dashboard JS every 8s)
curl https://munoapp.store/admin/sync-dashboard/live
# Expected: {"stats":{...},"cursors":[...],"recent_logs":[...],"ts":"..."}

# Test manual trigger button
curl -X POST https://munoapp.store/admin/sync-dashboard/trigger \
  -d '{"table":"subscription_plans"}' -H "Content-Type: application/json"
# Expected: {"ok":true,"message":"Sync started in background for subscription_plans."}

# Test cursor reset
curl -X POST https://munoapp.store/admin/sync-dashboard/reset \
  -d '{"table":"subscription_plans"}' -H "Content-Type: application/json"
# Expected: {"ok":true,"message":"Cursor for 'subscription_plans' reset to 0."}
```

---

## 11. Initial Full Sync Procedure

This is the **one-time bootstrap** to close all data gaps before continuous sync begins.

```bash
# Run on munoapp.store (Hetzner)

# Step 1: Ensure SSH key is authorized on mruodel
ssh -i /root/.ssh/id_rsa muhindo@209.74.87.69 "echo connected"
# Must return "connected" without a password prompt

# Step 2: Verify .env has sync config
grep SYNC /var/www/katogo/.env

# Step 3: Run migrations (creates db_sync_cursors, db_sync_logs if missing)
cd /var/www/katogo
php artisan migrate --force

# Step 4: Seed the cursor registry
php artisan sync:pull --seed
# Expected: "Done. 52 tables registered."

# Step 5: Check what would sync (dry run)
php artisan sync:pull --dry-run
# Review: each table shows N rows fetched

# Step 6: Run full sync (no force needed — all cursors at 0 so all rows qualify)
php artisan sync:pull
# This will take 2–4 hours for 18K+ subscription_transactions etc.
# Watch progress in another terminal:
#   tail -f /var/www/katogo/storage/logs/sync-pull.log

# Step 7: After completion, verify counts
php artisan sync:pull --status
# All tables should show last_run_at = just now, status = ok

# Step 8: Verify data gap is closed
mysql -u katogo -pKatogoDB@2026! katogo_3 -e "SELECT COUNT(*) FROM subscription_transactions"
# Should match (or be very close to) live server's 61,548

# Step 9: Enable continuous sync
# Already enabled in Kernel.php when SYNC_ENABLED=true
# Cron already running via /etc/cron.d/katogo
# Verify next run will fire:
php artisan schedule:list | grep sync:pull
```

---

## 12. Monitoring & Alerting

### 12.1 Admin Dashboard
- URL: `https://munoapp.store/admin/sync-dashboard`
- Auto-refreshes every 8 seconds via JSON polling
- Shows: status per table, last sync time, row counts, recent logs
- Buttons: manual trigger, cursor reset per table

### 12.2 Alert Conditions (to add)
```php
// In Kernel.php — add a health check that alerts if sync is too far behind
$schedule->call(function () {
    $stale = DbSyncCursor::where('enabled', true)
        ->where('priority', 1)
        ->where('last_run_at', '<', now()->subMinutes(30))
        ->where('status', '!=', 'idle')
        ->count();
    
    if ($stale > 0) {
        Log::critical("[SyncHealthCheck] {$stale} Tier-1 tables haven't synced in 30+ minutes!");
        // Optional: send push notification or email alert
    }
})->everyFifteenMinutes()->name('sync-health-check');
```

### 12.3 Log Files to Monitor
```
/var/www/katogo/storage/logs/sync-pull.log     → incremental sync output
/var/www/katogo/storage/logs/scheduler.log     → cron fires (confirm every minute)
/var/www/katogo/storage/logs/worker.log        → queue worker output (event pushes)
```

### 12.4 Key Metrics to Watch
| Metric | Green | Yellow | Red |
|--------|-------|--------|-----|
| Tier-1 sync lag | < 10 min | 10–30 min | > 30 min |
| Tier-2 sync lag | < 15 min | 15–60 min | > 60 min |
| Consecutive errors | 0 | 1–2 | 3+ |
| SSH tunnel open time | < 5s | 5–15s | > 15s |
| subscription_transactions gap | < 100 | 100–1000 | > 1000 |

---

## 13. Operations Runbook

### "Sync is behind / tables showing error status"
```bash
# 1. Check what's failing
php artisan sync:pull --status
# Look for tables with status=error

# 2. Check the logs
grep "ERROR" /var/www/katogo/storage/logs/sync-pull.log | tail -20

# 3. Common fixes:
# SSH tunnel failed → verify SSH key still authorized on mruodel
ssh -i /root/.ssh/id_rsa muhindo@209.74.87.69 "echo ok"

# Tunnel port conflict → kill any stuck ssh processes
pkill -f "ssh.*-L.*13306" && php artisan sync:pull --force --table=movie_models

# Table schema changed → reset cursor and re-sync
php artisan sync:pull --reset=table_name
php artisan sync:pull --force --table=table_name
```

### "Hetzner video URLs were overwritten by sync"
```bash
# Re-apply all Hetzner CDN URLs (safe, idempotent)
mysql -u katogo -pKatogoDB@2026! katogo_3 -e "
UPDATE movie_models mm
INNER JOIN movie_file_transfers mft ON mft.movie_id = mm.id
SET mm.url = mft.dest_url
WHERE mft.status = 'done' AND mft.dest_url IS NOT NULL AND mft.dest_url != ''
"
# Then verify
mysql -u katogo -pKatogoDB@2026! katogo_3 -e "SELECT COUNT(*) FROM movie_models WHERE url LIKE '%your-storageshare%'"
```

### "Need to add a new table to sync"
1. Add table to `SyncPullService::TABLE_CONFIG` with appropriate priority/frequency
2. Add table to `SyncExportService::ALLOWED_TABLES`
3. Deploy both files to munoapp.store
4. Run `php artisan sync:pull --seed` (idempotent — just adds the new entry)
5. Run `php artisan sync:pull --force --table=new_table_name`

### "Deploy failed — need to roll back"
```bash
# On affected server:
cd /var/www/katogo          # or /home/muhindo/movies
git log --oneline -5        # identify the last good commit hash
git reset --hard {hash}
php artisan config:cache && php artisan route:cache && php artisan view:cache
supervisorctl restart katogo-worker:*  # Hetzner only
php artisan up              # if was in maintenance mode
```

---

## 14. Security Considerations

| Concern | Mitigation |
|---------|-----------|
| SSH key compromise → read access to live DB | Key only allows SSH tunnel to port 3306; no shell access possible via tunnel |
| `SYNC_EXPORT_SECRET` leaked → read-only data access | Rotate secret in both servers' `.env` simultaneously |
| Event push spoofing → fake data injected | `X-Sync-Event-Secret` auth + table allowlist + `SYNC_ROLE` guard |
| Live server accidentally runs sync:pull | `SYNC_ROLE=source` guard in command + `SYNC_ENABLED=false` |
| Backup server pushes data to live | Event push endpoint 403s on live (`SYNC_ROLE≠replica`); outbound observers only on live |
| Credentials in git | `.env` in `.gitignore`; secrets in GitHub Actions secrets, not code |
| SQL injection in table names | Strict `ALLOWED_TABLES`/`TABLE_CONFIG` allowlists; no user-supplied table names |

---

## 15. Timeline Estimate

| Phase | Duration | Who |
|-------|----------|-----|
| Phase 0: SSH key + .env config | 30 min | Dev |
| Phase 1: Initial full sync | 2–4 hours (automated) | Cron |
| Phase 2: Verify continuous sync | 1 hour monitoring | Dev |
| Phase 3: HTTP export API | 2 hours coding | Dev |
| Phase 4: Real-time event push | 3 hours coding | Dev |
| Phase 5: GitHub Actions | 2 hours config | Dev |
| Phase 6: Monitoring alerts | 1 hour | Dev |
| **Total** | **~1 day active work + overnight first sync** | |

---

*End of plan. Approve to proceed with implementation.*
