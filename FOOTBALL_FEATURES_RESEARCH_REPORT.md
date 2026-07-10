# Football Features Research Report — Katogo App

**Date:** July 10, 2026
**Prepared for:** Muhindo (Katogo — Laravel backend + mobile app)
**Focus:** Premier League & major football leagues, free APIs, video/streaming options, feature ideas

---

## 1. Executive Summary

Katogo already has the core infrastructure a football section needs: a Laravel API backend, subscriptions/free-trial system, Firebase push notifications (OneSignal too), a media player, watch history, likes, and chat. Football features slot into this stack naturally.

This report covers:

1. **20+ football features** you can add, ranked by effort vs. impact
2. **Free data APIs** (live scores, fixtures, standings, stats) with real limits
3. **Video & streaming options** — what is legally programmable and what isn't
4. **Recommended architecture** for Laravel (caching strategy is critical because free tiers have tight rate limits)
5. **A phased roadmap**

**Bottom line recommendation:** Start with a **Match Center** (live scores + fixtures + standings) using **football-data.org** (free forever for 12 top competitions incl. Premier League) cached in your own DB, plus **Scorebat's free video API** for goal highlights, plus a **predictions mini-game** built on your existing user system. That combination costs $0 in data fees and creates daily-return usage.

---

## 2. Feature Catalog — What You Can Add

### Tier 1 — Core (build first, high retention value)

| Feature | Description | Data Source | Effort |
|---|---|---|---|
| **Live scores** | Real-time/near-real-time scores for PL, UCL, La Liga, etc. | football-data.org, API-Football, ESPN unofficial | Low-Med |
| **Fixtures & results** | Upcoming matches, matchday calendar, past results | Same as above | Low |
| **League standings** | Live tables for PL and other leagues | football-data.org (free) | Low |
| **Goal highlights (video)** | Embeddable goal clips & match highlights, updated in near real-time | **Scorebat free video API** | Low |
| **Match notifications** | Push alerts: kickoff, goals, HT/FT for followed teams | Your Firebase/OneSignal + polling scores | Med |
| **Follow your team** | Users pick favorite clubs → personalized fixtures, news, alerts | Your DB + any fixtures API | Low |

### Tier 2 — Engagement (daily-return mechanics)

| Feature | Description | Data Source | Effort |
|---|---|---|---|
| **Match predictions game** | Users predict scores, earn points, weekly/season leaderboards | Your DB + fixtures API for results settlement | Med |
| **Head-to-head & form** | Last 5 matches, H2H history before each game | API-Football / football-data.org | Low |
| **Live match events feed** | Goals, cards, subs as a timeline | API-Football (free tier), paid tiers for more | Med |
| **Football news feed** | Aggregated PL news from BBC Sport, Sky Sports RSS (free) | RSS feeds (BBC, Sky, Guardian) parsed server-side | Low |
| **Polls & fan votes** | Man of the Match votes, "who wins?" polls | Your DB entirely | Low |
| **Match chat / watch-along rooms** | Live chat room per match — you already have a chat backend | Your existing chat system | Med |
| **Trivia / quizzes** | Football quizzes with rewards (e.g., free trial days) | Self-authored or open trivia data | Low |

### Tier 3 — Advanced

| Feature | Description | Data Source | Effort |
|---|---|---|---|
| **Fantasy league companion** | FPL player prices, points, top picks — official FPL API is free & unauthenticated | `fantasy.premierleague.com/api/` | Med |
| **Player profiles & stats** | Top scorers, assists, player pages | football-data.org (scorers free), API-Football | Med |
| **Lineups & formations** | Confirmed XIs ~1hr before kickoff | API-Football (free tier includes lineups) | Med |
| **Odds & win probability** | Display odds / prediction percentages | Football-Data.co.uk (free CSVs), The Odds API (free tier) | Med |
| **Highlights VOD section** | A "Football" category in your existing movie UI listing full match replays & highlight compilations you have rights to, or Scorebat/YouTube embeds | Scorebat / YouTube | Med |
| **Stats-based AI summaries** | Auto-generated match previews/recaps from data | Any data API + LLM | Med |
| **Live audio commentary rooms** | User-generated "VJ-style" commentary over matches (very Katogo-native idea — VJ culture applied to football) | Your infra (audio streaming) | High |
| **Mini-games** | Penalty shootout game, score predictor streaks | Client-side | Med |

The **VJ commentary idea** deserves emphasis: Katogo's identity is VJ-translated content. A Luganda live commentary/watch-along audio room for big PL matches is a differentiator no international app offers.

---

## 3. Free Football Data APIs — Detailed Comparison

### 3.1 The main options

| API | Free tier | Premier League? | Rate limits | Commercial use | Best for |
|---|---|---|---|---|---|
| **[football-data.org](https://www.football-data.org/)** | Free forever, 12 top competitions (PL, UCL, La Liga, Serie A, Bundesliga, Ligue 1, Championship, WC, Euro…) | ✅ Yes | **10 req/min** | Yes (attribution) | Fixtures, standings, scores (slightly delayed on free), top scorers |
| **[API-Football](https://www.api-football.com/)** (api-sports.io) | 100 req/**day**, all endpoints, 1,200+ leagues | ✅ Yes | 100/day hard cap | Yes, all tiers | Lineups, events, stats, odds, H2H — richest free data, but tiny quota |
| **[TheSportsDB](https://www.thesportsdb.com/free_sports_api)** | Free, crowd-sourced, 600+ soccer leagues | ✅ Yes | 30 req/min | Hobby-grade | Team badges, logos, artwork, stadium images (great for UI assets) |
| **[Sportmonks](https://www.sportmonks.com/football-api/)** | Free plan: Danish Superliga + Scottish Premiership only | ❌ (paid for PL) | 180 req/min free | Yes | Testing their format; PL needs paid plan (~€39+/mo) |
| **[Official FPL API](https://fantasy.premierleague.com/api/bootstrap-static/)** | 100% free, no key needed | ✅ PL only | Unofficial, be gentle; has CORS (call server-side only) | Grey area (unofficial) | Player stats, prices, fixtures, gameweek data, fantasy features |
| **ESPN unofficial API** (`site.api.espn.com/apis/site/v2/sports/soccer/eng.1/scoreboard`) | Free, no key | ✅ Yes | Undocumented | Unofficial — can break anytime | Live scores backup source |
| **[Football-Data.co.uk](https://www.football-data.co.uk/)** | Free CSV downloads | ✅ Yes | n/a | Yes | Historical results + betting odds for your prediction models |
| **[StatsBomb Open Data](https://github.com/statsbomb/open-data)** | Free GitHub repo | Selected comps | n/a | Research | Deep event data (xG, passes) — analytics content, not live |
| **[openfootball](https://github.com/openfootball)** | Free, open public-domain datasets | ✅ Yes | n/a | Yes | Seed data: teams, seasons, fixtures in plain text/JSON |

### 3.2 Recommended combination (all free)

1. **football-data.org** as your primary: fixtures, standings, results, scorers for PL + 11 other competitions. 10 req/min is plenty **if you cache** (see §5).
2. **API-Football free** as enrichment: spend the 100 daily calls on lineups and match events for the day's big matches only.
3. **TheSportsDB** for one-time asset scraping: club logos, player photos, stadium images → store in your own CDN/storage.
4. **FPL API** for the fantasy/player-stats section (server-side calls only).
5. **ESPN unofficial** as a free fallback for live scores if primary is rate-limited.

### 3.3 Key endpoints cheat sheet

```
# football-data.org (free key: register at football-data.org)
GET https://api.football-data.org/v4/competitions/PL/matches?status=SCHEDULED
GET https://api.football-data.org/v4/competitions/PL/standings
GET https://api.football-data.org/v4/competitions/PL/scorers
GET https://api.football-data.org/v4/matches?date=2026-07-10
Header: X-Auth-Token: YOUR_KEY

# API-Football (free key: dashboard.api-football.com)
GET https://v3.football.api-sports.io/fixtures?league=39&season=2026
GET https://v3.football.api-sports.io/fixtures/lineups?fixture={id}
GET https://v3.football.api-sports.io/fixtures/events?fixture={id}
Header: x-apisports-key: YOUR_KEY        # league 39 = Premier League

# FPL (no key)
GET https://fantasy.premierleague.com/api/bootstrap-static/
GET https://fantasy.premierleague.com/api/fixtures/
GET https://fantasy.premierleague.com/api/element-summary/{player_id}/

# ESPN (no key, unofficial)
GET https://site.api.espn.com/apis/site/v2/sports/soccer/eng.1/scoreboard
GET https://site.api.espn.com/apis/site/v2/sports/soccer/eng.1/standings

# Scorebat video API (free feed)
GET https://www.scorebat.com/video-api/v3/feed/?token=YOUR_TOKEN
```

---

## 4. Video & Streaming — The Honest Picture

This is the part to get right, because it carries legal and business risk.

### 4.1 What is freely and legally programmable

**Scorebat Video API** — the best free option. A JSON feed of goal clips and match highlights (PL, UCL, La Liga, Serie A, Bundesliga + more) with ready-made **embed codes** for their player. Updated in near real-time as goals happen. Free tier available; premium tiers add filtering by competition/team and faster updates. This gives your app a "Goals & Highlights" tab with zero rights negotiation — the embeds are licensed on Scorebat's side.

**YouTube embeds** — official channels (Premier League, NBC Sports, SuperSport, club channels) publish highlights. You can embed via the YouTube iFrame/player API in-app. Free, legal, but region-dependent availability and you can't control ads.

**Your own licensed VOD** — you already run a video pipeline (HLS, Hetzner storage, media player). Any football content you actually license (local league matches, analysis shows, fan shows, VJ commentary recordings) can go through your existing movie infrastructure as a "Sports" category.

### 4.2 Live match streaming — the reality check

There is **no legal free API that gives you live Premier League match streams.** PL broadcast rights are sold per territory (in Uganda/Sub-Saharan Africa the rights holder is SuperSport/DStv, plus StarTimes for some competitions). Any "football live streaming API" you find on RapidAPI or GitHub offering direct HLS links to PL matches is redistributing pirated streams. Integrating those into Katogo would risk: app store removal, Google Ads account bans (you've already dealt with Google Ads compliance), payment processor termination, and legal action from rights holders. I'd strongly advise against it — it could take down the whole platform, not just the football feature.

**Legitimate routes to live content:**

1. **Sub-license local/lower-tier rights.** Uganda Premier League, FUFA Big League, regional tournaments — local rights are dramatically cheaper and often unexploited digitally. Katogo could become *the* streaming home of Ugandan football. This is a genuine market gap.
2. **Live audio commentary** (radio-style) of big matches is a different rights situation than video and is how many African services cover PL matchdays. Combine with your live-score data for a "listen + live match tracker" experience.
3. **Aggregator/affiliate model:** show "Where to watch" (DStv, SuperSport app, StarTimes) per fixture with deep links — possibly with affiliate revenue.
4. **Watch-along rooms:** users watch on their own licensed service; Katogo provides synchronized chat, polls, and VJ audio commentary. No video redistribution at all.

### 4.3 Programmable streaming URLs (for content you DO have rights to)

Since you asked about programmable stream URLs — for licensed/own content, use the same pattern as your movies:

```
1. Ingest:  OBS/encoder → RTMP → your media server (e.g., Nginx-RTMP,
            MediaMTX, or a service like Mux/Cloudflare Stream/Dacast)
2. Package: transcode to HLS (.m3u8 + .ts/.m4s segments), multi-bitrate
3. Protect: signed/tokenized URLs generated by Laravel per user session:
            /live/upl-match-123/index.m3u8?token={JWT, expires 5 min}
            → validate token in middleware or at the edge (CDN)
4. Deliver: CDN in front (Cloudflare) for bandwidth
5. Gate:    tie access to your existing subscription/free-trial system
```

Managed options if you don't want to run servers: **Cloudflare Stream Live** (~$1/1,000 min delivered), **Mux**, **Dacast** (white-label, has APIs). All give you programmable playback URLs + signed-URL security compatible with your existing player.

---

## 5. Laravel Integration Architecture

The golden rule with free tiers: **your users never hit the external API — they hit your DB.**

```
[football-data.org / API-Football / FPL / Scorebat]
        │  (scheduled fetch, respects rate limits)
        ▼
Laravel Scheduler (cron) → Jobs → MySQL tables + Redis cache
        ▼
Your existing REST API → mobile app / web
        ▼
Firebase/OneSignal push on change detection (goal! FT!)
```

**Suggested sync cadence** (fits inside 10 req/min + 100 req/day budgets):

| Data | Frequency | Source |
|---|---|---|
| Standings | 1×/hour on matchdays, 1×/day otherwise | football-data.org |
| Fixtures (next 7 days) | 2×/day | football-data.org |
| Live scores | Every 60–120s **only during live matches** | football-data.org / ESPN fallback |
| Lineups (today's top matches) | Once, ~60 min pre-kickoff | API-Football |
| Match events (top matches) | Every 2–3 min while live | API-Football (budget: ~3 matches/day) |
| FPL player data | 1×/day + after each gameweek | FPL API |
| Highlights feed | Every 15 min on matchdays | Scorebat |
| News RSS | Every 30 min | BBC/Sky/Guardian RSS |

**Suggested tables:** `fb_leagues`, `fb_teams`, `fb_matches`, `fb_standings`, `fb_match_events`, `fb_players`, `fb_highlights`, `fb_news`, `fb_user_follows`, `fb_predictions`, `fb_prediction_leaderboard`.

**Goal detection for push:** the live-score job diffs the new score against the stored score; on change, dispatch a notification job to users following either team. You already have this notification infrastructure for movies.

**Admin side:** laravel-admin (Encore) CRUD for leagues shown, featured matches, manual highlight curation, and prediction-game settlement overrides — same pattern as your existing Game Admin module.

---

## 6. Monetization Angles

Football features monetize your *existing* model rather than needing a new one. Match Center and scores stay free to drive installs and daily opens; highlights, predictions leaderboards with prizes, and any live/VOD sports content sit behind the subscription you already run. Matchday traffic spikes are prime slots for ads (interstitial before highlight clips) if you run ads, and "Where to watch" affiliate links (DStv/StarTimes) add a passive stream. If you later license Ugandan football, that becomes a subscription-tier differentiator no competitor has.

---

## 7. Phased Roadmap

**Phase 1 (1–2 weeks):** football-data.org integration → fixtures, results, standings, live scores for PL + top leagues; team-follow; DB/cache layer; basic Football tab in app.

**Phase 2 (1–2 weeks):** Scorebat highlights feed; push notifications for goals/FT; news RSS aggregation; club logos from TheSportsDB.

**Phase 3 (2–3 weeks):** Predictions game + leaderboards; match polls & MOTM votes; H2H/form displays; match chat rooms (reuse chat backend).

**Phase 4 (2–4 weeks):** FPL companion section; lineups & events via API-Football; trivia; AI match previews.

**Phase 5 (strategic):** Ugandan football rights + live streaming through your existing HLS pipeline; VJ live audio commentary rooms for big matches.

---

## 8. Risks & Caveats

Free tiers can change — cache everything and keep a fallback source configured. ESPN and FPL endpoints are unofficial and can break without notice; never make them your only source. Scorebat embeds require internet playback of their player (no offline). Do **not** integrate pirated "live stream link" APIs — the app-store, ads-account, and legal risk lands on Katogo itself. If prediction games ever involve paid entry or cash prizes, check Uganda's gaming/lottery regulations first (points + non-cash rewards keeps it clean).

---

## 9. Sources

- [football-data.org](https://www.football-data.org/) · [pricing/tiers](https://www.football-data.org/pricing)
- [API-Football](https://www.api-football.com/)
- [TheSportsDB free API](https://www.thesportsdb.com/free_sports_api) · [docs](https://www.thesportsdb.com/documentation)
- [Sportmonks Football API](https://www.sportmonks.com/football-api/) · [Premier League API](https://www.sportmonks.com/football-api/premier-league-api/)
- [Scorebat Video API](https://www.scorebat.com/video-api/) · [RapidAPI listing](https://rapidapi.com/scorebat/api/free-football-soccer-videos)
- [FPL API endpoints guide (Medium)](https://medium.com/@frenzelts/fantasy-premier-league-api-endpoints-a-detailed-guide-acbd5598eb19) · [FPL APIs Explained](https://www.oliverlooney.com/blogs/FPL-APIs-Explained)
- [ESPN hidden API docs (GitHub)](https://github.com/pseudo-r/Public-ESPN-API) · [soccer endpoints](https://github.com/pseudo-r/Public-ESPN-API/blob/main/docs/sports/soccer.md) · [gist](https://gist.github.com/akeaswaran/b48b02f1c94f873c6655e7129910fc3b)
- [Free football API comparison — TheStatsAPI](https://www.thestatsapi.com/blog/free-football-api-alternatives) · [football-data.org free tier 2026](https://www.thestatsapi.com/blog/football-data-org-free-tier-limits-2026)
- [Best Football Data APIs 2026 — footyapps](https://footyapps.com/guide/free-football-apis)
- [StatsBomb Open Data](https://github.com/statsbomb/open-data) · [openfootball datasets](https://github.com/openfootball/awesome-football)
- [Football-Data.co.uk (odds/history CSVs)](https://www.football-data.co.uk/)
- [Premier League RSS feeds list](https://rss.feedspot.com/premier_league_rss_feeds/)
- [Dacast sports OTT guide](https://www.dacast.com/blog/ott-sports-streaming/)
