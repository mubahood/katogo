# Swagger Annotation Guide (Laravel)

## Scope
Use this guide when documenting or updating API endpoints in controllers or dedicated files under `app/OpenApi`.

## Required for Every Endpoint
- `@OA\Get`, `@OA\Post`, `@OA\Put`, `@OA\Patch`, or `@OA\Delete`
- `path`, `tags`, and `summary`
- Security declaration for protected endpoints: `security={{"bearerAuth":{}}}`
- Parameters for all path/query inputs
- `@OA\RequestBody` for write endpoints
- Success response + common error responses (`401`, `403`, `404`, `422`, `500` as applicable)
- Realistic request/response examples

## Recommended Pattern
```php
/**
 * @OA\Post(
 *   path="/api/example",
 *   tags={"Account"},
 *   summary="Create example resource",
 *   security={{"bearerAuth":{}}},
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"name"},
 *       @OA\Property(property="name", type="string", example="Sample")
 *     )
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Successful response",
 *     @OA\JsonContent(ref="#/components/schemas/ApiResponse")
 *   ),
 *   @OA\Response(
 *     response=422,
 *     description="Validation error",
 *     @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *   )
 * )
 */
```

## Shared Components
Define shared components once in `app/OpenApi/OpenApiSpec.php`:
- `ApiResponse`
- `ErrorResponse`
- `Pagination`
- Common parameters (`id`, `page`, `per_page`, `lang`)

## Route Inventory Workflow
1. Refresh route inventory:
   - `php artisan route:list --path=api --json > storage/api-docs/route-inventory.json`
2. Regenerate generated endpoint annotations:
   - `php scripts/generate_swagger_annotations.php`
3. Regenerate endpoint inventory markdown:
   - `php scripts/build_swagger_inventory.php`
4. Build docs:
   - `php artisan l5-swagger:generate`
5. Confirm coverage:
   - verify all API operations exist in `storage/api-docs/api-docs.json`

## Tag Conventions
Use these tag names exactly:
- Auth
- Account
- Movies
- Watch History
- Moderation
- Subscription
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

## Internal/Test Endpoints
Tag internal routes as `Diagnostics/Test`. Keep them documented for internal QA. If needed for public docs, filter them in a separate doc profile later.
