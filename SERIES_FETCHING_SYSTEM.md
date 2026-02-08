# Series Fetching System — Munowatch Integration

> Comprehensive documentation for the series episode fetching, syncing, fixing, and activation system.
> Last updated: 2025-06-28

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [The MAMP Constraint](#the-mamp-constraint)
3. [Munowatch API Reference](#munowatch-api-reference)
4. [Backend: SeriesFixerService](#backend-seriesfixerservice)
5. [Backend: DebugPlayerProxyController](#backend-debugplayerproxycontroller)
6. [Backend: Routes](#backend-routes)
7. [Frontend: ugflix-series-player.js](#frontend-ugflix-series-playerjs)
8. [Data Flow: Batch Fetching](#data-flow-batch-fetching)
9. [Data Flow: End-of-Chain Detection](#data-flow-end-of-chain-detection)
10. [Data Flow: Series Activation](#data-flow-series-activation)
11. [Title Cleaning System](#title-cleaning-system)
12. [Database Schema](#database-schema)
13. [Configuration & Constants](#configuration--constants)
14. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│  BROWSER (Admin Panel)                                          │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  ugflix-series-player.js  (jQuery, ~1300 lines)             ││
│  │                                                             ││
│  │  Features:                                                  ││
│  │   • Video player + episode sidebar (eps LEFT, player RIGHT) ││
│  │   • Ranges panel for selective fetching                     ││
│  │   • Live progress bars per range                            ││
│  │   • Sidebar refreshes after each batch (live updates)       ││
│  │   • Auto-cascade CDN fallback playback                      ││
│  │   • Fix episode / Fix series buttons                        ││
│  └─────────────────────────────────────────┬───────────────────┘│
│                                            │ AJAX (POST)        │
├────────────────────────────────────────────┼────────────────────┤
│  LARAVEL BACKEND                           ▼                    │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │  DebugPlayerProxyController  (~405 lines)                   ││
│  │  Thin controller — delegates to services                    ││
│  └──────────────────┬──────────────────────────────────────────┘│
│                     │                                           │
│  ┌──────────────────▼──────────────────────────────────────────┐│
│  │  SeriesFixerService v2.0  (~1050 lines)                     ││
│  │                                                             ││
│  │  Core service for:                                          ││
│  │   • Fetching episode ranges from munowatch API              ││
│  │   • Traversing episode chains (nxt_eps_id)                  ││
│  │   • Upserting episodes to local DB                          ││
│  │   • Title cleaning                                          ││
│  │   • Series activation                                       ││
│  │   • Metadata refresh                                        ││
│  └──────────────────┬──────────────────────────────────────────┘│
│                     │                                           │
│  ┌──────────────────▼──────────────────────────────────────────┐│
│  │  Munowatch API (external)                                   ││
│  │   • /api/preview/v2/{videoId}/{userId}                      ││
│  │   • /api/episodes/range/{showId}/{seriesCode}/{season}      ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

### Key Design Decisions

1. **Batched Fetching**: Episodes are fetched in small batches (3 per request) to stay under the MAMP FastCGI 30-second idle timeout.
2. **FAST PATH**: Continuation batches skip the expensive resolve+range API calls (~5-10s savings per batch).
3. **Chain Traversal**: Episodes are linked via `nxt_eps_id`, not sequential video IDs. The system follows this chain to handle non-contiguous ID gaps.
4. **Live UI Updates**: The sidebar and header refresh after every batch response, so the user sees episodes appear in real-time.
5. **End-of-Chain Detection**: When the chain ends (`nxt_eps_id == 0` or self-referencing), the series can be auto-activated.

---

## The MAMP Constraint

**MAMP's FastCGI has a 30-second idle timeout** that cannot be easily changed. This is the core constraint that shapes the entire fetching architecture.

- Each munowatch preview API call takes ~1.5-5 seconds
- A batch of 3 episodes takes ~5-15 seconds (well within the 30s limit)
- Without batching, fetching 100+ episodes in a single request would always time out

### How We Work Around It

| Approach | How It Works |
|----------|-------------|
| **Batch Size = 3** | Each HTTP request fetches at most 3 episodes from munowatch |
| **FAST PATH** | Continuation batches send `continue_from` + `continue_ep` + `range_end_ep` to skip resolve+range API calls |
| **PHP Limits** | `set_time_limit(300)` + `ini_set('memory_limit', '256M')` as safety net (the real limit is MAMP's 30s) |
| **JS Loop** | JavaScript drives the batch loop, calling fetch-range in sequence until `has_more == false` |

---

## Munowatch API Reference

### Authentication

All requests require:
```
Authorization: Bearer <JWT>
X-Api-Key: <JWT>
Content-Type: application/x-www-form-urlencoded
User-Agent: okhttp/4.9.0
```

The JWT is stored as `SeriesFixerService::MUNOWATCH_JWT`.
The user ID is `169464` (`SeriesFixerService::MUNOWATCH_USER_ID`).

### Endpoint: Preview API

```
GET /api/preview/v2/{videoId}/{userId}
```

Returns full episode data:
```json
{
  "preview": {
    "video_title": "A Woman In A Veil 42",
    "playingUrl": "https://munotek.b-cdn.net/path/to/video.mp4",
    "thumbnail": "https://...",
    "duration": "45:30",
    "vjname": "VJ Ice P",
    "genre": "Series",
    "nxt_eps_id": "39573",       // Links to next episode (chain traversal)
    "series_code": "44521",
    "episodes": 103              // Total episodes for the series
  }
}
```

**Critical fields:**
- `nxt_eps_id` — The video ID of the next episode. `0` or self-referencing means last episode.
- `series_code` — Unique identifier for the series on munowatch. Used to query ranges.
- `playingUrl` — The actual video stream URL.

### Endpoint: Episodes Range API

```
GET /api/episodes/range/{showId}/{seriesCode}/{season}
```

Returns pagination metadata:
```json
[
  {"eps": "  -  20", "eps_range": "39531__39550"},
  {"eps": "  21-  40", "eps_range": "39551__39570"},
  {"eps": "  41-  60", "eps_range": "39571__39590"},
  {"eps": "  61-  80", "eps_range": "39591__39610"},
  {"eps": "  81- 100", "eps_range": "39611__39630"},
  {"eps": " 101- 103", "eps_range": "39631__39633"}
]
```

**Important:** The `eps_range` video ID boundaries are NOT always contiguous. Other series' videos may occupy IDs in the gaps. This is why we use `nxt_eps_id` chain traversal instead of simple ID incrementing.

---

## Backend: SeriesFixerService

**File:** `app/Services/SeriesFixerService.php` (~1050 lines)

### Class Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `MUNOWATCH_USER_ID` | `169464` | User ID for munowatch API auth |
| `MUNOWATCH_API_BASE` | `https://munowatch.org/api` | Base URL for all API calls |
| `MUNOWATCH_JWT` | `eyJ...` | JWT token for Bearer auth + X-Api-Key |

### Constructor

```php
public function __construct()
```
Instantiates `MovieFixerService` for episode fixing operations.

---

### Public Methods

#### `getSeriesInfo(int $seriesId): array`

Returns comprehensive series data for the debug player.

- Loads the `SeriesMovie` record
- Fetches all episodes ordered by season → episode number
- Deduplicates by `munowatch_id` (keeps first occurrence)
- Groups episodes by season

**Returns:**
```php
[
    'success'  => true,
    'series'   => [...],        // Series metadata (seriesToArray)
    'episodes' => [...],        // All episodes (episodeToArray)
    'seasons'  => ['1' => [..]], // Episodes grouped by season
    'total_episodes' => 96,
    'total_seasons'  => 1,
]
```

#### `fetchRemoteRanges(int $seriesId): array`

Fetches episode range/pagination metadata from the munowatch API.

1. Resolves `series_code` and `show_id` via `resolveSeriesCode()`
2. Calls `callEpisodesRangeApi()` to get ranges
3. Annotates each range with local sync status:
   - `local_count` — how many episodes already exist locally in this range
   - `is_complete` — whether `local_count >= episode_count`

**Returns:**
```php
[
    'success' => true,
    'ranges' => [
        ['start_ep' => 1, 'end_ep' => 20, 'episode_count' => 20,
         'start_video_id' => '39531', 'end_video_id' => '39550',
         'local_count' => 20, 'is_complete' => true, ...],
        // ... more ranges
    ],
    'total_ranges' => 6,
    'total_remote_episodes' => 103,
    'local_episode_count' => 96,
]
```

#### `fetchEpisodesForRange(int $seriesId, int $rangeIndex, int $season, int $batchSize, ?string $continueFrom, ?int $continueEp, ?int $rangeEndEp): array`

**The core batch-fetch method.** Fetches episodes for a specific range, with support for batching and continuation.

**Two execution paths:**

| Path | When | What Happens |
|------|------|-------------|
| **FAST PATH** | `continueFrom` + `continueEp` + `rangeEndEp` all provided | Skips resolve + range API calls. Jumps directly to chain traversal. Saves ~5-10 seconds per batch. |
| **NORMAL PATH** | First batch for a range (or no continuation params) | Resolves series code, fetches range data, then traverses chain. |

**Parameters:**
- `$seriesId` — Local series ID
- `$rangeIndex` — Which range (0-based index)
- `$season` — Season number (default 1)
- `$batchSize` — Max episodes per request (default 3; 0 = all)
- `$continueFrom` — Video ID to resume chain from
- `$continueEp` — Episode number to resume from
- `$rangeEndEp` — End episode of the range (enables FAST PATH)

**Returns:**
```php
[
    'success' => true,
    'range' => [...],              // Range metadata
    'episodes_fetched' => 3,       // Created + updated this batch
    'created' => 2,
    'updated' => 1,
    'skipped' => 0,
    'errors' => [],
    'has_more' => true,            // More batches needed for this range
    'chain_ended' => false,        // True if nxt_eps_id reached 0 (last episode)
    'next_video_id' => '39574',    // Video ID to continue from
    'next_ep_num' => 44,           // Episode number to continue from
    'range_end_ep' => 60,          // End episode of this range
    'message' => 'Eps 41-43: 2 created, 1 updated, 0 skipped.',
]
```

#### `syncAllEpisodes(int $seriesId, int $season): array`

Full sync: fetches ALL ranges and traverses ALL episodes in a single server-side call.
- `set_time_limit(3600)`, `memory_limit = 512M`
- Iterates each range and calls `traverseEpisodeChain()` for the full count
- 300ms delay between ranges to avoid API hammering
- Refreshes series metadata after completion

**Use case:** Background/CLI sync. Not recommended for browser use due to MAMP timeout.

#### `fixSeries(int $seriesId, int $maxEpisodes): array`

Sync + fix every episode:
1. Calls `syncAllEpisodes()` to ensure all episodes exist locally
2. Iterates each local episode and calls `MovieFixerService::fix()`
3. Refreshes series metadata

#### `fixEpisode(int $movieId): array`

Delegates to `MovieFixerService::fix()` for a single episode.

#### `checkAndActivateSeries(int $seriesId): array`

Called when end-of-chain is detected or after all ranges are fetched.

1. Counts local and active episodes
2. Cleans the series title via `cleanSeriesTitle()`
3. If any active episodes exist, marks `is_active = 'Yes'`, sets `total_episodes`, sets `muno_processed = 'Yes'`

**Returns:**
```php
[
    'local_count' => 96,
    'active_count' => 91,
    'remote_count' => 103,
    'activated' => true,
    'title_cleaned' => true,
    'new_title' => 'A Woman In A Veil',
    'reason' => 'Activated: 91/96 episodes active',
]
```

#### `cleanSeriesTitle(string $title): string`

Removes trailing episode/season/part numbers from series titles. Munowatch often appends episode numbers to the series title (e.g., "Winds of Love 139").

**Pattern removal (ordered most specific → least specific, loops until no match):**

| Pattern | Example Input | Cleaned Output |
|---------|--------------|----------------|
| `Season II: Part 1` | Snowpiercer Season II: Part 1 | Snowpiercer |
| `Season 2: Episode 1` | Wednesday Season 2: Episode 1 | Wednesday |
| `- Episode 5` | Love Rain - Episode 7 | Love Rain |
| `: Episode 5` | The Crown: Episode 10 | The Crown |
| `Episode 5` | Vincenzo Episode 3 | Vincenzo |
| `EP 5` / `EPS 5` | The Hijra EPS 12 | The Hijra |
| `: Part 5` | Game: Part 3 | Game |
| `- Part 5` | Squid Game - Part 2 | Squid Game |
| `Part 5` | Vincenzo Part 3 | Vincenzo |
| `Season II` | Snowpiercer Season II | Snowpiercer |
| `Season 2` | Wednesday Season 2 | Wednesday |
| `1 - 2` (range) | Hanna 1 - 2 | Hanna |

**Smart trailing number removal (after pattern stripping):**

| Number | Condition | Action | Example |
|--------|-----------|--------|---------|
| ≥ 5 | Always | Remove | "Heroes 14" → "Heroes" |
| 3-4 | Base has 2+ words | Remove | "King Geunchogo 4" → "King Geunchogo" |
| 3-4 | Base has 1 word | Keep | "Echo 3" → "Echo 3" |
| 1-2 | Always | Keep | "Ludik 2" → "Ludik 2", "Pearl Harbor 1" → "Pearl Harbor 1" |

Also cleans orphan trailing punctuation (`:`, `-`, `,`).

---

### Protected/Internal Methods

#### `resolveSeriesCode(SeriesMovie $series): array`

Resolves `series_code` and `show_id` for API calls.

Tries these sources in order:
1. `$series->series_code` (if already stored)
2. First episode's `munowatch_id` → call Preview API → extract `series_code`
3. `$series->external_url` → extract video ID → call Preview API

Persists discovered `series_code` to the database for future use.

#### `callEpisodesRangeApi(string $showId, string $seriesCode, int $season): array`

Calls the munowatch episodes/range endpoint. Returns raw range objects.

Handles:
- Leading whitespace in API response (trimmed)
- Error responses (`{error: true, msg: "..."}`)
- JSON parse errors
- Missing expected keys (`eps`, `eps_range`)

#### `fetchMunowatchPreview(string $videoId): array`

Fetches preview data for a single video ID from the munowatch API.

#### `traverseEpisodeChain(string $startVideoId, int $expectedCount, SeriesMovie $series, int $startEpNum): array`

**The core chain traversal engine.**

Starting from `$startVideoId`, follows the `nxt_eps_id` chain:
1. Fetch preview for current video ID
2. Upsert episode to local DB
3. Check `nxt_eps_id`:
   - If `0` or same as current → `chainEnded = true`, stop
   - Otherwise → move to `nxt_eps_id`
4. Repeat until `$expectedCount` reached or chain ends

**Safety features:**
- `$visited` set prevents infinite loops (self-referencing chain)
- `$maxIterations = expectedCount + 5` as a hard stop
- 150ms delay between API calls
- Fallback to incrementing video ID if preview fails

**Returns:**
```php
compact('created', 'updated', 'skipped', 'errors', 'nextVideoId', 'chainEnded')
```

#### `upsertEpisode(SeriesMovie $series, array $data): array`

Creates or updates a local episode record:
- Checks for existing episode by `munowatch_id` within the same series
- **Update:** Only updates fields that are better (longer title, missing thumbnail, etc.)
- **Create:** Sets all fields, marks as `Active` if URL exists, `Inactive` otherwise

#### `parseRangeObject(array $range): array`

Parses a raw range from the API:
- Input: `{"eps": "  21-  40", "eps_range": "39551__39570"}`
- Output: Structured array with start/end episode, start/end video ID, contiguity check, label

#### `refreshSeriesMetadata(SeriesMovie $series): void`

Updates series record:
- Sets `total_episodes` count
- Activates series if it has active episodes
- Cleans title via `cleanSeriesTitle()`

#### `extractEpisodeNumber(string $title): ?int`

Extracts episode number from various title formats:
- `S01E05` → 5
- `Episode 12` / `EP 12` / `EPS 12` → 12
- `Part 3` → 3
- Trailing number: `A Woman in a Veil 42` → 42

#### `extractSeasonNumber(string $title): ?int`

Extracts season number from title:
- `S02E05` → 2
- `Season 3` → 3

#### `seriesToArray(SeriesMovie $series): array` / `episodeToArray(MovieModel $ep): array`

Data formatters for JSON responses. Convert Eloquent models to clean arrays.

---

## Backend: DebugPlayerProxyController

**File:** `app/Admin/Controllers/DebugPlayerProxyController.php` (~405 lines)

Thin controller that delegates to `SeriesFixerService` and `MovieFixerService`.

### Endpoints

| Method | Route | Handler | Purpose |
|--------|-------|---------|---------|
| POST | `debug-player/series-info` | `seriesInfo()` | Get full series data for debug player |
| POST | `debug-player/series-remote-episodes` | `seriesRemoteEpisodes()` | Fetch pagination ranges from munowatch |
| POST | `debug-player/fetch-range` | `fetchRange()` | Fetch episodes for a specific range (batched) |
| POST | `debug-player/check-activation` | `checkActivation()` | Check & activate series + clean title |
| POST | `debug-player/fix-series` | `fixSeries()` | Sync + fix all episodes |
| POST | `debug-player/fix-episode` | `fixEpisode()` | Fix a single episode |
| POST | `debug-player/sync-series` | `syncSeries()` | Sync episodes from remote |
| POST | `debug-player/proxy` | `proxy()` | Test video URLs |
| POST | `debug-player/fix-movie` | `fixMovie()` | Fix movie (for non-series) |
| GET | `debug-player/stream` | `stream()` | Video stream proxy (bypasses CDN hotlink) |

### fetchRange() — The Batch Entry Point

```php
public function fetchRange(Request $request)
```

Accepts: `series_id`, `range_index`, `season`, `batch_size`, `continue_from`, `continue_ep`, `range_end_ep`

After fetching, **always appends refreshed `series_info`** to the response so the JS can update the sidebar in real-time.

### checkActivation()

```php
public function checkActivation(Request $request)
```

Called when JS detects `chain_ended = true` on the last range. Returns activation result + refreshed series info.

---

## Backend: Routes

**File:** `app/Admin/routes.php`

All debug-player routes are inside the admin middleware group:

```php
// Series debug player
$router->post('debug-player/series-info', 'DebugPlayerProxyController@seriesInfo');
$router->post('debug-player/series-remote-episodes', 'DebugPlayerProxyController@seriesRemoteEpisodes');
$router->post('debug-player/fix-series', 'DebugPlayerProxyController@fixSeries');
$router->post('debug-player/fix-episode', 'DebugPlayerProxyController@fixEpisode');
$router->post('debug-player/sync-series', 'DebugPlayerProxyController@syncSeries');
$router->post('debug-player/fetch-range', 'DebugPlayerProxyController@fetchRange');
$router->post('debug-player/check-activation', 'DebugPlayerProxyController@checkActivation');

// Stream proxy (excluded from admin CSRF)
Route::get(...)->name('debug-player.stream');
```

---

## Frontend: ugflix-series-player.js

**File:** `public/vendor/ugflix-debug-player/ugflix-series-player.js` (~1283 lines)

### State Object

```javascript
var _state = {
    series: null,          // Series metadata from backend
    episodes: [],          // All episodes array
    seasons: {},           // Episodes grouped by season
    currentEpisode: null,  // Currently playing
    currentSeason: '1',
    isPlaying: false,
    isLoading: false,
    isFixing: false,
    isFetchingRange: false,
    logs: [],
    ranges: null,          // Remote range data
    rangesVisible: false,
};
```

### Key Functions

#### UI Rendering

| Function | Purpose |
|----------|---------|
| `_buildModal()` | Creates the full-screen modal HTML + CSS + event bindings |
| `_open(seriesData)` | Opens player, resets state, fetches series info |
| `_close()` | Pauses video, clears source, hides modal |
| `_renderSidebar()` | Renders season tabs + episode list + footer count |
| `_renderEpisodeList()` | Renders episodes for current season with status indicators |
| `_renderSeriesHeader()` | Updates series name, badges, status indicators |
| `_renderEpisodeInfo(ep)` | Detail view for the currently selected episode |
| `_renderRangesList()` | Renders range cards with progress bars, fetch buttons |

#### Playback

| Function | Purpose |
|----------|---------|
| `_playEpisode(ep)` | Sets current episode, builds URL queue, starts cascade |
| `_tryPlayQueue(queue, idx)` | Auto-cascade: Stream Proxy → CDN Fallback → Direct |
| `_getStreamUrl(rawUrl)` | Builds stream proxy URL with auth token |
| `_getCdnFallback(url)` | Replaces dead munowatch hostnames with CDN host |

#### Fetching

| Function | Purpose |
|----------|---------|
| `_fetchSeriesInfo(seriesId)` | Initial load — gets all series data from backend |
| `_fetchRangesData()` | Loads range/pagination data from remote API |
| `_fetchSingleRange(rangeIdx, $btn)` | Batched fetch for one range (3 eps/request) |
| `_doFullSync()` | Batched fetch of ALL ranges sequentially |
| `_fetchAllMissingRanges()` | Batched fetch of only incomplete ranges |
| `_checkAndActivateSeries()` | Calls backend to activate series after chain ends |

#### Fix Operations

| Function | Purpose |
|----------|---------|
| `_fixEpisode(movieId, $btn)` | Fix single episode via backend |
| `_fixSeries()` | Fix entire series (confirm dialog → backend call) |
| `_syncSeries()` | Sync all episodes (loads ranges first if needed) |

#### Helpers

| Function | Purpose |
|----------|---------|
| `_log(msg, type)` | Appends timestamped entry to debug log |
| `_showOverlay(text)` / `_hideOverlay()` | Video overlay messages |
| `_updateEpisodeInState(movieId, data)` | Updates episode in state arrays |
| `_findEpisodeById(id)` | Looks up episode by ID |
| `_sanitizeUrl(url)` | Strips newlines/tabs |
| `_escHtml(s)` | HTML entity escaping |

### Range Panel

The Ranges panel shows one card per range with:
- Status icon (✅ complete, 🟡 partial, 🔴 empty)
- Episode span (e.g., "Eps 41–60")
- Count (local/total)
- Progress bar
- Contiguity indicator (⚡ chain for non-contiguous ranges)
- Fetch / Re-fetch button

### Batch Fetch Contexts

There are **three** fetch contexts in the JS, all using the same backend endpoint (`fetch-range`):

| Context | Function | Trigger | Progress Display |
|---------|----------|---------|-----------------|
| **Single Range** | `_fetchSingleRange()` | Click "⬇ Fetch" on a range card | Progress bar on the card + `N/M` on button |
| **Full Sync** | `_doFullSync()` | Click "🔄 Sync All" | `R/T: label B{n}` format on title bar button |
| **Fetch All Missing** | `_fetchAllMissingRanges()` | Click "⬇ Fetch All Missing Ranges" | Counter on "Fetch All" button + per-card spinners |

All three contexts:
1. Pre-initialize `continueFrom`/`continueEp`/`rangeEndEp` from range data (FAST PATH from first batch)
2. Track `anyChainEnded` flag
3. Live-update sidebar after each batch response
4. Call `_checkAndActivateSeries()` when chain ends

---

## Data Flow: Batch Fetching

### Sequence Diagram: Fetching a Single Range

```
JS (_fetchSingleRange)          Backend (fetchRange)              Munowatch API
        │                              │                                │
        │ POST {series_id, range=0,    │                                │
        │       batch_size=3,          │                                │
        │       continue_from=39531,   │ ──── FAST PATH ────            │
        │       continue_ep=1,         │ (skip resolve+range)           │
        │       range_end_ep=20}       │                                │
        ├─────────────────────────────►│                                │
        │                              │  traverseEpisodeChain()        │
        │                              │  ├── preview(39531) ──────────►│
        │                              │  │◄──────── {nxt_eps_id:39533} │
        │                              │  │  upsert ep#1                │
        │                              │  ├── preview(39533) ──────────►│
        │                              │  │◄──────── {nxt_eps_id:39535} │
        │                              │  │  upsert ep#2                │
        │                              │  ├── preview(39535) ──────────►│
        │                              │  │◄──────── {nxt_eps_id:39537} │
        │                              │  │  upsert ep#3                │
        │                              │  └── 3 fetched (= batchSize)  │
        │                              │                                │
        │◄─────────────────────────────┤  {has_more:true,               │
        │  JSON response               │   next_video_id:'39537',       │
        │                              │   chain_ended:false}           │
        │                              │                                │
        │ UPDATE sidebar (live)        │                                │
        │ UPDATE progress bar          │                                │
        │                              │                                │
        │ POST {continue_from=39537,   │                                │
        │       continue_ep=4,         │ ──── FAST PATH ────            │
        │       range_end_ep=20}       │                                │
        ├─────────────────────────────►│                                │
        │        ... (repeat) ...      │                                │
        │                              │                                │
        │◄─────────────────────────────┤  {has_more:false,              │
        │  (last batch)                │   chain_ended:false}           │
        │                              │                                │
        │ _finishRangeFetch()          │                                │
        │ Refresh ranges panel         │                                │
```

### FAST PATH vs NORMAL PATH

```
NORMAL PATH (first batch only, when no continue_from):
  ┌─────────────────────────────────────────────────┐
  │ 1. resolveSeriesCode()  (~2-5s: may hit API)    │
  │ 2. callEpisodesRangeApi()  (~2-5s: API call)    │
  │ 3. parseRangeObject()                            │
  │ 4. traverseEpisodeChain()  (~5-15s: 3 previews) │
  │    TOTAL: ~9-25 seconds                          │
  └─────────────────────────────────────────────────┘

FAST PATH (continuation batches):
  ┌─────────────────────────────────────────────────┐
  │ 1. (SKIP resolve + range API)                    │
  │ 2. traverseEpisodeChain()  (~5-15s: 3 previews) │
  │    TOTAL: ~5-15 seconds                          │
  └─────────────────────────────────────────────────┘

OPTIMIZATION: JS pre-initializes continueFrom/continueEp/rangeEndEp
from the range data, so even the FIRST batch uses FAST PATH when
the range data is already loaded in the frontend.
```

---

## Data Flow: End-of-Chain Detection

The munowatch `nxt_eps_id` field links episodes in a chain. When the chain ends:

```
traverseEpisodeChain():
  preview(videoId) → {nxt_eps_id: 0}      → chainEnded = true
  preview(videoId) → {nxt_eps_id: videoId} → chainEnded = true  (self-ref)

fetchEpisodesForRange():
  reads chainEnded from traverseEpisodeChain result
  hasMore = !chainEnded && (nextEp <= rangeEndEp)
  response includes: chain_ended: true

JS (_fetchSingleRange / _doFullSync / _fetchAllMissingRanges):
  if (resp.chain_ended) anyChainEnded = true
  // On completion:
  if (anyChainEnded) _checkAndActivateSeries()

_checkAndActivateSeries():
  POST /debug-player/check-activation {series_id}
  → Backend: checkAndActivateSeries()
    → Counts local/active episodes
    → Cleans title
    → Marks is_active = 'Yes' if activeCount > 0
  ← Returns: { activated, title_cleaned, new_title, series_info }
  → JS updates state + re-renders sidebar + header
```

---

## Data Flow: Series Activation

A series is activated when:
1. Chain has ended (last episode reached) — detected by `nxt_eps_id == 0` or self-referencing
2. The series has at least one episode with `status = 'Active'`

**What gets updated:**
- `series_movies.is_active` → `'Yes'`
- `series_movies.total_episodes` → local episode count
- `series_movies.muno_processed` → `'Yes'`
- `series_movies.title` → cleaned title (if dirty)

**Conservative approach:** We don't require ALL episodes to be active — just at least one. This accounts for episodes that may have broken URLs but are otherwise synced.

---

## Title Cleaning System

### Why It Exists

Munowatch appends episode numbers to series titles in the preview data. When we fetch episode data, the `video_title` field comes with the episode number baked in (e.g., "Winds of Love 139"). The `cleanSeriesTitle()` method strips these suffixes.

**Scale:** ~887 out of ~2420 muno series had dirty titles before this system was added.

### Algorithm

1. **Pattern loop:** Apply 12 regex patterns repeatedly until no more match. Patterns are ordered most-specific first to avoid partial matches.
2. **Trailing punctuation cleanup:** Remove orphan `:`, `-`, `,` left after pattern stripping.
3. **Smart trailing number removal:** Uses word count and number magnitude to decide if a trailing number is an episode number or part of the real title.

### Edge Cases

| Input | Output | Reasoning |
|-------|--------|-----------|
| `Echo 3` | `Echo 3` | 1-word base + number < 5 → kept |
| `Ludik 2` | `Ludik 2` | 1-word base + number < 3 → kept |
| `Pearl Harbor 1` | `Pearl Harbor 1` | number < 3 → kept |
| `24` | `24` | No trailing number pattern (just a number) |
| `King Geunchogo 6` | `King Geunchogo` | number ≥ 5 → always removed |
| `Heroes 14` | `Heroes` | number ≥ 5 → always removed |
| `My Secret Bride 4` | `My Secret Bride` | 3-word base + number ≥ 3 → removed |

---

## Database Schema

### `series_movies` Table

Key columns used by this system:

| Column | Type | Purpose |
|--------|------|---------|
| `id` | int | Primary key |
| `title` | varchar | Series title (cleaned on activation) |
| `series_code` | varchar | Munowatch series code (persisted after first resolve) |
| `munowatch_id` | varchar | Munowatch identifier |
| `is_active` | varchar | `'Yes'` / `'No'` / `'Failed'` |
| `is_muno` | varchar | `'Yes'` if from munowatch |
| `muno_processed` | varchar | `'Yes'` if fully processed |
| `total_episodes` | int | Local episode count |
| `total_seasons` | int | Season count |
| `external_url` | text | Original munowatch URL |
| `thumbnail` | text | Thumbnail URL |
| `vj` | varchar | VJ name |
| `genre` | varchar | Genre |

### `movies` Table (Episodes)

Key columns:

| Column | Type | Purpose |
|--------|------|---------|
| `id` | int | Primary key |
| `title` | varchar | Episode title |
| `url` | text | Video stream URL |
| `external_url` | text | Munowatch preview API URL |
| `munowatch_id` | varchar | Video ID on munowatch |
| `category_id` | int | → `series_movies.id` |
| `status` | varchar | `'Active'` / `'Inactive'` |
| `episode_number` | varchar | Episode number within series |
| `season_number` | varchar | Season number |
| `type` | varchar | `'Series'` for series episodes |
| `is_muno` | varchar | `'Yes'` if from munowatch |
| `muno_processed` | varchar | Processing status |

---

## Configuration & Constants

| Item | Value | Where |
|------|-------|-------|
| PHP version | 8.2.20 | `/Applications/MAMP/bin/php/php8.2.20/bin/php` |
| APP_URL | `http://localhost:8888/katogo/` | `.env` |
| MAMP FastCGI timeout | 30 seconds | MAMP config (cannot be changed easily) |
| Batch size | 3 episodes/request | Default in controller + JS |
| API delay | 150ms between previews | `traverseEpisodeChain()` |
| Range delay | 300ms between ranges | `syncAllEpisodes()` |
| Stream proxy token | SHA1 of `'debug-stream-' + APP_KEY` | `stream()` method |
| Dead CDN hosts | `munoserver*.*, muno*.club, gumite.club, munowatch.co` | JS `DEAD_HOSTS` |
| CDN replacement | `munotek.b-cdn.net` | JS `CDN_HOST` |

---

## Troubleshooting

### 500 Error on Fetch

**Cause:** MAMP FastCGI 30-second idle timeout.
**Solution:** Ensure `batch_size` stays ≤ 3. If the munowatch API is slow, even 3 might be too many — try reducing to 2.

### Episodes Not Appearing in Sidebar

**Cause:** Sidebar only updates after each batch response.
**Check:** Ensure `resp.series_info` is included in the fetch-range response. The controller always appends it after a successful fetch.

### Chain Never Ends

**Cause:** Some series on munowatch have a circular chain (last episode's `nxt_eps_id` points back to an earlier episode).
**Protection:** The `$visited` set in `traverseEpisodeChain()` detects revisits and breaks the loop.

### Title Not Cleaned

**Cause:** The trailing number is ≤ 2 and the base title has ≤ 1 word.
**By design:** We keep small numbers on short titles to protect real titles like "Echo 3" and "Ludik 2".

### Series Not Activated

**Cause:** No episodes with `status = 'Active'`. Common when all episodes have broken URLs.
**Solution:** Run "Fix Series" to repair broken URLs, then activation will trigger.

### Ranges Show as Complete But Episodes Missing

**Cause:** The `local_count` check uses video ID range bounds, not exact episode matching.
**Workaround:** Use "↻ Re-fetch" to re-traverse the chain for that range.

---

## File Inventory

| File | Lines | Purpose |
|------|-------|---------|
| `app/Services/SeriesFixerService.php` | ~1050 | Core service |
| `app/Admin/Controllers/DebugPlayerProxyController.php` | ~405 | HTTP controller |
| `app/Admin/routes.php` | ~150 | Route definitions |
| `public/vendor/ugflix-debug-player/ugflix-series-player.js` | ~1283 | Frontend UI |
| `SERIES_FETCHING_SYSTEM.md` | This file | Documentation |
