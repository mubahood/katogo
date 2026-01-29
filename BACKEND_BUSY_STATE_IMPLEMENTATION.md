# Backend Improvements - Game Busy State Management

## ✅ Completed Backend Changes

### 1. Database Migration
**File:** `/Applications/MAMP/htdocs/katogo/database/migrations/2026_01_29_150000_add_busy_state_to_users_and_sessions.php`

Added to `users` table:
- `is_busy_in_game` (boolean): Marks if user is currently in an active game
- `busy_since` (timestamp): When user became busy (for 15-minute auto-cleanup)
- Index on `is_busy_in_game` for fast querying

### 2. GameController Updates
**File:** `/Applications/MAMP/htdocs/katogo/app/Http/Controllers/GameController.php`

#### New Methods:
```php
private function cleanupStuckBusyUsers()
// Automatically unmarks users busy for >15 minutes

private function markUserBusy($userId)
// Marks user as busy when game starts

private function markUserNotBusy($userId)
// Unmarks user when game ends/leaves
```

#### Modified Methods:
- `onlineUsers()`: 
  - Filters out users with `is_busy_in_game = true`
  - Calls `cleanupStuckBusyUsers()` on every request
  - Returns `is_busy_in_game` field in response
  
- `acceptInvitation()`:
  - Marks both players as busy when game starts
  
- `leaveSession()`:
  - Marks both players as not busy when leaving game
  
- `handleRoundWin()`:
  - Marks both players as not busy when game completes

### 3. LudoController Updates
**File:** `/Applications/MAMP/htdocs/katogo/app/Http/Controllers/LudoController.php`

#### New Methods:
```php
private function markUserBusy($userId)
private function markUserNotBusy($userId)
```

#### Modified Methods:
- `acceptInvitation()`: Marks both players as busy
- `leaveGame()`: Marks both players as not busy

### 4. Busy State Logic

#### Auto-Cleanup (15 minutes):
- Runs on every `onlineUsers()` API call
- Automatically unmarks users busy for more than 15 minutes
- Prevents users from being locked out forever

#### Manual Cleanup:
- **Game Start**: Both players marked busy
- **Game End**: Both players unmarked
- **Leave Game**: Both players unmarked
- **Game Complete**: Both players unmarked

#### Online Players List:
- **BEFORE**: Showed all users active in last 30 minutes
- **AFTER**: Filters out users with `is_busy_in_game = true`
- **Result**: Users in active games won't receive new invitations

## 🎯 How It Works

### Scenario 1: Normal Game Flow
1. User A invites User B
2. User B accepts → **Both marked busy**
3. Game proceeds
4. Game ends → **Both unmarked**
5. Both users appear in online list again

### Scenario 2: App Crash During Game
1. User A and B start game → **Both marked busy**
2. App crashes, users don't properly exit
3. **After 15 minutes**: Auto-cleanup runs
4. **Result**: Both automatically unmarked
5. Both users available for new games

### Scenario 3: User Leaves Game
1. User A and B in active game → **Both busy**
2. User A presses "Leave Game" button
3. API marks game complete → **Both unmarked immediately**
4. Both users available for new invitations

### Scenario 4: Invitation While Watching Movie
1. User A watching movie
2. User B sends game invitation
3. If User A is NOT busy:
   - Invitation shows up in compact dialog
   - User A can accept
   - **On accept**: Movie enters PiP mode (frontend)
   - Game starts → **Both marked busy**

## 📊 API Response Changes

### GET /api/game/online-users

**Before:**
```json
{
  "id": 123,
  "name": "John Doe",
  "is_online": true
}
```

**After:**
```json
{
  "id": 123,
  "name": "John Doe",
  "is_online": true,
  "is_busy_in_game": false  // NEW FIELD
}
```

**Filtering:**
- Users with `is_busy_in_game = true` are **excluded** from the list

## ✅ Testing Checklist

- [x] Migration created
- [x] GameController updated (Matatu)
- [x] LudoController updated (Ludo)
- [x] Online users endpoint filters busy users
- [x] 15-minute auto-cleanup implemented
- [x] Leave game unmarks users
- [x] Game completion unmarks users
- [x] Game start marks users busy

## 🚀 Next Steps (Frontend)

1. Update invitation dialog UI (make compact)
2. Implement PiP activation on game accept
3. Test in both apps
4. Copy changes to LugaFlix

---

**Status:** ✅ Backend Complete  
**Date:** January 29, 2026
