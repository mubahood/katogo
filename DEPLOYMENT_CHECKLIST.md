# Katogo Deployment Checklist

> Use this checklist for every production deployment. Run steps in order.

## Pre-Deploy (Local)

- [ ] All tests pass locally (or no breaking changes)  
- [ ] `.env.production` values match expected — check `APP_ENV=production`, `APP_DEBUG=false`
- [ ] New migrations without data loss checked (`php artisan migrate --pretend`)
- [ ] `composer.json` changes → run `composer update` on PHP 8.1–8.3 machine → commit updated `composer.lock`

---

## Deploy Steps (SSH / Git)

```bash
# 1. SSH into server
ssh ulitscom@162.0.232.59 -p 21098

# 2. Navigate to project root
cd /home/ulitscom/katogo

# 3. Pull latest code
git fetch origin main
git merge origin/main

# 4. Install production-only packages (no dev dependencies)
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Run any pending migrations
php artisan migrate --force

# 6. Clear and regenerate caches
php artisan optimize
# This runs: config:cache + route:cache + view:cache + event:cache

# 7. Restart queue workers (so they pick up new code)
php artisan queue:restart

# 8. Verify health endpoint responds
curl -s https://katogo.ulits.com/api/health | head -c 200
```

---

## Post-Deploy Checks

- [ ] `/api/health` returns `{"status":"ok"}` with DB connection time
- [ ] `/api/v2/movies` returns paginated response (200 OK)
- [ ] `/api/v2/manifest` returns `304` on second request (ETag working)
- [ ] Check `storage/logs/laravel.log` for new errors after deploy  
      `tail -n 100 /home/ulitscom/katogo/storage/logs/laravel.log`
- [ ] Admin panel loads at `/admin` without errors
- [ ] Subscription payment webhook reachable (Pesapal IPN URL live)

---

## Log Maintenance (Monthly)

```bash
# Clear accumulated log file (backup first)
cp storage/logs/laravel.log storage/logs/laravel.log.bak
echo "" > storage/logs/laravel.log

# Or rotate automatically via cron (add to cPanel cron):
# 0 0 1 * * cd /home/ulitscom/katogo && php artisan log:clear 2>/dev/null
```

---

## Rollback (if deploy breaks production)

```bash
# Revert to last working commit
git log --oneline -5           # find the last good commit hash
git reset --hard <commit-hash> # or merge previous tag
composer install --no-dev --optimize-autoloader
php artisan migrate:rollback   # only if migration caused the issue
php artisan optimize
php artisan queue:restart
```

---

## Environment Variables to Verify on Each Deploy

| Variable | Expected Value |
|----------|---------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://katogo.ulits.com` |
| `DB_CONNECTION` | `mysql` |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_DRIVER` | `file` |
| `JWT_SECRET` | *(non-empty)* |
| `ONESIGNAL_APP_ID` | *(non-empty)* |
| `PESAPAL_CONSUMER_KEY` | *(non-empty)* |
| `RESPONSE_CACHE_ENABLED` | `true` |

---

## Quick Commands Reference

```bash
# Clear specific caches
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache
php artisan view:clear   && php artisan view:cache
php artisan cache:clear                            # clears file cache (including responsecache)

# Queue management
php artisan queue:work --tries=3 --timeout=60 &   # start worker in background
php artisan queue:monitor database                 # check queue backlog
php artisan queue:failed                           # list failed jobs
php artisan queue:retry all                        # retry all failed jobs

# Database
php artisan migrate:status             # check migration state
php artisan db:show                    # connection + table stats (Laravel 10)

# Response cache (spatie/laravel-responsecache)
php artisan responsecache:clear        # flush all cached API responses
```
