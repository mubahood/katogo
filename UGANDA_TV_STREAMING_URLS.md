# Uganda Free TV Streaming URLs

> **Last Updated:** February 2026  
> **Sources:** iptv-org/iptv GitHub repository, iptv-org API (streams.json, channels.json), git commit history, iptvcat.com, GitHub issues  
> **Protocol:** All streams use HLS (HTTP Live Streaming) with `.m3u8` playlists unless noted otherwise  
> **Tested From:** macOS (some streams may be geo-restricted to Uganda/East Africa)

---

## Table of Contents

1. [Currently Active Streams](#1-currently-active-streams-verified-live)
2. [Listed but Intermittent Streams](#2-listed-but-intermittent-streams)
3. [Previously Working Streams (from Git History)](#3-previously-working-streams-from-git-history)
4. [All Known Uganda Channels (No Stream Available)](#4-all-known-uganda-channels-no-stream-available)
5. [Raw M3U Playlist](#5-raw-m3u-playlist-for-import)
6. [CDN Providers & URL Patterns](#6-cdn-providers--url-patterns)
7. [Notes & Tips](#7-notes--tips)

---

## 1. Currently Active Streams (Verified Live)

These streams returned HTTP 200 at time of testing and are listed in the current iptv-org repository.

| # | Channel | Quality | Category | Stream URL | CDN Provider |
|---|---------|---------|----------|------------|--------------|
| 1 | **Ark TV** | 1080p | Religious | `https://stream.hydeinnovations.com/arktv-international/index.fmp4.m3u8` | Hyde Innovations (Flussonic) |
| 2 | **Alpha Digital** | 480p | Religious | `https://streamfi-alphatvdgtl1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes |
| 3 | **BTV** | 480p | Entertainment | `https://streamfi-alphadgtl1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes |
| 4 | **Bukedde TV 1** | 576p | General | `https://stream.hydeinnovations.com/bukedde1flussonic/index.m3u8` | Hyde Innovations (Flussonic) |
| 5 | **Bukedde TV 2** | 576p | General | `https://stream.hydeinnovations.com/bukedde2flussonic/index.m3u8` | Hyde Innovations (Flussonic) |
| 6 | **Dream TV** | 480p | Religious | `https://streamfi-dreamtv1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes |
| 7 | **FORT TV** | 480p | Entertainment | `https://fort.co-works.org/memfs/87017643-274a-4bc0-a786-7767a0d159c2.m3u8` | Co-Works |
| 8 | **Praise Jesus Tower TV** | 480p | Religious | `https://vsrv1.az-streamingserver.com:3555/live/dyjoqlgklive.m3u8` | AZ Streaming Server |
| 9 | **Ramogi TV** | 720p | General | `https://citizentv.castr.com/5ea49827ff3b5d7b22708777/live_9b761ff063f511eca12909b8ef1524b4/index.m3u8` | Castr |
| 10 | **TV West** | 720p | General | `https://stream.hydeinnovations.com/tvwest-flussonic/index.m3u8` | Hyde Innovations (Flussonic) |
| 11 | **Wan Luo TV** | 576p | General | `https://stream.hydeinnovations.com/luotv-flussonic/index.m3u8` | Hyde Innovations (Flussonic) |

---

## 2. Listed but Intermittent Streams

These are in the current iptv-org playlist but were not responding (offline, timeout, or 404) at time of testing. They may come back online as many are marked "[Not 24/7]".

| # | Channel | Quality | Category | Stream URL | Status | Notes |
|---|---------|---------|----------|------------|--------|-------|
| 1 | **3ABN TV Uganda** | 720p | Religious | `https://3abn.bozztv.com/3abn/3abn_uganda_live/index.m3u8` | 404 | Not 24/7 |
| 2 | **ACW UG TV** | 480p | General/Music | `https://live.acwugtv.com/hls/stream.m3u8` | Timeout | Server not responding |
| 3 | **Alpha TV Digital** | 480p | Religious | `https://streamfi-alphatvdgtl1.zettawiseroutes.com:8181/hls/stream.m3u8` | 404 | Different URL from Alpha Digital above |
| 4 | **BTM TV** | 480p | General | `https://btmug.zerocdn.org/hls/stream.m3u8` | Timeout | Not 24/7 |
| 5 | **Faraja Television** | 1080p | General | `https://panel.freedomflixtv.org:3868/hybrid/play.m3u8` | Timeout | Not 24/7 |
| 6 | **Galaxy TV** | 720p | Music | `https://stream.castr.com/6463248048d6cd3e143655b2/live_43351ad0f3b411ed81c78fcc31887c54/index.fmp4.m3u8` | 403 | Requires referrer (see below) |
| 7 | **Gugudde TV** | 480p | Religious | `https://jk3lzqq4lw79-hls-live.5centscdn.com/gugudde/c9a1fdac6e082dd89e7173244f34d7b3.sdp/chunks.m3u8` | Timeout | May be geo-restricted |
| 8 | **Salt TV** | 1080p | Religious | `https://live.salttelevision.com/app/stream/abr.m3u8` | 404 | Not 24/7 |

### Referrer Requirements

**Galaxy TV** requires an HTTP Referrer header to play:
```
Referrer: https://player.castr.com/live_43351ad0f3b411ed81c78fcc31887c54
```

In VLC: Open Network Stream → Show More Options → paste the referrer in "Edit Options":
```
:http-referrer=https://player.castr.com/live_43351ad0f3b411ed81c78fcc31887c54
```

---

## 3. Previously Working Streams (from Git History)

These streams were found in older commits of the iptv-org repository (October–November 2025) but have since been removed. They may still work intermittently or could be restored in the future.

### 3a. UVO TV / Aniview CDN Streams (via Fastly)

**Base URL pattern:** `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/{channel}/playlist.m3u8`

These streams were provided through uvotv.com's Aniview CDN. Currently returning HTTP 403 (Forbidden) — they may require accessing through the uvotv.com website or a specific app.

| # | Channel | Stream URL | Last Seen |
|---|---------|------------|-----------|
| 1 | **NBS TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/nbs/playlist.m3u8` | Oct 2025 |
| 2 | **NTV Uganda** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ntvuganda/playlist.m3u8` | Oct 2025 |
| 3 | **UBC TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ubc/playlist.m3u8` | Oct 2025 |
| 4 | **Spark TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/sparktv/playlist.m3u8` | Oct 2025 |
| 5 | **BBS TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/bbstv/playlist.m3u8` | Oct 2025 |
| 6 | **Bukedde TV 1** (alt) | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/bukkede1/playlist.m3u8` | Oct 2025 |
| 7 | **Bukedde TV 2** (alt) | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/bukkede2/playlist.m3u8` | Oct 2025 |
| 8 | **Baba TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/babatv/playlist.m3u8` | Oct 2025 |
| 9 | **KBS TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/kbstv/playlist.m3u8` | Oct 2025 |
| 10 | **KSTV Uganda** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/kstv/playlist.m3u8` | Oct 2025 |
| 11 | **KTV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ktv/playlist.m3u8` | Oct 2025 |
| 12 | **Chamuka TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/chamuka/playlist.m3u8` | Oct 2025 |
| 13 | **Top TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/toptv/playlist.m3u8` | Oct 2025 |
| 14 | **Channel 44 Uganda** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/channel44uganda/playlist.m3u8` | Oct 2025 |
| 15 | **Salt TV** (alt) | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/salttv/playlist.m3u8` | Oct 2025 |
| 16 | **TV West** (alt) | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/tvwest/playlist.m3u8` | Oct 2025 |
| 17 | **UCTV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/uctv/playlist.m3u8` | Oct 2025 |
| 18 | **Salam TV** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/salamtv/playlist.m3u8` | Oct 2025 |
| 19 | **Hope Channel Uganda** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/hopechanneluganda/playlist.m3u8` | Oct 2025 |

### 3b. Other Previously Working Streams

| # | Channel | Stream URL | CDN | Last Seen |
|---|---------|------------|-----|-----------|
| 1 | **CCCO Aspire TV** | `https://ythls.armelin.one/channel/UCixLGM43P-6J5JLKGlXPjCA.m3u8` | YouTube HLS Proxy | Nov 2025 |
| 2 | **COU Family Television** | `https://ythls.armelin.one/channel/UCEoMj0-s-_04oiOiWqyBtQg.m3u8` | YouTube HLS Proxy | Nov 2025 |
| 3 | **Freedom Love Zone TV** | `https://panel.freedomflixtv.org:3852/hybrid/play.m3u8` | Freedom Flix | Nov 2025 |
| 4 | **Freedom Movie Sphere TV** | `https://panel.freedomflixtv.org:3854/hybrid/play.m3u8` | Freedom Flix | Nov 2025 |
| 5 | **Galaxy TV** (alt) | `https://streamfi-galaxytv1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes | Nov 2025 |
| 6 | **Ground TV** | `https://stream.hydeinnovations.com/ground-tv/index.m3u8` | Hyde Innovations | Nov 2025 |
| 7 | **Host TV** | `https://streamfi-hosttv1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes | Nov 2025 |
| 8 | **KSTV Uganda** (alt) | `https://streamfi-kstv1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes | Nov 2025 |
| 9 | **Spirit of Glory TV** | `https://streamfi-spiritofglorytv1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes | Nov 2025 |
| 10 | **Spirit TV** | `https://streamfi-spirittv1.zettawiseroutes.com:8181/hls/stream.m3u8` | ZettaWise Routes | Nov 2025 |
| 11 | **Trust TV** | `https://50de0c354d.tuhlprintltd.com/live/trust-tv/index.m3u8` | Tuhlprint Ltd | Nov 2025 |
| 12 | **Westnile TV** | `https://az-streamingserver.com:8443/live/westniletv/playlist.m3u8` | AZ Streaming Server | Nov 2025 |

---

## 4. All Known Uganda Channels (No Stream Available)

These 87 Ugandan TV channels are registered in the iptv-org channels database but currently have **no known free streaming URL**. They may have streams in the future.

<details>
<summary>Click to expand full list (87 channels)</summary>

| Channel | Category |
|---------|----------|
| ABS TV | General |
| Akaboozi FM TV | General |
| Amazima TV | Religious |
| ATV | General |
| Awakening TV | Religious |
| Baba TV | General |
| BBG TV | General |
| BBS TV | General |
| Biiso TV | General |
| Bro. Ronnie TV | Religious |
| CBS TV Buganda | General |
| CBS TV Busoga | General |
| Channel 44 Uganda | General |
| Chamuka TV | General |
| City TV Uganda | General |
| Delta TV Uganda | General |
| Digida TV | General |
| E-Church TV | Religious |
| East Africa TV | General |
| Eastern Voice TV | General |
| EBB TV | General |
| EBS TV | General |
| EMTV Uganda | Entertainment |
| Faithful TV | Religious |
| Fire TV Uganda | Religious |
| HARVEST TV | Religious |
| HBC TV | General |
| Impact TV | General |
| K24 TV Uganda | News |
| KBS TV | General |
| Kingdom TV Uganda | Religious |
| KTV Uganda | General |
| Life TV Uganda | Religious |
| Live TV Uganda | General |
| LTV Uganda | General |
| Magic TV Uganda | Entertainment |
| Mercy TV | Religious |
| Miracle Television | Religious |
| Moyo TV | General |
| MTV Uganda | Music |
| Multichoice Uganda | General |
| NBS Television | News/General |
| NBS Uncut | Entertainment |
| Next Media TV | News |
| NTV Uganda | News/General |
| One Love TV | Entertainment |
| One TV Uganda | General |
| Pearl Magic Prime | Entertainment |
| Pearl of Africa TV | General |
| Power TV Uganda | Religious |
| Record TV Uganda | General |
| Revival Church TV | Religious |
| Rwenzori TV | General |
| Salam TV | Religious |
| Sanyuka TV | Entertainment |
| SBS TV Uganda | General |
| Shauri Yako TV | Entertainment |
| Simba TV | Entertainment |
| Spark TV | News |
| Star TV Uganda | General |
| Switch TV Uganda | Entertainment |
| Teso TV | General |
| Top TV Uganda | General |
| Tooro Television | General |
| True North TV | General |
| UBC TV | General |
| UCTV | General |
| Urban TV | Entertainment |
| UTV Uganda | General |
| Victory Church TV | Religious |
| Voice of Toro TV | General |
| Waka TV | Entertainment |
| Impact FM TV | General |
| NRG Radio TV Uganda | Music |
| Gospel Life TV | Religious |
| Breakthrough TV | Religious |
| Eagle TV Uganda | General |
| Crystal TV Uganda | Entertainment |
| Hope Channel Uganda | Religious |
| Kingdom Media TV | Religious |
| Lighthouse TV | Religious |
| Family TV Uganda | General |
| Heritage TV | General |
| Kampala TV | General |
| Light TV Uganda | Religious |
| Nile TV Uganda | General |
| Omega TV Uganda | Religious |

</details>

---

## 5. Raw M3U Playlist (For Import)

Copy this entire block and save as `uganda_tv.m3u` to import into VLC, IPTV Smarters, TiviMate, or any M3U-compatible player.

```m3u
#EXTM3U

#EXTINF:-1 tvg-id="ArkTV.ug" tvg-logo="https://i.imgur.com/yCHNZXD.png" group-title="Religious",Ark TV (1080p) [Not 24/7]
https://stream.hydeinnovations.com/arktv-international/index.fmp4.m3u8

#EXTINF:-1 tvg-id="AlphaDigital.ug" tvg-logo="https://i.imgur.com/sLt242H.png" group-title="Religious",Alpha Digital (480p)
https://streamfi-alphatvdgtl1.zettawiseroutes.com:8181/hls/stream.m3u8

#EXTINF:-1 tvg-id="BTV.ug" tvg-logo="https://i.imgur.com/rcHZ1al.png" group-title="Entertainment",BTV (480p)
https://streamfi-alphadgtl1.zettawiseroutes.com:8181/hls/stream.m3u8

#EXTINF:-1 tvg-id="BukeddeTV1.ug" tvg-logo="https://i.imgur.com/HFq5QlJ.png" group-title="General",Bukedde TV 1 (576p)
https://stream.hydeinnovations.com/bukedde1flussonic/index.m3u8

#EXTINF:-1 tvg-id="BukeddeTV2.ug" tvg-logo="https://i.imgur.com/ukwPZeY.png" group-title="General",Bukedde TV 2 (576p) [Not 24/7]
https://stream.hydeinnovations.com/bukedde2flussonic/index.m3u8

#EXTINF:-1 tvg-id="DreamTV.ug" tvg-logo="https://i.imgur.com/XRUDhqQ.png" group-title="Religious",Dream TV (480p)
https://streamfi-dreamtv1.zettawiseroutes.com:8181/hls/stream.m3u8

#EXTINF:-1 tvg-id="FORTTV.ug" tvg-logo="https://i.ibb.co/4KN5zW9/fort-tv-logo-GENE.png" group-title="Entertainment",FORT TV (480p)
https://fort.co-works.org/memfs/87017643-274a-4bc0-a786-7767a0d159c2.m3u8

#EXTINF:-1 tvg-id="PraiseJesusTowerTV.ug" tvg-logo="https://i.imgur.com/KT4qIve.png" group-title="Religious",Praise Jesus Tower TV (480p)
https://vsrv1.az-streamingserver.com:3555/live/dyjoqlgklive.m3u8

#EXTINF:-1 tvg-id="RamogiTV.ke" tvg-logo="https://i.imgur.com/N2Uz9mc.jpg" group-title="General",Ramogi TV (720p)
https://citizentv.castr.com/5ea49827ff3b5d7b22708777/live_9b761ff063f511eca12909b8ef1524b4/index.m3u8

#EXTINF:-1 tvg-id="TVWest.ug" tvg-logo="https://i.imgur.com/EiJzkIz.png" group-title="General",TV West (720p)
https://stream.hydeinnovations.com/tvwest-flussonic/index.m3u8

#EXTINF:-1 tvg-id="WanLuoTV.ug" tvg-logo="https://i.imgur.com/4PUUp3E.png" group-title="General",Wan Luo TV (576p)
https://stream.hydeinnovations.com/luotv-flussonic/index.m3u8

#EXTINF:-1 tvg-id="3ABNTVUganda.ug" tvg-logo="https://i.imgur.com/mml9lI2.png" group-title="Religious",3ABN TV Uganda (720p) [Not 24/7]
https://3abn.bozztv.com/3abn/3abn_uganda_live/index.m3u8

#EXTINF:-1 tvg-id="ACWUGTV.ug" tvg-logo="https://i.imgur.com/8pzEmcJ.jpeg" group-title="General;Music",ACW UG TV (480p)
https://live.acwugtv.com/hls/stream.m3u8

#EXTINF:-1 tvg-id="BTMTV.ug" tvg-logo="https://i.imgur.com/NUvA2jf.png" group-title="General",BTM TV (480p) [Not 24/7]
https://btmug.zerocdn.org/hls/stream.m3u8

#EXTINF:-1 tvg-id="FarajaTelevision.ug" tvg-logo="https://i.imgur.com/NmnOGHI.jpg" group-title="General",Faraja Television (1080p) [Not 24/7]
https://panel.freedomflixtv.org:3868/hybrid/play.m3u8

#EXTINF:-1 tvg-id="GalaxyTV.ug" tvg-logo="https://i.imgur.com/P5OABe5.png" group-title="Music",Galaxy TV (720p) [Not 24/7]
#EXTVLCOPT:http-referrer=https://player.castr.com/live_43351ad0f3b411ed81c78fcc31887c54
https://stream.castr.com/6463248048d6cd3e143655b2/live_43351ad0f3b411ed81c78fcc31887c54/index.fmp4.m3u8

#EXTINF:-1 tvg-id="GuguddeTV.ug" tvg-logo="https://i.imgur.com/XEakc6Q.png" group-title="Religious",Gugudde TV (480p)
https://jk3lzqq4lw79-hls-live.5centscdn.com/gugudde/c9a1fdac6e082dd89e7173244f34d7b3.sdp/chunks.m3u8

#EXTINF:-1 tvg-id="SaltTV.ug" tvg-logo="https://i.imgur.com/AK9nE6Y.png" group-title="Religious",Salt TV (1080p) [Not 24/7]
https://live.salttelevision.com/app/stream/abr.m3u8

#EXTINF:-1 tvg-id="NBS.ug" group-title="News",NBS TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/nbs/playlist.m3u8

#EXTINF:-1 tvg-id="NTVUganda.ug" group-title="News",NTV Uganda [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ntvuganda/playlist.m3u8

#EXTINF:-1 tvg-id="UBC.ug" group-title="General",UBC TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ubc/playlist.m3u8

#EXTINF:-1 tvg-id="SparkTV.ug" group-title="News",Spark TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/sparktv/playlist.m3u8

#EXTINF:-1 tvg-id="BBSTV.ug" group-title="General",BBS TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/bbstv/playlist.m3u8

#EXTINF:-1 tvg-id="BabaTV.ug" group-title="General",Baba TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/babatv/playlist.m3u8

#EXTINF:-1 tvg-id="KBSTV.ug" group-title="General",KBS TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/kbstv/playlist.m3u8

#EXTINF:-1 tvg-id="KTV.ug" group-title="General",KTV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/ktv/playlist.m3u8

#EXTINF:-1 tvg-id="ChamukaTv.ug" group-title="General",Chamuka TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/chamuka/playlist.m3u8

#EXTINF:-1 tvg-id="TopTV.ug" group-title="General",Top TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/toptv/playlist.m3u8

#EXTINF:-1 tvg-id="Channel44Uganda.ug" group-title="General",Channel 44 Uganda [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/channel44uganda/playlist.m3u8

#EXTINF:-1 tvg-id="UCTV.ug" group-title="General",UCTV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/uctv/playlist.m3u8

#EXTINF:-1 tvg-id="SalamTV.ug" group-title="Religious",Salam TV [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/salamtv/playlist.m3u8

#EXTINF:-1 tvg-id="HopeChannelUganda.ug" group-title="Religious",Hope Channel Uganda [Requires Referrer - May Not Work]
https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/hopechanneluganda/playlist.m3u8

#EXTINF:-1 tvg-id="CCCOAspireTV.ug" group-title="Religious",CCCO Aspire TV [YouTube Proxy - May Not Work]
https://ythls.armelin.one/channel/UCixLGM43P-6J5JLKGlXPjCA.m3u8

#EXTINF:-1 tvg-id="COUFamilyTV.ug" group-title="Religious",COU Family Television [YouTube Proxy - May Not Work]
https://ythls.armelin.one/channel/UCEoMj0-s-_04oiOiWqyBtQg.m3u8

#EXTINF:-1 tvg-id="FreedomLoveZone.ug" group-title="Entertainment",Freedom Love Zone TV [May Not Work]
https://panel.freedomflixtv.org:3852/hybrid/play.m3u8

#EXTINF:-1 tvg-id="FreedomMovieSphere.ug" group-title="Entertainment",Freedom Movie Sphere TV [May Not Work]
https://panel.freedomflixtv.org:3854/hybrid/play.m3u8

#EXTINF:-1 tvg-id="GroundTV.ug" group-title="General",Ground TV [May Not Work]
https://stream.hydeinnovations.com/ground-tv/index.m3u8

#EXTINF:-1 tvg-id="HostTV.ug" group-title="General",Host TV [May Not Work]
https://streamfi-hosttv1.zettawiseroutes.com:8181/hls/stream.m3u8

#EXTINF:-1 tvg-id="KSTVUganda.ug" group-title="General",KSTV Uganda [May Not Work]
https://streamfi-kstv1.zettawiseroutes.com:8181/hls/stream.m3u8

#EXTINF:-1 tvg-id="SpiritOfGloryTV.ug" group-title="Religious",Spirit of Glory TV [May Not Work]
https://streamfi-spiritofglorytv1.zettawiseroutes.com:8181/hls/stream.m3u8

#EXTINF:-1 tvg-id="SpiritTV.ug" group-title="Religious",Spirit TV [May Not Work]
https://streamfi-spirittv1.zettawiseroutes.com:8181/hls/stream.m3u8

#EXTINF:-1 tvg-id="TrustTV.ug" group-title="General",Trust TV [May Not Work]
https://50de0c354d.tuhlprintltd.com/live/trust-tv/index.m3u8

#EXTINF:-1 tvg-id="WestnileTV.ug" group-title="General",Westnile TV [May Not Work]
https://az-streamingserver.com:8443/live/westniletv/playlist.m3u8
```

---

## 6. CDN Providers & URL Patterns

Understanding the CDN providers helps discover new streams when channels switch URLs.

### Active CDN Providers

| CDN Provider | Base URL Pattern | Streams Hosted |
|--------------|-----------------|----------------|
| **Hyde Innovations** (Flussonic) | `https://stream.hydeinnovations.com/{channel}/index.m3u8` | Bukedde 1 & 2, TV West, Wan Luo TV, Ark TV, Ground TV |
| **ZettaWise Routes** | `https://streamfi-{channel}1.zettawiseroutes.com:8181/hls/stream.m3u8` | Alpha Digital, BTV, Dream TV, Galaxy TV, Host TV, KSTV, Spirit TV, Spirit of Glory TV |
| **Castr** | `https://stream.castr.com/{id}/live_{token}/index.fmp4.m3u8` | Galaxy TV |
| **Co-Works** | `https://fort.co-works.org/memfs/{uuid}.m3u8` | FORT TV |
| **AZ Streaming Server** | `https://vsrv1.az-streamingserver.com:{port}/live/{channel}.m3u8` | Praise Jesus Tower TV, Westnile TV |
| **Freedom Flix** | `https://panel.freedomflixtv.org:{port}/hybrid/play.m3u8` | Faraja TV, Freedom Love Zone, Freedom Movie Sphere |
| **5CentsCDN** | `https://{id}-hls-live.5centscdn.com/{channel}/{token}/chunks.m3u8` | Gugudde TV |

### Inactive/Historical CDN Providers

| CDN Provider | Base URL Pattern | Status |
|--------------|-----------------|--------|
| **UVO TV / Aniview (Fastly)** | `https://uvotv-aniview.global.ssl.fastly.net/hls/live/2119694/{channel}/playlist.m3u8` | 403 Forbidden (was hosting 19+ major Uganda channels) |
| **BozzTV** | `https://3abn.bozztv.com/3abn/{channel}/index.m3u8` | 404 (3ABN Uganda) |
| **ZeroCDN** | `https://btmug.zerocdn.org/hls/stream.m3u8` | Timeout (BTM TV) |
| **YouTube HLS Proxy** | `https://ythls.armelin.one/channel/{youtube_channel_id}.m3u8` | 404 (CCCO Aspire, COU Family) |
| **Tuhlprint Ltd** | `https://50de0c354d.tuhlprintltd.com/live/{channel}/index.m3u8` | Timeout (Trust TV) |

---

## 7. Notes & Tips

### How to Play These Streams

1. **VLC Media Player**: Media → Open Network Stream → paste URL
2. **IPTV Smarters / TiviMate**: Add playlist → paste the M3U URL or load the `.m3u` file
3. **Kodi**: PVR IPTV Simple Client → set M3U playlist path
4. **Web Browser**: Use an HLS player extension or site like `https://www.hlsplayer.org/`
5. **FFplay**: `ffplay "stream_url_here"`

### Stream Availability Notes

- Streams marked **[Not 24/7]** may only be active during broadcast hours (typically 6 AM – 12 AM EAT)
- **UVO TV streams (403)**: These major channels (NBS, NTV, UBC, Spark TV, etc.) were previously free through the UVO TV platform. They now appear to require authentication or are geo-restricted. Check https://uvotv.com for current access
- **Timeout (000)** streams may be temporarily down or geo-restricted to East Africa
- Stream URLs change frequently — check the iptv-org GitHub repository for the latest: https://github.com/iptv-org/iptv/blob/master/streams/ug.m3u
- The iptv-org API can be queried for live data: https://iptv-org.github.io/api/streams.json

### Data Sources Used

| Source | URL | What it provided |
|--------|-----|-----------------|
| iptv-org (current) | `https://iptv-org.github.io/iptv/countries/ug.m3u` | 19 currently listed streams |
| iptv-org streams API | `https://iptv-org.github.io/api/streams.json` | Verified stream data with metadata |
| iptv-org channels API | `https://iptv-org.github.io/api/channels.json` | 104 registered Uganda channels |
| iptv-org git history (Oct 2025) | Commit `0c62942` of `streams/ug.m3u` | ~50 streams (including uvotv CDN) |
| iptv-org git history (Nov 2025) | Commit `e13a2649` of `streams/ug.m3u` | ~30 streams |
| GitHub Issues | Issue #27595 | NTV Uganda stream confirmation |

---

## Summary Statistics

| Metric | Count |
|--------|-------|
| **Total unique stream URLs collected** | 49 |
| **Currently verified live (HTTP 200)** | 11 |
| **Listed but intermittent (Not 24/7 / Timeout)** | 8 |
| **Previously working (from git history)** | 30 |
| **Known Uganda channels (total)** | 104 |
| **Channels with no known stream** | ~87 |
| **CDN providers identified** | 12 |
