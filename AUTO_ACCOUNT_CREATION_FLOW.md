# Auto Account Creation Flow

This document summarizes the backend-safe flow for auto-created accounts and their transition to registered state.

## Flow Summary

1. App opens and no authenticated session exists.
2. Client sends device context to backend auto-account endpoint.
3. Backend creates a unique user with account_state=auto_created and account_origin=auto_device.
4. Backend returns auth/session payload for immediate in-app use.
5. User later completes profile and account is upgraded to registered state.

## Data Safety Rules

- Do not destroy existing user session/local preferences during upgrade.
- Keep user id continuity between auto-created and registered states.
- Maintain traceable account_origin and account_state values.

## Support Operations Relevance

- Tickets opened by auto-created users should default to ticket_type=auto_account_issue when relevant.
- Support agents can identify origin quickly via account_origin and platform_type.

## Troubleshooting

- If duplicate auto accounts appear: verify device identity uniqueness strategy.
- If profile completion fails: verify auth token continuity and account_state update transaction.
- If support cannot classify issue: verify ticket_type defaults and app payload consistency.
