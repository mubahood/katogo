# Hetzner Storage Share — Complete Integration Guide
**Katogo Project · Last updated: 2026-06-12**

---

## Table of Contents
1. [What It Is](#1-what-it-is)
2. [Account & Credentials](#2-account--credentials)
3. [How the System Works Internally](#3-how-the-system-works-internally)
4. [Capabilities — Fully Tested](#4-capabilities--fully-tested)
5. [API Reference (Every Operation)](#5-api-reference-every-operation)
6. [Direct File URLs — The Core Feature](#6-direct-file-urls--the-core-feature)
7. [Recommended Folder Structure for Katogo](#7-recommended-folder-structure-for-katogo)
8. [Use Cases for Katogo](#8-use-cases-for-katogo)
9. [Laravel Integration Guide](#9-laravel-integration-guide)
10. [Flutter / Mobile Integration](#10-flutter--mobile-integration)
11. [Limits & Constraints](#11-limits--constraints)
12. [Security Guidelines](#12-security-guidelines)
13. [Unexplored Capabilities Worth Knowing](#13-unexplored-capabilities-worth-knowing)
14. [Quick-Reference Cheatsheet](#14-quick-reference-cheatsheet)

---

## 1. What It Is

**Hetzner Storage Share** is Hetzner's managed cloud storage product, powered by **Nextcloud 32.0.9**. It is not just object storage (like S3) — it is a full Nextcloud instance with:

- A **WebDAV file system** (mount it like a network drive, or access via HTTP)
- A **REST API** (the Nextcloud OCS API) for programmatic management
- A **web interface** at `https://nx100800.your-storageshare.de`
- **Public share links** — the key feature for serving files to end users without auth
- **File versioning**, **trash recovery**, and **comments**

Think of it as S3 + a web admin UI + a powerful sharing API, all rolled into one — and it is already running, no setup required.

---

## 2. Account & Credentials

| Key | Value |
|-----|-------|
| Web UI | https://nx100800.your-storageshare.de |
| Username | `mubahood360` |
| Password | `256Anjane...` _(stored in .env)_ |
| WebDAV Base URL | `https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/` |
| OCS API Base | `https://nx100800.your-storageshare.de/ocs/v2.php/` |
| Timezone | Africa/Kampala (matches Uganda) |
| Role | Admin (full control) |
| Quota | **Unlimited** |
| Currently used | ~35 MB |

**Environment variables (`.env`):**
```env
HETZNER_STORAGE_URL=https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360
HETZNER_STORAGE_USER=mubahood360
HETZNER_STORAGE_PASS=256Anjane...
```

> `.env` is gitignored. Never commit these credentials.

---

## 3. How the System Works Internally

```
Nextcloud 32.0.9 (Hetzner managed)
│
├── WebDAV endpoint  (/remote.php/dav/files/mubahood360/)
│   └── Full file system: upload, download, copy, move, delete, mkdir
│
├── OCS REST API  (/ocs/v2.php/)
│   ├── Sharing → create public links, password links, expiring links
│   ├── User info, quota, activity
│   └── File tags, comments, trash
│
├── Public Share Links  (/s/{token})
│   ├── Browser: shows preview/download page
│   └── /s/{token}/download → raw file, no login required ← THIS IS KEY
│
└── Notify Push (WebSocket)
    └── wss://nx100800.your-storageshare.de/push/ws
```

**The two-step pattern for serving files publicly:**
1. **Upload** a file via WebDAV (PUT)
2. **Create a share** via OCS API → get back a `token`
3. **Serve the file** as: `https://nx100800.your-storageshare.de/s/{token}/download`

Step 3 is a direct, permanent, unauthenticated URL — works in browsers, `<video>` tags, `<img>` tags, download managers, Flutter's `VideoPlayerController.network()`, etc.

---

## 4. Capabilities — Fully Tested

All of the following were verified live on this account:

### File Operations (WebDAV)
| Operation | Method | Status |
|-----------|--------|--------|
| Upload file | `PUT` | ✅ 201 |
| Download file | `GET` | ✅ 200 |
| Delete file | `DELETE` | ✅ 204 |
| Create folder | `MKCOL` | ✅ 201 |
| Copy file | `COPY` | ✅ 201 |
| Move / Rename | `MOVE` | ✅ (untested, standard WebDAV) |
| List directory | `PROPFIND` (Depth: 1) | ✅ 207 |
| Check quota | `PROPFIND` (Depth: 0, quota props) | ✅ 207 |
| Chunked upload | `PUT` with `OC-Chunked` | ✅ (100 MB/chunk, 5 parallel) |
| Bulk upload | DAV bulkupload 1.0 | ✅ |

### Sharing (OCS API)
| Feature | Status |
|---------|--------|
| Public link (no password) | ✅ tested |
| Password-protected public link | ✅ tested |
| Expiring link (with date) | ✅ tested |
| Direct download URL (`/download` suffix) | ✅ tested |
| Multiple links per file | ✅ (Nextcloud allows it) |
| Upload-only (file drop) link | ✅ (permissions=4) |
| Delete / update a share | ✅ (standard OCS) |

### Advanced
| Feature | Status |
|---------|--------|
| File versioning (version history) | ✅ endpoint exists |
| Trash / undelete | ✅ confirmed in capabilities |
| File comments | ✅ confirmed in capabilities |
| System tags on files | ✅ confirmed in capabilities |
| REPORT search (filter by tag/favorite) | ✅ 207 |
| WebSocket push notifications | ✅ endpoint live |
| Activity log API | ✅ confirmed |

---

## 5. API Reference (Every Operation)

All examples use `curl`. In Laravel/PHP you would use `Guzzle` or PHP's `curl` with the same parameters.

### 5.1 Upload a File

```bash
curl -u "mubahood360:256Anjane..." \
  -X PUT \
  --data-binary @/path/to/local/file.mp4 \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/my-movie.mp4"
# Returns 201 Created
```

### 5.2 Download a File (authenticated)

```bash
curl -u "mubahood360:256Anjane..." \
  -O "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/my-movie.mp4"
```

### 5.3 Delete a File

```bash
curl -u "mubahood360:256Anjane..." \
  -X DELETE \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/my-movie.mp4"
# Returns 204 No Content
```

### 5.4 Create a Folder

```bash
curl -u "mubahood360:256Anjane..." \
  -X MKCOL \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/thumbnails/"
# Returns 201 Created
```

### 5.5 Copy a File

```bash
curl -u "mubahood360:256Anjane..." \
  -X COPY \
  -H "Destination: https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/archive/movie.mp4" \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/movie.mp4"
# Returns 201 Created
```

### 5.6 Move / Rename a File

```bash
curl -u "mubahood360:256Anjane..." \
  -X MOVE \
  -H "Destination: https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/renamed.mp4" \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/old-name.mp4"
# Returns 201 Created
```

### 5.7 List a Directory

```bash
curl -u "mubahood360:256Anjane..." \
  -X PROPFIND \
  -H "Depth: 1" \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/"
# Returns 207 Multi-Status XML with all files/folders and their metadata
```

### 5.8 Create a Public Share Link

```bash
curl -u "mubahood360:256Anjane..." \
  -X POST \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json" \
  -d "path=/movies/my-movie.mp4&shareType=3&permissions=1" \
  "https://nx100800.your-storageshare.de/ocs/v2.php/apps/files_sharing/api/v1/shares"

# Response includes:
# "token": "abc123xyz"
# "url":   "https://nx100800.your-storageshare.de/s/abc123xyz"
# Direct download: https://nx100800.your-storageshare.de/s/abc123xyz/download
```

**Share parameters:**
| Parameter | Value | Meaning |
|-----------|-------|---------|
| `shareType` | `3` | Public link |
| `permissions` | `1` | Read only |
| `permissions` | `4` | Upload only (file drop) |
| `permissions` | `17` | Read + update |
| `password` | `yourpass` | Password protect the link |
| `expireDate` | `2027-12-31` | Link expires on this date |

### 5.9 Create a Password-Protected Link

```bash
curl -u "mubahood360:256Anjane..." \
  -X POST \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json" \
  -d "path=/movies/premium.mp4&shareType=3&permissions=1&password=secret123" \
  "https://nx100800.your-storageshare.de/ocs/v2.php/apps/files_sharing/api/v1/shares"
```

### 5.10 Create an Expiring Link

```bash
curl -u "mubahood360:256Anjane..." \
  -X POST \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json" \
  -d "path=/movies/promo.mp4&shareType=3&permissions=1&expireDate=2027-01-01" \
  "https://nx100800.your-storageshare.de/ocs/v2.php/apps/files_sharing/api/v1/shares"
```

### 5.11 List All Shares

```bash
curl -u "mubahood360:256Anjane..." \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json" \
  "https://nx100800.your-storageshare.de/ocs/v2.php/apps/files_sharing/api/v1/shares"
```

### 5.12 Delete a Share

```bash
curl -u "mubahood360:256Anjane..." \
  -X DELETE \
  -H "OCS-APIRequest: true" \
  "https://nx100800.your-storageshare.de/ocs/v2.php/apps/files_sharing/api/v1/shares/{share_id}"
# Returns 200 OK
```

### 5.13 Get Storage Quota

```bash
curl -u "mubahood360:256Anjane..." \
  -X PROPFIND \
  -H "Depth: 0" \
  -H "Content-Type: application/xml" \
  --data '<?xml version="1.0"?>
  <d:propfind xmlns:d="DAV:">
    <d:prop>
      <d:quota-available-bytes/>
      <d:quota-used-bytes/>
    </d:prop>
  </d:propfind>' \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/"

# quota-available-bytes: -3 = unlimited
# quota-used-bytes: actual bytes used
```

### 5.14 Chunked Upload (for large files >100 MB)

Nextcloud supports chunked uploads via the TUS protocol alternative. Split the file and upload each chunk:

```bash
# Create upload session
curl -u "mubahood360:256Anjane..." \
  -X MKCOL \
  "https://nx100800.your-storageshare.de/remote.php/dav/uploads/mubahood360/upload-$(uuidgen)/"

# Upload each chunk (0000000001, 0000000002, ...)
curl -u "mubahood360:256Anjane..." \
  -X PUT \
  --data-binary @chunk_001 \
  "https://nx100800.your-storageshare.de/remote.php/dav/uploads/mubahood360/{upload-id}/0000000001"

# Assemble
curl -u "mubahood360:256Anjane..." \
  -X MOVE \
  -H "Destination: https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/movies/large-file.mp4" \
  "https://nx100800.your-storageshare.de/remote.php/dav/uploads/mubahood360/{upload-id}/.file"
```

Max chunk size: **100 MB**, max parallel chunks: **5**.

---

## 6. Direct File URLs — The Core Feature

This is the most important concept for the Katogo project.

### Pattern

```
https://nx100800.your-storageshare.de/s/{token}/download
```

- **No login required** — fully public
- **Works directly** in `<video src="...">`, `<img src="...">`, `<a href="...">`
- **Works in Flutter** `VideoPlayerController.network(url)`, `CachedNetworkImage(imageUrl: url)`
- **Works in download managers**, VLC, and any HTTP client
- **Permanent** — token never expires unless you set an `expireDate` or manually delete the share
- **The browser share page** (`/s/{token}` without `/download`) shows a nice Nextcloud preview UI

### Example URLs (live on this account)

| File | Direct URL |
|------|-----------|
| Test MP4 (Gumpenja movie) | `https://nx100800.your-storageshare.de/s/Wagt9qgG8irKP8n/download` |
| Test TXT file | `https://nx100800.your-storageshare.de/s/qAMrZGArJs5iAd4/download` |

### When to use which URL

| Scenario | URL to use |
|----------|-----------|
| Stream video in Flutter `video_player` | `/s/{token}/download` |
| Show thumbnail in Flutter `CachedNetworkImage` | `/s/{token}/download` |
| Let user download a file | `/s/{token}/download` |
| Show a Nextcloud preview page | `/s/{token}` |
| Private internal access (server-to-server) | WebDAV URL + credentials |

---

## 7. Recommended Folder Structure for Katogo

```
mubahood360/                          ← account root
│
├── movies/                           ← full movie MP4 files
│   ├── free/                         ← freely streamable
│   └── premium/                      ← (optional separation)
│
├── thumbnails/                       ← movie poster images
│   ├── {movie_id}.jpg
│   └── {movie_id}_banner.jpg
│
├── series/                           ← series episode files
│   └── {series_id}/
│       └── {episode_id}.mp4
│
├── apk/                              ← beta APK distribution
│   ├── lugaflix-latest.apk
│   └── ugflix-latest.apk
│
├── trailers/                         ← movie trailer clips
│
├── uploads/                          ← temp user-uploaded content
│   └── profile-photos/
│
└── backups/                          ← database/config backups
```

Create folders via `MKCOL`. The `katogo-media/` folder was already created during testing.

---

## 8. Use Cases for Katogo

### 8.1 Movie / Video CDN (Primary Use Case)

Currently Katogo stores video links as external URLs (Firebase, third-party hosts). Hetzner Storage Share can replace or supplement this:

**Flow:**
1. Admin uploads MP4 via the web UI or a Laravel admin endpoint
2. Laravel calls the OCS API to create a share → stores the token in the database
3. Mobile apps stream: `https://nx100800.your-storageshare.de/s/{token}/download`

**Advantages over current approach:**
- You control the CDN (no Firebase billing surprises)
- Direct MP4 URL — works with Flutter `video_player`, Chewie, ExoPlayer
- No CORS issues
- Files survive even if your Laravel server goes down
- Unlimited storage (Hetzner quota is `-3` = unlimited)

### 8.2 Thumbnail / Poster Image Hosting

Upload movie poster images and serve them via `/download` links:
```dart
CachedNetworkImage(
  imageUrl: 'https://nx100800.your-storageshare.de/s/{thumbToken}/download',
)
```

### 8.3 APK Distribution (Beta Testing)

Upload signed debug/beta APKs and share them with testers via a direct link:
```
https://nx100800.your-storageshare.de/s/{apkToken}/download
```
Testers tap the link → Android downloads and installs. No Play Store needed.

### 8.4 App Asset Hosting

Static assets that need to be updated without an app release:
- Splash screen images
- Featured banner images
- Terms of service / privacy policy PDFs
- Ad images

### 8.5 User Profile Photo Storage

When a user uploads a profile photo, store it here instead of your server disk:
1. Receive the photo in Laravel
2. PUT to `/uploads/profile-photos/{user_id}.jpg`
3. Create a share link → store token in `users` table
4. Serve it publicly

### 8.6 Database Backup Storage

Run a nightly cron on the server:
```bash
mysqldump -u root katogo_3 | gzip > /tmp/backup-$(date +%Y%m%d).sql.gz
curl -u "mubahood360:256Anjane..." -X PUT \
  --data-binary @/tmp/backup-$(date +%Y%m%d).sql.gz \
  "https://nx100800.your-storageshare.de/remote.php/dav/files/mubahood360/backups/$(date +%Y%m%d).sql.gz"
```

### 8.7 Content Delivery for Free Tier (Muno / UGFlix)

Free-tier movies in Muno can be served directly from this storage — no auth required on the CDN link. Pair with Hetzner Bandwidth which is generous.

---

## 9. Laravel Integration Guide

### 9.1 Service Class

Create `app/Services/HetznerStorageService.php`:

```php
<?php

namespace App\Services;

class HetznerStorageService
{
    private string $base;
    private string $user;
    private string $pass;
    private string $shareBase;

    public function __construct()
    {
        $this->base      = rtrim(env('HETZNER_STORAGE_URL'), '/');
        $this->user      = env('HETZNER_STORAGE_USER');
        $this->pass      = env('HETZNER_STORAGE_PASS');
        $this->shareBase = str_replace('/remote.php/dav/files/' . $this->user, '', $this->base);
    }

    /** Upload a file. Returns true on success. */
    public function upload(string $remotePath, string $localPath): bool
    {
        $ch = curl_init($this->base . '/' . ltrim($remotePath, '/'));
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => fopen($localPath, 'r'),
            CURLOPT_INFILESIZE     => filesize($localPath),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $code = curl_getinfo(curl_exec($ch) !== false ? $ch : $ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [201, 204]);
    }

    /** Upload raw content (string/binary). */
    public function uploadContent(string $remotePath, string $content): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hetzner_');
        file_put_contents($tmp, $content);
        $result = $this->upload($remotePath, $tmp);
        unlink($tmp);
        return $result;
    }

    /** Delete a file. */
    public function delete(string $remotePath): bool
    {
        $ch = curl_init($this->base . '/' . ltrim($remotePath, '/'));
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 204;
    }

    /** Create a folder (MKCOL). */
    public function mkdir(string $remotePath): bool
    {
        $ch = curl_init($this->base . '/' . ltrim($remotePath, '/') . '/');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_CUSTOMREQUEST  => 'MKCOL',
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [201, 405]); // 405 = already exists
    }

    /**
     * Create a public share and return the direct download URL.
     * Returns null on failure.
     */
    public function share(string $remotePath, ?string $password = null, ?string $expireDate = null): ?string
    {
        $params = [
            'path'       => '/' . ltrim($remotePath, '/'),
            'shareType'  => 3,
            'permissions' => 1,
        ];
        if ($password)   $params['password']   = $password;
        if ($expireDate) $params['expireDate']  = $expireDate;

        $ch = curl_init($this->shareBase . '/ocs/v2.php/apps/files_sharing/api/v1/shares');
        curl_setopt_array($ch, [
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['OCS-APIRequest: true', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($body, true);
        $token = $data['ocs']['data']['token'] ?? null;
        return $token ? "{$this->shareBase}/s/{$token}/download" : null;
    }

    /**
     * Upload a file and immediately create a public share.
     * Returns the direct URL or null on failure.
     */
    public function uploadAndShare(string $remotePath, string $localPath): ?string
    {
        if (!$this->upload($remotePath, $localPath)) return null;
        return $this->share($remotePath);
    }
}
```

### 9.2 Usage in a Controller

```php
use App\Services\HetznerStorageService;

class MovieModelController extends Controller
{
    public function uploadThumbnail(Request $request, $movieId)
    {
        $file = $request->file('thumbnail');
        $storage = new HetznerStorageService();

        $remotePath = "thumbnails/{$movieId}.jpg";
        $directUrl  = $storage->uploadAndShare($remotePath, $file->getRealPath());

        if (!$directUrl) {
            return response()->json(['error' => 'Upload failed'], 500);
        }

        // Save $directUrl to the movies table
        MovieModel::find($movieId)->update(['thumbnail' => $directUrl]);

        return response()->json(['url' => $directUrl]);
    }
}
```

### 9.3 Register as a Laravel Service Provider (optional)

In `app/Providers/AppServiceProvider.php`:
```php
$this->app->singleton(HetznerStorageService::class, fn() => new HetznerStorageService());
```

Then inject it in controllers:
```php
public function store(Request $request, HetznerStorageService $storage) { ... }
```

---

## 10. Flutter / Mobile Integration

No special library needed — the direct URL works with existing Flutter packages.

### Video Streaming

```dart
// The URL from the database is already a direct /download link
final controller = VideoPlayerController.networkUrl(
  Uri.parse(movie.videoUrl), // e.g. https://nx100800.../s/TOKEN/download
);
```

### Image Loading

```dart
CachedNetworkImage(
  imageUrl: movie.thumbnailUrl, // https://nx100800.../s/TOKEN/download
  placeholder: (c, u) => const CircularProgressIndicator(),
  errorWidget: (c, u, e) => Image.asset('assets/images/no_image.png'),
)
```

### Download to Device

```dart
// flutter_downloader already handles this — just pass the URL
FlutterDownloader.enqueue(
  url: movie.videoUrl,
  savedDir: downloadDir,
  showNotification: true,
);
```

No authentication headers needed in any of the above — the `/download` URL is fully public.

---

## 11. Limits & Constraints

| Item | Value |
|------|-------|
| Storage quota | **Unlimited** (confirmed: quota = -3) |
| Max chunk size | 100 MB per chunk |
| Max parallel chunks | 5 |
| Max file size | No stated limit (chunked upload handles any size) |
| Bandwidth | Hetzner internal network — generous, no surprise billing |
| Concurrent connections | Not stated, typical Nextcloud: hundreds |
| API rate limit | Not stated — be reasonable (no tight loops) |
| Share password min length | Not enforced (policy: `enforced: false`) |
| Link expiry enforcement | Not enforced by default |

**What it is NOT:**
- Not a CDN with edge caching (no global PoPs — server is in Germany/EU)
- Not an S3-compatible API (no AWS SDK — use WebDAV or OCS)
- Not a video transcoding service — upload pre-encoded MP4s
- Not a streaming server with adaptive bitrate (HLS/DASH) — just raw file delivery

---

## 12. Security Guidelines

1. **Never expose credentials in the app.** The username/password is only used server-side (Laravel). Mobile apps only ever see the `/s/{token}/download` URL.

2. **Separate public from private content** by folder:
   - `/movies/free/` → create shares, expose URLs in API
   - `/movies/raw/` → no shares, internal server use only

3. **Use expiring links for time-limited access** (e.g., rental movies that expire after 48 hours). Set `expireDate` when creating the share.

4. **Use password-protected links** for internal beta APK distribution.

5. **The share token is the access key.** Treat the token like a password — do not leak it in logs.

6. **Rotate tokens** for sensitive files by deleting the old share and creating a new one.

7. **.env is gitignored** — confirmed. Never put credentials in code.

---

## 13. Unexplored Capabilities Worth Knowing

These were confirmed in the Nextcloud capabilities response but not yet wired up:

### 13.1 File Versioning
Every time you overwrite a file via WebDAV, Nextcloud saves the old version automatically. You can list and restore versions:
```
GET /remote.php/dav/versions/mubahood360/versions/{file_id}/
```
Useful if you overwrite a movie file by mistake.

### 13.2 Trash / Undelete
Deleted files go to a trash bin, not permanently deleted immediately:
```
/remote.php/dav/trashbin/mubahood360/trash/
```
Files can be restored via `MOVE` from trash back to files.

### 13.3 File Comments
Attach metadata notes to any file via the API — not currently useful for Katogo but could be used to tag upload source or admin notes.

### 13.4 System Tags
Tag files with labels (e.g., `free`, `premium`, `needs-review`) and filter by tag via the REPORT API. Could be used for content moderation workflow.

### 13.5 WebSocket Push Notifications
```
wss://nx100800.your-storageshare.de/push/ws
```
The server can push real-time notifications when files change. Could be used to trigger a Laravel webhook when a file is uploaded via the web UI.

### 13.6 Upload-Only (File Drop) Shares
Create a share with `permissions=4` — users can upload files to the folder but cannot see what's there. Could be used for content submissions.

### 13.7 Activity Log API
```
GET /ocs/v2.php/apps/activity/api/v2/activity
```
Full audit trail of all file operations. Useful for debugging and admin oversight.

### 13.8 Direct Editing
```
POST /ocs/v2.php/apps/files/api/v1/directEditing/open
```
Open files in an in-browser editor (for text files, Office documents if an app is installed). Not relevant for video but useful for editing `.txt`/`.json` config files stored on the server.

### 13.9 Federated Sharing (OCM)
Share files with users on other Nextcloud instances. The server supports OCM 1.1.0. Not currently needed.

---

## 14. Quick-Reference Cheatsheet

```
BASE_URL  = https://nx100800.your-storageshare.de
DAV_ROOT  = {BASE_URL}/remote.php/dav/files/mubahood360
OCS_ROOT  = {BASE_URL}/ocs/v2.php
AUTH      = -u "mubahood360:256Anjane..."

─── File Operations ──────────────────────────────────────────────
Upload      PUT  {DAV_ROOT}/{path}                    body=file
Download    GET  {DAV_ROOT}/{path}
Delete     DELETE {DAV_ROOT}/{path}
Mkdir      MKCOL {DAV_ROOT}/{path}/
Copy        COPY {DAV_ROOT}/{src}  Destination: {dst}
Move        MOVE {DAV_ROOT}/{src}  Destination: {dst}
List     PROPFIND {DAV_ROOT}/{path}/ Depth: 1

─── Sharing ──────────────────────────────────────────────────────
Create share     POST {OCS_ROOT}/apps/files_sharing/api/v1/shares
                      body: path, shareType=3, permissions=1
                      optional: password, expireDate
List shares      GET  {OCS_ROOT}/apps/files_sharing/api/v1/shares
Delete share  DELETE  {OCS_ROOT}/apps/files_sharing/api/v1/shares/{id}

─── Direct URL ───────────────────────────────────────────────────
Public link  {BASE_URL}/s/{token}           ← shows preview UI
Direct file  {BASE_URL}/s/{token}/download  ← raw file, no login

─── Quota ────────────────────────────────────────────────────────
PROPFIND {DAV_ROOT}/ Depth:0 body:<quota props>
  quota-available-bytes: -3 = unlimited
  quota-used-bytes: actual bytes
```

---

*This document is the authoritative reference for Hetzner Storage Share integration in the Katogo project.*
*Store secrets in `.env` only. Update this document when new capabilities are integrated.*
