# Support Ticket Models and Workflow

This document defines the start-first support ticket module behavior for Katogo backend and admin.

## Core Models

### CustomerTicket

Purpose: Represents a top-level support thread for one user context.

Key fields:
- user_id: owning user.
- status: open | pending | in_progress | resolved | closed | escalated.
- ticket_type: general | account_opening | payment_thanks | payment_fail | auto_account_issue | subscription_issue | technical_issue | billing_issue | content_issue | movie_request.
- resolution_state: unresolved | resolved | cancelled.
- account_origin: auto_device | manual | google.
- app_type: lugaflix | ugflix | muno_app.
- platform_type: muno | luga | ugflix (and normalized aliases lugaflix / muno_app).
- platform: android | ios | web.
- assigned_to: admin_users.id for assigned support owner.
- rating_of_satisfaction: optional 1-5 value.
- agent_has_contacted_customer: support outreach marker.
- customer_has_responded: customer response marker.
- is_movie_request: boolean flag for ticket entries created from movie requests.
- movie_request_payload: JSON snapshot with requested titles and source context.

### MovieRequest

Purpose: Dedicated lifecycle model for missing-content requests while keeping communication in support tickets.

Key fields:
- user_id: requesting user.
- customer_ticket_id: linked support ticket id.
- status: submitted | reviewing | in_progress | fulfilled | rejected | cancelled.
- request_source: search | support | manual.
- platform_type: lugaflix | luga | ugflix | muno | muno_app.
- app_type: lugaflix | ugflix | muno_app.
- searched_query: original user search query.
- requested_movies: JSON array of requested titles.
- user_message: optional extra customer context.
- support_reply: optional latest support response for request lifecycle.
- support_reply_at: timestamp of support lifecycle reply.
- handled_by: admin/support user handling the request lifecycle.

### CustomerTicketRecord

Purpose: Stores ordered interaction records inside a ticket.

Key fields:
- customer_ticket_id: parent ticket.
- sender_type: user | support_team | system.
- sender_id: sender id when available.
- message: record content.
- action_type:
  - none
  - needs_user_action
  - needs_support_action
  - status_change
  - agent_has_contacted_customer
  - customer_has_responded
  - message_from_customer
  - rating_of_satisfaction
- action_description: optional detail.
- is_internal_note: support/admin-visible only.

## Role Responsibilities

- administrator:
  - Full support oversight.
  - Can assign/remove support_team role.
  - Can assign tickets and update status/resolution.
- support_team:
  - Can reply to tickets.
  - Can update status and ticket workflow data.
- normal_user:
  - Can create own ticket.
  - Can read/reply only to own ticket.

## Ticket Type Guidance

- account_opening: onboarding/registration support.
- payment_thanks: successful payment follow-up.
- payment_fail: failed payment troubleshooting.
- auto_account_issue: issues on auto-created account flow.
- technical_issue: app technical malfunction.
- subscription_issue: plan/state mismatch.
- billing_issue: charges/refunds/payment-method concerns.
- content_issue: content availability/quality requests.
- movie_request: customer asks for one or more missing movies.

Auto-generated welcome workflow:
- When an auto-created user (`account_origin=auto_device`) sets a first valid Uganda phone number, backend auto-creates a ticket:
  - `ticket_type=account_opening`
  - `subject=Welcome message`
  - `status=open`
  - `agent_has_contacted_customer=false`
- A system record is inserted with `action_type=needs_support_action` to prompt support outreach.
- This queue powers dashboard follow-up for WhatsApp welcome messaging.

## Lifecycle and Resolution

Status is operational workflow; resolution_state captures terminal support outcome.

Typical path:
- open -> pending -> in_progress -> resolved -> closed

Alternative path:
- open/in_progress -> escalated
- any non-closed state -> cancelled (resolution_state)

Rules:
- status=resolved defaults resolution_state=resolved when not explicitly set.
- User reply can move pending/resolved ticket back to in_progress.
- Internal notes do not set unread flag for customer.

## API and Admin Integration

API controller:
- app/Http/Controllers/Api/SupportTicketController.php

Admin controller:
- app/Admin/Controllers/SupportTeamController.php

Admin view:
- resources/views/admin/support_ticket_detail.blade.php

Welcome queue in admin:
- /admin/support-team shows `Welcome Message Queue` entries for pending outreach.
- Queue rows include user phone and one-click WhatsApp link (`wa.me/{phone}`).
- Once support replies on the welcome ticket, `agent_has_contacted_customer` flips to true and ticket leaves the queue.

Admin routes:
- app/Admin/routes.php

API routes:
- routes/api.php

Movie request API routes:
- POST `support/movie-requests`
- GET `support/movie-requests`
- GET `support/movie-requests/{id}`
- PATCH `support/movie-requests/{id}/status`

Admin movie request module:
- Route: `/admin/movie-requests`
- Controller: `app/Admin/Controllers/MovieRequestController.php`
- Includes grid filters, ticket deep-link, and lifecycle editing.

## App Login and Auto-Account Flow

Backend endpoints:
- POST `auth/login`
- POST `auth/auto-register`
- POST `auth/complete-profile`

Behavior:
- App startup/login screen attempts silent auto-account creation using a stable per-install device id.
- Backend creates or reuses account idempotently by hashed device id.
- Account markers:
  - `account_origin=auto_device`
  - `account_state=auto_created`
- Session is persisted using the existing token and local user model structure (no custom side-store or incompatible format).
- When profile is completed (`auth/complete-profile`), account is upgraded to `account_state=registered`.

Operational troubleshooting:
- If auto-register fails, login screen remains available for manual login.
- If token/session expires, existing background re-login behavior remains unchanged.

## Movie Request UX From Search

Frontend implementation:
- File: `lugaflix/lib/screens/shop/screens/shop/MoviesSearchScreen.dart`
- In no-results state, user can submit a movie request directly.

Submission flow:
1. User searches and gets no results.
2. User taps `Request this movie`.
3. App collects requested titles and optional message.
4. App calls POST `support/movie-requests`.
5. Backend creates/updates linked `movie_request` ticket and adds a ticket record with title list and message.
6. User is redirected to support module for conversation continuity.

Support communication model:
- Lifecycle updates can be managed from movie requests module.
- Customer communication remains anchored in `CustomerTicketRecord` so support has complete history in one place.

## Support Tickets Admin Listing

Location:
- /admin/support-tickets

Grid updates:
- Added direct customer phone column (`user.phone_number`) for faster outreach.
- Added concise, sortable operational columns: `Platform`, `Type`, `Status`, `Last Reply`, `Created`.
- Hidden by default to reduce clutter:
  - `Assigned To`
  - `Replies`

Inline ticket operations:
- `Status` is inline editable.
- `Type` is inline editable.
- `Resolve` (resolution_state) is inline editable.
- `Customer Replied` is switch-editable.
- `Rating` is inline editable.

Create and edit form:
- Standard Laravel Admin create and edit screens are available for support tickets.
- Admin can create a ticket manually for any existing user by searching user name, email, or id.
- Form fields cover core ticket metadata and workflow context:
  - `User`
  - `Subject`
  - `Status`
  - `Type`
  - `Resolve`
  - `Assigned To`
  - `App`
  - `Platform Type`
  - `Platform`
  - `Account Origin`
  - `Agent Contacted`
  - `Customer Replied`
  - `Unread For User`
  - `Unread For Support`
  - `Rating`
  - `Replies`
  - `Last Reply`
- Save logic keeps rating within 1 to 5 and auto-aligns `resolution_state` when a ticket is marked `resolved` or `closed`.
- Ticket detail view remains available separately at the custom ticket page for conversation history and reply actions.

Advanced filters:
- Ticket id and user id.
- Phone (searches `phone_number`).
- Subject text.
- Status, type, resolution state, platform, account origin.
- Agent contacted and customer replied flags.
- Satisfaction rating.
- Unread support state.
- Date ranges for created time and last reply time.

WhatsApp engagement modal:
- New `Engage` column button opens a modal on the same page.
- Modal auto-selects starter templates based on `ticket_type`.
- Agent can pick a template, edit text, and open WhatsApp via `wa.me/{normalized_number}`.
- Includes targeted templates for:
  - `auto_account_issue`
  - `payment_fail`
  - `account_opening`
  - `subscription_issue`
  - `technical_issue`
  - `billing_issue`
  - `content_issue`
  - `general`

## Testing Checklist

- Create ticket with each ticket_type and verify persistence.
- Update resolution_state through API and admin reply form.
- Post reply with each action_type and verify record creation.
- Verify only administrator can toggle support_team role.
- Verify platform_type normalization for muno/luga/ugflix aliases.
- Verify unread flags and contact/response booleans update correctly.
- Verify first real phone set on auto-created account generates one `Welcome message` ticket.
- Verify welcome queue displays pending users and WhatsApp links correctly.
- Verify missing-title search can submit movie request and auto-create linked ticket record.
- Verify `/admin/movie-requests` list shows submitted requests with ticket links and statuses.
- Verify support status update on movie request syncs ticket status and resolution state.
