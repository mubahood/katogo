<?php

/*
|--------------------------------------------------------------------------
| Movie Ranking / Top-Listing Reset
|--------------------------------------------------------------------------
|
| When set, views_time_count (the driver of Featured / Popular / top
| listings) only counts watch activity ON OR AFTER this date. Historical
| movie_views rows are untouched — resume positions and records survive —
| but the leaderboard starts afresh from this date.
|
| To reset the top listings again in future:
|   1. Set MOVIE_RANKING_RESET_DATE="YYYY-MM-DD HH:MM:SS" in .env
|   2. php artisan config:cache
|   3. Run the one-time recompute (see storage docs or ask the admin panel)
|
| Set to null/empty to rank on all-time watch history (legacy behavior).
|
*/

return [
    'reset_date' => env('MOVIE_RANKING_RESET_DATE', null),
];
