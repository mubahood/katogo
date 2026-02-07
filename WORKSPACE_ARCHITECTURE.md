# Katogo Platform — Workspace Architecture Reference

**Last Updated:** 7 February 2026  
**Purpose:** Quick-reference for understanding how all projects in this workspace relate to each other.

---

## Overview

This workspace contains **4 projects** that form a single platform: a **Luganda-translated movie streaming ecosystem** targeting Ugandan users. One Laravel backend serves three Flutter mobile apps, each with a different monetization strategy.

```
┌─────────────────────────────────────────────────────────────────┐
│                      MOBILE APPS (Flutter)                      │
│                                                                 │
│  ┌─────────────┐   ┌──────────────────┐   ┌─────────────────┐  │
│  │  LugaFlix   │   │  UGFlix (Mobo)   │   │      Muno       │  │
│  │  (Premium)  │   │   (Freemium)     │   │  (Free + Ads)   │  │
│  └──────┬──────┘   └────────┬─────────┘   └────────┬────────┘  │
│         │                   │                       │           │
└─────────┼───────────────────┼───────────────────────┼───────────┘
          │                   │                       │
          └───────────────────┼───────────────────────┘
                              │ HTTPS REST (JWT Auth)
                              ▼
          ┌───────────────────────────────────────────┐
          │        Katogo Backend (Laravel 10)        │
          │      katogo.schooldynamics.ug/api         │
          └───────────────────┬───────────────────────┘
                              │
            ┌─────────────────┼─────────────────┐
            ▼                 ▼                 ▼
      ┌──────────┐   ┌──────────────┐   ┌──────────┐
      │  MySQL   │   │   Firebase   │   │ Pesapal  │
      │ Database │   │   Storage    │   │ Payments │
      └──────────┘   └──────────────┘   └──────────┘
```

---

## Project Locations

| Project | Path | Role |
|---------|------|------|
| **Katogo** (Backend API) | `/Applications/MAMP/htdocs/katogo` | Laravel 10 REST API, admin panel, content crawler |
| **LugaFlix** (Mobile) | `/Users/mac/Desktop/github/lugaflix` | Premium Flutter app with SafeMode fallback |
| **UGFlix / Mobo** (Mobile) | `/Users/mac/Desktop/github/luganda-translated-movies-mobo` | Base/vanilla Flutter app, subscription freemium |
| **Muno** (Mobile) | `/Users/mac/Desktop/github/muno-app-free-luganda-movies` | Free Flutter app monetized with Google AdMob |

---

## Katogo Backend API

### Tech Stack
- **Framework:** Laravel 10, PHP 8.1+
- **Database:** MySQL
- **Auth:** JWT via `tymon/jwt-auth` (5-year token TTL)
- **Admin Panel:** Laravel-Admin (`encore/laravel-admin`) at `/admin/*`
- **Hosted on:** MAMP / Apache at `https://katogo.schooldynamics.ug`

### Key Controllers

| Controller | File | Responsibility |
|------------|------|----------------|
| `ApiController` | `app/Http/Controllers/ApiController.php` | Auth (login, register, Google OAuth), manifest endpoint, chat system, product CRUD, file uploads, user profile, dynamic model API |
| `DynamicCrudController` | `app/Http/Controllers/DynamicCrudController.php` | Movie listing/detail/search, account dashboard, watchlist/likes/wishlist, watch history, video progress, generic CRUD |
| `SubscriptionApiController` | `app/Http/Controllers/SubscriptionApiController.php` | Subscription plans, create/manage subscriptions, Pesapal payment callbacks/IPN, payment status checking |
| `GameController` | `app/Http/Controllers/GameController.php` | Matatu card game: online users, invitations, game sessions, card actions |
| `LudoController` | `app/Http/Controllers/LudoController.php` | Ludo board game: invitations, dice rolls, piece movement, turn management |
| `CoinController` | `app/Http/Controllers/CoinController.php` | Game coin balance, transaction history, leaderboard, offline win awards |
| `ModerationController` | `app/Http/Controllers/ModerationController.php` | Content filtering, user reports, blocking, legal consent |
| `ApiVideoTransferController` | `app/Http/Controllers/ApiVideoTransferController.php` | Video transfer pipeline (external URL → Firebase Storage) |

### Key Models

| Model | Table | Purpose |
|-------|-------|---------|
| `User` | `admin_users` | Users (extends Laravel-Admin's Administrator + JWTSubject). Tracks `app_type`, `game_coins_balance`, `is_busy_in_game`, `last_online_at` |
| `MovieModel` | `movie_models` | Movies & series episodes (~12K+ rows, 77 columns). Fields: title, url, thumbnail_url, genre, vj, type (Movie/Series), is_premium, is_muno, firebase_video_url |
| `SeriesMovie` | `series_movies` | Series metadata linking episodes |
| `Subscription` | `subscriptions` | User subscriptions with Pesapal payment tracking, start/end dates, grace period |
| `SubscriptionPlan` | `subscription_plans` | Plan tiers: name (en/lg/sw), price (UGX), duration_days, features, is_trial |
| `ChatHead` | `chat_heads` | Chat conversations (product-context or dating type) |
| `ChatMessage` | `chat_messages` | Individual messages (text + audio support) |
| `GameSession` | `game_sessions` | Matatu card game sessions |
| `LudoSession` | `ludo_sessions` | Ludo board game sessions |
| `CoinTransaction` | `coin_transactions` | Game coin balance changes |
| `GameInvitation` | `game_invitations` | Game invitations between users |
| `MovieView` | `movie_views` | Watch progress tracking (progress_seconds, duration_seconds) |
| `MovieLike` | `movie_likes` | Movie likes |
| `MovieWishlist` | `movie_wishlists` | Movie wishlist/saved |
| `Watchlist` | `watchlists` | User watchlist |
| `MovieSearch` | `movie_searches` | Search query analytics |
| `MovieCrawlerWebsite` | `movie_crawler_websites` | Registered content sources for crawler |
| `MovieCrawlerPage` | `movie_crawler_pages` | Individual crawled movie pages |
| `VideoTransfer` | `video_transfers` | Video file transfer pipeline records |
| `VideoPlaybackFailure` | `video_playback_failures` | Playback error reports from apps |
| `ContentReport` | `content_reports` | User-submitted moderation reports |
| `UserBlock` | `user_blocks` | User block relationships |
| `StockItem` | `stock_items` | E-commerce products/inventory |
| `Company` | `companies` | Multi-tenant company records |

### Manifest Endpoint (`GET /api/manifest`)

This is the **most important endpoint** — called on every app launch. Returns:

```
{
  top_movie          → Featured/trending movie for hero banner
  lists[]            → Categorized movie carousels (Latest, Continue Watching, 
                       Top, Trending, For You, genre-based)
  genres[]           → All unique genres
  vj[]               → All VJ (video jockey/translator) names
  APP_VERSION        → Minimum required app version (currently 22)
  UPDATE_NOTES       → Changelog text
  WHATSAPP_CONTAT_NUMBER → Support contact
  subscription       → Full subscription status (active, days remaining, grace period)
  dashboard_stats    → watchlist_count, watch_history_count, liked_movies_count, etc.
  safemode_auth      → MunoWatch API credentials (for LugaFlix SafeMode)
  platform_type      → Detected platform (ios/android)
}
```

### API Routes Summary

**Public (no auth):**
- `POST /api/auth/login`, `/register`, `/google` — Authentication
- `GET /api/random-movie` — Random movie
- `GET /api/subscription-plans` — List plans
- `GET/POST /api/subscriptions/pesapal/callback` — Payment callback
- `POST /api/subscriptions/pesapal/ipn` — Payment webhook
- `POST /api/video-playback-failures` — Error reporting

**Authenticated (JWT):**
- `GET /api/manifest` — App config + movie lists + subscription status
- `GET /api/movies`, `/api/movie/{id}` — Movie catalog
- `POST /api/video-progress` — Save watch position (throttled)
- `GET /api/watch-history`, `/api/account/watchlist`, `/api/account/likes` — User library
- `POST /api/chat-start`, `/api/chat-send` — Chat system
- `POST /api/subscriptions/create` — Start subscription + Pesapal payment
- `GET /api/game/online-users`, `POST /api/game/invite` — Matatu game
- `POST /api/ludo/invite`, `/api/ludo/session/{id}/roll` — Ludo game
- `GET /api/coins/balance`, `/api/coins/leaderboard` — Coin economy

**Catch-all (must be last in routes):**
- `GET /api/api/{model}`, `POST /api/api/{model}` — Dynamic model CRUD

### External Services

| Service | Purpose | Config |
|---------|---------|--------|
| **Firebase Storage** | Video CDN hosting | Project: `ugflix-71aa8`, credentials in `storage/app/firebase-credentials.json` |
| **Pesapal** | Payment gateway (Mobile Money + cards, UGX) | Production: `pay.pesapal.com/v3` |
| **Google OAuth** | Social login | Verifies via `oauth2.googleapis.com/tokeninfo` |
| **OneSignal** | Push notifications | App ID: `91f0416d-9c75-4ac2-9593-88cf9594a2f5` (shared) |
| **MunoWatch** | Content source + SafeMode backend | API: `munowatch.org/api/` |

### Content Pipeline

Movies are sourced via a **3-level crawler**:
1. `movie_crawler_websites` — Registers source APIs (MunoWatch, UgaWatch)
2. `movie_crawler_pages` — Discovers individual movies (pending → success/error)
3. `movie_models` — Final movie records, optionally transferred to Firebase Storage

---

## Three Flutter Mobile Apps

### Shared Codebase (~95% identical)

All three apps use Dart package name `ugflix` and share:
- **State management:** GetX
- **HTTP:** Dio → `https://katogo.schooldynamics.ug/api`
- **Local DB:** SQLite (sqflite), database name `movies_12`
- **Video player:** Chewie + video_player + mini-player overlay
- **Navigation:** GetX routing (`AppRouter` with named routes)
- **Push:** OneSignal (same app ID)
- **Auth:** JWT stored in SharedPreferences + Google Sign-In
- **Downloads:** flutter_downloader

**Shared screens:** Home, Auth (login/register), Account, Games (Matatu + Ludo), Dating, Subscriptions (plans/status/history/pending), Shop, Video player, Video transfers

**Shared models:** MovieModel, UserModel, ManifestModel, SubscriptionModel, SubscriptionPlanModel, SeriesModel, ChatHead, ChatMessage, Product

**Shared services:** GoogleAuthService, SubscriptionProtectionService, VideoPlaybackFailureService, PipService, UnifiedGameInvitationService, WhatsAppSupportService, ManifestService, SubscriptionService

### App Differences

| Property | LugaFlix | UGFlix (Mobo) | Muno |
|----------|----------|---------------|------|
| **Package ID** | `lugaflix.movies` | `ugflix.com` | `com.munoapp.free` |
| **`app_type` sent to API** | `"lugaflix"` | `"ugflix"` | `"ugflix"` |
| **Display name** | LugaFlix | UGFlix | Muno - Free Luganda Movies |
| **Version** | 6.0.6+606 | 6.0.6+606 | 1.0.0+2 |
| **Monetization** | Subscription-primary | Subscription-primary | Free + Google AdMob |
| **SafeMode (MunoWatch)** | YES — full embedded app | No | No |
| **Google AdMob ads** | No | No | YES — 5 ad types |
| **Extra UI widgets** | Enhanced subscription cards, timer, guard, WhatsApp button | None (base) | Ad manager UI |
| **MyApp widget type** | StatelessWidget | StatelessWidget | StatefulWidget (for ad lifecycle) |
| **Keystore** | `lugaflix-production.jks` | `ugflix.jks` + `omulimisakeystore.jks` | `ugflix.jks` + `omulimisakeystore.jks` |

### LugaFlix — Unique: SafeMode

LugaFlix embeds a **complete secondary app** at `lib/safemode/` that connects to `munowatch.org/api/` (not the Katogo backend). It uses Provider (not GetX) and has its own:
- Auth system (auto-login via manifest credentials)
- Movie browsing, search, genres, TV shows
- Video player
- Dashboard

SafeMode acts as a **fallback streaming experience** if the main Katogo backend is unavailable.

### Muno — Unique: Ad Monetization

Muno has `lib/services/ad_manager_service.dart` (818 lines) and `app_lifecycle_reactor.dart` implementing:

| Ad Type | Placement | Frequency Cap |
|---------|-----------|---------------|
| App Open | Cold start + resume (>30s bg) | 1 per 30 min |
| Interstitial | Every 3rd movie detail view; pre-roll | 1 per 90s, max 6/hr |
| Banner | Dashboard bottom (adaptive) | Always visible |
| Rewarded | HD unlock, preview extension, coins, ad-free 1hr | On demand |
| Mid-roll | Every 5 min for videos >10 min | During playback |

**Production Ad Unit IDs:**
- App Open: `ca-app-pub-9006886952721093/4325909033`
- Banner: `ca-app-pub-9006886952721093/1919237541`
- Interstitial: `ca-app-pub-9006886952721093/4158787457`
- Rewarded: `ca-app-pub-9006886952721093/9606155879`

---

## Subscription & Payment System

### Plans (in `subscription_plans` table)

| Plan | Price (UGX) | Duration | Notes |
|------|-------------|----------|-------|
| Free Trial | 0 | 15 days | Auto-assigned on first login; `is_trial: true` |
| Quick Start | 1,000 | 3 days | Entry-level |
| Two Weeks Special | 5,000 | 14 days | Mid-tier |
| Monthly Premium | 8,000 | 30 days | Featured |

Plans have names in English, Luganda (`lg`), and Swahili (`sw`).

### Payment Flow
1. App calls `POST /api/subscriptions/create` with `plan_id`
2. Backend creates `Subscription` (pending) + calls Pesapal to initialize payment
3. User redirected to Pesapal payment page (Mobile Money or card)
4. On completion: Pesapal sends callback + IPN webhook
5. Backend activates subscription, sets `start_date_time` / `end_date_time`
6. Manifest endpoint returns subscription status on every app load

### Merchant Reference Format
- UGFlix/Mobo/Muno: `SUB-{user_id}-{timestamp}`
- LugaFlix: `LUG-{user_id}-{timestamp}`

---

## Game System

### Matatu (Card Game)
- East African card game using standard 52-card deck
- Multiplayer via HTTP polling (no WebSockets)
- `is_busy_in_game` flag on users prevents double-matching
- Auto-cleanup of stuck games after 15 min

### Ludo (Board Game)
- 2-player and 4-player modes
- Actions: roll dice → move piece → pass turn
- Abandonment detection (30s poll threshold)

### Coins
- In-app currency earned by winning games
- `game_coins_balance` on user record
- `CoinTransaction` tracks all changes
- Global leaderboard

---

## Database Evolution

The system evolved from an **inventory management / e-commerce** app into a **streaming platform**:
- Dec 2023: Companies, stock items, categories (e-commerce origin)
- Jan 2024: Movies, scrapers, watchlists added
- Mar 2024: Series support, movie views/likes
- Oct 2025: Subscriptions, Pesapal payments, MunoWatch crawler, Google OAuth, content moderation, wishlists, search analytics
- Jan 2026: Matatu game, Ludo game, coin economy, audio chat messages

Total: **120+ migrations** across the `database/migrations/` directory.

---

## Key File Paths (Quick Reference)

### Backend (Katogo)
```
routes/api.php                              → All API route definitions
app/Http/Controllers/ApiController.php      → Auth, manifest, chat, products (2023 lines)
app/Http/Controllers/DynamicCrudController.php → Movies, account, CRUD (2530 lines)
app/Http/Controllers/SubscriptionApiController.php → Subscriptions (1448 lines)
app/Http/Controllers/GameController.php     → Matatu game (1215 lines)
app/Http/Controllers/LudoController.php     → Ludo game (475 lines)
app/Http/Controllers/CoinController.php     → Coin economy (152 lines)
app/Http/Controllers/ModerationController.php → Content moderation (715 lines)
app/Http/Middleware/JwtMiddleware.php        → JWT auth middleware
app/Models/User.php                         → User model (extends Administrator)
app/Models/MovieModel.php                   → Movie model (77 columns)
app/Models/Subscription.php                 → Subscription model
app/Services/SubscriptionPesapalService.php → Pesapal integration
app/Services/PaymentStatusChecker.php       → Payment retry/verification
config/cors.php                             → CORS configuration
```

### Mobile Apps (shared structure)
```
lib/main.dart                               → App entry point
lib/core/app.dart                           → MyApp widget + GetMaterialApp
lib/src/routing/routing.dart                → AppRouter with named routes
lib/utils/Utilities.dart                    → Utils class (HTTP, storage, config)
lib/models/ManifestService.dart             → Manifest fetch + caching
lib/models/ManifestModel.dart               → Manifest data model
lib/models/SubscriptionService.dart         → Subscription API calls
lib/models/MovieModel.dart                  → Movie data model + SQLite
lib/services/unified_game_invitation_service.dart → Game invite polling
lib/services/SubscriptionProtectionService.dart   → Content access gating
lib/screens/home/HomeScreen.dart            → Main home screen
lib/screens/subscriptions/                  → Subscription UI screens
lib/screens/games/matatu/                   → Matatu card game
lib/screens/games/ludo/                     → Ludo board game
```

### LugaFlix-only
```
lib/safemode/                               → Complete MunoWatch fallback app
lib/widgets/EnhancedSubscriptionStatusWidget.dart
lib/widgets/SubscriptionGuardWidget.dart
```

### Muno-only
```
lib/services/ad_manager_service.dart        → AdMob integration (818 lines)
lib/services/app_lifecycle_reactor.dart     → App Open ad lifecycle
```
