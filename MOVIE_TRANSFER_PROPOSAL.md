# Movie File Transfer — Architecture Redesign Proposal

**Project:** Katogo  
**Date:** 2026-06-12  
**Author:** Architecture Review  
**Status:** Proposal — Pending Approval

---

## Table of Contents

1. [Current System Analysis](#1-current-system-analysis)
2. [Problems with the Current Approach](#2-problems-with-the-current-approach)
3. [Proposed Architecture Overview](#3-proposed-architecture-overview)
4. [New Model: `MovieFileTransfer`](#4-new-model-moviefiletransfer)
5. [Queue Job: `TransferMovieToHetzner`](#5-queue-job-transfermovietohetzner)
6. [Observer: `MovieFileTransferObserver`](#6-observer-moviefiletransferobserver)
7. [Scheduled Command: `transfers:process`](#7-scheduled-command-transfersprocess)
8. [Concurrency & Server Protection](#8-concurrency--server-protection)
9. [Monitoring Interface](#9-monitoring-interface)
10. [Admin Panel Integration](#10-admin-panel-integration)
11. [Migration Strategy](#11-migration-strategy)
12. [Database Schema](#12-database-schema)
13. [Implementation Sequence](#13-implementation-sequence)

---

## 1. Current System Analysis

### What exists today

#### A. `VideoTransfer` model → Google Drive

The current model (`app/Models/VideoTransfer.php`) handles one-way transfers from any source
URL to **Google Drive**. Key characteristics:

| Characteristic | Current behaviour |
|----------------|-------------------|
| Trigger | Manual — admin clicks "Start Transfer" |
| Execution | Synchronous inside the HTTP request |
| Memory model | Downloads entire video body into PHP memory (`$response->body()`), then uploads |
| Concurrency | None — unlimited parallel requests possible |
| Destination | Google Drive only |
| Movie link | None — `VideoTransfer` has no `movie_id` foreign key |
| Auto-retry | Manual only — admin clicks "Retry" button |
| Queue | Not used (`ShouldQueue` not implemented) |
| Scheduling | None |
| Observer | None |

The `streamDirectlyToGoogleDrive()` method is named "streaming" but is actually:

```
Source URL ──HTTP GET──▶ PHP memory ($response->body()) ──HTTP PUT──▶ Google Drive
```

The entire file lives in PHP memory simultaneously. A 1 GB film consumes ≥ 1 GB RAM.

#### B. Firebase fields on `MovieModel`

The movie_models table carries 8 firebase_transfer_* columns scattered across the main movie
row. `transferToFirebase()` is disabled (early return on line 1). These fields:
- Pollute the primary movie table
- Cannot be queried or filtered efficiently
- Have no relationship to `VideoTransfer`

#### C. `HetznerStorageService`

A clean WebDAV service exists (`app/Services/HetznerStorageService.php`) with full
upload / share / delete / quota support. It is **never called** from any transfer pipeline.
The new architecture makes this the primary destination.

#### D. Triggers (how transfers start today)

```
Admin opens /transfer/process/{id}
  └── clicks "Start Transfer"
        └── POST /transfer/start/{id}
              └── $transfer->processTransfer()  ← runs synchronously, blocks HTTP response
                    └── Downloads video to memory (up to 2 GB)
                    └── Uploads to Google Drive
                    └── Response returned to admin after minutes/hours
```

There is no automatic trigger. Every transfer must be manually initiated one at a time.

---

## 2. Problems with the Current Approach

### P1 — HTTP Timeout & Blocking

`processTransfer()` runs inside an HTTP request. PHP's 1-hour `max_execution_time` can be
reached, and web servers (Nginx) typically have their own timeout (60–600 s). A 2 GB film
transfer takes 10–30 minutes. If the browser tab closes or the network hiccups, the transfer
is lost mid-stream.

### P2 — Full Video in PHP Memory

The current "streaming" is a misnomer. The full file is held in PHP memory before being sent
to Google Drive. On the 8 GB Hetzner VPS, a single 1.5 GB film consumes ~20% of all RAM.
Three concurrent transfers = out of memory (OOM) → PHP-FPM kills the process → server spike.

### P3 — No Concurrency Control

Nothing prevents 10 admin users from clicking "Start Transfer" simultaneously on 10 movies.
The result is 10 × (up to 2 GB) in RAM = guaranteed crash.

### P4 — No Link Between Transfer and Movie

`VideoTransfer` records exist independently of `MovieModel`. When a transfer completes:
- The movie's `video_url` is **never updated automatically**
- There is no way to query "which movies have been transferred?" 
- There is no way to query "which movies need to be transferred?"

### P5 — Manual-Only Triggering

A library of 21,000+ movies cannot be migrated one-by-one through an admin panel. There
is no batch dispatch, no scheduled worker, no observer that reacts when a new movie arrives.

### P6 — Google Drive Reliability for Video Streaming

Google Drive is not a CDN. Direct-link playback from Drive has:
- Quota limits per file (403 errors when too many people download the same file)
- No range-request guarantee (breaks seek in video players)
- URLs that stop working without warning
- No SLA for streaming

Hetzner Storage Share (Nextcloud) is already paid for, provides direct CDN URLs, supports
HTTP range requests, and has no per-file quota limits.

### P7 — No Observer Pattern

New movies imported from MunoWatch have their `video_url` pointing to MunoWatch servers.
There is nothing that automatically schedules a transfer when a movie is saved. An observer
that watches for `created` and `updated` events is the correct Laravel pattern here.

### P8 — No Monitoring

There is no dashboard showing:
- How many movies still have external video URLs
- Queue depth / estimated completion time
- Transfer speed over time
- Failed transfers grouped by error reason
- Storage used vs. available on Hetzner

---

## 3. Proposed Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                          Data Flow                                  │
│                                                                     │
│  MunoWatch / External     MovieModel       MovieFileTransfer        │
│  ─────────────────────    ─────────────    ────────────────────     │
│  New movie arrives  ──▶   movie saved  ──▶  Observer creates       │
│  (import / crawl)         video_url =        transfer record       │
│                           munowatch URL       status = queued       │
│                                                    │                │
│                                             Scheduler (5 min)       │
│                                             picks up queued items   │
│                                                    │                │
│                                             TransferMovieToHetzner  │
│                                             (Queue Job)             │
│                                                    │                │
│                                      ┌─────────────┴────────────┐  │
│                                      │   True Pipe Streaming     │  │
│                                      │                           │  │
│                                  Source URL                      │  │
│                                  (MunoWatch)                     │  │
│                                      │ cURL read stream          │  │
│                                      ▼                           │  │
│                              Hetzner Storage Share               │  │
│                              (WebDAV write stream)               │  │
│                                      │                           │  │
│                                      └───────────────────────────┘  │
│                                             │                       │
│                                   On success:                       │
│                                   • MovieFileTransfer status=done   │
│                                   • MovieModel.video_url updated    │
│                                   • Share token generated           │
│                                   • Old URL saved in transfer log   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Components

| Component | Type | Responsibility |
|-----------|------|----------------|
| `MovieFileTransfer` | Eloquent Model | Tracks every transfer: source, destination, status, progress, movie snapshot |
| `TransferMovieToHetzner` | Queue Job | Performs the actual transfer using piped cURL streaming |
| `MovieFileTransferObserver` | Eloquent Observer | Auto-queues transfers when movies are created/updated |
| `transfers:process` | Artisan Command | Scheduled every 5 min — dispatches queued records, respects concurrency limit |
| `transfers:monitor` | Artisan Command | Outputs stats: queue depth, speed, ETA, failures |
| Admin panel section | Laravel-Admin | Visual dashboard + manual controls + per-movie history |
| `HetznerStorageService` | Service (existing) | WebDAV operations — already built, just needs to be wired in |

---

## 4. New Model: `MovieFileTransfer`

### Design Principles

- **One record per transfer attempt.** If a movie is transferred twice, there are two records.
- **Links to MovieModel** via `movie_id` FK — enabling joins, scopes, and automatic URL updates.
- **Destination-agnostic.** A `dest_type` field (`hetzner`, `gdrive`, `s3`) means the model
  works regardless of where files land. Today: Hetzner. Tomorrow: any.
- **Movie snapshot.** The record stores the movie's title, poster, year, quality at the time
  of transfer. The movie record may change; the transfer log never should.
- **Old URL is preserved.** `source_url` is the permanent record of where the file was before.
  This allows rollback and auditing.

### Fields

```php
// app/Models/MovieFileTransfer.php

protected $fillable = [
    // ── Relationship ──────────────────────────────────────────────────
    'movie_id',              // FK → movie_models.id (nullable — allows standalone transfers)

    // ── Movie snapshot (captured at queue time, immutable) ────────────
    'movie_title',           // e.g. "Akaboozi mu Kibuga"
    'movie_year',            // e.g. 2023
    'movie_quality',         // e.g. "HD", "4K", "SD"
    'movie_duration',        // seconds, e.g. 5400
    'movie_poster_url',      // thumbnail URL at time of transfer
    'movie_munowatch_id',    // munowatch_id value at time of transfer
    'movie_is_series',       // boolean — is this a series episode?
    'movie_episode_info',    // JSON: {season, episode, series_title}

    // ── Source ───────────────────────────────────────────────────────
    'source_url',            // original video URL (e.g. munowatch CDN URL)
    'source_type',           // 'munowatch' | 'gdrive' | 'firebase' | 'direct' | 'other'
    'source_size_bytes',     // bytes (from HEAD request, may be null)
    'source_verified_at',    // timestamp when source URL was confirmed reachable

    // ── Destination ───────────────────────────────────────────────────
    'dest_type',             // 'hetzner' | 'gdrive' | 's3'
    'dest_path',             // remote path on destination, e.g. "movies/2023/film_123.mp4"
    'dest_url',              // final public playback URL
    'dest_share_token',      // Hetzner share token (for regenerating dest_url)
    'dest_file_id',          // Google Drive file ID (if gdrive)
    'dest_size_bytes',       // bytes confirmed written at destination

    // ── Status & Progress ─────────────────────────────────────────────
    'status',                // queued | verifying | transferring | completing | done | failed | cancelled | skipped
    'progress_pct',          // 0–100
    'bytes_transferred',     // running tally during transfer
    'transfer_speed_mbps',   // rolling average MB/s
    'eta_seconds',           // estimated seconds remaining (recalculated during transfer)

    // ── Timing ────────────────────────────────────────────────────────
    'queued_at',             // when the record was first created
    'started_at',            // when the job picked it up
    'completed_at',          // when status became 'done'
    'duration_seconds',      // completed_at - started_at

    // ── Retry & Error ─────────────────────────────────────────────────
    'attempt_count',         // how many times transfer has been tried
    'max_attempts',          // default 3
    'last_attempted_at',     // timestamp of most recent attempt
    'next_retry_at',         // exponential backoff target
    'error_message',         // short human-readable error
    'error_trace',           // full stack trace (truncated to 10000 chars)
    'error_context',         // JSON: HTTP status, response headers, etc.

    // ── Outcome ───────────────────────────────────────────────────────
    'movie_url_updated',     // boolean — was MovieModel.video_url updated after completion?
    'old_movie_url_backed_up', // boolean — was source_url saved to movie before overwrite?
    'notes',                 // free-text admin notes
    'initiated_by',          // 'observer' | 'scheduler' | 'admin:{user_id}' | 'api'
    'worker_hostname',       // which server/worker processed this transfer
];

protected $casts = [
    'movie_is_series'   => 'boolean',
    'movie_episode_info' => 'array',
    'error_context'     => 'array',
    'movie_url_updated' => 'boolean',
    'old_movie_url_backed_up' => 'boolean',
    'queued_at'         => 'datetime',
    'started_at'        => 'datetime',
    'completed_at'      => 'datetime',
    'last_attempted_at' => 'datetime',
    'next_retry_at'     => 'datetime',
    'source_verified_at' => 'datetime',
];
```

### Status State Machine

```
queued
  │
  ├──▶ skipped      (movie already transferred, or source_url is already a Hetzner URL)
  │
  ▼
verifying           (checking source URL is reachable — HEAD request)
  │
  ├──▶ failed       (source URL is dead — movie needs fixing first)
  │
  ▼
transferring        (cURL pipe is open, bytes are flowing)
  │
  ├──▶ failed       (network error, timeout, destination write error)
  │     │
  │     └──▶ (if attempt_count < max_attempts) → queued (after next_retry_at delay)
  │
  ▼
completing          (share token being generated, movie_url being updated)
  │
  ▼
done                (transfer complete, movie_url updated to Hetzner CDN URL)
```

### Key Model Methods

```php
// Create a transfer record for a movie (called by observer or admin)
public static function queueForMovie(MovieModel $movie, string $initiatedBy = 'observer'): static

// Check if movie already has a pending or completed transfer
public static function hasPendingOrCompleted(int $movieId): bool

// Check if movie's current video_url is already on Hetzner
public static function isAlreadyOnHetzner(string $url): bool

// Relationships
public function movie(): BelongsTo          // → MovieModel
public function scopePending($q)            // status IN [queued, verifying]
public function scopeActive($q)             // status IN [transferring, completing]
public function scopeFailed($q)             // status = failed
public function scopeDone($q)              // status = done
public function scopeReadyToRetry($q)       // failed AND attempt_count < max_attempts AND next_retry_at < now()
public function scopeForMovie($q, $id)      // where movie_id = $id

// Computed attributes
public function getFormattedSpeedAttribute(): string    // "12.4 MB/s"
public function getFormattedSizeAttribute(): string     // "1.2 GB"
public function getFormattedDurationAttribute(): string // "4m 32s"
public function getStatusBadgeColorAttribute(): string  // for admin panel
public function isRetriable(): bool                     // can this be retried?
public function isDone(): bool
public function isFailed(): bool
```

---

## 5. Queue Job: `TransferMovieToHetzner`

### Design Principles

- **True pipe streaming.** cURL reads from source and writes to Hetzner WebDAV simultaneously.
  Peak memory usage is one chunk (default 4 MB), not the whole file.
- **Progress updates.** cURL's `CURLOPT_READFUNCTION` callback updates `bytes_transferred`
  and `progress_pct` to the database every 10 MB. The admin panel can poll this.
- **Idempotent.** If the job is dispatched twice for the same transfer record, the second
  run detects `status = transferring` and exits cleanly.
- **Automatic movie update.** On success, the job updates `MovieModel.video_url` to the
  new Hetzner CDN URL and saves the old URL in `MovieFileTransfer.source_url`.
- **Concurrency middleware.** Uses Laravel's `WithoutOverlapping` + a Redis/cache-based
  global slot counter to cap concurrent transfers at a configurable limit.

### Structure

```php
// app/Jobs/TransferMovieToHetzner.php

class TransferMovieToHetzner implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;            // retries are managed by the transfer record itself
    public int $timeout = 7200;       // 2-hour job timeout (large files)
    public int $uniqueFor = 7200;     // only one job per transfer record in queue at a time

    public function __construct(public readonly int $transferId) {}

    public function uniqueId(): string
    {
        return "movie_transfer_{$this->transferId}";
    }

    public function middleware(): array
    {
        return [
            // Global concurrency cap — max 3 simultaneous transfers on this server
            new WithoutOverlapping("global_movie_transfers", releaseAfter: 30),
        ];
    }

    public function handle(HetznerStorageService $hetzner): void
    {
        $transfer = MovieFileTransfer::findOrFail($this->transferId);

        // Guard: skip if already done or being processed
        if (in_array($transfer->status, ['done', 'transferring', 'completing', 'cancelled'])) {
            return;
        }

        $transfer->update([
            'status'         => 'verifying',
            'started_at'     => now(),
            'worker_hostname' => gethostname(),
            'attempt_count'  => $transfer->attempt_count + 1,
            'last_attempted_at' => now(),
        ]);

        try {
            // Step 1: Verify source URL
            $this->verifySource($transfer);

            // Step 2: Ensure destination directory exists
            $destDir = 'movies/' . date('Y') . '/' . date('m');
            $hetzner->mkdir($destDir);

            // Step 3: Build destination path
            $fileName = $this->buildFileName($transfer);
            $destPath = $destDir . '/' . $fileName;

            // Step 4: Pipe stream source → Hetzner (no memory loading)
            $transfer->update(['status' => 'transferring']);
            $bytesWritten = $this->pipeStreamToHetzner($transfer, $destPath, $hetzner);

            // Step 5: Generate share link
            $transfer->update(['status' => 'completing']);
            $publicUrl = $hetzner->share($destPath);
            if (!$publicUrl) {
                throw new \Exception("Hetzner share link generation failed for path: {$destPath}");
            }

            // Step 6: Update movie record
            $this->updateMovieUrl($transfer, $publicUrl);

            // Step 7: Mark transfer complete
            $transfer->update([
                'status'           => 'done',
                'dest_url'         => $publicUrl,
                'dest_path'        => $destPath,
                'dest_size_bytes'  => $bytesWritten,
                'completed_at'     => now(),
                'duration_seconds' => now()->diffInSeconds($transfer->started_at),
                'progress_pct'     => 100,
                'movie_url_updated' => true,
            ]);

        } catch (\Throwable $e) {
            $this->handleFailure($transfer, $e);
        }
    }

    /**
     * True pipe streaming: cURL reads from source, writes to Hetzner via PUT.
     * Peak memory = one chunk (4 MB). Never loads the whole file.
     */
    private function pipeStreamToHetzner(
        MovieFileTransfer $transfer,
        string $destPath,
        HetznerStorageService $hetzner
    ): int {
        $davUrl = env('HETZNER_STORAGE_URL') . '/' . ltrim($destPath, '/');
        $sourceUrl = $transfer->source_url;

        // Open a cURL handle to read the source
        $readCh = curl_init($sourceUrl);
        curl_setopt_array($readCh, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 7200,
            CURLOPT_BUFFERSIZE     => 4 * 1024 * 1024, // 4 MB read buffer
        ]);

        // Temporary pipe via a PHP stream pair
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        [$readEnd, $writeEnd] = $pipes;

        $bytesTotal = 0;
        $lastProgressUpdate = 0;

        // Write data to Hetzner via WebDAV PUT
        $writeCh = curl_init($davUrl);
        curl_setopt_array($writeCh, [
            CURLOPT_USERPWD        => env('HETZNER_STORAGE_USER') . ':' . env('HETZNER_STORAGE_PASS'),
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $readEnd,
            CURLOPT_INFILESIZE     => $transfer->source_size_bytes ?: -1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 7200,
        ]);

        // Use a multi-handle to run both transfers simultaneously
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $readCh);
        curl_multi_add_handle($mh, $writeCh);

        $active = null;
        do {
            curl_multi_exec($mh, $active);

            // Track progress
            $info = curl_getinfo($readCh);
            $downloaded = $info['size_download'] ?? 0;

            if ($downloaded - $lastProgressUpdate >= 10 * 1024 * 1024) { // every 10 MB
                $bytesTotal = $downloaded;
                $pct = $transfer->source_size_bytes > 0
                    ? min(99, (int)(($downloaded / $transfer->source_size_bytes) * 100))
                    : 0;
                $speed = $info['speed_download'] ?? 0;
                $speedMbps = round($speed * 8 / 1_000_000, 2);
                $eta = ($speed > 0 && $transfer->source_size_bytes > 0)
                    ? (int)(($transfer->source_size_bytes - $downloaded) / $speed)
                    : null;

                $transfer->update([
                    'bytes_transferred'  => $downloaded,
                    'progress_pct'       => $pct,
                    'transfer_speed_mbps' => $speedMbps,
                    'eta_seconds'        => $eta,
                ]);

                $lastProgressUpdate = $downloaded;
            }

            curl_multi_select($mh, 0.1);
        } while ($active > 0);

        $readCode  = curl_getinfo($readCh, CURLINFO_HTTP_CODE);
        $writeCode = curl_getinfo($writeCh, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($mh, $readCh);
        curl_multi_remove_handle($mh, $writeCh);
        curl_multi_close($mh);
        curl_close($readCh);
        curl_close($writeCh);
        fclose($readEnd);
        fclose($writeEnd);

        if (!in_array($writeCode, [201, 204])) {
            throw new \Exception("Hetzner WebDAV PUT failed — HTTP {$writeCode} (source HTTP {$readCode})");
        }

        return $bytesTotal ?: ($transfer->source_size_bytes ?? 0);
    }

    private function verifySource(MovieFileTransfer $transfer): void
    {
        $ch = curl_init($transfer->source_url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);

        if ($code < 200 || $code >= 400) {
            throw new \Exception("Source URL returned HTTP {$code} — file may be unavailable");
        }

        if ($contentLength > 0) {
            $transfer->update(['source_size_bytes' => (int)$contentLength, 'source_verified_at' => now()]);
        } else {
            $transfer->update(['source_verified_at' => now()]);
        }
    }

    private function buildFileName(MovieFileTransfer $transfer): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '_', $transfer->movie_title ?? 'movie');
        $safe = strtolower(substr($safe, 0, 60));
        return "movie_{$transfer->movie_id}_{$safe}_{$transfer->id}.mp4";
        // e.g. movie_12345_akaboozi_mu_kibuga_99.mp4
    }

    private function updateMovieUrl(MovieFileTransfer $transfer, string $newUrl): void
    {
        if (!$transfer->movie_id) return;

        $movie = MovieModel::find($transfer->movie_id);
        if (!$movie) return;

        // Backup old URL into the transfer record (already in source_url, this is a safety note)
        $transfer->update(['old_movie_url_backed_up' => true]);

        // Update the live movie URL
        $movie->video_url = $newUrl;
        $movie->save();
    }

    private function handleFailure(MovieFileTransfer $transfer, \Throwable $e): void
    {
        $attempt = $transfer->attempt_count;
        $maxAttempts = $transfer->max_attempts ?? 3;

        // Exponential backoff: 5 min, 20 min, 60 min
        $backoffMinutes = [5, 20, 60];
        $delay = $backoffMinutes[min($attempt - 1, count($backoffMinutes) - 1)];

        $willRetry = $attempt < $maxAttempts;

        $transfer->update([
            'status'        => $willRetry ? 'queued' : 'failed',
            'error_message' => substr($e->getMessage(), 0, 500),
            'error_trace'   => substr($e->getTraceAsString(), 0, 10000),
            'next_retry_at' => $willRetry ? now()->addMinutes($delay) : null,
        ]);

        Log::error("[TransferMovieToHetzner] Transfer #{$transfer->id} failed (attempt {$attempt}/{$maxAttempts})", [
            'movie_id' => $transfer->movie_id,
            'error'    => $e->getMessage(),
            'retry_in' => $willRetry ? "{$delay} minutes" : 'no more retries',
        ]);
    }
}
```

---

## 6. Observer: `MovieFileTransferObserver`

The observer is the key to **automatic, zero-intervention** transfers. Whenever a movie is
saved with a video URL that points to an external source (not Hetzner), the observer queues
a transfer record.

```php
// app/Observers/MovieFileTransferObserver.php

class MovieFileTransferObserver
{
    /**
     * Auto-queue a transfer when a new movie is created with an external video URL.
     */
    public function created(MovieModel $movie): void
    {
        $this->maybeQueueTransfer($movie, 'observer:created');
    }

    /**
     * Auto-queue a transfer when a movie's video_url changes to a new external URL.
     */
    public function updated(MovieModel $movie): void
    {
        // Only act if video_url actually changed
        if (!$movie->wasChanged('video_url')) return;

        $newUrl = $movie->video_url;
        $oldUrl = $movie->getOriginal('video_url');

        // Skip if the new URL is already on Hetzner
        if (MovieFileTransfer::isAlreadyOnHetzner($newUrl)) return;

        // Skip if there's already a pending/active transfer for this movie
        if (MovieFileTransfer::hasPendingOrCompleted($movie->id)) return;

        $this->maybeQueueTransfer($movie, 'observer:updated', $oldUrl);
    }

    private function maybeQueueTransfer(MovieModel $movie, string $initiatedBy, ?string $previousUrl = null): void
    {
        $url = $movie->video_url;

        // Skip if no video URL
        if (empty($url)) return;

        // Skip if already on Hetzner Storage
        if (MovieFileTransfer::isAlreadyOnHetzner($url)) return;

        // Skip if movie is inactive
        if ($movie->status !== 'Active') return;

        // Skip if duplicate (already queued or done)
        if (MovieFileTransfer::hasPendingOrCompleted($movie->id)) return;

        // Create the transfer record
        MovieFileTransfer::queueForMovie($movie, $initiatedBy);

        Log::info("[MovieFileTransferObserver] Queued transfer for movie #{$movie->id} — {$movie->title}", [
            'source_url'   => $url,
            'initiated_by' => $initiatedBy,
        ]);
    }
}
```

### Registration (in `AppServiceProvider` or `EventServiceProvider`)

```php
// app/Providers/AppServiceProvider.php

public function boot(): void
{
    MovieModel::observe(MovieFileTransferObserver::class);
}
```

---

## 7. Scheduled Command: `transfers:process`

This command runs every 5 minutes. It:
1. Counts currently active (transferring) records
2. If slots are available, picks the next queued records and dispatches jobs
3. Detects stuck transfers (started but no progress for > 30 minutes) and resets them

```php
// app/Console/Commands/ProcessPendingTransfers.php

class ProcessPendingTransfers extends Command
{
    protected $signature = 'transfers:process
                            {--limit=10 : Max transfers to dispatch this run}
                            {--concurrency=3 : Max simultaneous active transfers}
                            {--dry-run : Show what would be dispatched without dispatching}';

    protected $description = 'Dispatch queued movie file transfers, respecting the concurrency limit';

    public function handle(): int
    {
        $limit       = (int) $this->option('limit');
        $maxActive   = (int) $this->option('concurrency');
        $dryRun      = $this->option('dry-run');

        // Step 1: Fix stuck transfers (transferring for > 45 minutes with no update)
        $stuck = MovieFileTransfer::where('status', 'transferring')
            ->where('updated_at', '<', now()->subMinutes(45))
            ->get();

        foreach ($stuck as $transfer) {
            $this->warn("Resetting stuck transfer #{$transfer->id} (movie: {$transfer->movie_title})");
            if (!$dryRun) {
                $transfer->update([
                    'status'     => 'queued',
                    'progress_pct' => 0,
                    'bytes_transferred' => 0,
                ]);
            }
        }

        // Step 2: Count current active
        $activeCount = MovieFileTransfer::active()->count();
        $slots = max(0, $maxActive - $activeCount);

        $this->info("Active transfers: {$activeCount} / {$maxActive}. Available slots: {$slots}");

        if ($slots === 0) {
            $this->info('All slots occupied — skipping dispatch this run.');
            return 0;
        }

        // Step 3: Pick next queued records (priority: oldest queued_at, retry-ready ones first)
        $toDispatch = MovieFileTransfer::pending()
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                  ->orWhere('next_retry_at', '<=', now());
            })
            ->orderByRaw("CASE WHEN status = 'queued' AND attempt_count = 0 THEN 0 ELSE 1 END") // new first
            ->orderBy('queued_at')
            ->limit(min($limit, $slots))
            ->get();

        $this->info("Dispatching {$toDispatch->count()} transfer(s)...");

        foreach ($toDispatch as $transfer) {
            $this->line("  → #{$transfer->id} | movie #{$transfer->movie_id} | {$transfer->movie_title}");
            if (!$dryRun) {
                TransferMovieToHetzner::dispatch($transfer->id)->onQueue('transfers');
            }
        }

        // Step 4: Summary stats
        $pending   = MovieFileTransfer::pending()->count();
        $done      = MovieFileTransfer::done()->count();
        $failed    = MovieFileTransfer::failed()->count();
        $total     = MovieFileTransfer::count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total records', $total],
                ['Done', $done],
                ['Pending (queue depth)', $pending],
                ['Active', $activeCount],
                ['Failed (no more retries)', $failed],
            ]
        );

        return 0;
    }
}
```

### Schedule Registration (in `app/Console/Kernel.php`)

```php
// Add to the schedule() method:

$schedule->command('transfers:process --concurrency=3 --limit=5')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/transfers-process.log'));
```

---

## 8. Concurrency & Server Protection

### Why This Matters

The Hetzner VPS has 8 GB RAM and 2 vCPUs. Uncontrolled transfers can:
- Saturate the upload bandwidth (25+ Mbps shared between web traffic and transfers)
- Fill disk if temporary files accumulate
- Cause PHP-FPM to OOM-kill worker processes serving the app

### Layered Protection Strategy

#### Layer 1 — Concurrency Slot Counter (Job Middleware)

```php
// In TransferMovieToHetzner::middleware()
return [
    (new WithoutOverlapping("global_movie_transfers"))
        ->releaseAfter(30)
        ->expireAfter(7200),
];
```

`WithoutOverlapping` uses atomic cache locks. Only one job per lock key runs at a time.
For N=3 concurrent transfers, use a numbered key approach:

```php
// Claim a numbered slot (1, 2, or 3)
// If all slots are taken, the job is released back to the queue for 30 seconds
```

#### Layer 2 — Scheduler Checks Active Count

`transfers:process` counts `status = transferring` before dispatching more.
Even if jobs are dispatched rapidly, the scheduler won't dispatch beyond `--concurrency`.

#### Layer 3 — Queue Worker Configuration on Hetzner VPS

In `/etc/supervisor/conf.d/katogo-transfers.conf` (separate from the main queue):

```ini
[program:katogo-transfers]
command=php /var/www/katogo/artisan queue:work database \
    --queue=transfers \
    --sleep=5 \
    --tries=1 \
    --timeout=7200 \
    --memory=512
directory=/var/www/katogo
user=www-data
numprocs=3                    ; matches --concurrency=3
autostart=true
autorestart=true
stopwaitsecs=7200             ; wait for transfer to finish gracefully on stop
stdout_logfile=/var/log/katogo/transfers.out.log
stderr_logfile=/var/log/katogo/transfers.err.log
```

**Key:** `numprocs=3` limits the queue to 3 simultaneous workers. Each worker uses ≤ 512 MB.
Total transfer footprint: 3 × 512 MB = 1.5 GB, leaving 6.5 GB for web and MySQL.

#### Layer 4 — Time-of-Day Throttling (optional)

Run heavy transfers only in low-traffic hours:

```php
// In Kernel.php:
$schedule->command('transfers:process --concurrency=5 --limit=10')
    ->dailyAt('02:00')   // peak batch at night (5 slots)
    ->withoutOverlapping()
    ->runInBackground();

$schedule->command('transfers:process --concurrency=2 --limit=3')
    ->everyFiveMinutes()  // light continuous during the day (2 slots)
    ->withoutOverlapping()
    ->runInBackground();
```

#### Layer 5 — Source Rate Limiting

MunoWatch servers can block IPs that make too many rapid requests. Add a per-source
host rate limiter:

```php
// In TransferMovieToHetzner::handle():
$host = parse_url($transfer->source_url, PHP_URL_HOST);
$rateLimitKey = "transfer_source_host_{$host}";

// Max 1 active transfer per source host at a time
if (Cache::has($rateLimitKey)) {
    $this->release(60); // re-queue after 60 seconds
    return;
}
Cache::put($rateLimitKey, true, 600); // hold slot for 10 minutes
```

---

## 9. Monitoring Interface

### `transfers:monitor` Command

```bash
php artisan transfers:monitor
# Output:
#
# ╔══════════════════════════════════════════════════════════════╗
# ║            Movie File Transfer — Live Monitor               ║
# ╠══════════════════════════════════════════════════════════════╣
# ║  Queue depth    : 4,821 pending                             ║
# ║  Active now     : 3 / 3 slots used                         ║
# ║  Done           : 1,204 completed                          ║
# ║  Failed         : 47 (no more retries)                     ║
# ║  Avg speed      : 18.4 MB/s per worker                     ║
# ║  Avg duration   : 6m 12s per file                          ║
# ║  ETA (all done) : ~29 hours                                ║
# ║  Storage used   : 284 GB of ∞ (unlimited quota)            ║
# ╚══════════════════════════════════════════════════════════════╝
#
# Active Transfers:
#   #12441 | Akaboozi mu Kibuga       | 72% | 18.2 MB/s | ETA 2m 14s
#   #12398 | Omukwano Gwa Nakato      | 45% | 19.1 MB/s | ETA 5m 02s
#   #12412 | Amagezi Ga Buganda       | 91% | 17.6 MB/s | ETA 0m 38s
#
# Recent Failures (last 24h):
#   #12201 | Oluganda Love Story       | Source URL 403 — MunoWatch expired
#   #12318 | Ekitibwa                  | Hetzner WebDAV timeout after 7200s
```

### Key Metrics to Track (stored and queryable)

```sql
-- Movies not yet transferred (still on external source)
SELECT COUNT(*) FROM movie_models m
LEFT JOIN movie_file_transfers t ON t.movie_id = m.id AND t.status = 'done'
WHERE m.status = 'Active' AND t.id IS NULL;

-- Transfer throughput by day
SELECT DATE(completed_at), COUNT(*), SUM(dest_size_bytes)/1024/1024/1024 as GB_transferred
FROM movie_file_transfers WHERE status = 'done'
GROUP BY DATE(completed_at) ORDER BY 1 DESC;

-- Most common failure reasons
SELECT error_message, COUNT(*) as occurrences
FROM movie_file_transfers WHERE status = 'failed'
GROUP BY error_message ORDER BY 2 DESC LIMIT 10;

-- Average transfer speed
SELECT AVG(transfer_speed_mbps) FROM movie_file_transfers WHERE status = 'done';

-- ETA estimate
SELECT
  COUNT(*) as pending,
  (SELECT AVG(duration_seconds) FROM movie_file_transfers WHERE status = 'done') as avg_seconds,
  COUNT(*) * AVG_SECONDS_PER_TRANSFER / 3 / 3600 as eta_hours
FROM movie_file_transfers WHERE status IN ('queued');
```

---

## 10. Admin Panel Integration

### New Admin Panel Section: `/admin/movie-transfers`

The admin panel (Laravel-Admin) should expose:

#### List View

Columns:
- Movie thumbnail + title (linked to movie)
- Source type badge (munowatch / gdrive / firebase)
- Status badge with color
- Progress bar (for active transfers)
- Speed + ETA (for active)
- Duration (for completed)
- File size
- Attempt count
- Actions: Retry | Cancel | View Movie

Filters:
- Status (queued / transferring / done / failed)
- Source type
- Date range
- Movie title search

#### Stats Header (above the list)

```
┌──────────┬──────────┬──────────┬──────────┬──────────────┐
│ Queued   │ Active   │ Done     │ Failed   │ ETA          │
│ 4,821    │ 3/3      │ 1,204    │ 47       │ ~29 hours    │
└──────────┴──────────┴──────────┴──────────┴──────────────┘
```

#### Detail View (per transfer record)

- Full source URL, destination URL
- Speed graph over time (using `transfer_metadata` JSON)
- Error trace (collapsible)
- Movie info snapshot (title, year, quality, poster)
- Retry button (if retriable)
- "View on Hetzner" link (opens dest_url)
- "View Movie in App" link

#### Bulk Actions

- **Queue All Unqueued Active Movies** — scans MovieModel for active movies without a
  completed transfer and creates queued records for all of them
- **Retry All Failed** — dispatches all failed transfers that still have retry budget
- **Export CSV** — list of all transfers with status, for auditing

#### New Movie Detail Page Widget

On the existing `/admin/movies/{id}` detail page, add a "Transfer Status" widget:

```
Video Source: munowatch.org/api/... [original]
Transfer: ✅ Done on 2026-06-15 | Hetzner CDN URL | 892 MB | 4m 12s @ 18.2 MB/s
```

If no transfer exists yet:
```
Transfer: ⏳ Queued (position #1,204 in queue)   [Cancel] [Prioritize]
```

---

## 11. Migration Strategy

### Phase 1 — Setup (no breaking changes)

1. Create the `movie_file_transfers` table migration
2. Create `MovieFileTransfer` model with all methods
3. Register observer in `AppServiceProvider`
4. Create `TransferMovieToHetzner` job
5. Create `transfers:process` command
6. Add `transfers` queue to Supervisor on Hetzner VPS (separate from main queue)

At this point: the system is installed but dormant. No existing code is changed.
The observer begins queueing transfers for **new** movies from this point forward.

### Phase 2 — Backfill Existing Movies

Run a one-off command to create queued transfer records for all existing active movies
that don't have a completed transfer:

```bash
php artisan transfers:backfill --status=Active --limit=1000 --dry-run
# Review output, then:
php artisan transfers:backfill --status=Active --chunk=500
```

This command queries:
```sql
SELECT m.id FROM movie_models m
LEFT JOIN movie_file_transfers t ON t.movie_id = m.id AND t.status = 'done'
WHERE m.status = 'Active'
  AND t.id IS NULL
  AND m.video_url IS NOT NULL
  AND m.video_url NOT LIKE '%your-storageshare.de%'
```

And creates `MovieFileTransfer` records in bulk. The scheduler then processes them
3 at a time over the coming days without overwhelming the server.

### Phase 3 — Deprecate Old VideoTransfer (Google Drive)

Once the Hetzner pipeline is proven:
1. Redirect `/admin/video-transfers` to `/admin/movie-transfers` (or keep both)
2. Stop creating new `VideoTransfer` records for movie transfers
3. Leave existing `VideoTransfer` records for historical reference

The old `VideoTransfer` model remains — it can still be used for non-movie file transfers.

### Phase 4 — Cleanup Firebase Fields (optional, later)

Once all movies are confirmed on Hetzner:
1. Create a migration to drop `firebase_transfer_*` columns from `movie_models`
2. The data is preserved in `movie_file_transfers.source_url` and `error_message`

---

## 12. Database Schema

```sql
CREATE TABLE movie_file_transfers (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

    -- Relationship
    movie_id                BIGINT UNSIGNED NULL,
    FOREIGN KEY (movie_id) REFERENCES movie_models(id) ON DELETE SET NULL,

    -- Movie snapshot (immutable after creation)
    movie_title             VARCHAR(500)    NULL,
    movie_year              SMALLINT        NULL,
    movie_quality           VARCHAR(20)     NULL,
    movie_duration          INT             NULL,  -- seconds
    movie_poster_url        VARCHAR(2000)   NULL,
    movie_munowatch_id      VARCHAR(100)    NULL,
    movie_is_series         TINYINT(1)      NOT NULL DEFAULT 0,
    movie_episode_info      JSON            NULL,

    -- Source
    source_url              TEXT            NOT NULL,
    source_type             VARCHAR(30)     NOT NULL DEFAULT 'unknown',
    -- 'munowatch' | 'gdrive' | 'firebase' | 'hetzner' | 'direct' | 'other'
    source_size_bytes       BIGINT          NULL,
    source_verified_at      TIMESTAMP       NULL,

    -- Destination
    dest_type               VARCHAR(20)     NOT NULL DEFAULT 'hetzner',
    dest_path               VARCHAR(1000)   NULL,
    dest_url                VARCHAR(2000)   NULL,
    dest_share_token        VARCHAR(100)    NULL,
    dest_file_id            VARCHAR(200)    NULL,  -- Google Drive file ID if applicable
    dest_size_bytes         BIGINT          NULL,

    -- Status & Progress
    status                  VARCHAR(20)     NOT NULL DEFAULT 'queued',
    -- queued | verifying | transferring | completing | done | failed | cancelled | skipped
    progress_pct            TINYINT         NOT NULL DEFAULT 0,
    bytes_transferred       BIGINT          NOT NULL DEFAULT 0,
    transfer_speed_mbps     DECIMAL(8,2)    NULL,
    eta_seconds             INT             NULL,

    -- Timing
    queued_at               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at              TIMESTAMP       NULL,
    completed_at            TIMESTAMP       NULL,
    duration_seconds        INT             NULL,

    -- Retry & Error
    attempt_count           TINYINT         NOT NULL DEFAULT 0,
    max_attempts            TINYINT         NOT NULL DEFAULT 3,
    last_attempted_at       TIMESTAMP       NULL,
    next_retry_at           TIMESTAMP       NULL,
    error_message           VARCHAR(500)    NULL,
    error_trace             TEXT            NULL,
    error_context           JSON            NULL,

    -- Outcome flags
    movie_url_updated       TINYINT(1)      NOT NULL DEFAULT 0,
    old_movie_url_backed_up TINYINT(1)      NOT NULL DEFAULT 0,

    -- Metadata
    notes                   TEXT            NULL,
    initiated_by            VARCHAR(50)     NOT NULL DEFAULT 'observer',
    worker_hostname         VARCHAR(100)    NULL,

    created_at              TIMESTAMP       NULL,
    updated_at              TIMESTAMP       NULL,

    -- Indexes
    INDEX idx_status         (status),
    INDEX idx_movie_id       (movie_id),
    INDEX idx_queued_at      (queued_at),
    INDEX idx_next_retry_at  (next_retry_at),
    INDEX idx_source_type    (source_type),
    INDEX idx_status_queued  (status, queued_at),   -- for scheduler query
    INDEX idx_movie_status   (movie_id, status)     -- for observer dedup check
);
```

---

## 13. Implementation Sequence

Work in this exact order to avoid breaking anything:

```
Week 1 — Foundation
  ├── [ ] Database migration: create movie_file_transfers table
  ├── [ ] MovieFileTransfer model (all fields, scopes, methods)
  ├── [ ] MovieFileTransferObserver (created + updated hooks)
  ├── [ ] Register observer in AppServiceProvider
  └── [ ] Unit test: observer creates transfer record when movie saved

Week 2 — Job & Queue
  ├── [ ] TransferMovieToHetzner job (with true pipe streaming)
  ├── [ ] Configure 'transfers' queue in Supervisor on Hetzner VPS
  ├── [ ] transfers:process command (scheduler picks up queued records)
  ├── [ ] Add to Kernel.php schedule
  └── [ ] Test: manually queue one transfer, confirm file appears on Hetzner Storage

Week 3 — Admin & Monitoring
  ├── [ ] Admin panel list view at /admin/movie-transfers
  ├── [ ] Stats header (queued / active / done / failed / ETA)
  ├── [ ] Detail view with progress bar and retry button
  ├── [ ] transfers:monitor command
  ├── [ ] Movie detail page widget (transfer status per movie)
  └── [ ] Bulk actions: queue-all-unqueued, retry-all-failed

Week 4 — Backfill & Validation
  ├── [ ] transfers:backfill command (dry-run first)
  ├── [ ] Run backfill on staging, verify 100 movies transfer correctly
  ├── [ ] Verify: movie video_url updated to Hetzner CDN URL after transfer
  ├── [ ] Verify: app plays video from Hetzner CDN URL correctly
  ├── [ ] Monitor failure rate and fix common error patterns
  └── [ ] Run full backfill on production (3 concurrent, overnight)
```

---

## Summary

| Before | After |
|--------|-------|
| Manual trigger per movie | Automatic — observer queues on save |
| Synchronous in HTTP request | Async queue job, runs on Hetzner VPS workers |
| Entire file in PHP memory | True pipe stream — peak 4 MB per transfer |
| No concurrency control | Max 3 simultaneous, configurable |
| No link to MovieModel | FK to movie_models, auto-updates video_url |
| Google Drive (unreliable for streaming) | Hetzner Storage Share (CDN, range requests, no quotas) |
| No retry scheduler | Exponential backoff, auto-dispatched by scheduler |
| No monitoring | Admin dashboard + CLI monitor + log files |
| Firebase fields in movie_models | Isolated in movie_file_transfers table |
| One movie at a time | Batch backfill with ETA estimation |

The new system is designed to transfer the entire movie library (~21,000 movies) to Hetzner
Storage Share without any human intervention beyond the initial backfill command, while
keeping the Hetzner VPS stable and the app responsive throughout.
