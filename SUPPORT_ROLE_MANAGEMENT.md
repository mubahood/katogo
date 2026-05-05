# Support Role Management

This guide explains how role assignment works for support operations.

## Roles

- administrator: full admin and support governance.
- support_team: handles support ticket communication and workflow.
- normal_user: application usage and personal support ticket interactions.

## Admin UI Assignment Flow

Route:
- /admin/support-team

Actions:
- Assign support_team role from the users grid.
- Remove support_team role from the users grid.

Security rule:
- Only administrator users can toggle support_team role assignments.

## Welcome Message Queue (Admin + Support Team)

Location:
- /admin/support-team

How it works:
- When an auto-created account sets a first valid phone number, backend auto-creates a `Welcome message` ticket.
- This appears in `Welcome Message Queue` with:
	- user info
	- phone number
	- one-click WhatsApp link
	- quick link to open ticket detail

Operational expectation:
- Support agent sends WhatsApp welcome message.
- Agent opens ticket and replies/logs action.
- Ticket moves out of queue once contact is recorded.

Implementation:
- app/Admin/Controllers/SupportTeamController.php
- endpoint: POST /admin/support-team/toggle

## Troubleshooting

If role toggle fails with 403:
- Confirm logged-in admin has administrator role slug.
- Confirm admin_roles table contains support_team slug.
- Run support role seeding migration if missing.

If support member cannot reply to ticket:
- Confirm role relation exists in admin_role_users.
- Confirm API auth token belongs to same user id.
