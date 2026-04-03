<?php

namespace App\Services;

use App\Models\MovieCrawlerPage;
use App\Models\MovieCrawlerWebsite;
use Illuminate\Support\Facades\Log;

/**
 * CrawlerService
 *
 * Thin service wrapper around MovieCrawlerPage / MovieCrawlerWebsite methods
 * so callers are decoupled from the model layer (P10-02 / P10-13 / P10-14).
 *
 * The fetch/process logic remains on the models (they use $this heavily).
 * This service provides a stable, type-safe entry point for controllers and
 * scheduled commands.
 */
class CrawlerService
{
    /**
     * Fetch the remote HTML/JSON content for a crawler page and optionally
     * process it immediately.
     *
     * @param  MovieCrawlerPage  $page          The page record to populate
     * @param  bool              $doProcess     Whether to run processPageContent() after fetching
     */
    public static function fetchPageContent(MovieCrawlerPage $page, bool $doProcess = true): void
    {
        $page->fetch_page_content($doProcess);
    }

    /**
     * Parse the previously-fetched page_content and create/update MovieModel records.
     *
     * @param  MovieCrawlerPage  $page
     */
    public static function processPageContent(MovieCrawlerPage $page): void
    {
        $page->process_page_content();
    }

    /**
     * Trigger a full crawl cycle for a website: fetch the listing page and
     * dispatch individual page fetches.
     *
     * @param  MovieCrawlerWebsite  $website
     */
    public static function crawlWebsite(MovieCrawlerWebsite $website): void
    {
        $website->fetch_movies();
    }
}
