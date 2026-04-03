# Query Builder Audit — V2 API Controllers

**Date**: 2025-06-21  
**Goal**: Replace Eloquent model-based queries in V2 API with `DB::table()` Query Builder equivalents to eliminate model-hydration overhead and fix N+1 patterns. Output must be 100% identical.

---

## Changes Made

### SearchController.php

| Method | Type | Change |
|--------|------|--------|
| `searchAll()` Phase 2 | **N+1 fix** | Replaced per-series `MovieModel::count()` inside loop with single `DB::table('movie_models')->groupBy('category_id')->COUNT(*)` batch fetch |
| `searchAll()` swap loop | **N+1 fix** | Replaced per-category `MovieModel::first()` inside loop with single `DB::table('movie_models')->whereIn()->get()->unique('category_id')` batch fetch |
| `allTrending()` | **N+1 fix** | Replaced per-series `MovieModel::count()` inside loop with single GROUP BY batch fetch |

### MovieController.php

| Method | Type | Change |
|--------|------|--------|
| `index()` | Model→DB::table | `MovieModel::select(LIST_FIELDS)->...->paginate()` → `DB::table('movie_models')->select(LIST_FIELDS)->...->paginate()` |
| `search()` phases 1–4 | Model→DB::table | All `MovieModel::where()->pluck('id')` and `SeriesMovie::where()->pluck('id')` → `DB::table()->select('id')->pluck('id')` |
| `search()` final fetch | Model→DB::table | `MovieModel::select()->whereIn()->get()->keyBy('id')` → `DB::table()->select()->get()->keyBy('id')` |
| `related()` scoring phases | Model→DB::table | All `MovieModel::where()->pluck('id')` → `DB::table()->select('id')->pluck('id')` |
| `related()` final fetch | Model→DB::table | `MovieModel::select()->whereIn()->get()->keyBy('id')` → `DB::table()->select()->get()->keyBy('id')` |

### ManifestController.php

| Method | Type | Change |
|--------|------|--------|
| `buildMovieSections()` closures | Model→DB::table | `$activeMovies = fn() => MovieModel::where([])` → `$activeMovies = fn() => DB::table('movie_models')->where()->where()->where()` |
| `buildMovieSections()` total counts | Model→DB::table | `MovieModel::where([])->count()` → `DB::table()->count()` |
| All `->get(self::SLIM_FIELDS)` | Eloquent syntax fix | Changed to `->select(self::SLIM_FIELDS)->get()` (DB::table doesn't accept columns in `get()`) |
| All `->first(self::SLIM_FIELDS)` | Eloquent syntax fix | Changed to `->select(self::SLIM_FIELDS)->first()` |
| `getFeaturedMovie()` | Model→DB::table | All `MovieModel::where([])` → `DB::table('movie_models')->where()->where()->where()` |
| `pickNextFeaturedMovie()` | Model→DB::table | All `MovieModel::where([])` → `DB::table('movie_models')` |
| `getContinueWatching()` batch load | Model→DB::table | `MovieModel::whereIn()->get()` → `DB::table('movie_models')->select()->whereIn()->get()` |
| Still Active guard | Model→DB::table | `MovieModel::where('id', ...)->exists()` → `DB::table('movie_models')->where()->exists()` |

---

## What Was NOT Changed

| Controller | Method | Reason |
|------------|--------|--------|
| MovieController | `show()` | Uses `->toArray()`, cached count lambdas, model-specific methods |
| MovieController | `episodes()` | Small result set, Eloquent ok |
| MovieController | `seriesIndex()` | Keep as Eloquent (complex join already, `->toArray()` used) |
| MovieController | `playback()` | `MovieView::updateOrCreate()`, `$movie->increment()` — model methods required |
| MovieController | `fix*()` | Admin/diagnostic logic, low traffic, complex model interactions |
| SearchController | `searchSeries()` | Already used DB::table UNION |
| SearchController | `trending()` | Already used DB::table |
| ManifestController | `getContinueWatching()` MovieView query | Needs Carbon `->toIso8601String()` on `updated_at` |
| ManifestController | `getDashboardStats()` | Uses simple count queries; already efficient |
| ManifestController | `checkPendingPayments()` | Uses `SubscriptionTransaction` with model methods (save, check_payment_status) |

---

## Performance Impact Breakdown

### N+1 Fixes (highest impact — O(N) → O(1) queries)

1. **`searchAll()` Phase 2** — Was: 1 COUNT query per matched series. Now: 1 GROUP BY query for ALL series IDs.
   - Example: Search returning 15 series → was 15 COUNT queries, now 1.

2. **`searchAll()` swap loop** — Was: 1 SELECT query per series in `$seenCategories`. Now: 1 batch SELECT for all.
   - Example: 20-item page with 3 series → was 3 queries, now 1.

3. **`allTrending()` episodes loop** — Was: 1 COUNT per popular series shown. Now: 1 GROUP BY for all.
   - Example: 12 popular series → was 12 COUNT queries, now 1.

### Model Hydration Elimination

4. **`index()` paginate** — Was: hydrate 20–50 Eloquent models per page. Now: raw stdClass objects.
   - Saves Eloquent model construction overhead for every list request.

5. **`search()` ID pluck phases** — Was: Eloquent hydration even for `->pluck('id')`. Now: DB::table returns scalar IDs directly.
   - Each search could fire 6–10 pluck queries — all now use DB::table.

6. **`related()` scoring plucks** — Same pattern as search phases.

7. **`buildMovieSections()`** — Was: Eloquent model instantiation for 20+ section queries per cache-miss.
   - Every app open fires a manifest request. Sections are cached 30 min, but cache-misses (every 30 min) now much faster.

8. **`getFeaturedMovie()`** — Replaced 4 `MovieModel::where([])` calls with cleaner DB::table.

9. **`getContinueWatching()`** batch load — DB::table for movie batch fetch.

---

## Safety: Output Compatibility

- `DB::table()->paginate()` returns the same `LengthAwarePaginator` as Eloquent's paginate — `->total()`, `->currentPage()`, `->lastPage()`, `->items()` all identical.
- `->items()` returns stdClass objects instead of Eloquent models, but `cleanUrls()` already handles both via `instanceof Model ? ->toArray() : (array)$item` check.
- `Collection::unique()`, `->keyBy()`, `->pluck()` all work identically on stdClass objects.
- `slimMovie()` accesses properties directly (`$movie->id`), works on both Eloquent and stdClass.

---

## Test Results (Local)

```
GET /api/v2/movies           → 200 ✅  (20 items, pagination correct)
GET /api/v2/search/all?q=action → 200 ✅  (_score, _item_type, series fields present)
GET /api/v2/search/all/trending → 200 ✅
GET /api/v2/manifest         → 200 ✅  (21 sections, genres, VJs, featured)
GET /api/v2/series           → 200 ✅
```
