# Katogo Streaming Optimization Guide

Server (Python/ffmpeg) + Flutter setup to make video start faster and buffer less.

## 1. Server side — `scripts/optimize_videos.py`

```bash
# Step 1 (do this first, safe + fast): fix slow-start on ALL existing MP4s
python3 scripts/optimize_videos.py faststart /var/www/movies

# Step 2 (background batch, re-encodes): adaptive HLS, 360p/480p/720p
python3 scripts/optimize_videos.py hls-batch /var/www/movies /var/www/hls
# → produces /var/www/hls/{movie}/master.m3u8  ← this is the new stream URL
```

Run the HLS batch with `nohup ... &` or a queue worker — expect roughly 0.3–1× movie
duration per file on a typical VPS with `veryfast` preset.

**DB change:** add `hls_url` column to `movie_models`; app uses it when present,
falls back to the MP4 `url` otherwise. Migrate the catalog gradually (most-watched first —
your search analytics tell you which).

## 2. Nginx — serve segments correctly

```nginx
location /hls/ {
    root /var/www;
    add_header Access-Control-Allow-Origin *;

    location ~ \.m3u8$ {
        add_header Cache-Control "no-cache";          # playlists must stay fresh
        types { application/vnd.apple.mpegurl m3u8; }
    }
    location ~ \.ts$ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        types { video/mp2t ts; }
    }
}
# For MP4s you haven't converted yet, make sure range requests work (default on),
# and enable sendfile/tcp_nopush:
sendfile on; tcp_nopush on; aio threads;
```

Put **Cloudflare (free)** in front of the media domain — `.ts` segments are immutable
and cache perfectly at edges close to your users. This alone often halves buffering.

## 3. Flutter side

Use **`better_player_plus`** (maintained fork of better_player) — adaptive HLS,
quality selector, buffer tuning, caching:

```dart
BetterPlayerDataSource(
  BetterPlayerDataSourceType.network,
  movie.hlsUrl ?? movie.url,                 // master.m3u8 preferred
  videoFormat: BetterPlayerVideoFormat.hls,
  bufferingConfiguration: const BetterPlayerBufferingConfiguration(
    minBufferMs: 3000,        // start playing sooner
    maxBufferMs: 60000,
    bufferForPlaybackMs: 1500,
    bufferForPlaybackAfterRebufferMs: 3000,
  ),
  cacheConfiguration: const BetterPlayerCacheConfiguration(
    useCache: true,           // caches watched segments (resume = instant)
    maxCacheSize: 200 * 1024 * 1024,
    maxCacheFileSize: 50 * 1024 * 1024,
  ),
)
```

Alternative: **`media_kit`** (newer, very smooth, mpv-based) if you're refactoring the
player anyway. Plain `video_player` also plays HLS but offers no buffer/cache control.

Extra Flutter wins: pre-initialize the controller on the movie detail page (before the
user taps Play), and start playback at a low quality by listing 360p first in the master
playlist (the script's ladder order already does this).

## 4. Why this works

| Problem today | Fix |
|---|---|
| Player waits for metadata at end of MP4 | `faststart` remux → instant start |
| One big bitrate for all networks | HLS ladder → phone picks 360p on 3G, 720p on WiFi |
| Every seek re-downloads from server far away | 6s segments + Cloudflare edge cache |
| Rebuffering kills sessions | Lower `bufferForPlaybackMs`, adaptive down-switch |
| Rewatching redownloads | Flutter segment cache |

## 5. Rollout order

1. `faststart` batch on all MP4s (zero risk, same files).
2. Nginx headers + Cloudflare on media domain.
3. Flutter player upgrade with buffer/cache config.
4. HLS batch for top-100 most-watched movies, `hls_url` in API.
5. Convert the rest gradually; new uploads get HLS at ingest time.
