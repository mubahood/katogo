#!/bin/bash
# Katogo queue worker — safe single-instance runner for shared hosting (Namecheap)
#
# Add to cPanel cron: */5 * * * * /home/USERNAME/public_html/movies/scripts/queue-worker.sh
# (adjust path to match actual home directory)
#
# How it works:
#   - flock ensures only ONE instance runs at a time (no overlapping workers)
#   - --max-time=270 makes the worker stop after 4.5 min so cron can restart it cleanly
#   - --timeout=90 matches SolveFLWCaptchaJob timeout
#   - --sleep=5 polls every 5s when queue is empty (saves CPU on shared hosting)
#   - On job failure the worker keeps running (failed jobs go to failed_jobs table)

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LOCK_FILE="/tmp/katogo-queue-worker.lock"
LOG_FILE="$APP_DIR/storage/logs/queue-worker.log"
PHP_BIN="${PHP_BIN:-php}"

# Rotate log if > 10 MB
if [ -f "$LOG_FILE" ] && [ "$(stat -c%s "$LOG_FILE" 2>/dev/null || stat -f%z "$LOG_FILE" 2>/dev/null)" -gt 10485760 ]; then
    mv "$LOG_FILE" "${LOG_FILE}.old"
fi

exec flock -n "$LOCK_FILE" \
    "$PHP_BIN" "$APP_DIR/artisan" queue:work \
        --queue=default,transfers,notifications \
        --timeout=90 \
        --sleep=5 \
        --tries=3 \
        --max-time=270 \
        --memory=256 \
        >> "$LOG_FILE" 2>&1
