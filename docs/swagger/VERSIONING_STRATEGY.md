# OpenAPI Versioning Strategy

## Goals
- Keep API documentation aligned with route behavior.
- Support current v1/v2 APIs and future v3 without breaking client integrations.
- Allow internal and public documentation views when needed.

## Version Model
- v1 routes: paths under `/api/...` without `/v2/` prefix.
- v2 routes: paths under `/api/v2/...`.
- v3 routes (future): paths under `/api/v3/...`.

## Documentation Policy
- One generated OpenAPI artifact per release branch: `storage/api-docs/api-docs.json`.
- Keep stable tag naming across versions where semantics are unchanged.
- Use version-specific tags only when endpoint behavior materially differs.

## Change Rules
- Additive changes (new endpoint, new optional field): update docs in same PR.
- Breaking changes: either
  - introduce new versioned route (`/api/v3/...`), or
  - deprecate old operation with `deprecated=true` and migration note.
- Do not silently repurpose existing fields without updating examples and schema notes.

## Suggested Release Workflow
1. Update route annotations and shared schemas.
2. Run `php artisan l5-swagger:generate`.
3. Diff `storage/api-docs/api-docs.json` in PR.
4. Validate major client flows with Swagger UI.
5. Ship with changelog entry referencing API docs delta.

## Future Split Option
If public and internal docs must diverge:
- Maintain two L5-Swagger documentation profiles.
- Profile A: public endpoints only.
- Profile B: internal/test endpoints included.
- Keep both generated in CI to prevent drift.
