# Support Plan (Execution Order)

This file defines the implementation order for support, movie requests, and auto-account login. We execute in this order to protect user sessions and ship high-impact value first.

## Scope

- Backend: Katogo Laravel (`/Applications/MAMP/htdocs/katogo`)
- Frontend: Lugaflix Flutter (`/Users/mac/Desktop/github/lugaflix`)
- Out of scope for this session: other apps/repos

## Priority Order

1. Support Team account and role controls
2. Auto-account creation and app login continuity
3. Support ticket management alignment
4. Movie request management integrated with support tickets
5. Search-not-found movie request UX
6. Validation, QA checklist, and rollout notes

## Why This Order

- Role controls unblock operations immediately.
- Auto-account/session stability must be correct before adding more user-entry points.
- Ticket model is the shared backbone for support and movie requests.
- Movie requests should reuse ticket communication and audit rails.

## Current Session Task List

1. Add movie request backend module (model, migration, API controller, admin controller, routes, menu).
2. Integrate movie requests with support tickets and ticket records.
3. Add movie request context fields into customer tickets.
4. Integrate auto-account creation into mobile login screen safely (no local data/session disruption).
5. Add “not found in search -> submit movie request” UX.
6. Document usage and operations for support/admin and mobile flows.
7. Run lint/syntax checks and endpoint smoke checks.

## Dependency Notes

- `movie_requests` depends on existing `customer_tickets` and `customer_ticket_records`.
- Search request UX depends on authenticated API session.
- Auto-account login must preserve current storage conventions:
  - persisted token in SharedPreferences
  - existing `LoggedInUserModel` save/load
  - existing `MainController` user hydration

## Data Model Decisions

- Keep support as the communication source of truth.
- Movie request is a dedicated module with status lifecycle and requested titles list.
- Ticket includes movie-request context snapshot fields (`is_movie_request`, `movie_request_payload`) for fast support operations.

## QA Focus Areas

- Existing users can still login normally.
- New device/user can auto-create account and reach home without manual signup.
- Profile completion still upgrades account state to registered.
- No-results search can submit request and creates ticket + ticket record + movie request.
- Support/admin can see and manage movie requests in admin panel.
- Ticket and movie request statuses stay consistent.

## Operational Guidance

- Use support tickets as the primary conversation timeline.
- Use movie request status for request lifecycle tracking.
- Use admin movie-request module for queue management and support follow-up.
- Keep user communication actionable, short, and status-aware.

## Payment Auto-Ticket Rules

- Payment events now auto-generate support tickets from controller payment flows.
- Trigger sources include:
  - `pesapalCallback`
  - `pesapalIpn`
  - `flutterwaveCallback`
  - `flutterwaveWebhook`
  - `getPaymentStatus`
  - `getPending` (for stale pending subscriptions)
- Trigger mapping:
  - Payment success (`status=Active`, `payment_status=Completed`) -> `ticket_type=payment_thanks`, `status=resolved`
  - Payment failed (`status=Failed` or `payment_status=Failed`) -> `ticket_type=payment_fail`, `status=open`
  - Payment pending longer than 15 minutes (`payment_status in Pending/Processing` and `created_at <= now-15m`) -> `ticket_type=billing_issue`, `status=pending`
- Idempotency / dedupe:
  - Each auto event writes a `CustomerTicketRecord` with a deterministic `action_description` signature:
    - `AUTO_PAYMENT_TICKET|subscription={id}|trigger={event}`
  - If the same signature already exists, no duplicate record/ticket is created.
- Each generated record includes payment context for support handling:
  - subscription id, plan, amount/currency, gateway, tracking/reference ids, payment/subscription status.

## Testing Checklist

- [ ] New install -> login screen -> auto-account loader -> home
- [ ] Existing session -> login screen does not overwrite user state
- [ ] Search nonexistent title -> movie request dialog -> submit success
- [ ] Backend creates linked ticket + record + movie request row
- [ ] Admin can view `movie-requests` menu and update status
- [ ] Ticket status maps correctly when movie request status changes
- [ ] No regressions in support chat flows

## Rollout Notes

- Run migrations before deploying API/controller code.
- Verify `admin_menu` entry for `movie-requests` after migration.
- Announce support workflow update to agents (new queue + status semantics).
