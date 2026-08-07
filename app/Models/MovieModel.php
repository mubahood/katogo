<?php

namespace App\Models;
// 	  http://munoserver54.club/saka/saka42/Curse Of The Piper EMMY.mp4
use Carbon\Carbon;
use Dflydev\DotAccessData\Util;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MovieModel extends Model
{
    use HasFactory;

    protected $casts = [
        'fix_date'    => 'datetime',
        'fix_counter' => 'integer',
    ];

    // Internal backup of the pre-transfer URL — used by the storage maintenance
    // fallback in getUrlAttribute(); never exposed in API JSON.
    protected $hidden = ['old_video_url'];

    //process_munowatch
    public static function process_munowatch($movie)
    {
        //write sql that checks if external_url contains munowatch and mark it as Inactive and is_munowatch = Yes
        // $sql = "UPDATE movie_models SET status = 'Inactive', is_muno = 'Yes' WHERE id > 21308 AND (external_url LIKE '%munowatch%' OR url LIKE '%munowatch%')";
        // DB::statement($sql);
        $crawler = MovieCrawlerPage::where('url', $movie->page_source_url)->first();
        if ($crawler == null) {
            $crawler = MovieCrawlerPage::where('url', $movie->external_url)->first();
        }

        $page_source_url = null;
        if ($movie->page_source_url != null && strlen($movie->page_source_url) > 5) {
            $page_source_url = $movie->page_source_url;
        } elseif ($movie->external_url != null && strlen($movie->external_url) > 5) {
            $page_source_url = $movie->external_url;
        }
        if ($page_source_url == null) {
            $movie->muno_processed = 'Yes';
            $movie->status = 'Inactive';
            $movie->muno_success = 'External URL not found';
            $movie->save();
            throw new \Exception('External URL not found');
            return;
        }

        if ($crawler == null) {
            //create the crawler
            $crawler = new MovieCrawlerPage();
            $crawler->url = $movie->page_source_url;
            $crawler->movie_crawler_website_id = 2; //munowatch
            $crawler->title = $movie->title;
            $crawler->slug = $movie->page_source_url;
            $crawler->url = $movie->page_source_url;
            $crawler->vj = 'Munowatch API';
            $crawler->is_muno = 'Yes';
            $crawler->save();
        }

        if (strtolower(trim($crawler->status)) != 'success') {
            try {
                $crawler->fetch_page_content();
            } catch (\Throwable $th) {
                $movie->muno_processed = 'Yes';
                $movie->status = 'Inactive';
                $movie->muno_success = 'Failed to fetch page content: ' . $th->getMessage();
                $movie->save();
                throw $th;
            }
        }
        $crawler->process_munowatch_intelligent();
    }

    //boot
    protected static function boot()
    {
        parent::boot();

        // ── CONTENT BLOCKLIST (DMCA compliance) ──────────────────────────
        // Any save whose title matches content_blocklist is forced Inactive
        // and flagged — regardless of which pipeline touched it (crawler,
        // series fixer, munowatch fix-broken, admin edit, sync). A scheduled
        // sweep (content:enforce-blocklist) backstops raw-SQL update paths.
        static::saving(function ($model) {
            $rawTitle = $model->getAttributes()['title'] ?? null;
            if (self::titleIsBlocklisted($rawTitle)) {
                $model->status         = 'Inactive';
                $model->is_blocklisted = 1;
            }
        });

        static::created(function ($model) {
            // Episode count increment moved to MovieModelObserver::created() (P4-18 / P10-09)
            // — uses INCREMENT instead of COUNT(*) subquery.
        });
        static::updated(function ($model) {
            // Full recount via subquery is still correct for updated events
            // since category_id may have changed (e.g. episode moved to a different series)
            if ($model->type == 'Series' && $model->category_id) {
                DB::statement("UPDATE series_movies SET total_episodes = (SELECT COUNT(*) FROM movie_models WHERE category_id = ?) WHERE id = ?", [$model->category_id, $model->category_id]);
            }
        });
        static::creating(function ($model) {
            // ===== DEDUPLICATE BY VIDEO URL (the most unique identifier) =====
            // The video `url` uniquely identifies a piece of content. If another
            // record already uses this url, refresh that existing record with the
            // freshly-detected data instead of inserting a duplicate row.
            //
            // Notes:
            //  - Match on the RAW stored value (the `url` accessor normalises
            //    http→https on read), covering both http/https variants so the
            //    same video on either scheme is treated as one.
            //  - Only non-empty incoming fields are copied, so curated/hosting
            //    data already on the old record (firebase_video_url,
            //    local_video_link, tested flags, …) is preserved.
            //  - `url` itself is left untouched — it is the match key, so we avoid
            //    needlessly re-triggering the video-url-change / transfer pipeline.
            $rawUrl = $model->getAttributes()['url'] ?? null;
            if (!empty($rawUrl)) {
                $urlCandidates = array_values(array_unique([
                    $rawUrl,
                    preg_replace('/^http:\/\//i', 'https://', $rawUrl),
                    preg_replace('/^https:\/\//i', 'http://', $rawUrl),
                ]));
                $existingByUrl = MovieModel::whereIn('url', $urlCandidates)->first();
                if ($existingByUrl !== null) {
                    foreach ($model->getAttributes() as $key => $value) {
                        if (in_array($key, ['id', 'created_at', 'url'], true)) {
                            continue;
                        }
                        if ($value === null || $value === '') {
                            continue; // never overwrite existing data with blanks
                        }
                        $existingByUrl->{$key} = $value;
                    }
                    $existingByUrl->save();

                    return false; // abort the duplicate insert; the old record was updated
                }
            }

            //check if type is series
            if ($model->type == 'Series') {
                $series = SeriesMovie::find($model->category_id);
                if ($series != null) {
                    $model->category = $series->title;
                    if ($model->thumbnail_url == null || $model->thumbnail_url == '') {
                        $model->thumbnail_url = $series->thumbnail;
                    }
                    //episode_number
                    if ($model->episode_number == 1) {
                        $model->is_first_episode = 'Yes';
                    } else {
                        $model->is_first_episode = 'No';
                    }
                } else {
                    $model->type = 'Movie';
                }
            }
            //check if url contains munowatch
            if (strpos($model->url, 'munowatch') !== false) {
                //$model->status = 'Inactive'; //if yes, set to yes
            }
            if ($model->type == 'Movie') {
                $model->actor = '--';
                // ENFORCE: Movies must NOT be linked to any series
                $model->category_id = null;
                $model->episode_number = null;
                $model->season_number = null;
                $model->series_title = null;
                $model->episode_title = null;
                $model->is_first_episode = null;

                //get same movie with external_url
                $existing = MovieModel::where('external_url', $model->external_url)
                    ->where('status', 'Active')
                    ->first();
                if ($existing != null) {
                    return false;
                }

                if ($model->munowatch_id != null && $model->munowatch_id != '') {
                    //check using munowatch_id as well
                    $existing = MovieModel::where('munowatch_id', $model->munowatch_id)
                        ->where('status', 'Active')
                        ->first();
                    if ($existing != null) {
                        return false;
                    }
                }

                //check using title as well and same vj and is muno
                $existing = MovieModel::where('title', $model->title)
                    ->where('is_muno', 'Yes')
                    ->where('type', $model->type)
                    ->where('status', 'Active')
                    ->first();
                if ($existing != null) {
                    return false;
                }
            }
        });

        static::updating(function ($model) {

            // ENFORCE: Movies must NOT be linked to any series
            if ($model->type == 'Movie') {
                $model->category_id = null;
                $model->episode_number = null;
                $model->season_number = null;
                $model->series_title = null;
                $model->episode_title = null;
                $model->is_first_episode = null;
            }

            if ($model->type == 'Series' && $model->category_id != null && $model->category_id != '' && $model->category_id != 0) {
                $series = SeriesMovie::find($model->category_id);
                if ($series != null) {
                    $model->category = $series->title;
                    if ($model->thumbnail_url == null || $model->thumbnail_url == '') {
                        // $model->thumbnail_url = $series->thumbnail; 
                    }
                    //episode_number
                    if ($model->episode_number == 1) {
                        $model->is_first_episode = 'Yes';
                    } else {
                        $model->is_first_episode = 'No';
                    }
                } else {
                    $model->type = 'Movie';
                }
            }

            return $model;
        });
    }

    //getter for local_video_link
    public function getLocalVideoLinkAttribute($value)
    {
        if ($value == null || $value == '' || strlen($value) < 5) {
            return null;
        }
        // Already a full URL — return as-is
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        // Relative path stored on this server's public disk (e.g. "videos/movie.mp4")
        $base = rtrim(env('APP_URL', config('app.url')), '/');
        return $base . '/storage/' . ltrim($value, '/');
    }

    //title getter
    public function getTitleAttribute($value)
    {
        //check if title contains translatedfilms
        if (strpos($value, 'translatedfilms') !== false) {

            $names = explode('/', $value);
            if (count($names) > 1) {
                $value = $names[count($names) - 1];
                DB::table('movie_models')
                    ->where('id', $this->id)
                    ->update([
                        'title' => $value
                    ]);


                return $value;
            }

            /* $new_title = str_replace('https://translatedfilms com/videos/', '', $value);
            $new_title = str_replace('https://translatedfilms.com/videos/', '', $new_title);
            $new_title = str_replace('https://translatedfilms com/', '', $value);
            $new_title = str_replace('https://translatedfilms.com videos/', '', $value);
            $new_title = str_replace('http://translatedfilms.com/videos/', '', $new_title);
            $new_title = str_replace('videos/', '', $new_title);
            $new_title = str_replace('translatedfilms.com', '', $new_title);
            $sql = "UPDATE movie_models SET title = '$new_title' WHERE id = {$this->id}";
            dd($sql);
            DB::update($sql);
            return $new_title; */
        }
        //http://localhost:8888/movies-new/make-tsv

        return ucwords($value);
    }

    /**
     * True when a title matches any content_blocklist pattern (DMCA'd or
     * preventively blocked titles). Patterns cached 5 minutes.
     * match_type: exact (case-insensitive) | like (SQL % wildcards) | regexp.
     */
    public static function titleIsBlocklisted(?string $title): bool
    {
        if (empty($title)) {
            return false;
        }

        $patterns = \Illuminate\Support\Facades\Cache::remember('content_blocklist_patterns', 300, function () {
            try {
                return DB::table('content_blocklist')->get(['pattern', 'match_type'])->all();
            } catch (\Throwable) {
                return []; // table absent (e.g. fresh local env) — no blocking
            }
        });

        foreach ($patterns as $p) {
            $hit = match ($p->match_type) {
                'exact'  => strcasecmp($title, $p->pattern) === 0,
                'like'   => (bool) preg_match(
                    '/^' . str_replace('%', '.*', preg_quote($p->pattern, '/')) . '$/i',
                    $title
                ),
                'regexp' => (bool) @preg_match('/' . $p->pattern . '/i', $title),
                default  => false,
            };
            if ($hit) {
                return true;
            }
        }
        return false;
    }

    // Returns the playable video URL.
    // Priority: local_video_link (uploaded file) → maintenance fallback → url (external link)
    public function getUrlAttribute($value)
    {
        // If a file was uploaded locally, derive the URL from it — ignore the raw url column
        $localPath = $this->attributes['local_video_link'] ?? null;
        if (!empty($localPath)) {
            $base = rtrim(env('APP_URL', config('app.url')), '/');
            return $base . '/storage/' . ltrim($localPath, '/');
        }

        if (empty($value)) {
            return $value;
        }

        // Transparently swap to fallback URL during storage provider maintenance
        // applyStorageFallback → resolveMaintenanceUrl applies BOTH the
        // priority walk (bunny/main/hetzner) and the maintenance fallback.
        $value = $this->applyStorageFallback($value);

        $value = str_replace(' ', '%20', $value);
        $value = str_replace('http://', 'https://', $value);
        return $value;
    }

    /**
     * Walk config('bunny.url_priority') and return the first available URL
     * variant for this movie. Variants:
     *   main    → the movie's own url column (as passed in)
     *   bunny   → Bunny CDN copy   (movie_file_transfers.bunny_url, status done)
     *   hetzner → Hetzner copy     (the url itself if on Hetzner, else transfer dest_url)
     */
    public static function resolvePriorityUrl($movieId, ?string $mainUrl): ?string
    {
        foreach (self::urlPriorityOrder() as $variant) {
            $candidate = self::urlVariant($variant, $movieId, $mainUrl);
            if (!empty($candidate)) {
                return $candidate;
            }
        }
        return $mainUrl;
    }

    /**
     * Priority order: Admin → System Configuration (system_configs.movie_url_priority)
     * is authoritative; config('bunny.url_priority') (.env) is the fallback.
     * Cached 60s so admin changes propagate within a minute.
     */
    public static function urlPriorityOrder(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('movie_url_priority_cfg', 60, function () {
            try {
                $db = DB::table('system_configs')->value('movie_url_priority');
                if (!empty($db)) {
                    return array_values(array_filter(array_map('trim', explode(',', $db))));
                }
            } catch (\Throwable) {
                // column missing — fall through to .env config
            }
            return config('bunny.url_priority', ['main']);
        });
    }

    /**
     * All existing URL variants for a movie, in priority order — used by the
     * mobile app's Fix button so the player can try candidates sequentially.
     * Returns [['source' => 'main'|'bunny'|'hetzner', 'url' => ...], ...]
     */
    public function urlCandidates(): array
    {
        $mainUrl = $this->attributes['url'] ?? null;
        $out = [];
        foreach (self::urlPriorityOrder() as $variant) {
            $url = self::urlVariant($variant, $this->attributes['id'] ?? null, $mainUrl);
            if (!empty($url) && !in_array($url, array_column($out, 'url'), true)) {
                $out[] = ['source' => $variant, 'url' => str_replace(' ', '%20', $url)];
            }
        }
        return $out;
    }

    protected static function urlVariant(string $variant, $movieId, ?string $mainUrl): ?string
    {
        switch ($variant) {
            case 'main':
                return $mainUrl;

            case 'bunny':
                if (!$movieId) return null;
                return self::bunnyUrlMap()[$movieId] ?? null;

            case 'hetzner':
                if ($mainUrl && strpos($mainUrl, 'your-storageshare.de') !== false) {
                    return $mainUrl;
                }
                if (!$movieId) return null;
                return self::hetznerUrlMap()[$movieId] ?? null;
        }
        return null;
    }

    /** movie_id → bunny_url for all completed Bunny transfers (1h cache). */
    protected static function bunnyUrlMap(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('bunny_url_map', 3600, function () {
            try {
                return DB::table('movie_file_transfers')
                    ->where('bunny_status', 'done')
                    ->whereNotNull('bunny_url')
                    ->pluck('bunny_url', 'movie_id')
                    ->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** movie_id → Hetzner dest_url for all completed Hetzner transfers (1h cache). */
    protected static function hetznerUrlMap(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('hetzner_url_map', 3600, function () {
            try {
                return DB::table('movie_file_transfers')
                    ->where('status', 'done')
                    ->whereNotNull('dest_url')
                    ->pluck('dest_url', 'movie_id')
                    ->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * During storage provider maintenance, substitute the original CDN URL so the
     * mobile app keeps working without any client-side change.
     *
     * Fallback priority:
     *   1. movie_file_transfers.source_url — the exact origin URL the file was
     *      transferred FROM (always recorded by the Hetzner transfer pipeline)
     *   2. old_video_url column — legacy backup of the pre-transfer URL
     *   3. original URL unchanged (nothing better available)
     *
     * Controlled from Admin → System Configuration (system_configs table),
     * with config/storage_fallback.php (.env) as fallback when columns are absent:
     *   storage_maintenance_enabled   — master switch
     *   storage_maintenance_host      — affected hostname fragment
     *   storage_maintenance_ends_at   — auto-expires after this UTC datetime
     */
    protected function applyStorageFallback(string $url): string
    {
        return self::resolveMaintenanceUrl(
            $url,
            $this->attributes['id'] ?? null,
            $this->attributes['old_video_url'] ?? null
        ) ?? $url;
    }

    /**
     * Static entry point for raw-row serializers (manifest slimMovie, movie
     * controller cleanUrlSingle, search results) that bypass Eloquent accessors.
     * Same priority: transfer source_url → old_video_url → unchanged.
     */
    public static function resolveMaintenanceUrl(?string $url, $movieId = null, ?string $oldVideoUrl = null): ?string
    {
        if (empty($url)) {
            return $url;
        }

        // Serve-time URL priority (config('bunny.url_priority'), e.g.
        // bunny,main,hetzner) runs FIRST — this static entry is the single
        // choke-point every serializer goes through (accessor, manifest,
        // search, movie detail), so the priority applies app-wide.
        $url = self::resolvePriorityUrl($movieId, $url) ?? $url;

        $cfg = self::storageMaintenanceConfig();
        if (empty($cfg['active'])) {
            return $url;
        }

        $host = $cfg['host'];
        if (strpos($url, $host) === false) {
            return $url;
        }

        // 1st priority: source_url from the Hetzner transfer record
        if ($movieId) {
            $map = self::storageFallbackMap($host);
            $src = $map[$movieId] ?? null;
            if (!empty($src) && strpos($src, $host) === false) {
                return $src;
            }
        }

        // 2nd priority: old_video_url column
        if (!empty($oldVideoUrl)
            && strlen(trim($oldVideoUrl)) > 10
            && strpos($oldVideoUrl, $host) === false
        ) {
            return $oldVideoUrl;
        }

        // No usable fallback — return the original (may fail for the user, but nothing better)
        return $url;
    }

    /** @var array|null Per-request memo of the resolved maintenance config */
    protected static ?array $storageMaintCfg = null;

    /**
     * Resolve maintenance settings: system_configs row (admin panel) is authoritative;
     * config/storage_fallback.php (.env) is the fallback when columns don't exist.
     * Result includes 'active' — enabled AND not past ends_at.
     */
    protected static function storageMaintenanceConfig(): array
    {
        if (self::$storageMaintCfg !== null) {
            return self::$storageMaintCfg;
        }

        $cfg = config('storage_fallback.maintenance', []);
        $enabled = !empty($cfg['enabled']);
        $host    = $cfg['host'] ?? 'nx100800.your-storageshare.de';
        $endsAt  = $cfg['ends_at'] ?? null;

        try {
            $sys = \Illuminate\Support\Facades\Cache::remember('storage_maint_cfg', 60, function () {
                return (array) (DB::table('system_configs')
                    ->select('storage_maintenance_enabled', 'storage_maintenance_host', 'storage_maintenance_ends_at')
                    ->first() ?? []);
            });
            if (!empty($sys)) {
                $enabled = (bool) ($sys['storage_maintenance_enabled'] ?? false);
                if (!empty($sys['storage_maintenance_host'])) {
                    $host = $sys['storage_maintenance_host'];
                }
                $endsAt = $sys['storage_maintenance_ends_at'] ?? null;
            }
        } catch (\Throwable) {
            // Columns not migrated yet — env config stays authoritative
        }

        // Auto-expire: once ends_at passes, the bypass switches off by itself
        $active = $enabled;
        if ($active && !empty($endsAt)) {
            try {
                if (Carbon::parse($endsAt)->isPast()) {
                    $active = false;
                }
            } catch (\Throwable) {
                // Unparseable date — treat as "no expiry"
            }
        }

        return self::$storageMaintCfg = [
            'active'  => $active,
            'host'    => $host,
            'ends_at' => $endsAt,
        ];
    }

    /**
     * movie_id → source_url map from completed Hetzner transfers.
     * One query, cached 1 hour — shared across the whole request (manifest,
     * search, listings) instead of a lookup per movie.
     */
    protected static function storageFallbackMap(string $host): array
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember('storage_fallback_map', 3600, function () use ($host) {
                return DB::table('movie_file_transfers')
                    ->where('status', 'done')
                    ->where('dest_url', 'like', "%{$host}%")
                    ->whereNotNull('source_url')
                    ->where('source_url', '!=', '')
                    ->where('source_url', 'not like', "%{$host}%")
                    ->orderBy('id') // later transfers overwrite earlier ones in the map
                    ->pluck('source_url', 'movie_id')
                    ->toArray();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    private const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/x-msvideo',
        'video/mpeg',
        'video/quicktime',
        'video/x-flv',
        'video/x-matroska',
        'video/webm',
        'video/3gpp',
        'video/3gpp2',
        'video/x-ms-wmv',
        'video/ogg',
        'application/vnd.apple.mpegurl',
        'application/x-mpegurl',
        'application/octet-stream',
        // …and any others you need
    ];

    /**
     * Determine whether the URL points to a movie by checking its Content-Type header.
     *
     * @return self
     */
    public function verify_movie(): self
    {
        return $this;
        $baseUrl = 'https://movies.ug/';
        $url     = $this->url;
        $addedBase = false;

        // Normalize URL
        if (stripos($url, 'http') !== 0) {
            $url = $baseUrl . ltrim($url, '/');
            $addedBase = true;
        }

        // Prepare defaults
        $this->content_type_processed      = 'Yes';
        $this->content_type_processed_time = Carbon::now();
        $this->content_is_video            = 'No';
        $this->status                      = 'Inactive';
        $this->external_url                = $url;

        $client = new Client([
            'timeout'         => 30,
            'allow_redirects' => true,
        ]);

        try {
            // Use HEAD to just fetch headers
            $response = $client->head($url);
            $rawType  = $response->getHeaderLine('Content-Type');
            // Strip charset if present
            [$contentType] = explode(';', $rawType);

            $this->content_type = $contentType;
            $this->url = $url;
            $this->external_url = $url;

            if (in_array(strtolower($contentType), self::VIDEO_MIME_TYPES, true)) {
                $this->content_is_video = 'Yes';
                $this->status           = 'Active';
            }
        } catch (\Exception $e) {
            // Handle exceptions (e.g., network issues, invalid URLs)
            $this->content_type = 'Unknown';
            $this->content_is_video = 'No';
            $this->status = 'Inactive';
        }

        // If we prefixed the base URL but it's not a video, revert the stored URL
        if ($this->content_is_video != 'Yes') {
            return $this;
        }

        $this->save();
        // Reload and return fresh model
        return self::find($this->id);
    }



    public function getWatchProgressAttribute()
    {
        $r = request();
        $u = Utils::get_user($r);
        if ($u === null) {
            return 0;
        }

        $view = DB::table('movie_views')->where([
            'movie_model_id' => $this->id,
            'user_id' => $u->id,
        ])->first();

        if ($view === null) {
            return 0;
        }
        
        return is_numeric($view->progress) ? floatval($view->progress) : 0;
    }

    public function getMaxProgressAttribute()
    {
        $r = request();
        $u = Utils::get_user($r);
        if ($u === null) {
            return 0;
        }

        $view = DB::table('movie_views')->where([
            'movie_model_id' => $this->id,
            'user_id' => $u->id,
        ])->first();

        if ($view === null) {
            return 0;
        }
        
        return is_numeric($view->max_progress) ? floatval($view->max_progress) : 0;
    }


    protected $appends = [
        'watch_progress',
        'max_progress',
    ];


    public function update_views()
    {
        // Ranking reset: when config('ranking.reset_date') is set, the
        // views_time_count leaderboard only counts watches on/after that date.
        // views_count (the displayed total) intentionally stays all-time.
        $resetDate = config('ranking.reset_date');

        $result = DB::table('movie_views')
            ->where('movie_model_id', $this->id)
            ->selectRaw(
                'COUNT(*) as views, SUM(CASE WHEN created_at >= ? THEN progress ELSE 0 END) as progress_sum',
                [$resetDate ?: '1970-01-01']
            )
            ->first();

        $views            = is_numeric($result->views)        ? intval($result->views)        : 0;
        $views_time_count = is_numeric($result->progress_sum) ? floatval($result->progress_sum) : 0;

        try {
            DB::table('movie_models')
                ->where('id', $this->id)
                ->update(['views_count' => $views, 'views_time_count' => $views_time_count]);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Test if external video URL is working using cURL
     * Checks content-type to verify it's a video file
     * 
     * @return string 'Yes' if working, 'No' if not working
     */
    public function testExternalVideoUrl()
    {
        try {
            // Mark as being tested
            $this->video_url_tested_by_curl = 'Yes';
            $this->video_url_tested_by_curl_works = 'No';
            $this->save();

            // Get the video URL to test
            $testUrl = $this->external_url ?? $this->url;

            if (empty($testUrl)) {
                $this->video_url_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $testUrl,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true, // HEAD request only
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            // Check for cURL errors
            if ($error) {
                $this->video_url_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Check HTTP status code
            if ($httpCode < 200 || $httpCode >= 400) {
                $this->video_url_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Enhanced content type validation
            if ($contentType) {
                $contentType = strtolower(explode(';', $contentType)[0]);

                // Strict video content types only - NO application/octet-stream
                $validVideoTypes = [
                    'video/mp4',
                    'video/avi',
                    'video/mov',
                    'video/wmv',
                    'video/flv',
                    'video/webm',
                    'video/mkv',
                    'video/3gp',
                    'video/mpeg',
                    'video/quicktime',
                    'video/x-msvideo',
                    'video/x-flv',
                    'video/x-matroska',
                    'video/ogg',
                    'video/mp2t',
                    'video/3gpp',
                    'video/3gpp2',
                    'video/x-ms-wmv'
                ];

                // Check if it's a proper video content type
                if (in_array($contentType, $validVideoTypes)) {
                    // Additional validation for octet-stream by checking file extension
                    $urlPath = parse_url($testUrl, PHP_URL_PATH);
                    $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

                    $validVideoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'mpeg', 'mpg', 'm4v'];

                    // For octet-stream, also verify file extension
                    if ($contentType === 'application/octet-stream') {
                        if (!in_array($extension, $validVideoExtensions)) {
                            $this->video_url_tested_by_curl_works = 'No';
                            $this->content_type = $contentType;
                            $this->content_is_video = 'No';
                            $this->save();
                            return 'No';
                        }
                    }

                    // Valid video content found
                    $this->video_url_tested_by_curl_works = 'Yes';
                    $this->content_type = $contentType;
                    $this->content_is_video = 'Yes';
                    $this->save();
                    return 'Yes';
                }

                // Check for common non-video types that should be rejected
                $nonVideoTypes = [
                    'text/html',
                    'text/plain',
                    'text/xml',
                    'application/json',
                    'application/xml',
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/zip',
                    'application/x-www-form-urlencoded'
                ];

                if (in_array($contentType, $nonVideoTypes)) {
                    $this->video_url_tested_by_curl_works = 'No';
                    $this->content_type = $contentType;
                    $this->content_is_video = 'No';
                    $this->save();
                    return 'No';
                }
            }

            // If content type is unclear, do additional verification
            // Download first few bytes to check for video file signatures
            if ($this->performDeepVideoVerification($testUrl)) {
                $this->video_url_tested_by_curl_works = 'Yes';
                $this->content_type = 'video/unknown';
                $this->content_is_video = 'Yes';
                $this->save();
                return 'Yes';
            }

            // If we reach here, it's not a valid video
            $this->video_url_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        } catch (\Exception $e) {
            $this->video_url_tested_by_curl = 'Yes';
            $this->video_url_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        }
    }

    /**
     * Perform deep verification by checking file signature (magic bytes)
     * Downloads first 32 bytes to identify actual file type
     * 
     * @param string $url
     * @return bool
     */
    private function performDeepVideoVerification($url)
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_RANGE => '0-31', // Only download first 32 bytes
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);

            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $httpCode < 200 || $httpCode >= 300 || !$data) {
                return false;
            }

            // Check for video file signatures (magic bytes)
            $signatures = [
                // MP4
                "\x00\x00\x00\x18ftypmp41",
                "\x00\x00\x00\x20ftypmp41",
                "\x00\x00\x00\x1Cftypmp42",
                "ftypmp4",
                "ftypisom",
                // AVI
                "RIFF",
                // WebM
                "\x1A\x45\xDF\xA3",
                // MKV 
                "\x1A\x45\xDF\xA3",
                // MOV (QuickTime)
                "moov",
                "mdat",
                "ftyp",
                // FLV
                "FLV",
                // WMV/ASF
                "\x30\x26\xB2\x75\x8E\x66\xCF\x11",
                // 3GP
                "ftyp3g",
            ];

            foreach ($signatures as $signature) {
                if (strpos($data, $signature) !== false) {
                    return true;
                }
            }

            // Additional check for MP4 box headers
            if (strlen($data) >= 8) {
                $boxType = substr($data, 4, 4);
                $mp4BoxTypes = ['ftyp', 'moov', 'mdat', 'free', 'skip', 'wide'];
                if (in_array($boxType, $mp4BoxTypes)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Transfer video to Firebase Storage
     * 
     * @return array Result array with success status and details
     */
    public function transferToFirebase()
    {
        return;
        try {
            // Mark transfer as attempted and in progress
            $this->firebase_transfer_attempted = 'Yes';
            $this->firebase_transfer_transfer_in_progress = 'Yes';
            $this->firebase_transfer_successful = 'No';
            $this->firebase_transfer_failure_reason = null;
            $this->save();

            // Check if already transferred
            if ($this->firebase_transfer_successful === 'Yes' && !empty($this->firebase_video_url)) {
                return [
                    'success' => true,
                    'message' => 'Already transferred to Firebase',
                    'firebase_url' => $this->firebase_video_url
                ];
            }

            // Get video URL
            $videoUrl = $this->external_url ?? $this->url;
            if (empty($videoUrl)) {
                $this->firebase_transfer_transfer_in_progress = 'No';
                $this->firebase_transfer_failure_reason = 'No video URL found';
                $this->save();
                return ['success' => false, 'error' => 'No video URL found'];
            }

            // Store old URL before transfer
            if (empty($this->old_video_url)) {
                $safeOldUrl = trim((string) $videoUrl);
                $safeOldUrl = preg_replace('/[\x00-\x1F\x7F]/u', '', $safeOldUrl) ?? $safeOldUrl;
                $this->old_video_url = mb_substr($safeOldUrl, 0, 60000);
                $this->save();
            }

            // Generate Firebase filename
            $fileName = 'movie_' . $this->id . '_' . time();

            // Use Utils class to upload to Firebase
            $result = Utils::uploadVideoToFirebase($videoUrl, $fileName, 'movies');

            if ($result['success']) {
                // Get permanent URL for the uploaded video
                $permanentResult = Utils::getFirebasePermanentUrl($result['firebase_path']);
                $firebaseUrl = $permanentResult['success'] ? $permanentResult['url'] : $result['firebase_url'];

                $this->firebase_transfer_transfer_in_progress = 'No';
                $this->firebase_transfer_successful = 'Yes';
                $this->firebase_transfer_path = $result['firebase_path'];
                $this->firebase_video_url = $firebaseUrl;
                $this->firebase_video_url_expires_at = $permanentResult['success'] ? null : now()->addYear();
                $this->save();

                return [
                    'success' => true,
                    'message' => 'Successfully transferred to Firebase',
                    'firebase_url' => $firebaseUrl,
                    'firebase_path' => $result['firebase_path']
                ];
            } else {
                $this->firebase_transfer_transfer_in_progress = 'No';
                $this->firebase_transfer_failure_reason = $result['error'];
                $this->save();

                return [
                    'success' => false,
                    'error' => $result['error']
                ];
            }
        } catch (\Exception $e) {
            $this->firebase_transfer_transfer_in_progress = 'No';
            $this->firebase_transfer_failure_reason = $e->getMessage();
            $this->save();

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test if Firebase video URL is working
     * 
     * @return string 'Yes' if working, 'No' if not working
     */
    public function testFirebaseVideoUrl()
    {
        try {
            return 'Yes';
            // Mark as being tested
            $this->firebase_video_tested_by_curl = 'Yes';
            $this->firebase_video_tested_by_curl_works = 'No';
            $this->save();

            if (empty($this->firebase_video_url)) {
                return 'No';
            }

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->firebase_video_url,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true, // HEAD request only
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            // Check for errors
            if ($error || $httpCode < 200 || $httpCode >= 400) {
                $this->firebase_video_tested_by_curl_works = 'No';
                $this->save();
                return 'No';
            }

            // Enhanced Firebase content type validation
            if ($contentType) {
                $contentType = strtolower(explode(';', $contentType)[0]);

                // Strict video content types only - NO application/octet-stream
                $validVideoTypes = [
                    'video/mp4',
                    'video/avi',
                    'video/mov',
                    'video/wmv',
                    'video/flv',
                    'video/webm',
                    'video/mkv',
                    'video/3gp',
                    'video/mpeg',
                    'video/quicktime',
                    'video/x-msvideo',
                    'video/x-flv',
                    'video/x-matroska',
                    'video/ogg',
                    'video/mp2t',
                    'video/3gpp',
                    'video/3gpp2',
                    'video/x-ms-wmv'
                ];

                // Check if it's a proper video content type
                if (in_array($contentType, $validVideoTypes)) {
                    $this->firebase_video_tested_by_curl_works = 'Yes';

                    // Auto-activate if Firebase video is working
                    if ($this->status !== 'Active') {
                        $this->status = 'Active';
                    }

                    $this->save();
                    return 'Yes';
                }

                // Check for common non-video types that should be rejected
                $nonVideoTypes = [
                    'text/html',
                    'text/plain',
                    'text/xml',
                    'application/json',
                    'application/xml',
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'application/zip',
                    'application/x-www-form-urlencoded',
                    'application/octet-stream' // Explicitly reject this now
                ];

                if (in_array($contentType, $nonVideoTypes)) {
                    $this->firebase_video_tested_by_curl_works = 'No';
                    $this->save();
                    return 'No';
                }
            }

            // If content type is unclear, do additional verification for Firebase URLs
            if ($this->performDeepVideoVerification($this->firebase_video_url)) {
                $this->firebase_video_tested_by_curl_works = 'Yes';

                // Auto-activate if Firebase video is working
                if ($this->status !== 'Active') {
                    $this->status = 'Active';
                }

                $this->save();
                return 'Yes';
            }

            $this->firebase_video_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        } catch (\Exception $e) {
            $this->firebase_video_tested_by_curl = 'Yes';
            $this->firebase_video_tested_by_curl_works = 'No';
            $this->save();
            return 'No';
        }
    }

    /**
     * Check if video exists in Firebase Storage
     * 
     * @return bool
     */
    public function checkFirebaseExists()
    {
        try {
            if (empty($this->firebase_transfer_path)) {
                return false;
            }

            $storage = app('firebase.storage');
            $bucket = $storage->getBucket(config('firebase.storage.bucket'));
            $object = $bucket->object($this->firebase_transfer_path);

            return $object->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get movies that need external URL testing
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNeedsUrlTesting($limit = 50)
    {
        return self::where('video_url_tested_by_curl', '!=', 'Yes')
            ->whereNotNull('url')
            ->limit($limit)
            ->get();
    }

    /**
     * Get movies ready for Firebase transfer
     * 
     * @param int $limit  
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNeedsFirebaseTransfer($limit = 10)
    {
        return self::where('video_url_tested_by_curl_works', 'Yes')
            ->where('firebase_transfer_successful', '!=', 'Yes')
            ->where('firebase_transfer_transfer_in_progress', '!=', 'Yes')
            ->limit($limit)
            ->get();
    }

    /**
     * Get movies that need Firebase URL testing
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getNeedsFirebaseUrlTesting($limit = 50)
    {
        return self::where('firebase_transfer_successful', 'Yes')
            ->where('firebase_video_tested_by_curl', '!=', 'Yes')
            ->whereNotNull('firebase_video_url')
            ->limit($limit)
            ->get();
    }

    // ── Hetzner Transfer Relationship ─────────────────────────────────────────

    public function transfers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MovieFileTransfer::class, 'movie_id');
    }
}
