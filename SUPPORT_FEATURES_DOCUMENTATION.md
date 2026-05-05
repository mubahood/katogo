# Katogo Support Features — Complete Implementation Documentation

> **Status: All features implemented and deployed.**
> Last updated: 2026 support module completion sprint.

---

## Table of Contents

1. [Feature Overview](#1-feature-overview)
2. [Support Team Role Management](#2-support-team-role-management)
3. [Auto-Account Creation (Device Login)](#3-auto-account-creation-device-login)
4. [Support Ticket Management](#4-support-ticket-management)
5. [Movie Request Management](#5-movie-request-management)
6. [Payment Auto-Ticket Generation](#6-payment-auto-ticket-generation)
7. [Database Reference](#7-database-reference)
8. [API Reference](#8-api-reference)
9. [Admin Panel Guide](#9-admin-panel-guide)
10. [Operations Runbook](#10-operations-runbook)
11. [Testing Checklist](#11-testing-checklist)

---

## 1. Feature Overview

| Feature | Backend | Frontend | Admin Panel | Status |
|---|---|---|---|---|
| Support Team Role Management | `SupportTeamController` | — | `/admin/support-team` | ✅ Done |
| Auto-Account Creation | `ApiController::auto_register` | `AutoAccountService` + `login_screen.dart` | `/admin/auto-created-accounts` | ✅ Done |
| Support Ticket Management | `CustomerTicket` + `CustomerTicketRecord` + `SupportTicketController` | Ticket screens in Flutter | `/admin/support-tickets` | ✅ Done |
| Movie Request Management | `MovieRequest` + `MovieRequestController` (API) | Movie request dialogs | `/admin/movie-requests` | ✅ Done |
| Payment Auto-Ticket Generation | `SubscriptionApiController::syncPaymentSupportTicketByStatus` | — | Visible in `/admin/support-tickets` | ✅ Done |

---

## 2. Support Team Role Management

### Purpose

Admins can promote any user account to the **Support Team** role directly from the admin panel user list. Support team members gain elevated access to ticket management tools.

### How It Works

- **Role:** `support_team` (slug). Role ID is seeded via migration `2026_05_02_000004_seed_support_team_role`.
- **Assignment:** Stored in `admin_role_users` table (same as all other admin roles).
- **Restriction:** Only accounts with the `administrator` role can assign/remove the support_team role. Support agents cannot modify roles.

### Admin Panel Location

`/admin/support-team`

The page shows:
1. **KPI tiles** — Total users, auto-created accounts, support agents, total tickets.
2. **Welcome Queue** — New auto-created accounts that need a WhatsApp welcome message (`account_opening` ticket type, no agent contact yet).
3. **Users grid** — All user accounts with platform, origin, status, assigned role badge, and action buttons.

### Assign / Remove Role (UI)

Each user row has a button:
- Green **Assign Support Role** → calls `POST /admin/support-team/toggle` with `action=assign`.
- Red **Remove Support Role** → calls `POST /admin/support-team/toggle` with `action=remove`.

The response updates the button in-place via JavaScript without a page reload.

### AJAX Endpoint

```
POST /admin/support-team/toggle
```

**Request body:**
```json
{
  "user_id": 42,
  "action": "assign"   // or "remove"
}
```

**Response (success):**
```json
{
  "success": true,
  "message": "Support role assigned.",
  "has_role": true
}
```

**Restrictions enforced server-side:**
- Caller must be an `administrator`.
- Cannot assign to another administrator (role conflict guard).
- All changes written to `support_audit_logs` table for traceability.

### Code Reference

| File | Location |
|---|---|
| Controller | `app/Admin/Controllers/SupportTeamController.php` |
| Route (admin) | `app/Admin/routes.php` — `support-team`, `support-team/toggle` |
| Migration (role seed) | `database/migrations/2026_05_02_000004_seed_support_team_role.php` |
| Migration (user fields) | `database/migrations/2026_05_02_000001_add_support_fields_to_admin_users.php` |

---

## 3. Auto-Account Creation (Device Login)

### Purpose

On first launch, the mobile app silently creates an account tied to the device without requiring any sign-up form. This removes friction for first-time users while maintaining a real authenticated session.

### Flow (High Level)

```
App launch → LoginScreen.initState()
    └─ AutoAccountService.checkAndCreate()
        ├─ Already logged in? → Skip, show login form
        ├─ Get/generate stable device_id (UUID stored in SharedPreferences)
        ├─ POST auth/auto-register → { device_id, device_platform, device_model, app_type }
        ├─ Backend: device_id already used? → Return existing account (idempotent)
        ├─ Backend: New device → Create guest user + assign role + create welcome ticket
        ├─ Save token to SharedPreferences (same as normal login)
        └─ Navigate to HomeScreen
```

### Backend: `POST /api/auth/auto-register`

**File:** `app/Http/Controllers/ApiController.php` — method `auto_register()`

**Request parameters:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `device_id` | string | ✅ | Min 4 chars. Hashed server-side with HMAC-SHA256 before storing. |
| `device_platform` | string | — | `android` or `ios`. Defaults to `android`. |
| `device_model` | string | — | Human-readable device name (max 80 chars). |
| `app_type` | string | — | `lugaflix`, `ugflix`, or `muno_app`. |

**Idempotency:** The raw `device_id` is hashed using `hash_hmac('sha256', $deviceId, APP_KEY)` before storing. On repeat calls with the same device, the existing account is returned with a refreshed token — no duplicate accounts are created.

**On success:**
```json
{
  "code": 1,
  "message": "Account created and logged in successfully.",
  "data": {
    "user": { "id": 123, "username": "guest_a1b2c3d4", "token": "eyJ...", ... },
    "company": { ... }
  }
}
```

**On failure (e.g., device_id too short):**
```json
{ "code": 0, "message": "device_id is required (min 4 chars)." }
```

**What the backend creates:**
- `users` row with:
  - `name = "Guest User"`, `first_name = "Guest"`, `last_name = "User"`
  - `username = "guest_{8-char-hex}"`
  - `email = "guest_{hash}@auto.lugaflix.app"` (placeholder, not used for login)
  - `account_origin = "auto_device"`
  - `account_state = "auto_created"`
  - `device_id = hashedDeviceId`
  - `device_model`, `device_platform`, `app_type` from request
- `admin_role_users` row assigning `role_id = 2` (normal_user)
- Long-lived JWT token (5-year TTL)

### Account Upgrade (Profile Completion)

When a guest user fills in their name/phone/email:

```
POST /api/auth/complete-profile   (requires valid JWT token)
```

This upgrades `account_state` from `auto_created` to `registered` and persists the real credentials.

### Flutter Implementation

**Files:**
- `lib/services/auto_account_service.dart` — Core logic, UUID generation, API call, session persistence.
- `lib/screens/auth/login_screen.dart` — Calls `AutoAccountService.checkAndCreate()` in `initState()` via `WidgetsBinding.instance.addPostFrameCallback`.

**Key behaviors:**
- `auto_device_id` UUID is stored permanently in SharedPreferences. Reinstalling the app preserves the same account if SharedPreferences survives (Android adaptive/cloud backup).
- If SharedPreferences is cleared (factory reset), a new account is created — by design.
- The service NEVER overwrites an existing valid session. If `LoggedInUserModel.getLoggedInUser()` returns a user with `id > 0`, auto-register is skipped entirely.
- Network failures are silent. The login form remains available as a fallback.
- The loading overlay shows **"Preparing your account..."** while auto-registration is in progress.

### Admin Visibility

All auto-created accounts are visible at `/admin/auto-created-accounts`, showing device model, platform, origin, and account state. The **Welcome Queue** on `/admin/support-team` shows accounts that have not yet received a welcome WhatsApp message.

### Code Reference

| File | Purpose |
|---|---|
| `app/Http/Controllers/ApiController.php` | `auto_register()` and `complete_profile()` methods |
| `routes/api.php` lines 36–40 | Route: `POST auth/auto-register` |
| `routes/api.php` line 132 | Route: `POST auth/complete-profile` |
| `lib/services/auto_account_service.dart` | Flutter service |
| `lib/screens/auth/login_screen.dart` | Login screen integration |
| `app/Admin/Controllers/AutoCreatedAccountController.php` | Admin panel list |

---

## 4. Support Ticket Management

### Purpose

A structured support conversation system where users open tickets (manually or automatically) and support agents respond, track status, and escalate as needed.

### Ticket Lifecycle

```
open → in_progress → resolved → closed
         │
         └─→ escalated → in_progress → resolved
         │
         └─→ pending (waiting for user response)
```

**Valid statuses:** `open`, `pending`, `in_progress`, `resolved`, `closed`, `escalated`

**Valid ticket types:**

| Type | Trigger |
|---|---|
| `general` | Manual user request |
| `account_opening` | Auto-created account (welcome queue) |
| `payment_thanks` | Successful payment auto-ticket |
| `payment_fail` | Failed payment auto-ticket |
| `billing_issue` | Pending payment > 15 minutes auto-ticket |
| `auto_account_issue` | Problem with auto-created account |
| `subscription_issue` | Subscription problem |
| `technical_issue` | App technical bug report |
| `content_issue` | Content quality complaint |
| `movie_request` | User requesting a movie to be added |

**Valid resolution states:** `unresolved`, `resolved`, `cancelled`

### Ticket Record (Message)

Each ticket has one or more `CustomerTicketRecord` rows representing individual messages or system events.

**Sender types:** `user`, `support_team`, `system`, `auto`

**Action types:** `needs_user_action`, `status_update`, `internal_note`, `escalation`, `resolution`

### Admin Panel: Support Tickets

**Location:** `/admin/support-tickets`

**Columns:**
- ID | User | Phone | App | Platform | Type | Status | Resolution | Rating | Unread Badge | Last Reply | Created

**Features:**
- **Inline edits:** Platform, Type, Status, Resolution, Rating — click to edit in-place.
- **Unread badge:** Red `!` badge for tickets with unread user messages.
- **Sort by last_reply_at:** Most recently active tickets appear at top by default.
- **WhatsApp Engage button:** Opens a pre-filled WhatsApp message template based on ticket type. Templates include payment thank-you, payment failure help, billing follow-up, and general support messages.
- **Respond button:** Opens a modal with:
  - Previous messages thread
  - Reply textarea
  - Movie search (for `movie_request` type tickets)
  - Status update dropdown
- **Quick search:** Full-text across user name, email, phone, subject.
- **Filter panel:** By app type, platform, ticket type, status, resolution state, rating.

**Ticket detail page:** `/admin/support-tickets/{id}`

Shows full message thread with sender identification, timestamps, and a reply form.

**Agent reply endpoint:**

```
POST /admin/support-tickets/{id}/reply
```

```json
{
  "message": "We have added the movie you requested.",
  "status": "resolved"
}
```

### API Endpoints (Mobile)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/support-tickets` | List user's tickets with reply indicators |
| `POST` | `/api/support-tickets` | Create new ticket |
| `GET` | `/api/support-tickets/{id}` | Ticket detail + records |
| `POST` | `/api/support-tickets/{id}/reply` | User reply to ticket |
| `GET` | `/api/support-tickets/unread-count` | Unread message count badge |

**Code Reference:**

| File | Purpose |
|---|---|
| `app/Models/CustomerTicket.php` | Primary ticket model |
| `app/Models/CustomerTicketRecord.php` | Message/event records |
| `app/Http/Controllers/Api/SupportTicketController.php` | API controller |
| `app/Admin/Controllers/SupportTeamController.php` | Admin ticket list + reply |
| `database/migrations/2026_05_02_000002_create_customer_tickets_table.php` | Table structure |
| `database/migrations/2026_05_02_000003_create_customer_ticket_records_table.php` | Records table |
| `database/migrations/2026_05_03_190000_add_ticket_type_resolution_and_tracking_fields.php` | Extended fields |

---

## 5. Movie Request Management

### Purpose

Users can request movies to be added to the platform. Each request creates a linked support ticket for communication and a `movie_requests` record for lifecycle tracking.

### User Flow

1. User searches for a movie in the app — title not found.
2. User taps **"Request this movie"** button.
3. App calls `POST /api/movie-requests` with search query and movie title(s).
4. Backend creates:
   - A `movie_requests` record.
   - A `customer_tickets` row (type: `movie_request`, or reuses an existing open one).
   - A `customer_ticket_records` row with the request context.
5. User sees confirmation: "Your request has been submitted."

### Request Lifecycle

```
submitted → reviewing → in_progress → fulfilled
                              │
                              └─→ rejected → cancelled
```

**Valid statuses:** `submitted`, `reviewing`, `in_progress`, `fulfilled`, `rejected`, `cancelled`

### API Endpoint: Create Movie Request

```
POST /api/movie-requests
Authorization: Bearer {token}
```

**Request body:**
```json
{
  "searched_query": "Kigulawo",
  "requested_movies": ["Kigulawo 2023", "Kigulawo Part 2"],
  "user_message": "I really want to watch this movie. It was great.",
  "request_source": "search",
  "platform_type": "lugaflix",
  "app_type": "lugaflix"
}
```

**Idempotency:** If the user already has an open `movie_request` ticket, the new request is attached to the same ticket instead of opening a duplicate.

**Response:**
```json
{
  "code": 1,
  "message": "Movie request submitted.",
  "data": {
    "movie_request": { "id": 7, "status": "submitted", ... },
    "ticket_id": 14
  }
}
```

### API Endpoint: List Movie Requests

```
GET /api/movie-requests
Authorization: Bearer {token}
```

Returns the user's movie requests with enhanced reply indicators:
- `has_support_reply` — boolean
- `support_reply_preview` — First 80 chars of the reply
- `support_reply_at_effective` — Latest reply timestamp

### Admin Panel: Movie Requests

**Location:** `/admin/movie-requests`

**Columns:**
- ID | User | Phone | App | Platform | Source | Status (inline edit) | Query | Requested Movies | Ticket link | Created

**Features:**
- **Status inline edit** — Click to change status to `submitted / reviewing / in_progress / fulfilled / rejected / cancelled`.
- **Ticket link** — Direct link to the associated support ticket.
- **Detail page** — Shows full request with support reply field.
- **Replying from the form** — When a support reply is saved, a `CustomerTicketRecord` is automatically created so the reply appears in the ticket conversation thread.
- **Auto ticket status sync** — When status changes to `fulfilled`, ticket is marked `resolved`. When `rejected`/`cancelled`, ticket is marked `closed`.
- **Quick search** — Search by query text, user name, email, or phone.
- **Filters** — By status, source, platform, date range.

### Code Reference

| File | Purpose |
|---|---|
| `app/Models/MovieRequest.php` | Movie request model |
| `app/Http/Controllers/Api/MovieRequestController.php` | API controller (create, index) |
| `app/Admin/Controllers/MovieRequestController.php` | Admin panel controller |
| `database/migrations/2026_05_04_000100_create_movie_requests_table.php` | Table structure |
| `database/migrations/2026_05_04_000110_add_movie_request_fields_to_customer_tickets_table.php` | Ticket context fields |

---

## 6. Payment Auto-Ticket Generation

### Purpose

Payment events (success, failure, pending-too-long) automatically create support tickets so the support team has visibility into payment issues without waiting for users to manually report them.

### Trigger Points

The private method `syncPaymentSupportTicketByStatus(Subscription $subscription)` in `SubscriptionApiController` is called from 7 locations:

| Trigger | Method | When Called |
|---|---|---|
| Pesapal success IPN | `pesapalIpn()` | Pesapal server-to-server IPN confirming payment |
| Pesapal callback | `callback()` | User returns to app after Pesapal payment |
| Pesapal IPN update | `pesapalIpn()` | Any payment status update from Pesapal |
| Flutterwave callback | `flutterwaveCallback()` | User returns from Flutterwave payment page |
| Flutterwave webhook | `flutterwaveWebhook()` | Flutterwave server-to-server event |
| Get payment status | `getPaymentStatus()` | Client polling for subscription status |
| Get pending | `getPending()` | Batch check for stale pending subscriptions |
| Finalize success state | `finalizeSuccessfulSubscriptionState()` | After confirmed payment, before cache clear |

### Trigger Mapping

| Payment Condition | Ticket Type | Ticket Status | Description |
|---|---|---|---|
| `subscription.status = Active` AND `payment_status = Completed` | `payment_thanks` | `resolved` | Successful payment — thank-you ticket, already resolved |
| `subscription.status = Failed` OR `payment_status = Failed` | `payment_fail` | `open` | Failed payment — needs agent follow-up |
| `payment_status IN (Pending, Processing)` AND `created_at ≤ now() - 15 minutes` | `billing_issue` | `pending` | Stuck payment — monitoring ticket |

### Idempotency (No Duplicate Tickets)

Before creating any ticket or record, the method checks for an existing `CustomerTicketRecord` with the **exact same signature** in `action_description`:

```
AUTO_PAYMENT_TICKET|subscription={id}|trigger={event_name}
```

Example:
```
AUTO_PAYMENT_TICKET|subscription=42|trigger=payment_success
```

If a record with this signature already exists, the method returns without creating anything. This means:
- Multiple IPN calls for the same payment → only one ticket.
- Client polling every 5 seconds → only one ticket.
- Re-running `getPending` cron → no spam.

### Ticket Content

Each auto-generated `CustomerTicketRecord` includes full payment context in `action_description_meta`:
- Subscription ID, plan name, amount, currency
- Gateway (`pesapal` or `flutterwave`)
- Gateway tracking ID and merchant reference
- Payment status, subscription status
- Trigger source (which controller method created the ticket)

### Safety

The entire `syncPaymentSupportTicketByStatus` method is wrapped in `try/catch(\Throwable)`. Any error in ticket creation is logged but **does not interrupt the payment flow**. Payments always complete regardless of ticket system state.

### Code Reference

| File | Location |
|---|---|
| Main method | `app/Http/Controllers/SubscriptionApiController.php` — `syncPaymentSupportTicketByStatus()` (~line 2341) |
| Trigger: finalizeSuccessfulSubscriptionState | ~line 2338 |
| Trigger: getPending | ~line 1010 |
| Trigger: pesapalIpn (failed branch) | ~line 1515 |
| Trigger: pesapalIpn (success branch) | ~line 1600 |
| Trigger: flutterwaveCallback | ~line 1670 |
| Trigger: flutterwaveWebhook | ~line 1742 |
| Trigger: getPaymentStatus | ~line 1800 |

### SQL Verification Queries

```sql
-- Count auto-generated payment tickets by type
SELECT ticket_type, status, COUNT(*) as total
FROM customer_tickets
WHERE ticket_type IN ('payment_thanks', 'payment_fail', 'billing_issue')
GROUP BY ticket_type, status;

-- Check idempotency records for a specific subscription
SELECT action_description, created_at
FROM customer_ticket_records
WHERE action_description LIKE 'AUTO_PAYMENT_TICKET|subscription=42|%';

-- Find pending subscriptions older than 15 minutes
SELECT id, user_id, payment_status, created_at,
       TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS age_minutes
FROM subscriptions
WHERE payment_status IN ('Pending', 'Processing')
  AND created_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
ORDER BY created_at ASC;

-- Verify no duplicate tickets for same subscription+trigger
SELECT action_description, COUNT(*) as count
FROM customer_ticket_records
WHERE action_description LIKE 'AUTO_PAYMENT_TICKET|%'
GROUP BY action_description
HAVING count > 1;
```

---

## 7. Database Reference

### `customer_tickets`

| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK | References `users.id` |
| `status` | enum | `open, pending, in_progress, resolved, closed, escalated` |
| `ticket_type` | string | See ticket type list in Section 4 |
| `resolution_state` | string | `unresolved, resolved, cancelled` |
| `subject` | string | Short description |
| `account_origin` | string | `auto_device, manual, google` |
| `app_type` | string | `lugaflix, ugflix, muno_app` |
| `platform_type` | string | `lugaflix, luga, ugflix, muno, muno_app` |
| `platform` | string | `android, ios, web` |
| `assigned_to` | bigint FK | Admin user assigned to this ticket |
| `last_reply_at` | timestamp | Most recent reply timestamp |
| `reply_count` | int | Total reply count |
| `rating_of_satisfaction` | int | 1–5 user rating |
| `agent_has_contacted_customer` | bool | True after first agent outreach |
| `customer_has_responded` | bool | True after first user reply |
| `has_unread_user` | bool | Unread message for user |
| `has_unread_support` | bool | Unread message for support |
| `is_movie_request` | bool | True for movie request tickets |
| `movie_request_payload` | json | Snapshot of movie request data |

### `customer_ticket_records`

| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `customer_ticket_id` | bigint FK | References `customer_tickets.id` |
| `sender_type` | string | `user, support_team, system, auto` |
| `sender_id` | bigint | ID of sender (user or admin) |
| `message` | text | Message body |
| `action_type` | string | `needs_user_action, status_update, internal_note, escalation, resolution` |
| `action_description` | string | Short label or auto-ticket signature |
| `is_internal_note` | bool | Not shown to user if true |
| `show_to_customer` | bool | Explicitly shown in user-facing ticket view |
| `is_read_by_user` | bool | |
| `is_read_by_support` | bool | |
| `has_unread_user` | bool | |
| `customer_seen` | bool | |
| `customer_seen_at` | timestamp | |

### `movie_requests`

| Column | Type | Description |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK | |
| `customer_ticket_id` | bigint FK | Linked support ticket |
| `status` | string | `submitted, reviewing, in_progress, fulfilled, rejected, cancelled` |
| `request_source` | string | `search, support, manual` |
| `platform_type` | string | |
| `app_type` | string | |
| `searched_query` | string | What the user searched for |
| `requested_movies` | json | Array of requested movie titles |
| `user_message` | text | User's note |
| `support_reply` | text | Agent's response |
| `support_reply_at` | timestamp | When agent replied |
| `handled_by` | bigint FK | Admin user who replied |

### `support_audit_logs`

Immutable log of all role changes and sensitive support actions.

| Column | Type | Description |
|---|---|---|
| `actor_id` | bigint | Admin user who performed the action |
| `actor_role` | string | `administrator, support_team, system` |
| `event_type` | string | e.g., `role_assigned`, `role_removed`, `ticket_replied` |
| `entity_type` | string | e.g., `user`, `ticket` |
| `entity_id` | bigint | ID of the affected entity |
| `description` | text | Human-readable summary |
| `meta` | json | Additional context |
| `ip_address` | string | Actor's IP address |

---

## 8. API Reference

### Auth Endpoints

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/api/auth/register` | None | Normal email/password registration |
| `POST` | `/api/auth/login` | None | Email/password login |
| `POST` | `/api/auth/auto-register` | None | Silent device-based auto-registration |
| `POST` | `/api/auth/complete-profile` | Bearer token | Upgrade auto account to registered |

### Support Ticket Endpoints

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/support-tickets` | Bearer | List user's tickets |
| `POST` | `/api/support-tickets` | Bearer | Create ticket |
| `GET` | `/api/support-tickets/{id}` | Bearer | Get ticket + records |
| `POST` | `/api/support-tickets/{id}/reply` | Bearer | Add reply to ticket |
| `GET` | `/api/support-tickets/unread-count` | Bearer | Unread badge count |

### Movie Request Endpoints

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/api/movie-requests` | Bearer | List user's movie requests |
| `POST` | `/api/movie-requests` | Bearer | Submit a movie request |

---

## 9. Admin Panel Guide

### Navigation Menu

After logging in to `/admin`, the left sidebar contains:

```
Users
  └─ Auto-Created Accounts   /admin/auto-created-accounts
  └─ Support Team            /admin/support-team
Support
  └─ Support Tickets         /admin/support-tickets
  └─ Movie Requests          /admin/movie-requests
```

### Support Team Page Walkthrough

1. **Open** `/admin/support-team`.
2. Review the **KPI tiles** at the top (total users, agents, tickets).
3. **Welcome Queue** shows users who signed up via auto-account and haven't received a welcome WhatsApp. Click the phone number to open WhatsApp.
4. **Users grid** below — find a user and click **Assign Support Role** to promote them. The button turns red with a **Remove** option.

### Support Tickets Page Walkthrough

1. **Open** `/admin/support-tickets`.
2. Tickets are sorted by `last_reply_at` (most recent first).
3. **Red `!` badge** = unread user message. Prioritize these.
4. Click **Status** cell inline to change without leaving the list.
5. Click **Engage (WhatsApp)** to open a pre-filled WhatsApp message for the user's phone.
6. Click **Respond** to open the reply modal with full conversation thread.
7. Use the **search bar** at the top to find tickets by name, email, phone, or subject.
8. Use the **Filter** button to narrow by app, platform, ticket type, or status.

### Movie Requests Page Walkthrough

1. **Open** `/admin/movie-requests`.
2. New requests arrive with status `submitted`.
3. Change status to `reviewing` to acknowledge receipt.
4. When content team adds the movie, change to `fulfilled` — this auto-resolves the linked ticket.
5. Click the **ticket ID** link to see the full conversation with the user.
6. To reply to the user, open the detail page and fill in the **Support Reply** field — it automatically creates a ticket record visible to the user in their app.

---

## 10. Operations Runbook

### Checking for Stuck Pending Payments

```sql
SELECT s.id, u.name, u.phone_number, s.plan_name, s.amount,
       s.currency, s.payment_status, s.gateway,
       TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) AS age_minutes
FROM subscriptions s
JOIN users u ON u.id = s.user_id
WHERE s.payment_status IN ('Pending', 'Processing')
  AND s.created_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
ORDER BY s.created_at ASC;
```

These should have a `billing_issue` ticket auto-generated. Check:
```sql
SELECT ct.id, ct.status, ct.ticket_type, ctr.action_description
FROM customer_tickets ct
JOIN customer_ticket_records ctr ON ctr.customer_ticket_id = ct.id
WHERE ct.ticket_type = 'billing_issue'
  AND ctr.action_description LIKE 'AUTO_PAYMENT_TICKET|subscription=%'
ORDER BY ct.created_at DESC LIMIT 10;
```

### Handling a `payment_fail` Ticket

1. Go to `/admin/support-tickets` and filter by `Type = payment_fail`.
2. Click **Engage (WhatsApp)** to send a pre-built payment failure support message.
3. After resolving, change status to `resolved`.
4. Set resolution state to `resolved`.

### Reviewing Auto-Account Welcome Queue

1. Go to `/admin/support-team`.
2. Look at the **Welcome Queue** table.
3. For each user, click the WhatsApp button to send the welcome message.
4. After contacting, the ticket's `agent_has_contacted_customer` will be updated by the reply system.

### Promoting a User to Support Agent

1. Go to `/admin/support-team`.
2. Find the user by name or email using the search/filter.
3. Click **Assign Support Role**.
4. Confirm the button changed to **Remove Support Role** (red).
5. Change is effective immediately — no reload required.

### Reverting a Support Agent Demotion

Same as above — click **Remove Support Role**. The change is logged in `support_audit_logs`.

---

## 11. Testing Checklist

### Auto-Account Creation

- [ ] **Fresh install**: Open app → login screen shows → "Preparing your account..." overlay visible → redirects to home without manual login.
- [ ] **Repeat launch**: Open app again → login screen briefly shown → immediately redirects to home (existing session).
- [ ] **Idempotency**: Clear app data but not SharedPreferences → auto-register with same device_id → same account returned.
- [ ] **Factory reset**: Clear SharedPreferences → new UUID → new account created, distinct from previous.
- [ ] **Fallback**: Disable network → auto-register fails silently → login form shown → manual login works.
- [ ] **Profile upgrade**: Complete profile form → `account_state` changes to `registered` in DB.

### Support Ticket Management

- [ ] User creates ticket via app → visible in `/admin/support-tickets`.
- [ ] Admin replies → reply visible in user's app ticket view.
- [ ] Status inline edit works without page reload.
- [ ] WhatsApp Engage button opens correct pre-filled message.
- [ ] Unread badge appears and clears correctly.
- [ ] Filter by ticket type works.

### Movie Requests

- [ ] User submits movie request via app → `movie_requests` row created.
- [ ] Linked ticket created with `is_movie_request = true`.
- [ ] Request visible in `/admin/movie-requests` immediately.
- [ ] Admin changes status to `fulfilled` → linked ticket status changes to `resolved`.
- [ ] Admin adds support reply → reply visible in user's ticket thread in app.

### Payment Auto-Tickets

- [ ] Successful payment → `payment_thanks` ticket created, status `resolved`.
- [ ] Failed payment → `payment_fail` ticket created, status `open`.
- [ ] Pending payment > 15 min → `billing_issue` ticket created, status `pending`.
- [ ] Same payment triggers multiple callbacks → only one ticket/record per trigger type (idempotency).
- [ ] Payment flow not affected by ticket system errors (wrap in try/catch verified).

### Support Team Role Management

- [ ] Admin can assign `support_team` role to a user.
- [ ] Admin can remove `support_team` role from a user.
- [ ] Support team agent cannot assign roles.
- [ ] Role change logged in `support_audit_logs`.
- [ ] KPI tiles on support-team page show correct counts.
