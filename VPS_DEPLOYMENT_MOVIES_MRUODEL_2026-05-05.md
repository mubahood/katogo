# VPS Deployment Log - movies.mruodel.com

Date: 2026-05-05
Target: https://movies.mruodel.com
Server: 209.74.87.69
SSH user: muhindo
App path: /home/muhindo/movies

## Objective
Deploy latest backend changes safely without losing any existing server data/changes, run migrations, ensure seed/menu completeness, and verify key routes.

## Pre-Deployment Findings
- Production app confirmed at `/home/muhindo/movies`.
- `.env` confirmed production:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://movies.mruodel.com/`
- Server git write path had issues (stash/pull failed due `.git/objects` write error), so deployment switched to a no-git-write method.

## Zero-Loss Safeguards Applied
1. Created full backup archive before changes:
   - `/home/muhindo/deploy_backups/movies_full_20260505_011157.tgz`
   - Size: `438M`
2. Created git metadata snapshot:
   - `/home/muhindo/deploy_backups/20260505_011115/`

## Deployment Method Used
Because server-side git write was unreliable:
- Used `rsync` from local source to server app directory (safe fallback), excluding volatile/runtime directories:
  - `.git/`
  - `.env`
  - `storage/api_cache/`
  - `storage/logs/`
  - `node_modules/`
  - `transition-data/`

## Database + Menu + Cache Actions
1. Ran migrations:
- 2026_05_02_000001_add_support_fields_to_admin_users
- 2026_05_02_000002_create_customer_tickets_table
- 2026_05_02_000003_create_customer_ticket_records_table
- 2026_05_02_000004_seed_support_team_role
- 2026_05_02_000005_add_auto_created_accounts_menu_item
- 2026_05_03_190000_add_ticket_type_resolution_and_tracking_fields
- 2026_05_03_191000_add_support_menu_items
- 2026_05_03_192000_create_support_audit_logs_table
- 2026_05_04_000100_create_movie_requests_table
- 2026_05_04_000110_add_movie_request_fields_to_customer_tickets_table
- 2026_05_04_000120_add_movie_requests_menu_item
- 2026_05_05_120000_add_customer_visibility_fields_to_ticket_records

2. Ensured support role exists (idempotent check):
- `support_team role exists`

3. Ensured required admin menu entries exist:
- `auto-created-accounts`
- `support-team`
- `support-tickets`
- `movie-requests`

4. Cleaned menu duplicates (created during idempotent repair), keeping canonical entries only.

5. Cleared/rebuilt caches:
- `php artisan cache:clear`
- `php artisan config:clear`
- `php artisan route:clear`
- `php artisan view:clear`
- `php artisan optimize`

## Verification Results
1. Live route exists:
- `POST /api/auth/auto-register`

2. Live API smoke test:
- `POST https://movies.mruodel.com/api/auth/auto-register` with short device id returned expected validation:
  - `device_id is required (min 4 chars)`

3. Movie request routes present (API + admin resource routes).

4. Final admin menu entries:
- `61|Auto-Created Accounts|auto-created-accounts`
- `62|Support Team|support-team`
- `63|Support Tickets|support-tickets`
- `64|Movie Requests|movie-requests`

## Notes
- Server git write permissions should be repaired later to restore standard `git pull` deploy flow.
- Current deployment is complete and production is running with required support/ticket/movie-request features and migrations.
