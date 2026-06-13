<?php

namespace App\Console\Commands;

use App\Models\MovieModel;
use App\Models\MyCounter;
use App\Models\NamzCrawlLog;
use App\Models\NamzCrawlSession;
use App\Models\SeriesMovie;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// simple_html_dom is not autoloaded — it lives in the Models directory
require_once app_path('Models/simple_html_dom.php');

class NamzCrawl extends Command
{
    protected $signature = 'namz:crawl
                            {--from=47         : Start ID (inclusive)}
                            {--to=9200         : End ID (inclusive)}
                            {--delay=800       : Milliseconds between requests to avoid hammering server}
                            {--batch=50        : IDs per batch before sleeping}
                            {--session=        : Resume existing session ID}
                            {--dry-run         : Parse pages but do not write to DB}
                            {--force           : Re-process IDs already marked as SUCCESS in MyCounter}
                            {--single=         : Process a single namz ID only}';

    protected $description = 'Crawl namzentertainment.com for movies and series metadata';

    private Client $client;
    private CookieJar $cookieJar;
    private bool $dryRun = false;
    private NamzCrawlSession $session;

    const SITE          = 'https://namzentertainment.com';
    const COUNTER_TYPE  = 'namz_crawler_v2';

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $from  = (int) $this->option('from');
        $to    = (int) $this->option('to');
        $delay = (int) $this->option('delay');
        $batch = (int) $this->option('batch');
        $single = $this->option('single');

        if ($single !== null) {
            $from = $to = (int) $single;
        }

        // Resolve or create session
        $sessionId = $this->option('session');
        if ($sessionId && $existingSession = NamzCrawlSession::find($sessionId)) {
            $this->session = $existingSession;
            $this->session->update(['status' => NamzCrawlSession::STATUS_RUNNING, 'ended_at' => null]);
            $from = $this->session->id_current; // resume from where we left off
            $this->info("Resuming session #{$this->session->id} from ID {$from}");
        } else {
            $this->session = NamzCrawlSession::create([
                'status'       => NamzCrawlSession::STATUS_RUNNING,
                'id_from'      => $from,
                'id_to'        => $to,
                'id_current'   => $from,
                'triggered_by' => PHP_SAPI === 'cli' ? 'manual' : 'scheduler',
                'started_at'   => now(),
            ]);
        }

        $this->info("Namz Crawler — Session #{$this->session->id}");
        $this->info("Range: {$from} → {$to} | Delay: {$delay}ms | Dry: " . ($this->dryRun ? 'YES' : 'NO'));

        // Authenticate
        if (!$this->login()) {
            $this->session->update(['status' => NamzCrawlSession::STATUS_FAILED, 'notes' => 'Login failed', 'ended_at' => now()]);
            $this->error('Login to namzentertainment.com failed. Aborting.');
            return 1;
        }

        $bar = $this->output->createProgressBar($to - $from + 1);
        $bar->start();
        $processed = 0;

        for ($i = $from; $i <= $to; $i++) {
            // Stop signal file
            if (file_exists(storage_path('namz_stop'))) {
                @unlink(storage_path('namz_stop'));
                $this->session->update(['status' => NamzCrawlSession::STATUS_STOPPED, 'ended_at' => now(), 'id_current' => $i]);
                $this->newLine();
                $this->warn("Stop signal received at ID {$i}. Session #{$this->session->id} marked Stopped.");
                return 0;
            }

            // Update session progress every 10 IDs
            if ($i % 10 === 0) {
                $this->session->update(['id_current' => $i]);
            }

            $this->processId($i);

            $bar->advance();
            $processed++;

            // Rate limiting: sleep between requests
            if ($delay > 0 && $i < $to) {
                // After each batch, sleep a bit longer to avoid hammering
                if ($processed % $batch === 0) {
                    usleep(($delay * 2) * 1000);
                } else {
                    usleep($delay * 1000);
                }
            }

            // Re-authenticate if session expired (every 200 IDs)
            if ($processed % 200 === 0) {
                $this->info("\nRe-authenticating at ID {$i}...");
                $this->login();
            }
        }

        $bar->finish();
        $this->newLine();

        $this->session->update([
            'status'     => NamzCrawlSession::STATUS_COMPLETED,
            'ended_at'   => now(),
            'id_current' => $to,
        ]);

        $this->printSummary();
        return 0;
    }

    // ── Authentication ─────────────────────────────────────────────────────────

    private function login(): bool
    {
        try {
            $email    = config('services.namz.email',    env('NAMZ_EMAIL',    'mubahood360@gmail.com'));
            $password = config('services.namz.password', env('NAMZ_PASSWORD', '0783204665'));

            $this->cookieJar = new CookieJar();
            $this->client = new Client([
                'allow_redirects' => true,
                'verify'          => false,
                'timeout'         => 30,
                'connect_timeout' => 15,
                'headers'         => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,*/*;q=0.9',
                    'Accept-Language' => 'en-US,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate',
                ],
            ]);

            // Step 1: GET signin.php to grab fresh CSRF token
            $signinResponse = $this->client->get(self::SITE . '/signin.php', [
                'cookies' => $this->cookieJar,
            ]);
            $signinHtml = (string) $signinResponse->getBody();

            // Extract CSRF token
            preg_match('/name="token"\s+value="([^"]+)"/', $signinHtml, $m);
            $token = $m[1] ?? '';
            if (!$token) {
                $this->warn('Could not extract CSRF token from signin page.');
                return false;
            }

            // Step 2: POST to alter.php with credentials
            $this->client->post(self::SITE . '/alter.php', [
                'cookies'     => $this->cookieJar,
                'form_params' => [
                    'token'    => $token,
                    'usname'   => $email,
                    'pword'    => $password,
                    'remember' => 'on',
                    'submit'   => 'Sign in',
                ],
            ]);

            // Step 3: Verify session via lock.php
            $lockResp = $this->client->post(self::SITE . '/lock.php', [
                'cookies' => $this->cookieJar,
            ]);
            // lock.php prepends a token before the JSON body: "TOKEN{...json...}"
            $lockBody = (string) $lockResp->getBody();
            $jsonStart = strpos($lockBody, '{');
            $lockJson  = $jsonStart !== false ? json_decode(substr($lockBody, $jsonStart), true) : null;
            if (!$lockJson || (($lockJson['success'] ?? '') != '1' && ($lockJson['success'] ?? '') != 1)) {
                $this->warn('lock.php verification failed after login. Body: ' . substr($lockBody, 0, 100));
                return false;
            }

            $this->info('Logged in to namzentertainment.com ✓');
            return true;
        } catch (\Throwable $e) {
            $this->warn('Login error: ' . $e->getMessage());
            Log::error('[NamzCrawl] Login error: ' . $e->getMessage());
            return false;
        }
    }

    private function fetchUrl(string $url): ?string
    {
        try {
            $resp = $this->client->get($url, [
                'cookies'    => $this->cookieJar,
                'on_headers' => function (\Psr\Http\Message\ResponseInterface $response) {
                    $location = $response->getHeader('Location')[0] ?? '';
                    if (stripos($location, 'signin') !== false) {
                        throw new \RuntimeException('Session expired, redirected to signin');
                    }
                },
            ]);
            $html = (string) $resp->getBody();

            // Detect signin page in body (redirect may have followed already)
            if (strpos($html, 'action="alter.php"') !== false || strpos($html, 'signin.php') !== false && strpos($html, 'details__title') === false) {
                throw new \RuntimeException('Session expired — signin page returned');
            }
            return $html;
        } catch (\RuntimeException $e) {
            // Re-login and retry once
            $this->warn("\nSession expired, re-logging in...");
            if ($this->login()) {
                return $this->fetchUrl($url);
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('[NamzCrawl] Fetch error for ' . $url . ': ' . $e->getMessage());
            return null;
        }
    }

    // ── Process one ID ─────────────────────────────────────────────────────────

    private function processId(int $namzId): void
    {
        $url = self::SITE . "/prev.php?id={$namzId}";

        // Skip if already successfully processed (unless --force)
        if (!$this->option('force')) {
            $done = MyCounter::where([
                'type'        => self::COUNTER_TYPE,
                'count_value' => $namzId,
                'status'      => 'SUCCESS',
            ])->exists();
            if ($done) {
                $this->incrementSession('total_skipped');
                return;
            }
        }

        $log = NamzCrawlLog::create([
            'session_id'   => $this->session->id,
            'namz_id'      => $namzId,
            'url'          => $url,
            'status'       => NamzCrawlLog::STATUS_PENDING,
            'video_status' => NamzCrawlLog::VIDEO_PENDING,
        ]);

        $html = $this->fetchUrl($url);
        if ($html === null) {
            $this->saveLogError($log, 'fetch_failed', 'Could not fetch page');
            return;
        }

        // Parse with simple_html_dom
        $dom = \str_get_html($html);
        if (!$dom) {
            $this->saveLogError($log, 'parse_failed', 'simple_html_dom returned false');
            return;
        }

        // ── Extract title ──────────────────────────────────────────────────────
        $titleEl = $dom->find('.details__title', 0);
        if (!$titleEl) {
            // No title = page doesn't exist or is private
            $this->saveLogSkipped($log, NamzCrawlLog::RESULT_NO_TITLE, $namzId);
            $dom->clear();
            return;
        }
        $title = trim($titleEl->plaintext);
        if (empty($title)) {
            $this->saveLogSkipped($log, NamzCrawlLog::RESULT_NO_TITLE, $namzId);
            $dom->clear();
            return;
        }

        // ── Extract metadata ───────────────────────────────────────────────────
        $poster     = $this->extractPoster($dom, $html);
        $genre      = null;
        $vjVoice    = null;
        $year       = null;
        $duration   = null;
        $country    = null;
        $description= null;
        $isPremium  = 'No';

        // Parse .card__list items for quality/VJ
        foreach ($dom->find('.card__list li') as $li) {
            $t = trim($li->plaintext);
            if (stripos($t, 'vj') !== false) {
                $vjVoice = $t;
            }
        }

        // Parse .card__meta li for genre/year/duration/country
        foreach ($dom->find('.card__meta li') as $li) {
            $t = trim($li->plaintext);
            $tLow = strtolower($t);
            if (str_contains($tLow, 'genre')) {
                $genre = trim(str_replace(['Genre:', 'Genres:', 'Genre', 'Genres', ':'], '', $t));
            } elseif (str_contains($tLow, 'upload year') || str_contains($tLow, 'year')) {
                preg_match('/(\d{4})/', $t, $ym);
                $year = $ym[1] ?? null;
            } elseif (str_contains($tLow, 'running time') || str_contains($tLow, 'duration')) {
                preg_match('/(\d+)\s*min/i', $t, $dm);
                $duration = isset($dm[1]) ? ((int) $dm[1]) : null;
            } elseif (str_contains($tLow, 'country')) {
                $country = trim(preg_replace('/Country\s*:/i', '', $t));
            }
        }

        // Description
        $descEl = $dom->find('.card__description', 0);
        if ($descEl) {
            $description = trim(strip_tags($descEl->innertext));
        }

        // Check if PAID content
        $cardPlay = $dom->find('.card__play', 0);
        if ($cardPlay && strtoupper(trim($cardPlay->title)) === 'PAID') {
            $isPremium = 'Yes';
        }

        // ── Check for series episodes (.accordion__list) — Old structure ────────
        // New site doesn't use this but keep for backward compat with older IDs
        $episodes = $this->extractEpisodes($dom);

        // ── Video URL ─────────────────────────────────────────────────────────
        // The video URL is loaded by obfuscated JS — static HTML always has empty <source src="">
        // We cannot get it from static HTML. Mark as url_pending.
        $videoUrl    = null;
        $videoStatus = NamzCrawlLog::VIDEO_NOT_FOUND;

        // Fallback: check if <source src=""> has something (older pages might)
        $sourceEl = $dom->find('source', 0);
        if ($sourceEl) {
            $srcVal = trim($sourceEl->getAttribute('src'));
            if (!empty($srcVal) && filter_var($srcVal, FILTER_VALIDATE_URL)) {
                $videoUrl    = $srcVal;
                $videoStatus = NamzCrawlLog::VIDEO_FOUND;
            }
        }

        $dom->clear();

        if ($this->dryRun) {
            $log->update([
                'status'        => NamzCrawlLog::STATUS_SUCCESS,
                'result'        => 'dry_run',
                'title'         => $title,
                'video_url'     => $videoUrl,
                'video_status'  => $videoStatus,
                'error_message' => '[DRY RUN — no DB write]',
            ]);
            $this->incrementSession('total_processed');
            $this->incrementSession('total_success');
            return;
        }

        // ── Persist movie/series ───────────────────────────────────────────────
        if (count($episodes) > 0) {
            $this->persistSeries($namzId, $url, $title, $poster, $genre, $vjVoice, $year, $duration, $description, $episodes, $log);
        } else {
            $this->persistMovie($namzId, $url, $title, $poster, $genre, $vjVoice, $year, $duration, $country, $description, $isPremium, $videoUrl, $videoStatus, $log);
        }

        // Mark as done in MyCounter (model has no $fillable so use direct insert)
        \DB::table('my_counters')->insert([
            'type'           => self::COUNTER_TYPE,
            'count_value'    => $namzId,
            'status'         => 'SUCCESS',
            'status_message' => $title,
            'data'           => $url,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->incrementSession('total_processed');
        $this->incrementSession('total_success');
    }

    // ── Persist single movie ───────────────────────────────────────────────────

    private function persistMovie(
        int $namzId, string $url, string $title, ?string $poster,
        ?string $genre, ?string $vj, ?string $year, ?int $duration,
        ?string $country, ?string $description, string $isPremium,
        ?string $videoUrl, string $videoStatus, NamzCrawlLog $log
    ): void {
        // Anti-duplicate: check by namz_id first, then by title+vj
        $movie = MovieModel::where('namz_id', $namzId)->first();
        $isNew = false;

        if (!$movie) {
            // Secondary dedup: same title + same VJ from namz source
            $movie = MovieModel::where('title', $title)
                ->where('imdb_url', 'like', '%namzentertainment%')
                ->first();
        }

        if (!$movie) {
            // Third dedup: check by imdb_id (old crawler used this field)
            $movie = MovieModel::where('imdb_id', $namzId)
                ->where('imdb_url', 'like', '%namzentertainment%')
                ->first();
        }

        if (!$movie) {
            $movie = new MovieModel();
            $isNew = true;
        }

        $movie->title         = $title;
        $movie->namz_id       = $namzId;
        $movie->imdb_id       = $namzId;
        $movie->imdb_url      = $url;
        $movie->page_source_url = $url;
        $movie->type          = 'Movie';
        $movie->platform_type = 'namz';

        if ($poster) {
            $movie->thumbnail_url = $poster;
            $movie->poster_url    = $poster;
        }
        if ($genre)       $movie->genre    = $genre;
        if ($vj)          $movie->vj       = $vj;
        if ($year)        $movie->year     = $year;
        if ($duration)    $movie->duration = $duration;
        if ($country)     $movie->country  = $country;
        if ($description) $movie->description = $description;

        $movie->is_premium    = $isPremium;
        $movie->content_type  = 'video/mp4';
        $movie->content_is_video = 'Yes';

        if ($videoUrl) {
            $movie->url          = $videoUrl;
            $movie->external_url = $videoUrl;
            $movie->status       = 'Active';
            $movie->namz_url_status = 'found';
        } else {
            // No video URL from static HTML — mark inactive, pending resolution
            if ($isNew || !$movie->url) {
                $movie->url          = '';
                $movie->external_url = $url; // use namz page as reference
                $movie->status       = 'Inactive';
            }
            $movie->namz_url_status = 'pending';
            $movie->fix_status      = 'namz_url_pending';
        }

        $movie->save();

        $result = $isNew ? NamzCrawlLog::RESULT_NEW_MOVIE : NamzCrawlLog::RESULT_EXISTING;
        if ($isNew) $this->incrementSession('total_new_movies');
        if ($videoStatus === NamzCrawlLog::VIDEO_NOT_FOUND) $this->incrementSession('total_url_pending');

        $log->update([
            'status'       => NamzCrawlLog::STATUS_SUCCESS,
            'result'       => $result,
            'title'        => $title,
            'movie_id'     => $movie->id,
            'video_url'    => $videoUrl,
            'video_status' => $videoStatus,
        ]);
    }

    // ── Persist series with episodes ───────────────────────────────────────────

    private function persistSeries(
        int $namzId, string $url, string $title, ?string $poster,
        ?string $genre, ?string $vj, ?string $year, ?int $_duration,
        ?string $description, array $episodes, NamzCrawlLog $log
    ): void {
        // Anti-duplicate for series
        $series = SeriesMovie::where('external_url', $url)->first()
            ?? SeriesMovie::where('title', $title)->first();

        $isNew = !$series;
        if (!$series) {
            $series = new SeriesMovie();
        }

        $series->title       = $title;
        $series->external_url = $url;
        if ($poster)      $series->thumbnail = $poster;
        if ($genre)       $series->genre     = $genre;
        if ($vj)          $series->vj        = $vj;
        if ($year)        $series->year      = $year;
        if ($description) $series->description = $description;
        $series->is_active    = 'No'; // will activate once episodes have URLs
        $series->save();

        if ($isNew) $this->incrementSession('total_new_series');

        $urlPending = 0;
        foreach ($episodes as $ep) {
            $epTitle  = $ep['title'];
            $epUrl    = $ep['url'];
            $epNum    = $ep['number'];

            // Anti-duplicate for episode
            $epMovie = MovieModel::where('category_id', $series->id)
                ->where('episode_number', $epNum)
                ->first();

            $isNewEp = !$epMovie;
            if (!$epMovie) {
                $epMovie = new MovieModel();
            }

            $epMovie->title          = $series->title . ' - ' . $epTitle;
            $epMovie->type           = 'Series';
            $epMovie->category_id    = $series->id;
            $epMovie->category       = $series->title;
            $epMovie->episode_number = $epNum;
            $epMovie->platform_type  = 'namz';
            $epMovie->namz_id        = $namzId;
            $epMovie->imdb_id        = $namzId;
            $epMovie->imdb_url       = $url;
            $epMovie->is_premium     = 'No';
            $epMovie->content_type   = 'video/mp4';
            $epMovie->content_is_video = 'Yes';
            if ($poster)      $epMovie->thumbnail_url = $poster;
            if ($description) $epMovie->description  = $description;

            if ($epUrl && filter_var($epUrl, FILTER_VALIDATE_URL)) {
                $epMovie->url          = $epUrl;
                $epMovie->external_url = $epUrl;
                $epMovie->status       = 'Active';
                $epMovie->namz_url_status = 'found';
            } else {
                if ($isNewEp || !$epMovie->url) {
                    $epMovie->url    = '';
                    $epMovie->status = 'Inactive';
                }
                $epMovie->namz_url_status = 'pending';
                $epMovie->fix_status      = 'namz_url_pending';
                $urlPending++;
            }

            $epMovie->save();
        }

        $this->incrementSession('total_url_pending', $urlPending);

        $log->update([
            'status'       => NamzCrawlLog::STATUS_SUCCESS,
            'result'       => $isNew ? NamzCrawlLog::RESULT_NEW_SERIES : NamzCrawlLog::RESULT_EXISTING,
            'title'        => $title,
            'series_id'    => $series->id,
            'video_status' => $urlPending > 0 ? NamzCrawlLog::VIDEO_NOT_FOUND : NamzCrawlLog::VIDEO_FOUND,
        ]);
    }

    // ── HTML Parsing Helpers ───────────────────────────────────────────────────

    private function extractPoster(\simple_html_dom $dom, string $html): ?string
    {
        // Background image in details__bg
        if (preg_match('/details__bg.*?data-bg="([^"]+)"/s', $html, $m)) {
            $bg = trim($m[1]);
            if ($bg) return $this->absoluteUrl($bg);
        }
        // Fallback: first img in .card__cover
        $img = $dom->find('.card__cover img', 0);
        if ($img) {
            $src = trim($img->getAttribute('src'));
            if ($src) return $this->absoluteUrl($src);
        }
        return null;
    }

    private function extractEpisodes(\simple_html_dom $dom): array
    {
        $episodes = [];
        $accordionList = $dom->find('.accordion__list', 0);
        if (!$accordionList) return [];

        $count = 0;
        foreach ($accordionList->find('tr') as $tr) {
            $tds = $tr->find('td');
            if (!$tds || count($tds) === 0) continue;

            $td0       = $tr->find('td', 0);
            $epName    = $td0 ? trim($td0->plaintext) : '';
            $dataTarget= $tr->getAttribute('data-target');

            $count++;
            $num = $count;
            foreach (explode(' ', $epName) as $part) {
                if (is_numeric(trim($part))) {
                    $num = (int) trim($part);
                    break;
                }
            }

            $episodes[] = [
                'title'  => $epName,
                'url'    => $dataTarget ?: '',
                'number' => $num,
            ];
        }
        return $episodes;
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) return $url;
        return rtrim(self::SITE, '/') . '/' . ltrim($url, '/');
    }

    // ── Session helpers ────────────────────────────────────────────────────────

    private function incrementSession(string $field, int $by = 1): void
    {
        $this->session->increment($field, $by);
    }

    private function saveLogError(NamzCrawlLog $log, string $result, string $message): void
    {
        $log->update([
            'status'        => NamzCrawlLog::STATUS_FAILED,
            'result'        => NamzCrawlLog::RESULT_ERROR,
            'error_message' => $message,
        ]);
        $this->incrementSession('total_failed');
        $this->incrementSession('total_processed');
        Log::warning("[NamzCrawl] ID {$log->namz_id}: {$result} — {$message}");
    }

    private function saveLogSkipped(NamzCrawlLog $log, string $result, int $namzId): void
    {
        $log->update([
            'status' => NamzCrawlLog::STATUS_SKIPPED,
            'result' => $result,
        ]);
        $this->incrementSession('total_skipped');
        $this->incrementSession('total_processed');

        // Mark as "done" in MyCounter so we don't retry IDs that don't exist
        if ($result === NamzCrawlLog::RESULT_NO_TITLE) {
            \DB::table('my_counters')->insert([
                'type'           => self::COUNTER_TYPE,
                'count_value'    => $namzId,
                'status'         => 'SUCCESS',
                'status_message' => 'no_title',
                'data'           => self::SITE . "/prev.php?id={$namzId}",
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }

    private function printSummary(): void
    {
        $this->session->refresh();
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info("Session #{$this->session->id} Complete");
        $this->info("Processed : {$this->session->total_processed}");
        $this->info("Success   : {$this->session->total_success}");
        $this->info("Skipped   : {$this->session->total_skipped}");
        $this->info("Failed    : {$this->session->total_failed}");
        $this->info("New Movies: {$this->session->total_new_movies}");
        $this->info("New Series: {$this->session->total_new_series}");
        $this->info("URL Pend. : {$this->session->total_url_pending}");
        $this->info("Duration  : {$this->session->duration}");
        $this->info('═══════════════════════════════════════');
    }
}
