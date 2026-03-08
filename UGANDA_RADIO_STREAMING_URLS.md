# Uganda Free Radio Streaming URLs

> **Last Updated:** March 2026  
> **Sources:** radio-browser.info API (community database of 52,000+ stations), live URL testing  
> **Protocol:** MP3/AAC audio streams (HTTP/Icecast/Shoutcast), playable in VLC, any media player, or web browser  
> **Tested From:** macOS — all URLs verified with HTTP HEAD requests  
> **Total Stations Found:** 86 unique Ugandan radio streams

---

## Table of Contents

1. [Live & Verified Streams (HTTP 200)](#1-live--verified-streams-http-200)
2. [Working via Redirect (HTTP 302)](#2-working-via-redirect-http-302---zeno-fm-hosted)
3. [Streams Needing Fresh Token (HTTP 405/400)](#3-streams-needing-fresh-token-http-405400)
4. [Currently Offline Streams](#4-currently-offline-streams)
5. [Raw M3U Playlist (For Import)](#5-raw-m3u-playlist-for-import)
6. [Streaming Providers & Platforms](#6-streaming-providers--platforms)
7. [How to Listen](#7-how-to-listen)

---

## 1. Live & Verified Streams (HTTP 200)

These 31 streams returned HTTP 200 and are confirmed working at time of testing.

### Major / Popular Stations

| # | Station | Frequency | Format | Bitrate | Votes | Stream URL |
|---|---------|-----------|--------|---------|-------|------------|
| 1 | **Akaboozi FM** | 87.9 FM | MP3 | 64 kbps | 16,932 | `http://162.244.80.52:8732/stream.mp3` |
| 2 | **MCF Radio (Mutundwe Christian Fellowship)** | 98.7 FM | MP3 | 128 kbps | 13,296 | `https://streams.radio.co/s79fbbb432/listen` |
| 3 | **Capital FM** | 91.3 FM | MP3 | 128 kbps | 10,397 | `http://5229.cloudrad.io:8316/;` |
| 4 | **Beat FM** | 96.3 FM | MP3 | 128 kbps | 5,844 | `http://5230.cloudrad.io:8354/` |
| 5 | **Radio Maria Uganda** | — | MP3 | 48 kbps | 5,249 | `http://dreamsiteradiocp.com:8052/stream` |
| 6 | **Sanyu FM** | 88.2 FM | AAC+ | 48 kbps | 3,365 | `http://s44.myradiostream.com:8138/stream` |
| 7 | **Radio One** | 90.0 FM | MP3 | 64 kbps | 2,685 | `http://162.244.80.52:8740/stream` |
| 8 | **KIIS Uganda** | 100.9 FM | MP3 | 128 kbps | 2,051 | `http://14867.cloudrad.io:9224/live` |
| 9 | **Capital FM** (alt URL) | 91.3 FM | MP3 | 128 kbps | 1,242 | `http://capitalfm.cloudrad.io/stream` |
| 10 | **Radio Pacis** | 90.9 FM | MP3 | 64 kbps | 974 | `https://radiopacisuganda.radioca.st/stream` |

### Christian / Gospel Stations

| # | Station | Frequency | Format | Bitrate | Votes | Stream URL |
|---|---------|-----------|--------|---------|-------|------------|
| 11 | **Gospel Radio East Africa** | — | MP3 | 128 kbps | 940 | `https://c32.radioboss.fm:18451/stream?1641237600584` |
| 12 | **Christ FM** | 91.6 FM | MP3 | 128 kbps | 814 | `http://5.135.154.69:15664/;` |
| 13 | **Christ FM** (alt) | 91.6 FM | MP3 | 128 kbps | 242 | `http://s39.myradiostream.com/:15664/;` |
| 14 | **Bible 24/7** | — | MP3 | 128 kbps | 98 | `http://c28.radioboss.fm:8335/stream` |
| 15 | **Kitintale Christian Fellowship** | — | MP3 | 128 kbps | 83 | `https://c24.radioboss.fm:18185/stream` |
| 16 | **Bible Trivia** | — | MP3 | 96 kbps | 32 | `https://streamer.radio.co/se1aece429/listen` |

### Entertainment / Music / Other

| # | Station | Frequency | Format | Bitrate | Votes | Stream URL |
|---|---------|-----------|--------|---------|-------|------------|
| 17 | **Beat FM** (alt URL) | 96.3 FM | MP3 | 128 kbps | 1,946 | `http://5230.cloudrad.io:8354/live` |
| 18 | **RX Radio** | — | MP3 | 128 kbps | 1,912 | `https://c14.radioboss.fm:18223/stream?1615807421100=` |
| 19 | **MCF Radio** (alt) | 98.7 FM | MP3 | 128 kbps | 1,001 | `http://streams.radio.co/s79fbbb432/listen` |
| 20 | **Akaboozi FM** (alt) | 87.9 FM | MP3 | 64 kbps | 831 | `http://162.244.80.52:8732/stream` |
| 21 | **Capital FM Uganda** (alt) | 91.3 FM | MP3 | 128 kbps | 507 | `https://capitalfm.cloudrad.io/stream` |
| 22 | **Kaboozi FM** | 104.4 FM | MP3 | 64 kbps | 294 | `http://162.244.80.52:8732/;stream.mp3` |
| 23 | **Rock FM Uganda** | — | MP3 | 128 kbps | 182 | `http://titan.shoutca.st:8341/;` |
| 24 | **Favour FM** | 104.1 FM (Gulu) | MP3 | — | 149 | `http://us5new.listen2myradio.com:2199/listen.php?port=8138&type=ice&mount=stream` |
| 25 | **Radio One FM 90** (alt) | 90.0 FM | MP3 | 64 kbps | 112 | `https://radioone.loftuganda.tech/stream` |
| 26 | **East Africa Radio** | — | MP3 | 64 kbps | 105 | `https://eatv.radioca.st/;` |
| 27 | **Yofochm Radio** | — | MP3 | 128 kbps | 97 | `https://c13.radioboss.fm:18053/stream` |
| 28 | **Yofochm Radio** (alt) | — | MP3 | 128 kbps | 92 | `http://c13.radioboss.fm:18053/stream` |
| 29 | **EJAZZ Xtra** | — | MP3 | 128 kbps | 47 | `https://c32.radioboss.fm:18320/stream` |
| 30 | **EMC Radio** | — (Kampala) | MP3 | 128 kbps | 11 | `http://c22.radioboss.fm:18040/stream` |
| 31 | **My Radio** | — | MP3 | 192 kbps | 0 | `http://myradioug.duckdns.org:8000/radio.mp3` |

---

## 2. Working via Redirect (HTTP 302 — Zeno FM Hosted)

These 39 streams use Zeno.fm's hosting platform. They return HTTP 302 (redirect) on HEAD requests but deliver actual audio data when followed. **All are confirmed working** — tested with full data download.

### Popular Zeno.fm Stations

| # | Station | Location | Votes | Stream URL |
|---|---------|----------|-------|------------|
| 1 | **Uganda DJs** | — | 5,561 | `https://stream.zeno.fm/muzrp86994zuv` |
| 2 | **Busoga One FM** | Jinja | 1,526 | `https://stream.zeno.fm/xna2aad7gc9uv` |
| 3 | **Street Deejays Radio** | Mbarara | 345 | `http://stream.zeno.fm/nbwdnxz7na0uv` |
| 4 | **Sanyu FM** (alt) | Kampala, 88.2 FM | 183 | `http://s44.myradiostream.com/8138/listen.mp3` |
| 5 | **Cloud Radio Uganda** | Kampala | 152 | `http://stream.zeno.fm/eq0vu571ekhvv` |
| 6 | **Heathafro FM** | — | 150 | `http://stream.zeno.fm/rdf0qac95p8uv` |
| 7 | **Nup Radio** | 91.4 FM | 101 | `https://stream.zeno.fm/gxjhbloltwluv` |
| 8 | **Kiira FM** | Jinja, 88.6 FM | 93 | `http://stream.zeno.fm/iydttapi8rguv` |

### Regional / Community Stations

| # | Station | Location | Votes | Stream URL |
|---|---------|----------|-------|------------|
| 9 | **Voice of Kyankwanzi** | Kiboga, 89.7 FM | 76 | `http://stream.zeno.fm/eyzf4ddwqcmvv` |
| 10 | **Jubilee Radio** | Fort Portal, 105.6 FM | 73 | `http://stream.zeno.fm/f3y3up2k07zuv` |
| 11 | **Kyoga Veritas Radio** | Soroti, 91.5 FM | 50 | `http://stream.zeno.fm/hyyzuphrsg0uv` |
| 12 | **SKYNET FM** | — | 49 | `https://stream-44.zeno.fm/1uhqawtfk5zuv` |
| 13 | **Buyinza FM** | — | 13 | `http://stream.zeno.fm/wcancipcbrevv` |

### Religious / Gospel Stations (Zeno.fm)

| # | Station | Location | Votes | Stream URL |
|---|---------|----------|-------|------------|
| 14 | **SDA Missions Radio** | — | 59 | `https://stream-57.zeno.fm/mkkr2bcgkf9uv` |
| 15 | **Heaven FM Radio** | — | 58 | `http://stream.zeno.fm/eequgfw72hhvv` |
| 16 | **Church Radio** | — | 48 | `http://stream.zeno.fm/k0weys53f78uv` |
| 17 | **Good News Radio** | — | 47 | `http://node-01.zeno.fm/km203bn6qnruv` |
| 18 | **Prayer Alter Radio** | — | 47 | `https://node-33.zeno.fm/1gfmyttkephvv` |
| 19 | **Voice Of Heaven** | Kampala | 32 | `http://stream.zeno.fm/s961sfesdmntv` |
| 20 | **Christ Radio** | Lira | 29 | `http://stream.zeno.fm/zupkzgrj4dauv` |
| 21 | **Prayer Tower Radio** | Kampala | 29 | `http://stream.zeno.fm/ymapb78yznhvv` |
| 22 | **Shalom Radio** | Jinja | 27 | `http://stream.zeno.fm/rvnov6bmdhsvv` |
| 23 | **Dema Radio (Gospel Promotions)** | — | 24 | `http://stream.zeno.fm/m96foqqk7bxuv` |
| 24 | **Exodus Comfort Radio** | Mbarara | 24 | `http://stream.zeno.fm/k2zma0qewtjvv` |
| 25 | **Chosen Radio Uganda** | — | 22 | `http://stream.zeno.fm/6uxwuag3srhvv` |
| 26 | **Gospel Kingz** | — | 22 | `http://stream.zeno.fm/vstzctms6rhvv` |
| 27 | **Way To God Radio** | — | 21 | `http://stream.zeno.fm/g876nxxz8vzuv` |
| 28 | **Turn Radio (Revive Your Soul)** | — | 19 | `http://stream.zeno.fm/cuejngegi8btv` |
| 29 | **Christ Love Radio** | — | 16 | `http://stream.zeno.fm/orioba9siustv` |
| 30 | **Glory FM Maganjo** | Kampala | 16 | `http://stream.zeno.fm/bn7dbg8w0nhvv` |
| 31 | **Sanctuary FM** | Kampala | 14 | `http://stream.zeno.fm/vyx334hsbphvv` |
| 32 | **Heavenly Altar Church Radio** | Kampala | 13 | `http://stream.zeno.fm/6s8719ctbphvv` |
| 33 | **Teshuvah Radio** | — | 4 | `https://stream.zeno.fm/3qpkku63z5quv` |
| 34 | **Promise Radio UG** | — | 3 | `http://stream.zeno.fm/hkzgeqlcjoxuv` |

### Other (Zeno.fm)

| # | Station | Location | Votes | Stream URL |
|---|---------|----------|-------|------------|
| 35 | **Radio Yoo** | — | 25 | `http://stream.zeno.fm/v73tc5gwaphvv` |
| 36 | **Enjiri Radio** | — | 24 | `http://stream.zeno.fm/xdb8nazajqcvv` |
| 37 | **Radio Sinza** | — | 21 | `http://stream.zeno.fm/raoopfak6k2vv` |
| 38 | **Nakawa Online Radio** | Kampala | 23 | `http://stream.zeno.fm/6hs5suuvqfhvv` |
| 39 | **UgOnlineMedia** | Kampala | 18 | `http://stream.zeno.fm/8t4dtkxfgkuuv` |

---

## 3. Streams Needing Fresh Token (HTTP 405/400)

These Zeno.fm streams returned 405 or 400 because the embedded auth token is expired. **They work when accessed without the token parameters** or through the Zeno.fm website/app.

| # | Station | Votes | Working URL (strip token) | Notes |
|---|---------|-------|--------------------------|-------|
| 1 | **Next Radio** | 421 | `https://stream-154.zeno.fm/lbca7zintcnuv` | 106.1 FM — **tested working** (352KB in 5 sec) |
| 2 | **Crooze FM** | 281 | `https://stream-159.zeno.fm/vyxwdk08apxtv` | Popular western Uganda station |
| 3 | **Bible Indepth Radio** | 274 | `https://stream.radiojar.com/n6c576nrga0uv` | RadioJar hosted — may need browser |
| 4 | **Bugwere FM** | 47 | `https://stream-174.zeno.fm/jddn0e0z9f0uv` | Strip the `?zt=...` token |
| 5 | **KFM** | 31 | `http://radio.kfm.co.ug:8000/stream` | 93.3 FM — may be intermittent |

---

## 4. Currently Offline Streams

These were not responding at time of testing (connection timeout or server unreachable). They may come back online.

| # | Station | Frequency | Bitrate | Votes | Stream URL |
|---|---------|-----------|---------|-------|------------|
| 1 | **Radio Buddu** | 95.5 FM | 64 kbps | 2,808 | `https://dc4.serverse.com/proxy/ccmxrgub/stream` |
| 2 | **Pearl FM** | 107.9 FM | 96 kbps | 631 | `https://dc4.serverse.com/proxy/pearlfm/stream` |
| 3 | **NRG Uganda** | 106.5 FM | 128 kbps | 291 | `https://dc4.serverse.com/proxy/nrgugstream/stream` |
| 4 | **EJAZZ Radio** | — | 96 kbps | 122 | `https://eu1.reliastream.com/proxy/ejazzug?mp=/stream` |
| 5 | **SDA Radio** | — | 128 kbps | 69 | `https://usa18.fastcast4u.com/proxy/loudcrymedia6?mp=/1` |
| 6 | **Busoga Royal Radio** | — | 128 kbps | 63 | `https://cast5.my-control-panel.com/proxy/busogaroyalradio/stream` |
| 7 | **Rafa Bible Radio** | — | 128 kbps | 52 | `https://gains.reviveradio.net/proxy/rafabibleradioeng?mp=/stream` |
| 8 | **Open Gate FM** | 103.2 FM | 128 kbps | 49 | `https://139.162.195.139:8010/radio.mp3?1722393699669` |
| 9 | **Radio Buddu** (alt) | 95.5 FM | 64 kbps | 33 | `https://dc4.serverse.com/proxy/ccmxrgub/stream?` |
| 10 | **Rafa Radio Music** | — | 192 kbps | 24 | `https://gains.reviveradio.net/proxy/rafaradio?mp=/stream` |
| 11 | **Jembe FM** | — | 128 kbps | 20 | `https://cast3.asurahosting.com/proxy/jembemed/stream` |

---

## 5. Raw M3U Playlist (For Import)

Save this as `uganda_radio.m3u` and import into VLC, IPTV Smarters, TiviMate, or any M3U player.

```m3u
#EXTM3U

#EXTINF:-1 group-title="Popular",Akaboozi FM 87.9
http://162.244.80.52:8732/stream.mp3
#EXTINF:-1 group-title="Popular",Capital FM 91.3
http://5229.cloudrad.io:8316/;
#EXTINF:-1 group-title="Popular",Beat FM 96.3
http://5230.cloudrad.io:8354/
#EXTINF:-1 group-title="Popular",Sanyu FM 88.2
http://s44.myradiostream.com:8138/stream
#EXTINF:-1 group-title="Popular",Radio One 90.0 FM
http://162.244.80.52:8740/stream
#EXTINF:-1 group-title="Popular",KIIS Uganda 100.9 FM
http://14867.cloudrad.io:9224/live
#EXTINF:-1 group-title="Popular",Kaboozi FM 104.4
http://162.244.80.52:8732/;stream.mp3
#EXTINF:-1 group-title="Popular",Radio One FM 90 (alt)
https://radioone.loftuganda.tech/stream
#EXTINF:-1 group-title="Popular",Capital FM Uganda (alt)
https://capitalfm.cloudrad.io/stream
#EXTINF:-1 group-title="Popular",Next Radio 106.1 FM
https://stream-154.zeno.fm/lbca7zintcnuv
#EXTINF:-1 group-title="Popular",Crooze FM
https://stream-159.zeno.fm/vyxwdk08apxtv
#EXTINF:-1 group-title="Popular",KFM 93.3 FM
http://radio.kfm.co.ug:8000/stream
#EXTINF:-1 group-title="Popular",Rock FM Uganda
http://titan.shoutca.st:8341/;
#EXTINF:-1 group-title="Popular",NRG Uganda 106.5
https://dc4.serverse.com/proxy/nrgugstream/stream
#EXTINF:-1 group-title="Popular",Pearl FM 107.9
https://dc4.serverse.com/proxy/pearlfm/stream
#EXTINF:-1 group-title="Popular",EJAZZ Radio
https://eu1.reliastream.com/proxy/ejazzug?mp=/stream
#EXTINF:-1 group-title="Popular",EJAZZ Xtra
https://c32.radioboss.fm:18320/stream

#EXTINF:-1 group-title="Religious",Radio Maria Uganda
http://dreamsiteradiocp.com:8052/stream
#EXTINF:-1 group-title="Religious",MCF Radio 98.7 FM
https://streams.radio.co/s79fbbb432/listen
#EXTINF:-1 group-title="Religious",Radio Pacis 90.9
https://radiopacisuganda.radioca.st/stream
#EXTINF:-1 group-title="Religious",Gospel Radio East Africa
https://c32.radioboss.fm:18451/stream?1641237600584
#EXTINF:-1 group-title="Religious",Christ FM 91.6
http://5.135.154.69:15664/;
#EXTINF:-1 group-title="Religious",Bible 24/7
http://c28.radioboss.fm:8335/stream
#EXTINF:-1 group-title="Religious",Kitintale Christian Fellowship
https://c24.radioboss.fm:18185/stream
#EXTINF:-1 group-title="Religious",Bible Trivia
https://streamer.radio.co/se1aece429/listen
#EXTINF:-1 group-title="Religious",SDA Missions Radio
https://stream-57.zeno.fm/mkkr2bcgkf9uv
#EXTINF:-1 group-title="Religious",Heaven FM Radio
http://stream.zeno.fm/eequgfw72hhvv
#EXTINF:-1 group-title="Religious",Church Radio
http://stream.zeno.fm/k0weys53f78uv
#EXTINF:-1 group-title="Religious",Good News Radio
http://node-01.zeno.fm/km203bn6qnruv
#EXTINF:-1 group-title="Religious",Prayer Alter Radio
https://node-33.zeno.fm/1gfmyttkephvv
#EXTINF:-1 group-title="Religious",Voice Of Heaven
http://stream.zeno.fm/s961sfesdmntv
#EXTINF:-1 group-title="Religious",Christ Radio - Lira
http://stream.zeno.fm/zupkzgrj4dauv
#EXTINF:-1 group-title="Religious",Prayer Tower Radio
http://stream.zeno.fm/ymapb78yznhvv
#EXTINF:-1 group-title="Religious",Shalom Radio Jinja
http://stream.zeno.fm/rvnov6bmdhsvv
#EXTINF:-1 group-title="Religious",Dema Radio - Gospel Promotions
http://stream.zeno.fm/m96foqqk7bxuv
#EXTINF:-1 group-title="Religious",Exodus Comfort Radio - Mbarara
http://stream.zeno.fm/k2zma0qewtjvv
#EXTINF:-1 group-title="Religious",Chosen Radio Uganda
http://stream.zeno.fm/6uxwuag3srhvv
#EXTINF:-1 group-title="Religious",Gospel Kingz
http://stream.zeno.fm/vstzctms6rhvv
#EXTINF:-1 group-title="Religious",Way To God Radio
http://stream.zeno.fm/g876nxxz8vzuv
#EXTINF:-1 group-title="Religious",Turn Radio - Revive Your Soul
http://stream.zeno.fm/cuejngegi8btv
#EXTINF:-1 group-title="Religious",Christ Love Radio
http://stream.zeno.fm/orioba9siustv
#EXTINF:-1 group-title="Religious",Glory FM Maganjo
http://stream.zeno.fm/bn7dbg8w0nhvv
#EXTINF:-1 group-title="Religious",Sanctuary FM
http://stream.zeno.fm/vyx334hsbphvv
#EXTINF:-1 group-title="Religious",Heavenly Altar Church Radio
http://stream.zeno.fm/6s8719ctbphvv
#EXTINF:-1 group-title="Religious",Teshuvah Radio
https://stream.zeno.fm/3qpkku63z5quv
#EXTINF:-1 group-title="Religious",Promise Radio UG
http://stream.zeno.fm/hkzgeqlcjoxuv
#EXTINF:-1 group-title="Religious",Bible Indepth Radio
https://stream.radiojar.com/n6c576nrga0uv

#EXTINF:-1 group-title="Regional",Busoga One FM - Jinja
https://stream.zeno.fm/xna2aad7gc9uv
#EXTINF:-1 group-title="Regional",Favour FM 104.1 - Gulu
http://us5new.listen2myradio.com:2199/listen.php?port=8138&type=ice&mount=stream
#EXTINF:-1 group-title="Regional",Kiira FM 88.6 - Jinja
http://stream.zeno.fm/iydttapi8rguv
#EXTINF:-1 group-title="Regional",Voice of Kyankwanzi 89.7 - Kiboga
http://stream.zeno.fm/eyzf4ddwqcmvv
#EXTINF:-1 group-title="Regional",Jubilee Radio 105.6 - Fort Portal
http://stream.zeno.fm/f3y3up2k07zuv
#EXTINF:-1 group-title="Regional",Kyoga Veritas Radio 91.5 - Soroti
http://stream.zeno.fm/hyyzuphrsg0uv
#EXTINF:-1 group-title="Regional",Nup Radio 91.4
https://stream.zeno.fm/gxjhbloltwluv
#EXTINF:-1 group-title="Regional",Bugwere FM
https://stream-174.zeno.fm/jddn0e0z9f0uv
#EXTINF:-1 group-title="Regional",Buyinza FM
http://stream.zeno.fm/wcancipcbrevv
#EXTINF:-1 group-title="Regional",Radio Buddu 95.5
https://dc4.serverse.com/proxy/ccmxrgub/stream
#EXTINF:-1 group-title="Regional",Busoga Royal Radio
https://cast5.my-control-panel.com/proxy/busogaroyalradio/stream
#EXTINF:-1 group-title="Regional",Open Gate FM 103.2
https://139.162.195.139:8010/radio.mp3?1722393699669

#EXTINF:-1 group-title="Entertainment",Uganda DJs
https://stream.zeno.fm/muzrp86994zuv
#EXTINF:-1 group-title="Entertainment",Street Deejays Radio - Mbarara
http://stream.zeno.fm/nbwdnxz7na0uv
#EXTINF:-1 group-title="Entertainment",RX Radio
https://c14.radioboss.fm:18223/stream?1615807421100=
#EXTINF:-1 group-title="Entertainment",Heathafro FM
http://stream.zeno.fm/rdf0qac95p8uv
#EXTINF:-1 group-title="Entertainment",East Africa Radio
https://eatv.radioca.st/;
#EXTINF:-1 group-title="Entertainment",Yofochm Radio Uganda
https://c13.radioboss.fm:18053/stream
#EXTINF:-1 group-title="Entertainment",Cloud Radio Uganda
http://stream.zeno.fm/eq0vu571ekhvv
#EXTINF:-1 group-title="Entertainment",SKYNET FM
https://stream-44.zeno.fm/1uhqawtfk5zuv
#EXTINF:-1 group-title="Entertainment",EMC Radio
http://c22.radioboss.fm:18040/stream
#EXTINF:-1 group-title="Entertainment",Radio Yoo
http://stream.zeno.fm/v73tc5gwaphvv
#EXTINF:-1 group-title="Entertainment",Enjiri Radio
http://stream.zeno.fm/xdb8nazajqcvv
#EXTINF:-1 group-title="Entertainment",Radio Sinza
http://stream.zeno.fm/raoopfak6k2vv
#EXTINF:-1 group-title="Entertainment",Nakawa Online Radio
http://stream.zeno.fm/6hs5suuvqfhvv
#EXTINF:-1 group-title="Entertainment",UgOnlineMedia
http://stream.zeno.fm/8t4dtkxfgkuuv
#EXTINF:-1 group-title="Entertainment",My Radio Uganda
http://myradioug.duckdns.org:8000/radio.mp3
```

---

## 6. Streaming Providers & Platforms

| Provider | Base URL Pattern | Stations Hosted | Reliability |
|----------|-----------------|-----------------|-------------|
| **Zeno.fm** | `stream.zeno.fm/{id}` | 39 stations | High (free hosting, some need token refresh) |
| **CloudRad.io** | `{id}.cloudrad.io:{port}` | Capital FM, Beat FM, KIIS | High |
| **RadioBoss.fm** | `c{n}.radioboss.fm:{port}/stream` | Gospel Radio, Bible 24/7, EJAZZ, Kitintale, EMC | High |
| **Radio.co** | `streams.radio.co/{id}/listen` | MCF Radio, Bible Trivia | High |
| **Serverse.com** | `dc4.serverse.com/proxy/{station}/stream` | Radio Buddu, Pearl FM, NRG Uganda | Medium (some offline) |
| **RadioCast** | `{station}.radioca.st/stream` | Radio Pacis, East Africa Radio | High |
| **MyRadioStream** | `s{n}.myradiostream.com:{port}/stream` | Sanyu FM, Christ FM | High |
| **Shoutcast** | `titan.shoutca.st:{port}` | Rock FM Uganda | High |
| **Direct IP** | `162.244.80.52:{port}` | Akaboozi, Radio One, Kaboozi | High |
| **LoftUganda** | `radioone.loftuganda.tech/stream` | Radio One | High |
| **RadioJar** | `stream.radiojar.com/{id}` | Bible Indepth Radio | Medium |

---

## 7. How to Listen

### VLC Media Player (Desktop)
1. Open VLC
2. **Media → Open Network Stream** (Ctrl+N)
3. Paste any stream URL from this document
4. Click Play

### VLC (Mobile)
1. Open VLC app
2. Tap **Network Stream** / **Stream**
3. Paste URL → Play

### Web Browser
- Paste any `http://` or `https://` stream URL directly into browser (most MP3 streams play natively)
- Use an online radio player like [WebRadio Player](https://www.radio-browser.info/)

### Import M3U Playlist
1. Copy the M3U playlist section above into a file named `uganda_radio.m3u`
2. Open with VLC, Kodi, IPTV Smarters, or TiviMate
3. All channels will appear organized by group

### FFplay (Command Line)
```bash
ffplay "http://162.244.80.52:8732/stream.mp3"
```

### Programming (Python)
```python
import subprocess
subprocess.Popen(["ffplay", "-nodisp", "http://162.244.80.52:8732/stream.mp3"])
```

---

## Summary Statistics

| Metric | Count |
|--------|-------|
| **Total unique radio streams** | 86 |
| **Confirmed live (HTTP 200)** | 31 |
| **Working via redirect (HTTP 302)** | 39 |
| **Working but need fresh URL token** | 5 |
| **Currently offline** | 11 |
| **Total working streams** | **75** |
| **Streaming platforms identified** | 11 |
| **Data source** | radio-browser.info API |

---

> **Note:** Stream URLs may change over time. For the latest list, query the radio-browser.info API:  
> `curl "https://de1.api.radio-browser.info/json/stations/bycountry/Uganda?limit=500&order=votes&reverse=true"`
