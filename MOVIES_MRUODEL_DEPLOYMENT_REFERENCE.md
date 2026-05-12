# movies.mruodel.com Successful Deployment Reference

## Purpose
This document captures the exact deployment flow that successfully reflected Fix Lab changes on the live site:
- https://movies.mruodel.com/subscriptions

## Critical Lesson
The main reason earlier deploys did not reflect online was deploying to the wrong server/path.

- Not the final live target for this domain: `162.0.232.59:/home/ulitscom/katogo`
- Actual live target serving `movies.mruodel.com`: `209.74.87.69:/home/muhindo/movies`

Always verify the serving host/path first before deploying.

---

## Pre-Deploy Checks (Local)
Run from local project:

```bash
cd /Applications/MAMP/htdocs/katogo
git status --short
php -l app/Admin/Controllers/SubscriptionController.php
php -l app/Admin/routes.php
```

If syntax is clean, continue.

---

## 1) Verify Which Server/Path Is Actually Live
Use SSH and quick checks to confirm the app serving `movies.mruodel.com`:

```bash
sshpass -p '3l3I74nNabgm0UE5OA' ssh -o StrictHostKeyChecking=no -o PreferredAuthentications=password root@209.74.87.69 '
cd /home/muhindo/movies
pwd
ls -la public | head -40
curl -kIs https://movies.mruodel.com/subscriptions | head -n 10
'
```

Expected:
- Laravel project structure present
- `/subscriptions` returns admin/login redirect headers (not unrelated content)

---

## 2) Backup Live Files Before Overwrite
Create timestamped backups on live host:

```bash
ts=$(date +%Y%m%d%H%M%S)
sshpass -p '3l3I74nNabgm0UE5OA' ssh -o StrictHostKeyChecking=no -o PreferredAuthentications=password root@209.74.87.69 "
mkdir -p /home/muhindo/movies/.deploy_backups/$ts/app/Admin/Controllers
mkdir -p /home/muhindo/movies/.deploy_backups/$ts/app/Admin
mkdir -p /home/muhindo/movies/.deploy_backups/$ts/public/assets
cp -f /home/muhindo/movies/app/Admin/Controllers/SubscriptionController.php /home/muhindo/movies/.deploy_backups/$ts/app/Admin/Controllers/SubscriptionController.php
cp -f /home/muhindo/movies/app/Admin/routes.php /home/muhindo/movies/.deploy_backups/$ts/app/Admin/routes.php
if [ -f /home/muhindo/movies/public/assets/sub-fix-modal.js ]; then
  cp -f /home/muhindo/movies/public/assets/sub-fix-modal.js /home/muhindo/movies/.deploy_backups/$ts/public/assets/sub-fix-modal.js
fi
printf 'BACKUP_TS:%s\n' "$ts"
"
```

---

## 3) Copy Updated Files to Live Host
Files synced during successful deployment:
- `app/Admin/Controllers/SubscriptionController.php`
- `app/Admin/routes.php`
- `public/assets/sub-fix-modal.js`

```bash
sshpass -p '3l3I74nNabgm0UE5OA' scp -o StrictHostKeyChecking=no -o PreferredAuthentications=password \
  /Applications/MAMP/htdocs/katogo/app/Admin/Controllers/SubscriptionController.php \
  root@209.74.87.69:/home/muhindo/movies/app/Admin/Controllers/SubscriptionController.php

sshpass -p '3l3I74nNabgm0UE5OA' scp -o StrictHostKeyChecking=no -o PreferredAuthentications=password \
  /Applications/MAMP/htdocs/katogo/app/Admin/routes.php \
  root@209.74.87.69:/home/muhindo/movies/app/Admin/routes.php

sshpass -p '3l3I74nNabgm0UE5OA' scp -o StrictHostKeyChecking=no -o PreferredAuthentications=password \
  /Applications/MAMP/htdocs/katogo/public/assets/sub-fix-modal.js \
  root@209.74.87.69:/home/muhindo/movies/public/assets/sub-fix-modal.js
```

---

## 4) Validate + Clear Caches on Live

```bash
sshpass -p '3l3I74nNabgm0UE5OA' ssh -o StrictHostKeyChecking=no -o PreferredAuthentications=password root@209.74.87.69 '
cd /home/muhindo/movies
php -l app/Admin/Controllers/SubscriptionController.php
php -l app/Admin/routes.php
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
'
```

---

## 5) Verify the Fix Lab Markers Exist on Live

```bash
sshpass -p '3l3I74nNabgm0UE5OA' ssh -o StrictHostKeyChecking=no -o PreferredAuthentications=password root@209.74.87.69 '
cd /home/muhindo/movies
grep -n "js-sub-fix-lab\|flex-direction:column\|column('"'"'fix_lab_action'"'"', '"'"'Fix Lab'"'"')\|file_get_contents(public_path('"'"'assets/sub-fix-modal.js'"'"'))" app/Admin/Controllers/SubscriptionController.php | head -50
ls -la public/assets/sub-fix-modal.js
'
```

Expected:
- `js-sub-fix-lab` found
- `flex-direction:column` found
- `column('fix_lab_action', 'Fix Lab')` found
- inline loader `file_get_contents(public_path('assets/sub-fix-modal.js'))` found
- JS file present in `public/assets`

---

## 6) Browser-Side Confirmation
After deployment and cache clear:
1. Open https://movies.mruodel.com/subscriptions
2. Hard refresh (`Cmd+Shift+R`)
3. Confirm Fix Lab appears (dedicated column and/or actions area)

---

## Known Pitfalls and Fixes

### Pitfall A: Deploying to wrong host/path
Symptom:
- Code changes exist on one server but do not appear online.

Fix:
- Validate the actual host serving the domain and deploy there.

### Pitfall B: New static files returning 404
Symptom:
- New file exists physically but URL still returns 404.

Fix used:
- Load JS inline from Laravel controller:

```php
Admin::script(file_get_contents(public_path('assets/sub-fix-modal.js')));
```

This bypasses static asset serving/caching edge issues.

### Pitfall C: `git pull` blocked by local modified/untracked files
Symptom:
- Merge aborts with "would be overwritten" errors.

Fix options:
- Stash/commit local changes, or
- Remove conflicting untracked files before pull, or
- Use direct SCP of known files (used successfully here).

---

## Minimal One-Shot Sync Command (Used)

```bash
sshpass -p '3l3I74nNabgm0UE5OA' scp -o StrictHostKeyChecking=no -o PreferredAuthentications=password \
  /Applications/MAMP/htdocs/katogo/app/Admin/Controllers/SubscriptionController.php \
  root@209.74.87.69:/home/muhindo/movies/app/Admin/Controllers/SubscriptionController.php && \
sshpass -p '3l3I74nNabgm0UE5OA' ssh -o StrictHostKeyChecking=no -o PreferredAuthentications=password root@209.74.87.69 "
cd /home/muhindo/movies &&
php -l app/Admin/Controllers/SubscriptionController.php &&
php artisan view:clear && php artisan cache:clear && php artisan config:clear && php artisan route:clear &&
grep -n \"column('fix_lab_action', 'Fix Lab')\\|js-sub-fix-lab\" app/Admin/Controllers/SubscriptionController.php | head -20
"
```

---

## Security Note
Credentials are currently used via `sshpass` in command history. Rotate credentials and migrate to SSH keys where possible.
