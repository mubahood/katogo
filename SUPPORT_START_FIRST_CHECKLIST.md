# Support Start-First Checklist

This checklist tracks delivery for start-first support module item 3.

## Ticket Module Delivery

- [x] Extended CustomerTicket taxonomy fields (type, resolution, tracking).
- [x] Extended CustomerTicketRecord action taxonomy.
- [x] Added migration for ticket taxonomy and tracking columns.
- [x] Updated API support ticket controller for taxonomy validation and state updates.
- [x] Updated admin support controller and ticket detail form.
- [x] Confirmed two core ticket controllers remain active and wired in routes.
- [x] Added migration to ensure support menu entries exist.
- [x] Added support role and ticket workflow documentation.
- [x] Added auto-welcome ticket generation when first phone is set on auto-created account.
- [x] Added admin Welcome Message Queue with WhatsApp quick actions.

## Validation

- [x] PHP syntax validation for touched models/controllers/migrations.
- [x] Route listing includes support API and admin routes.
- [x] Database migrations applied for support taxonomy/menu/audit tables.
- [x] Dummy end-to-end command verification passed with rollback-safe data.
- [x] Dummy verification includes welcome-ticket auto-creation and audit checks.

## Remaining Suggested QA

- [x] Run migration in local environment and verify schema changes.
- [ ] Manual admin UI walkthrough: support-team and support-tickets.
- [x] API smoke tests for create/reply/status with new fields (dummy verification command).
