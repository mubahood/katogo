# Katogo Platform — Mobile Apps Plan

> **Scope:** 5 Flutter apps (LugaFlix as master, 4 children inherit from it)
> **Companion docs:** `SERVER_PLAN.md` for backend tasks, `IMPROVEMENT_PLAN.md` for full task list
> **Audit date:** June 2026

---

## ⚠ PRODUCTION SYSTEM

Three of the five apps (LugaFlix, UGFlix, Muno) are **live on Google Play with real users**.
All backend testing routes through **Hetzner** (`munoapp.store`) — change `BASE_URL` in
`AppConfig.dart` during testing, revert before release build.

---

## 1. App Family Architecture

### The 5-App Model

All apps are Flutter. LugaFlix is the **parent and master** — all feature development
and bug fixes happen there first. Children inherit via `rsync` + import rewriting.

```
LugaFlix   ← PARENT / MASTER — develop ALL features here
    │
    ├── UGFlix     (package: ugflix,    app_type: ugflix,    ID: ugflix.com)
    ├── Muno       (package: ugflix,    app_type: muno_app,  ID: com.munoapp.free)
    ├── Katogo     (package: katogo,    app_type: katogo,    ID: katogo.movies)
    └── VJ Junior  (package: vjjunior, app_type: vjjunior,  ID: vjjunior.movies)
```

### What Is Shared (≈95% of code)

Everything in `lib/` except the 5 identity files listed below. This includes:
- All 195 screens
- All models, services, controllers, widgets
- TV mode, Safe mode, Games, Dating layer
- Download system, Video player, Streaming

### What Is Per-App (the 5 identity files)

| File | What differs |
| ---- | ------------ |
| `lib/utils/AppConfig.dart` | APP_NAME, app_type, ONESIGNAL_APP_ID, PLAY_STORE_ID |
| `lib/utils/CustomTheme.dart` | Primary, accent, secondary colors |
| `lib/utils/app_theme.dart` | MaterialTheme primary color |
| `lib/utils/my_colors.dart` | Accent color constants |
| `android/app/build.gradle.kts` | namespace, applicationId |

### Sync Commands (LugaFlix → Children)

```bash
PARENT="/Users/mac/Desktop/github/lugaflix"
UGFLIX="/Users/mac/Desktop/github/luganda-translated-movies-mobo"
MUNO="/Users/mac/Desktop/github/muno-app-free-luganda-movies"
KATOGO="/Users/mac/Desktop/github/katogo-app"
VJJR="/Users/mac/Desktop/github/VJJuniorApp"

# Single file sync to all children
sync_file() {
  local f="$1"
  cp "$PARENT/$f" "$UGFLIX/$f"
  cp "$PARENT/$f" "$MUNO/$f"
  cp "$PARENT/$f" "$KATOGO/$f" && sed -i '' 's|package:ugflix/|package:katogo/|g' "$KATOGO/$f"
  cp "$PARENT/$f" "$VJJR/$f"   && sed -i '' 's|package:ugflix/|package:vjjunior/|g' "$VJJR/$f"
}
```

---

## 2. Codebase Stats (LugaFlix)

| Metric | Count |
| ------ | ----- |
| Total Dart files | 387 |
| Screen files | 195 |
| Service files | ~25 |
| Model files | ~20 |
| Widget files | ~30 |
| Directory depth | 6 levels max |

### Key Directory Map

```
lib/
├── controllers/         ← State management (GetX/Provider)
├── core/                ← Base classes, routing
├── models/              ← API response models (MovieModel, SubscriptionModel...)
├── safemode/            ← Complete parallel mini-app for parental mode
│   └── screens/         ← Auth, home, genres, movie detail, player for safe mode
├── screens/
│   ├── account/         ← Profile edit, account merge wizard, deletion
│   ├── admin/           ← Admin panel, subscription manager
│   ├── auth/            ← Login, register, password reset, profile wizard
│   ├── blog/            ← Blog posts
│   ├── dating/          ← User discovery, profile view/edit
│   ├── games/           ← Ludo, Checkers, Chess, Matatu (offline + online lobby)
│   ├── ios_review/      ← Simplified screens for App Store reviewers
│   ├── shop/            ← Main app shell, home, movie listing, player, downloads
│   │   └── screens/shop/
│   │       ├── full_app/     ← Bottom tab layout (home, search, downloads, account)
│   │       │   └── section/  ← AccountSection, MenuScreen, SectionDashboard...
│   │       └── movies/       ← MovieDetailScreen, MoviesListingScreen, DownloadList
│   ├── streaming/       ← Radio player, TV player, station list
│   ├── subscriptions/   ← Plans, details, history, fix payments, pending
│   └── support/         ← Ticket system, movie requests
├── services/            ← auto_account_service, download_service, SubscriptionProtectionService...
├── utils/               ← AppConfig, CustomTheme, app_theme, my_colors, Utilities, SubscriptionGuard
└── widgets/             ← Shared UI components
```

---

## 3. Server Connection

### 3.1 AppConfig.dart — The Single Source of Truth

```dart
// lib/utils/AppConfig.dart (LugaFlix)
static const String BASE_URL = "https://movies.mruodel.com";   // Production (Namecheap)
// static const String BASE_URL = "https://munoapp.store";     // Hetzner (testing)
// static const String BASE_URL = "http://10.0.2.2:8888/katogo"; // Local MAMP

static const String API_BASE_URL = "$BASE_URL/api";
static const String app_type = "lugaflix";   // This differentiates apps server-side
```

**To switch to Hetzner for testing:** Comment production URL, uncomment Hetzner URL. Then rebuild.

**Never commit the Hetzner URL as the active URL in LugaFlix before production testing is complete.**

### 3.2 How app_type Works End-to-End

```
Flutter app sends in every API call:
  POST /api/subscriptions/create
  Body: { "plan_id": 1, "app_type": "lugaflix", ... }

Backend (SubscriptionApiController) reads app_type to:
  - Filter which plans are available (subscription_plans.app_type)
  - Tag the subscription record (subscriptions.app_type)
  - Tag the user's registration (admin_users.app_type)
  - Control content access rules
```

---

## 4. Authentication Flow

### 4.1 Three Login Paths

```
Path 1: Auto device account (silent)
  App opens → no stored token → POST /api/auth/auto-register
  → Server creates account with DEVICE_{fingerprint} username
  → JWT returned, stored in secure storage
  → User can watch free content immediately

Path 2: Google OAuth
  User taps "Continue with Google" → Google sign-in sheet
  → POST /api/auth/google with google_id_token
  → Server finds or creates account linked to Google profile
  → JWT returned

Path 3: Email/Password
  Login screen → POST /api/auth/login
  → JWT returned, 5-year TTL
```

### 4.2 Profile Wizard (Auto → Full Account Upgrade)

When an auto-account user wants to subscribe or access premium features:
1. `GET /api/auth/profile-wizard/state` → returns which steps are incomplete
2. Wizard screens: PersonalInfo → Contact → Password → Preferences → Photo → Finish
3. `POST /api/auth/profile-wizard/finish` → marks account as fully registered

**Known Issue:** Profile wizard in LugaFlix has a `.toLowerCase()` bug in
`profile_completion_wizard_screen.dart` — fixed in LugaFlix, not yet synced to UGFlix + Muno.

### 4.3 JWT Storage

JWT is stored in Flutter's `flutter_secure_storage` (keychain/keystore).
Token TTL is 5 years. There is no server-side session invalidation on logout
(token blacklist not implemented yet — see `SERVER_PLAN.md` Section 8.3).

---

## 5. Subscription & Payment Flow (Mobile Side)

### 5.1 Full Journey

```
SubscriptionPlansScreen
  ↓ user selects plan
  ↓ user selects payment method (MTN/Airtel/Card)
  POST /api/subscriptions/create
  ↓ server returns { payment_url, subscription_id, gateway }

If Pesapal (card):
  Open WebView with payment_url
  User pays → Pesapal redirects back → webhook fires
  → App polls GET /api/subscriptions/{id}/check-payment
  → On "Completed" → show success screen

If Flutterwave (MoMo):
  App does NOT open WebView
  → Server dispatches SolveFLWCaptchaJob (auto-solves captcha)
  → USSD push sent to user's phone
  → App shows "Check your phone" screen with countdown
  → User enters PIN on their phone
  → Flutterwave webhook fires → subscription activated
  → App polls until status = "Active"
```

### 5.2 Current Friction Points (Mobile)

| Screen | Issue | Fix (IMPROVEMENT_PLAN #) |
| ------ | ----- | ------------------------ |
| SubscriptionPlansScreen | Plain text list, no visual plan cards | 2.2 |
| Payment method picker | Text dropdown, no logos | 2.6 |
| MoMo waiting screen | No countdown, no retry | 2.3 |
| Post-payment | Abrupt return to home | 2.5 |
| Grace period | No in-app banner | 2.4 |
| Failed captcha | No retry button visible | 2.6 |

### 5.3 Subscription Models

```dart
// lib/models/SubscriptionModel.dart — key fields used in UI
class SubscriptionModel {
  int id;
  String status;           // "Pending","Active","Expired","Cancelled","CaptchaFailed"
  String paymentStatus;    // "Pending","Completed","AwaitingPIN","CaptchaFailed"
  String paymentGateway;   // "pesapal","flutterwave"
  DateTime? endDateTime;
  DateTime? gracePeriodEnd;
  double? amountPaid;
  String? currency;        // "UGX"
}
```

### 5.4 SubscriptionGuard

`lib/utils/SubscriptionGuard.dart` wraps screens that require an active subscription.
If the user's subscription is expired (or in grace period), it intercepts navigation
and shows the plans screen. This needs to also check `gracePeriodEnd` and show the
banner described in IMPROVEMENT_PLAN Section 3.3.

---

## 6. Video Player

### 6.1 Current Implementation

- Player: `better_player` package (or similar — confirm in `pubspec.yaml`)
- PiP: Native Android PiP via `PipService.dart` MethodChannel `{app_id}/pip`
- Background audio: handled in `PipService`
- TV detection: `tv_detector.dart` MethodChannel `{app_id}/tv_detector`
- Progress saving: `POST /api/save-view-progress` fires on pause/exit

### 6.2 Video URL Resolution

`lib/models/MovieModel.dart` has URL resolution logic:
1. First tries `url` field (Hetzner CDN URL if transferred)
2. Falls back to `external_url` (source URL — may be Namecheap local or MunoWatch)
3. SSL fallback: if `https://` fails, retries with `http://`

This logic was improved in LugaFlix — sync to UGFlix + Muno is pending.

### 6.3 Improvements Needed

| Feature | Current | Target |
| ------- | ------- | ------ |
| Seek gesture | Unknown | Double-tap ±10s with visual indicator |
| Speed control | Unknown | Long-press → speed picker (0.5x/1x/1.5x/2x) |
| Quality switch | Unknown | Quality picker in player controls |
| Next episode | Manual | Auto-play overlay after series episode ends |
| Subtitles | Not supported | VTT track support |
| Orientation | Unknown | Force landscape on play |
| Resuming | Basic | "Resume from 1:23:45?" prompt on re-open |

---

## 7. Download System

### 7.1 Current State

`lib/services/download_service.dart` handles:
- Download initiation: `POST /api/v2/downloads/initiate`
- File download to local storage (Hive/file system)
- Manifest handling: `GET /api/v2/manifest` returns downloadable content list
- SSL fallback: tries https, falls back to http

**This file was updated in LugaFlix but NOT synced to UGFlix or Muno.** This means
UGFlix and Muno users may have broken downloads.

`lib/screens/shop/screens/shop/movies/DownloadListScreen.dart` — shows downloaded movies.

### 7.2 Download State Model

```dart
// lib/screens/shop/models/MovieDownload.dart
class MovieDownload {
  String movieId;
  String filePath;
  double progress;  // 0.0–1.0
  String status;    // downloading, completed, failed, paused
}
```

### 7.3 Offline Playback

Downloaded movies play locally without internet. The player detects local file path
and serves from there instead of streaming URL. Works when status = "completed".

---

## 8. TV Mode

### 8.1 Current Implementation

- TV detection: `MethodChannel('lugaflix.movies/tv_detector')` (per-app channel name)
- When TV detected, app enters "TV mode" — larger UI, D-pad navigation
- `TVPlayerScreen.dart`: dedicated player for TV mode with VideoUrlResolver + SSL fallback
- Remote control: Flutter handles D-pad input via keyboard event mapping

**Issue:** `TVPlayerScreen.dart` was updated in LugaFlix (VideoUrlResolver + SSL fallback)
but NOT synced to UGFlix + Muno. TV users on those apps may have playback issues.

### 8.2 Radio / Streaming Stations

`lib/screens/streaming/` — RadioPlayerScreen, TVPlayerScreen, StationListScreen.
Content comes from `streaming_stations` and `streaming_urls` tables (added June 2026).
This is a live radio/TV streaming feature distinct from VOD.

---

## 9. Games

### 9.1 What Exists

| Game | Mode | Status |
| ---- | ---- | ------ |
| Ludo | Offline + Online multiplayer | Online disabled server-side (503) |
| Checkers | Offline + Online multiplayer | Online disabled server-side (503) |
| Chess | Offline vs AI | Working |
| Matatu | Offline vs AI | Working |
| Trivia | Solo (question bank) | Working — `GET /api/v2/trivia/questions` |

### 9.2 Re-enabling Online Games

Server is returning 503 for all game routes (resource optimization decision).
Backend code is complete and tested. To re-enable:
1. Remove 503 stub in `routes/api.php` (the `$gameDisabled` closure block)
2. Uncomment original game route group below it
3. Test on Hetzner: create 50 dummy game sessions between TEST_ users
4. Monitor queue load (game invitations use `SendChatNotification` job)

### 9.3 Game Coins Economy

`CoinTransaction` model tracks coin awards/spends.
`admin_users.game_coins_balance` stores balance (also in `users.game_coins_balance`).
Coins are awarded for offline wins, subscription milestones.
Coin shop / purchase flow is not yet built.

---

## 10. Safe Mode (Parental Controls)

### 10.1 Architecture

Safe mode is a **complete parallel mini-app** inside LugaFlix (`lib/safemode/`).
It has its own:
- Auth screens (`safemode/screens/auth/`)
- Home, genres, movie list, player, search screens
- PIN gate: user sets a 4-digit PIN in settings, entering safe mode requires it
- Content filtered to age-appropriate only

### 10.2 Current State

Safe mode is functional. Analytics go to `SafemodeView` model and are tracked in
`safemode_views` table. `GET /api/v2/safemode-analytics` returns usage stats.

The `parental_pin` field is NOT yet in the DB schema (it's in the app as local storage).
Adding it to `admin_users` (see IMPROVEMENT_PLAN Section 5.4) will allow PIN sync
across devices.

---

## 11. Dating / Social Layer

### 11.1 What Exists in Flutter

The dating screens are already built:

| Screen | Location | Status |
| ------ | -------- | ------ |
| User discovery list | `lib/screens/dating/UsersListScreen.dart` | Exists |
| Profile view | `lib/screens/dating/ProfileViewScreen.dart` | Exists |
| Profile edit (main) | `lib/screens/dating/AccountEditMainScreen.dart` | Exists |
| Profile edit (personal) | `lib/screens/dating/AccountEditPersonalScreen.dart` | Exists |
| Profile edit (lifestyle) | `lib/screens/dating/AccountEditLifestyleScreen.dart` | Exists |

### 11.2 What the Backend Is Missing

The dating screens exist in Flutter but the DB schema doesn't have the supporting
fields yet. See IMPROVEMENT_PLAN Sections 5.1–5.4 for the full migration plan.

The API endpoints for user discovery (`GET /api/users-list`) exist via `DynamicCrudController`
but have no filtering, no privacy controls, and no distance/preference matching.

**Priority work to connect dating layer:**
1. Run profile field migrations
2. Add `GET /api/users/discover` endpoint with filtering by district, age range, looking_for
3. Add `GET /api/users/{id}/profile` endpoint (respects `profile_visible` flag)
4. Connect `UsersListScreen` to the new endpoint
5. Add profile photo upload flow

---

## 12. Push Notifications

### 12.1 Current Setup

- Provider: OneSignal
- App ID configured in `AppConfig::ONESIGNAL_APP_ID`
- Server sends via `NotificationService::sendPush()`
- Current notification types:
  - Trending movie added
  - Subscription activated (partial)

### 12.2 Notification Types to Add

| Type | Trigger | Deep Link |
| ---- | ------- | --------- |
| Subscription expiry (3 days) | Scheduled job | → SubscriptionPlansScreen |
| Subscription expiry (1 day) | Scheduled job | → SubscriptionPlansScreen |
| Subscription expired | On expiry | → SubscriptionPlansScreen |
| New movie in favourite genre | When movie added, genre matches | → MovieDetailScreen |
| Payment confirmed | FLW/Pesapal webhook processed | → SubscriptionDetailsScreen |
| MoMo USSD sent | After captcha solve | → "Check your phone" screen |
| Game invitation | Opponent invites | → Game lobby screen |

### 12.3 Deep Link Handling

When user taps a notification, Flutter must navigate to the right screen.
Add to OneSignal notification handler in `main.dart`:

```dart
OneSignal.Notifications.addClickListener((event) {
  final data = event.notification.additionalData;
  final type = data?['type'] as String?;
  switch (type) {
    case 'subscription_expiry':
      Navigator.pushNamed(context, '/subscription-plans');
      break;
    case 'new_movie':
      final id = data?['movie_id'];
      Navigator.pushNamed(context, '/movie/$id');
      break;
    case 'payment_confirmed':
      Navigator.pushNamed(context, '/subscription-details');
      break;
  }
});
```

---

## 13. Pending Sync: LugaFlix → UGFlix + Muno

These 12 files need to be synced. UGFlix and Muno use `package:ugflix/` (same as LugaFlix),
so no import rewriting is needed — just copy.

| # | File | Why it matters |
| - | ---- | -------------- |
| 1 | `lib/services/auto_account_service.dart` | bypassGuard fix + app_type injection |
| 2 | `lib/services/download_service.dart` | URL resolution, manifests, SSL fallback |
| 3 | `lib/utils/Utilities.dart` | Cache bypass fix |
| 4 | `lib/utils/app_theme.dart` | Status bar fix, deprecated API removal |
| 5 | `lib/screens/auth/login_screen.dart` | Status bar brightness |
| 6 | `lib/services/google_auth_service.dart` | Debug logging cleanup |
| 7 | `lib/screens/splash_screen.dart` | Auto-account flow + iOS part skip |
| 8 | `lib/screens/streaming/TVPlayerScreen.dart` | VideoUrlResolver + SSL fallback |
| 9 | `lib/screens/games/ludo/services/ludo_multiplayer_service.dart` | Polling interval fix |
| 10 | `lib/services/unified_game_invitation_service.dart` | Polling interval fix |
| 11 | `lib/screens/auth/profile_completion_wizard_screen.dart` | .toLowerCase() bug fix |
| 12 | `lib/models/MovieModel.dart` | Improved URL resolution logic |

```bash
PARENT="/Users/mac/Desktop/github/lugaflix"
UGFLIX="/Users/mac/Desktop/github/luganda-translated-movies-mobo"
MUNO="/Users/mac/Desktop/github/muno-app-free-luganda-movies"

for f in \
  "lib/services/auto_account_service.dart" \
  "lib/services/download_service.dart" \
  "lib/utils/Utilities.dart" \
  "lib/utils/app_theme.dart" \
  "lib/screens/auth/login_screen.dart" \
  "lib/services/google_auth_service.dart" \
  "lib/screens/splash_screen.dart" \
  "lib/screens/streaming/TVPlayerScreen.dart" \
  "lib/screens/games/ludo/services/ludo_multiplayer_service.dart" \
  "lib/services/unified_game_invitation_service.dart" \
  "lib/screens/auth/profile_completion_wizard_screen.dart" \
  "lib/models/MovieModel.dart"
do
  cp "$PARENT/$f" "$UGFLIX/$f" && echo "✓ UGFlix: $f"
  cp "$PARENT/$f" "$MUNO/$f"   && echo "✓ Muno:   $f"
done
```

---

## 14. Katogo + VJ Junior — First Launch Checklist

Both apps are code-complete (built from LugaFlix, all branding applied).
These manual steps remain before they can be submitted to Google Play:

```
KATOGO:
[ ] 1. Replace assets/images/logo.png → Katogo logo (1024x1024 PNG)
[ ] 2. Run: flutter pub run flutter_launcher_icons
[ ] 3. Firebase: register katogo.movies in project ugnews24-bd189
[ ] 4. Download + replace android/app/google-services.json
[ ] 5. iOS: register katogo.movies, download + replace ios/Runner/GoogleService-Info.plist
[ ] 6. OneSignal: create Katogo app → copy ID → update AppConfig.dart
[ ] 7. Android signing: keytool -genkey → create android/key.properties
[ ] 8. Google Play Console: create app with package katogo.movies
[ ] 9. Backend: confirm katogo app_type returns correct subscription plans
[ ] 10. Test on Hetzner: full flow (register → subscribe → watch → download)
[ ] 11. Build: flutter build appbundle --flavor mobile --release
[ ] 12. Submit to Google Play internal track

VJ JUNIOR: same steps with vjjunior.movies
```

---

## 15. Mobile Task List

Cross-reference with `IMPROVEMENT_PLAN.md` — same numbers.

### Immediate (do now)

```
[ ] 3.1  Sync 12 files LugaFlix → UGFlix + Muno (critical bug fixes)
[ ] 3.2  Commit UGFlix
[ ] 3.3  Commit Muno
```

### This Week

```
[ ] 3.4  Katogo: logo, Firebase, OneSignal, signing key
[ ] 3.5  VJ Junior: logo, Firebase, OneSignal, signing key
[ ] 3.6  Test Katogo full flow on Hetzner
[ ] 3.7  Test VJ Junior full flow on Hetzner
[ ] 2.1  Add formatUGX() utility, apply throughout
[ ] 2.3  MoMo countdown timer "Check your phone" screen
[ ] 2.4  Grace period banner widget
```

### Next Two Weeks

```
[ ] 4.1  Add shimmer, lottie, flutter_animate packages
[ ] 4.2  Replace spinners with skeleton loaders
[ ] 4.3  Hero transitions (movie card → detail)
[ ] 4.6  "Continue Watching" rail on home screen
[ ] 4.8  Double-tap seek ±10s in player
[ ] 4.9  "Next Episode" auto-play overlay
[ ] 2.2  Plan comparison cards (SubscriptionPlansScreen)
[ ] 4.13 Sync Phase 4 changes → all 4 children
```

### Future

```
[ ] 5.3  Star rating widget (MovieDetailScreen)
[ ] 5.8  "Because You Watched" personalised rail
[ ] 6.7  Share movie via WhatsApp (share_plus)
[ ] 7.1  Re-enable online games (after Hetzner tested)
[ ] 7.5  Dating profile screens → profile wizard
[ ] 6.4  Search autocomplete / suggestions
[ ] 6.6  Subtitle track support in player
```
