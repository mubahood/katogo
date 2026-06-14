# Katogo Database Sync System
## Technical Proposal — Namecheap → Hetzner Continuous Replication

**Author:** Muhindo Mubaraka  
**Date:** 2026-06-12  
**Status:** Approved for Implementation  
**Revision:** 1.0

---

## 1. Executive Summary

The Katogo platform currently runs its live production workload on a Namecheap VPS (`katogo.schooldynamics.ug`). A new Hetzner VPS (`munoapp.store`, 91.98.42.156) has been provisioned as the permanent successor. This document specifies a **continuous, incremental, one-way database replication system** that keeps the Hetzner database in near-real-time sync with Namecheap until the DNS cutover is complete.

The system is built entirely inside the existing Laravel 10 codebase — no external replication tools (Debezium, Canal, Maxwell) are required. Both servers run the same code; activation is controlled by `.env` flags.

---

## 2. Problem Statement

| Concern | Detail |
|---|---|
| Active users | All live traffic currently hits Namecheap; their data must reach Hetzner before cutover |
| Table count | ~52 tables require replication; 8 tables are Hetzner-local and must not be overwritten |
| Update semantics | Rows are inserted AND updated (subscriptions change status; movie URLs get rewritten) |
| Conflict risk | Hetzner already has Hetzner-CDN URLs written to `movie_models.url` — these must survive |
| Zero-downtime goal | Sync must run continuously so cutover is a simple DNS flip, not a migration event |

---

## 3. Architecture Overview

```
┌─────────────────────────────────────┐        HTTPS/443        ┌──────────────────────────────────────┐
│      NAMECHEAP VPS (Source)         │ ◄──────────────────────► │      HETZNER VPS (Destination)       │
│  katogo.schooldynamics.ug           │                          │  munoapp.store  91.98.42.156         │
│                                     │                          │                                      │
│  ┌───────────────────────────────┐  │                          │  ┌────────────────────────────────┐  │
│  │  SyncExportController         │  │   GET /admin/sync/       │  │  SyncPullService               │  │
│  │  /admin/sync/export/{table}   │◄─┼──── export/{table}?      │  │  (called by SyncPull command)  │  │
│  │                               │  │     cursor_id=X          │  │                                │  │
│  │  - Auth: Bearer token         │  │     &limit=500           │  │  - Calls Namecheap API         │  │
│  │  - IP whitelist               │  │                          │  │  - Paginates through results   │  │
│  │  - Read-only SELECT queries   │  │   ← JSON rows            │  │  - UPSERT into local DB        │  │
│  │  - Cursor-based pagination    │  │                          │  │  - Updates db_sync_cursors     │  │
│  └───────────────────────────────┘  │                          │  └────────────────────────────────┘  │
│                                     │                          │                                      │
│  MySQL: katogo_3                    │                          │  MySQL: katogo_3                     │
│  (Source of truth)                  │                          │  (Replica — grows to match source)   │
└─────────────────────────────────────┘                          └──────────────────────────────────────┘
                                                                         ▲
                                                                         │  every 5 min
                                                                 ┌───────────────────┐
                                                                 │  Laravel Scheduler │
                                                                 │  sync:pull         │
                                                                 └───────────────────┘
```

### Data Flow (per table per run)

```
1. Read db_sync_cursors for this table (last_id, last_updated_ts)
2. GET /admin/sync/export/{table}?cursor_id={last_id}&updated_ts={last_ts}&limit=500
3. Source returns: { rows: [...], next_cursor_id: X, next_updated_ts: T, has_more: bool }
4. Hetzner: INSERT ... ON DUPLICATE KEY UPDATE (upsert all rows)
5. Update db_sync_cursors (new last_id, new last_ts, rows_synced, status)
6. Write row to db_sync_logs
7. If has_more: repeat steps 2-6 (up to MAX_PAGES_PER_RUN)
8. After movie_models sync: reapply Hetzner CDN URLs (see §5.3)
```

---

## 4. Table Sync Matrix

### Priority 1 — Every 5 min (User & Payment Data)
| Table | Cursor Strategy | Notes |
|---|---|---|
| `admin_users` | id + updated_at | Core user accounts |
| `subscriptions` | id + updated_at | Status changes frequently |
| `subscription_transactions` | id + created_at | Append-mostly |
| `subscription_plans` | id + updated_at | Rarely changes |

### Priority 2 — Every 5 min (Content & Engagement)
| Table | Cursor Strategy | Notes |
|---|---|---|
| `movie_models` | id + updated_at | **Post-sync: reapply Hetzner CDN URLs** |
| `series_movies` | id + updated_at | Episode listings |
| `movie_views` | id + created_at | High volume, append-only |
| `movie_likes` | id + created_at | Append-only |
| `movie_downloads` | id + updated_at | Status updates |
| `movie_requests` | id + updated_at | User requests |
| `movie_searches` | id + created_at | Append-only |
| `movie_wishlists` | id + created_at | Append-only |
| `customer_tickets` | id + updated_at | Status changes |
| `customer_ticket_records` | id + created_at | Append-mostly |

### Priority 3 — Every 15 min (Support & Moderation)
| Table | Cursor Strategy | Notes |
|---|---|---|
| `content_reports` | id + updated_at | Soft-delete aware |
| `content_moderation_logs` | id + created_at | Append-only |
| `video_playback_failures` | id + updated_at | Status changes |
| `chat_heads` | id + updated_at | Last message updates |
| `chat_messages` | id + created_at | High volume |
| `movie_crawler_websites` | id + updated_at | Crawler state |
| `movie_crawler_pages` | id + updated_at | Large table |
| `munowatch_categories` | id + updated_at | Small |
| `munowatch_movie_categories` | id + created_at | Pivot, append-only |
| `movie_pics` | id + created_at | Append-only |
| `safemode_views` | id + created_at | Append-only |

### Priority 4 — Every 60 min (Config & Reference)
| Table | Cursor Strategy | Notes |
|---|---|---|
| `admin_roles` | id + updated_at | Rarely changes |
| `admin_permissions` | id + updated_at | Rarely changes |
| `admin_menu` | id + updated_at | Rarely changes |
| `admin_operation_log` | id + created_at | Audit trail |
| `streaming_stations` | id + updated_at | |
| `streaming_urls` | id + updated_at | |
| `pages` | id + updated_at | |
| `links` | id + updated_at | |
| `blog_posts` | id + updated_at | |
| `blog_comments` | id + updated_at | |
| `blog_likes` | id + created_at | |
| `system_configs` | id + updated_at | |
| `scraper_models` | id + updated_at | |
| `game_stats` | id + updated_at | |
| `trending_notifications` | id + created_at | |
| `coin_transactions` | id + created_at | |
| `user_blocks` | id + updated_at | Soft-delete |
| `merged_accounts` | id + created_at | |
| `page_visits` | id + created_at | |
| `learning_material_categories` | id + updated_at | |
| `learning_material_posts` | id + updated_at | |
| `support_audit_logs` | id + created_at | |
| `trivia_questions` | id + updated_at | |

### Pivot tables (no `id`) — Full re-sync hourly
| Table | Key Columns | Strategy |
|---|---|---|
| `admin_role_users` | role_id, user_id | Full REPLACE INTO |
| `admin_role_permissions` | role_id, permission_id | Full REPLACE INTO |
| `admin_user_permissions` | user_id, permission_id | Full REPLACE INTO |

### EXCLUDED — Never sync from Namecheap
| Table | Reason |
|---|---|
| `movie_file_transfers` | Hetzner-only table (Namecheap doesn't have it) |
| `sessions` | Server-local, ephemeral |
| `jobs` / `failed_jobs` / `job_batches` | Queue state, server-local |
| `cache` / `cache_locks` | Ephemeral |
| `password_reset_tokens` | Server-local security |
| `personal_access_tokens` | Server-local |
| `users` | Shadowed by admin_users (same data) |

---

## 5. Key Design Decisions

### 5.1 Dual-Cursor CDC (Change Data Capture Without Binlog)

Each table maintains two cursors:
- `last_synced_id` — highest `id` seen. Used to pull NEW rows: `WHERE id > :cursor`
- `last_updated_ts` — highest `updated_at` seen. Used to pull MODIFIED rows: `WHERE updated_at > :ts AND id <= :last_id`

This catches both INSERT and UPDATE events without MySQL binlog access. The look-back window for updates is configurable (default: also look at rows updated in last 2 hours, in case of clock skew between servers).

### 5.2 UPSERT Strategy (Safe Overwrite)

All inserts use `INSERT ... ON DUPLICATE KEY UPDATE`. This means:
- New rows on Namecheap → INSERT on Hetzner
- Updated rows on Namecheap → UPDATE on Hetzner (Namecheap wins)
- Rows only on Hetzner → untouched (Hetzner-local data preserved)

This is intentional: Namecheap is the source of truth until DNS cutover.

### 5.3 movie_models URL Preservation

`movie_models.url` is actively rewritten by the Hetzner transfer pipeline (pointing to Hetzner CDN). A naive full-column upsert would overwrite Hetzner CDN URLs with old Namecheap URLs.

**Solution:** After every `movie_models` sync batch, re-run:
```sql
UPDATE movie_models mm
INNER JOIN movie_file_transfers mft ON mft.movie_id = mm.id
SET mm.url = mft.dest_url
WHERE mft.status = 'done' AND mft.dest_url IS NOT NULL;
```

This "reapplies" Hetzner CDN URLs within the same sync run, so the next sync batch (or a manual check) always sees Hetzner URLs in place.

### 5.4 Authentication & Security

```
Source  (Namecheap): Validates X-Sync-Token header + optional Hetzner IP check
Destination (Hetzner): Sends Bearer token on every request

Token:  64-char hex, same value in both .env as SYNC_SECRET_TOKEN
Transport: HTTPS only (existing Nginx TLS on both servers)
Rate limit: 120 requests/min (handled by Laravel throttle middleware)
Read-only: Export controller only runs SELECT queries — no writes possible
```

### 5.5 Failure Handling

| Failure | Behaviour |
|---|---|
| HTTP 4xx/5xx from source | Log error, mark table status='error', skip to next table, retry next run |
| Connection timeout | Same as above; exponential backoff after 3 consecutive failures |
| Partial page (mid-transfer crash) | Cursor not advanced; next run re-fetches same page (idempotent) |
| Source table missing | Skip with warning (Namecheap may be behind on migrations) |
| DB write failure on Hetzner | Rollback batch transaction, log error, don't advance cursor |

---

## 6. Implementation Plan

### Phase 1 — Database Infrastructure (immediate)
- Migration: `db_sync_cursors` table (per-table state)
- Migration: `db_sync_logs` table (audit trail)
- Eloquent models: `DbSyncCursor`, `DbSyncLog`

### Phase 2 — Source (Namecheap): Export API
- `SyncExportController` — authenticated read-only API
- Routes: `GET /admin/sync/handshake`, `GET /admin/sync/export/{table}`
- `.env`: `SYNC_ENABLED=true`, `SYNC_SECRET_TOKEN=<hex>`

### Phase 3 — Destination (Hetzner): Pull Engine
- `SyncPullService` — pagination, UPSERT, cursor management
- `SyncPull` artisan command (`sync:pull [--table=] [--dry-run] [--reset]`)
- `.env`: `SYNC_SOURCE_URL`, `SYNC_SECRET_TOKEN`, `SYNC_BATCH_SIZE`

### Phase 4 — Scheduler
- `sync:pull` every 5 minutes via `Kernel.php`
- `withoutOverlapping(10)` guard

### Phase 5 — Admin Dashboard
- `SyncDashboardController` — live status, per-table cards, logs
- Routes: `GET /admin/sync-dashboard`, `GET /admin/sync-dashboard/live` (JSON)
- Manual trigger, reset cursor, enable/disable per table

### Phase 6 — Deploy & Activate
1. Run migration on both servers
2. Add env vars to both servers
3. Deploy code to Namecheap (source side only needs the export controller)
4. Run `php artisan sync:pull --dry-run` on Hetzner to verify connectivity
5. Run `php artisan sync:pull` to start first full sync
6. Enable scheduler entry
7. Monitor dashboard until all tables show `status=ok`

---

## 7. Admin Dashboard Wireframe

```
┌─────────────────────── DB Sync Dashboard ──────────────────────────────┐
│  Source: katogo.schooldynamics.ug     Last full run: 2026-06-12 14:32  │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────┐ │
│  │ 52 tables│ │ 49  ok   │ │  2 sync  │ │  1 error │ │ [▶ Run Now]  │ │
│  │  tracked │ │          │ │  pending │ │          │ │              │ │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────────┘ │
│                                                                         │
│  ┌─ Priority 1 — Users & Payments ──────────────────────────────────┐  │
│  │ admin_users          ● ok   last: 14:32   rows: 28,746  +3/run   │  │
│  │ subscriptions        ● ok   last: 14:32   rows: 9,212   +1/run   │  │
│  │ subscription_transactions  ● ok  last: 14:32  rows: 3,500 +2/run │  │
│  │ subscription_plans   ● ok   last: 14:32   rows: 8      +0/run    │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌─ Priority 2 — Movies & Engagement ──────────────────────────────┐   │
│  │ movie_models         ● ok   last: 14:32   rows: 90,441  +22/run  │  │
│  │ movie_views          ⚠ sync  last: 14:27   rows: 2.1M   pending  │  │
│  │ ...                                                              │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌─ Recent Sync Logs ──────────────────────────────────────────────┐   │
│  │ 14:32:01  admin_users          ok    +3 rows  122ms             │   │
│  │ 14:32:02  subscriptions        ok    +1 row   89ms              │   │
│  │ 14:31:55  movie_crawler_pages  error  HTTP 503  [retry]         │   │
│  └──────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8. Environment Variables Reference

### Namecheap server (.env additions)
```ini
SYNC_ENABLED=true
SYNC_SECRET_TOKEN=<generate: openssl rand -hex 32>
SYNC_ALLOWED_IPS=91.98.42.156   # Hetzner VPS IP (leave empty to skip IP check)
```

### Hetzner server (.env additions)
```ini
SYNC_ENABLED=true
SYNC_SECRET_TOKEN=<same token as Namecheap>
SYNC_SOURCE_URL=https://katogo.schooldynamics.ug
SYNC_BATCH_SIZE=500
SYNC_MAX_PAGES_PER_TABLE=20
SYNC_TIMEOUT_SECONDS=30
```

---

## 9. Cutover Checklist

When Hetzner is ready to become production:

- [ ] Verify all tables show `status=ok` in sync dashboard
- [ ] Verify `last_synced_at` is within last 10 minutes for all P1/P2 tables
- [ ] Take final mysqldump of Namecheap as cold backup
- [ ] Put Namecheap into maintenance mode (`php artisan down`)
- [ ] Run one final `sync:pull` on Hetzner to catch last writes
- [ ] Flip DNS: `katogo.schooldynamics.ug` → Hetzner IP
- [ ] Flip DNS: `munoapp.store` → stays on Hetzner
- [ ] Disable `SYNC_ENABLED` on both servers
- [ ] Remove sync scheduler entry
- [ ] Monitor Hetzner for 30 minutes post-cutover
