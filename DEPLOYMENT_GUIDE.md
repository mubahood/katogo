# Katogo Backend — Deployment Guide

## Overview

| Item | Detail |
|------|--------|
| **Framework** | Laravel (PHP 8.2) |
| **Server** | Shared hosting (cPanel) at `162.0.232.59` |
| **SSH User** | `ulitscom` |
| **SSH Port** | `21098` (NOT the default 22) |
| **SSH Password** | `256@Anjane#` |
| **Server Path** | `/home/ulitscom/katogo/` |
| **Production URL** | `https://katogo.ugnews24.info` |
| **Database** | MySQL — `ulitscom_katogo` on `127.0.0.1` |
| **GitHub Repo** | `https://github.com/muhindo-dev/katogo.git` |
| **Branch** | `main` |
| **Server Git Remote** | `dev` (points to `muhindo-dev/katogo.git`) |

---

## Quick Deploy (Copy-Paste)

Run this from your local machine to deploy in one shot:

```bash
# 1. Commit and push locally
cd /Applications/MAMP/htdocs/katogo
git add -A && git commit -m 'your commit message' && git push origin main

# 2. Deploy to server (single command)
sshpass -p '256@Anjane#' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 -p 21098 ulitscom@162.0.232.59 \
  'cd /home/ulitscom/katogo && git pull dev main && php artisan cache:clear && php artisan config:clear && php artisan route:clear'
```

---

## Step-by-Step Deployment

### Step 1: Make and Test Changes Locally

```bash
cd /Applications/MAMP/htdocs/katogo

# Edit your files...

# Run PHP syntax check on changed files
php -l app/Http/Controllers/YourController.php

# Run any test scripts
php test_subscription_improvements.php
php test_payment_flow.php
php test_payment_status_checker.php
```

### Step 2: Commit and Push to GitHub

```bash
cd /Applications/MAMP/htdocs/katogo

# Stage files
git add app/Http/Controllers/SubscriptionApiController.php
git add app/Services/SubscriptionPesapalService.php
# ... or git add -A for everything

# Commit
git commit -m 'fix: description of changes'

# Push to GitHub
git push origin main
```

### Step 3: SSH into the Production Server

```bash
sshpass -p '256@Anjane#' ssh -o StrictHostKeyChecking=no -o ConnectTimeout=30 -p 21098 ulitscom@162.0.232.59
```

**Important notes:**
- Port is **21098** (cPanel SSH), NOT port 22
- If you get `Permission denied` or `Connection refused`, the server may be rate-limiting SSH connections. Wait 30–60 seconds and retry.
- If `sshpass` is not installed: `brew install sshpass` (macOS via homebrew-file/third-party tap) or enter password manually.

### Step 4: Pull Code on Server

```bash
cd /home/ulitscom/katogo
git pull dev main
```

**Why `dev` and not `origin`?**
The server has two remotes configured:
- `dev` → `https://github.com/muhindo-dev/katogo.git` ← **use this one**
- `origin` → `https://github.com/mubahood/katogo` (separate repo)

### Step 5: Clear Laravel Caches

```bash
cd /home/ulitscom/katogo
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

All three must be run after every deploy. Laravel caches config, routes, and application data aggressively in production.

### Step 6: Run Database Migrations (if any)

```bash
php artisan migrate --force
```

The `--force` flag is required in production (`APP_ENV=production`). Only run this if your changes include new migration files.

### Step 7: Verify Deployment

```bash
# Check PHP syntax on key files
php -l app/Http/Controllers/SubscriptionApiController.php
php -l app/Services/SubscriptionPesapalService.php

# Check routes load correctly
php artisan route:list --path=subscriptions

# Run test suite on server
php test_subscription_improvements.php

# Check recent logs for errors
tail -50 storage/logs/laravel.log
```

### Step 8: Test API Externally

```bash
# From your local machine
curl -s 'https://katogo.ugnews24.info/api/subscription-plans' | python3 -m json.tool
```

---

## Server Environment Details

### PHP Version
```
PHP 8.2.30 (cli)
```

### Key .env Settings
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://katogo.ugnews24.info
APP_PRODUCTION_URL=https://katogo.ugnews24.info
DB_HOST=127.0.0.1
DB_DATABASE=ulitscom_katogo
PESAPAL_ENVIRONMENT=production
PESAPAL_PRODUCTION_URL=https://pay.pesapal.com/v3
PESAPAL_IPN_URL=https://katogo.ugnews24.info/api/subscriptions/pesapal/ipn
PESAPAL_CALLBACK_URL=https://katogo.ugnews24.info/api/subscriptions/pesapal/callback
PESAPAL_CURRENCY=UGX
```

### Directory Structure on Server
```
/home/ulitscom/
├── katogo/                          ← Laravel backend (this project)
│   ├── app/
│   ├── public/                      ← Web root (served by web server)
│   │   ├── storage/                 ← Public storage (images, etc.)
│   │   └── index.php                ← Laravel entry point
│   ├── storage/
│   │   └── logs/laravel.log         ← Application logs
│   ├── .env                         ← Production environment config
│   └── artisan                      ← Laravel CLI
├── fao/                             ← Another Laravel project (unrelated)
└── public_html/                     ← WordPress site (u-lits.com)
```

### Cron Jobs
No Laravel scheduler cron job is currently configured. If needed in the future:
```bash
# Add via cPanel > Cron Jobs, or:
crontab -e
# Add this line:
* * * * * cd /home/ulitscom/katogo && php artisan schedule:run >> /dev/null 2>&1
```

---

## Git Remote Configuration (Server)

The server has two remotes. **Always use `dev`** for deployments:

| Remote | URL | Purpose |
|--------|-----|---------|
| `dev` | `https://github.com/muhindo-dev/katogo.git` | **Active development** — push/pull here |
| `origin` | `https://github.com/mubahood/katogo` | Legacy/alternate repo — do not use for deploy |

Your local machine uses `origin` → `muhindo-dev/katogo.git`, which is the same repo as the server's `dev`.

---

## Troubleshooting

### SSH Connection Refused / Timed Out
- **Wrong port**: Must use `-p 21098`, NOT port 22
- **Rate limited**: cPanel limits SSH login attempts. Wait 60 seconds.
- **Firewall**: cPanel may block IPs after failed attempts. Use cPanel web UI instead.

### "Permission denied" on SSH
- Password is `256@Anjane#` — note the `@` and `#` characters
- Wrap in single quotes when using sshpass: `sshpass -p '256@Anjane#'`

### Changes Not Visible After Deploy
1. Did you clear caches? Run all three: `cache:clear`, `config:clear`, `route:clear`
2. Check if LiteSpeed cache is enabled. Purge via cPanel > LiteSpeed Web Cache Manager
3. Check `php artisan route:list` to verify your routes are registered

### Laravel Error 500
```bash
# Check the log
tail -100 /home/ulitscom/katogo/storage/logs/laravel.log

# Check PHP syntax
php -l app/Http/Controllers/YourFile.php

# Check permissions
ls -la storage/
chmod -R 775 storage/ bootstrap/cache/
```

### Git Pull Conflicts on Server
```bash
# If the server has local changes conflicting with your pull:
cd /home/ulitscom/katogo
git stash                    # Stash local changes
git pull dev main            # Pull latest
git stash pop                # Re-apply local changes (if needed)
```

### Database Migration Failed
```bash
# Check migration status
php artisan migrate:status

# Rollback last migration
php artisan migrate:rollback --step=1

# Re-run
php artisan migrate --force
```

---

## cPanel Web Interface

You can also deploy via the **cPanel Git Version Control** UI:

1. Log into cPanel (check your hosting provider for URL)
2. Navigate to **Git™ Version Control**
3. Find repository: `/home/ulitscom/katogo` on branch `main`
4. Click **Pull or Deploy** tab
5. Click **Update from Remote** to pull latest code

After pulling via cPanel, you still need to SSH in to clear caches.

---

## Available Test Scripts

| Script | Purpose | Run |
|--------|---------|-----|
| `test_subscription_improvements.php` | All subscription fixes (26 tests) | `php test_subscription_improvements.php` |
| `test_payment_flow.php` | E2E Pesapal payment initialization | `php test_payment_flow.php` |
| `test_payment_status_checker.php` | Payment status checker service | `php test_payment_status_checker.php` |

Always run these after deploying subscription-related changes.
