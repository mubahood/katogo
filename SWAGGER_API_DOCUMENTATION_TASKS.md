# Swagger API Documentation Master Tasks (Laravel)

## Objective
Build complete, production-ready OpenAPI/Swagger documentation for all API endpoints in this Laravel project, with request/response examples and clear auth rules.

## Project Scope
- API routes source: `routes/api.php`
- Controllers: `app/Http/Controllers/**`
- Target docs UI: `/api/documentation`
- OpenAPI output: `storage/api-docs/api-docs.json`

## Success Criteria
- Every API route in `routes/api.php` is documented.
- Each endpoint has: summary, tags, auth requirement, parameters, request body (if any), success response, error responses.
- Shared schemas/components are reused.
- Examples are present for request + response.
- Documentation generation is automated and repeatable.

---

## Phase 1: Install and Configure Swagger
- [x] Install package `darkaonline/l5-swagger`.
- [x] Publish package config.
- [x] Configure doc paths and generation settings in `config/l5-swagger.php`.
- [x] Set API title, version, server URLs, and security schemes.
- [x] Generate first docs output and verify `/api/documentation` loads.

### Commands
```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
php artisan l5-swagger:generate
```

---

## Phase 2: Build Endpoint Inventory (Single Source of Truth)
- [x] Export full route list for APIs.
- [x] Save route inventory snapshot to `storage/api-docs/route-inventory.json`.
- [x] Normalize aliases/duplicate routes.
- [x] Mark route auth type: `public`, `jwt`, `admin`, `internal/test`.
- [x] Group endpoints by domain/tag.
- [x] Create endpoint tracker table in this file.

### Route inventory command
```bash
php artisan route:list --path=api --json
```

### Domain tags to use
- Auth
- Account
- Movies
- Watchlist
- Wishlist
- Watch History
- Moderation
- Subscription
- Payments
- V2 Manifest
- V2 Movies
- V2 Search
- V2 Blog
- V2 Streaming
- V2 Downloads
- V2 SafeMode
- V2 Trivia
- V2 Game Stats
- Diagnostics/Test

---

## Phase 3: Define Global OpenAPI Components
- [x] Add global `@OA\Info`, `@OA\Server`, and `@OA\Tag` definitions.
- [x] Define JWT bearer auth scheme.
- [x] Define standard response envelope schema (code/status/message/data).
- [x] Define shared error schema.
- [x] Define pagination schema.
- [x] Define reusable common parameters (`id`, `page`, `per_page`, `lang`, etc.).

### Example: JWT Security Scheme
```php
/**
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="JWT"
 * )
 */
```

---

## Phase 4: Document Endpoints Domain by Domain

## 4.1 Auth Endpoints
- [x] POST `/api/auth/register`
- [x] POST `/api/auth/login`
- [x] POST `/api/auth/google`
- [x] POST `/api/auth/password-reset`
- [x] POST `/api/auth/request-password-reset-code`

## 4.2 Subscription and Payment Endpoints
- [x] GET `/api/subscription-plans`
- [x] GET `/api/subscriptions/payment-gateways`
- [x] GET/POST `/api/subscriptions/pesapal/callback`
- [x] POST `/api/subscriptions/pesapal/ipn`
- [x] GET `/api/subscriptions/flutterwave/callback`
- [x] POST `/api/subscriptions/flutterwave/webhook`
- [x] GET `/api/subscriptions/payment-status/{trackingId}`
- [x] Authenticated subscription routes under JWT middleware

## 4.3 Account / Social / Moderation Endpoints
- [x] Account dashboard/watchlist/watch-history/likes/wishlist endpoints
- [x] Chat endpoints
- [x] Moderation report/block/legal consent endpoints

## 4.4 Core Movie Endpoints
- [x] GET `/api/random-movie`
- [x] GET `/api/movies`
- [x] GET `/api/movies/{id}`
- [x] GET `/api/movie/{id}`
- [x] Video progress endpoints

## 4.5 V2 Endpoints
- [x] `/api/v2/manifest`
- [x] `/api/v2/movies*`
- [x] `/api/v2/search*`
- [x] `/api/v2/blog*`
- [x] `/api/v2/streaming*`
- [x] `/api/v2/downloads*`
- [x] `/api/v2/safemode*`
- [x] `/api/v2/subscriptions*` (payment fix)
- [x] `/api/v2/trivia*`
- [x] `/api/v2/game-stats*`

## 4.6 Test/Internal Routes
- [x] Free trial test endpoints (`/api/test-*`)
- [x] One-time migration endpoint (`/api/run-migration`)
- [x] Decide whether to hide internal routes in production docs.

---

## Phase 5: Add Request/Response Examples for Every Endpoint
- [x] Add at least 1 success example per endpoint.
- [x] Add common error examples (401, 403, 404, 422, 429, 500 where applicable).
- [x] Add realistic payloads that match actual controller validation.
- [x] Ensure examples follow current response envelope.

### Example Endpoint Annotation Template
```php
/**
 * @OA\Post(
 *   path="/api/auth/login",
 *   tags={"Auth"},
 *   summary="Authenticate user and return JWT token",
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"email","password"},
 *       @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *       @OA\Property(property="password", type="string", format="password", example="secret123")
 *     )
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Login successful",
 *     @OA\JsonContent(
 *       @OA\Property(property="code", type="integer", example=1),
 *       @OA\Property(property="status", type="integer", example=200),
 *       @OA\Property(property="message", type="string", example="Login successful"),
 *       @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1Qi...")
 *       )
 *     )
 *   ),
 *   @OA\Response(response=422, description="Validation error")
 * )
 */
```

---

## Phase 6: Validation and Quality Gates
- [x] Regenerate docs and fix all warnings/errors.
- [x] Confirm each route appears exactly once unless aliases are intentional.
- [x] Verify protected endpoints show `bearerAuth` security.
- [x] Verify schemas resolve with no unresolved `$ref`.
- [x] Validate at least one documented endpoint exists per tag (Swagger spec smoke-check).

### Commands
```bash
php artisan l5-swagger:generate
php artisan route:list --path=api
```

---

## Phase 7: CI/CD and Team Workflow
- [x] Add documentation generation command to deployment/checklist.
- [x] Add PR checklist item: "OpenAPI updated for API changes".
- [x] Add internal guide for writing Swagger annotations in controllers.
- [x] Add strategy for versioning docs (`v1`, `v2`, future `v3`).

---

## Endpoint Tracking Table

The full normalized tracker (150 operations) is maintained in:
- `docs/swagger/ENDPOINT_INVENTORY.md`

| # | Method | Path | Controller@Action | Auth | Tag | Status | Example Added |
|---|--------|------|-------------------|------|-----|--------|---------------|
| 1 | POST | /api/auth/login | ApiController@login | public | Auth | Done | Yes |
| 2 | GET | /api/subscription-plans | SubscriptionApiController@listPlans | public | Subscription | Done | Yes |
| 3 | GET | /api/v2/movies | Api\\V2\\MovieController@index | jwt | V2 Movies | Done | Yes |

Status values:
- Pending
- In Progress
- Done
- Blocked

---

## Implementation Notes
- Prefer reusable schemas to avoid duplicate definitions.
- Keep aliases documented but clearly labeled as backward compatibility.
- Internal/test endpoints should be tagged `Diagnostics/Test` and can be conditionally hidden.
- For each controller update, regenerate docs immediately to catch annotation errors early.

## Daily Progress Log
- [x] Day 1: package setup + global schemas + Auth endpoints
- [x] Day 2: Subscription/Payments + Account + Movies
- [x] Day 3: V2 endpoints + validation + CI integration

---

## Final Completion Summary

**Status: COMPLETE**  
**Completed: 2026-04-27**

### Deliverables
| Artifact | Location |
|---|---|
| OpenAPI spec | `storage/api-docs/api-docs.json` |
| Route inventory (JSON) | `storage/api-docs/route-inventory.json` |
| Normalized endpoint tracker | `docs/swagger/ENDPOINT_INVENTORY.md` |
| Swagger annotation guide | `docs/swagger/ANNOTATION_GUIDE.md` |
| Versioning strategy | `docs/swagger/VERSIONING_STRATEGY.md` |
| PR checklist | `.github/pull_request_template.md` |
| Bulk annotation file | `app/OpenApi/GeneratedApiEndpoints.php` |
| Shared components | `app/OpenApi/OpenApiSpec.php` |
| Auth endpoint examples | `app/Http/Controllers/ApiController.php` |
| Automation scripts | `scripts/generate_swagger_annotations.php`, `scripts/build_swagger_inventory.php` |
| Composer shortcuts | `composer swagger:generate`, `composer swagger:routes`, `composer swagger:annotate` |

### Validation Results
- Total operations documented: **150**
- Missing from spec: **0**
- Tags covered: **16** (Auth, Account, Movies, Watch History, Moderation, Subscription, V2 Manifest, V2 Movies, V2 Search, V2 Blog, V2 Streaming, V2 Downloads, V2 SafeMode, V2 Trivia, V2 Game Stats, Diagnostics/Test)
- Shared schemas: `ApiResponse`, `ErrorResponse`, `Pagination`
- Security: `bearerAuth` JWT scheme applied
- Generation: error-free (`php artisan l5-swagger:generate`)

### Ongoing Maintenance
Run this command after any route or controller change:
```bash
composer swagger:generate
```
