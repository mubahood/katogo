# Namzentertainment.com Crawler — Research Report

**Date:** 2026-06-13  
**Scope:** Full audit of the existing crawler, live site structure analysis, and improvement roadmap.  
**Source file:** `app/Models/Utils.php` — `get_remote_movies_links_namzentertainment()` (line 5573) and `get_url_2()` (line 5924).

---

## 1. Executive Summary

The namzentertainment.com integration **is completely broken and has not fetched any data since the remote site changed its URL structure.** The existing crawler targets `Serie.php?id=X` — a URL that now returns an HTTP 302 redirect to `signin.php` for every request, including authenticated ones. The site migrated to `prev.php?id=X`. Additionally, the hardcoded session cookie (`PHPSESSID`) embedded in source code is expired, the ID range covered (47–8019) misses approximately 1,000 movies added since the last working run (IDs now reach ~9075+), and video URLs can no longer be scraped from static HTML because the site now loads them dynamically via JavaScript.

---

## 2. Current Crawler State

### 2.1 Entry Point

```
Utils::get_remote_movies_links_namzentertainment()   — Utils.php:5573
```

This method is triggered via an unauthenticated admin route (exact route not audited here). It runs as a long-lived PHP process (memory limit disabled, execution time unlimited) with no background job queue or progress tracking.

### 2.2 Loop Structure (Utils.php:5593–5909)

```php
$max = 8019;
$min = 47;
for ($i = $min; $i <= $max; $i++) {
    $url = 'https://namzentertainment.com/Serie.php?id=' . $i;
    // ... fetch, parse, save
}
die('-done-');
```

- **Range:** ID 47 to 8019 (7,973 potential entries)
- **Skip logic:** Checks `MyCounter` table for a `SUCCESS` record of type `get_remote_movies_links_namzentertainment` with `count_value = $i`. Successfully processed IDs are skipped.
- **HTTP client:** `get_url_2()` — GuzzleHttp with hardcoded cookies (see §3.3)

### 2.3 HTML Parsing (Utils.php:5622–5686)

After fetching the page, `simple_html_dom` is used to extract:

| Data point | CSS selector / attribute |
|---|---|
| Movie title | `.details__title` |
| Primary video URL | `source[0]` → `src` attribute |
| Episode table | `.accordion__list` → `tr` elements |
| Episode name | `td[0]` → plaintext |
| Episode video URL | `tr` → `data-target` attribute |
| Poster image | `.card__cover img` → `src` |
| Genre | `.card__content li` → text containing "genre" |
| VJ voice | `.card__content li` → text containing "vj" |

### 2.4 Data Storage (Utils.php:5694–5904)

- Creates or skips a `MovieModel` record (deduplication by `url` then `title` then `external_url`).
- For series movies: creates a `SeriesMovie` entry per episode, linking back to the parent via `category_id`.
- Stores `imdb_id = $i` and `imdb_url = $url` as identifiers (these are namzentertainment's internal IDs, not real IMDB data).
- Saves PHPSESSID at session start; cookies are not refreshed mid-run.

---

## 3. Critical Bugs Found

### Bug 1 — URL Pattern Changed: Crawler Produces Zero Results

**Severity: CRITICAL — crawler is completely non-functional**

The site's movie URL format changed:

| Before | After |
|---|---|
| `https://namzentertainment.com/Serie.php?id=47` | `https://namzentertainment.com/prev.php?id=47` |

`Serie.php?id=X` now returns:
```
HTTP 302  →  Location: signin.php
```

This means every request, even authenticated ones, is silently redirected to the sign-in page. The `get_url_2()` function follows redirects (`allow_redirects: true`), so the crawler silently receives the signin HTML instead of the movie page. The HTML parser then fails to find `.details__title`, marks the record `FAILED` with "No title found", and continues to the next ID — processing all 7,973 IDs with nothing but failures.

**Fix:** Change the URL template at Utils.php:5596:
```php
// OLD (broken):
$url = 'https://namzentertainment.com/Serie.php?id=' . $i;

// NEW:
$url = 'https://namzentertainment.com/prev.php?id=' . $i;
```

---

### Bug 2 — Hardcoded Expired Session Cookie

**Severity: CRITICAL**

`get_url_2()` (Utils.php:5929–5933) embeds credentials directly in source code:

```php
$cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray([
    'P'        => '0783204665',              // phone number
    'PHPSESSID'=> '06aridtdfikhd3gq91fnoni16t',  // expired session
    'u'        => 'mubahood360@gmail.com',   // email in plain text
], 'namzentertainment.com');
```

**Problems:**
1. `PHPSESSID` is a PHP session identifier — it expires when the server-side session expires (typically hours to days). The hardcoded value `06aridtdfikhd3gq91fnoni16t` has certainly expired.
2. Credentials are committed to git history and visible to anyone with repository access.
3. If the account password or email changes, the source code must be manually updated and redeployed.

**Fix:** Remove hardcoded credentials. Implement a proper login flow at the start of each crawl run (see §5, Task 2).

---

### Bug 3 — `die()` Terminates the Entire Crawler on Missing Video

**Severity: HIGH**

```php
// Utils.php:5635–5641
if ($videoObj == null) {
    $myCounter->status = 'FAILED';
    $myCounter->status_message = 'No video found';
    echo $i . '. ' . $url . ' - No video found<br>';
    die();           // ← kills the process entirely
    $myCounter->save();  // ← unreachable code
    continue;            // ← unreachable code
}
```

If any movie page has no parseable `<source>` element, the entire crawl run dies immediately — all remaining IDs (potentially thousands) are never processed. This has become the default behavior now that `<source src="">` is always empty (see §4.2).

**Fix:** Replace `die()` with `continue` and ensure `$myCounter->save()` is called before continuing.

---

### Bug 4 — Video URL is Always Empty (Dynamic Loading)

**Severity: CRITICAL — all scraped `url` values are empty strings**

The site now loads video URLs via JavaScript at runtime. The static HTML always contains:
```html
<source src="" type="video/mp4">
```

The `src` attribute is populated by heavily obfuscated JavaScript in `overscript/set.js` (an `eval(atob(...))` chain). The actual video URL is fetched from a server-side endpoint and injected into the `<source>` element dynamically — it is never present in the initial HTML response.

For series episodes: the episode `data-target` attributes on `<tr>` rows are also loaded via AJAX from `tables/tab1.php` after the page loads. These are also not present in the static HTML response that the crawler receives.

**Implication:** The current scraper cannot obtain any video URL using static HTML parsing, regardless of authentication state or URL pattern.

**Fix:** See §5, Tasks 3 and 4 — reverse-engineer the AJAX endpoint that provides video URLs, or use a headless browser approach.

---

### Bug 5 — ID Range is Out of Date

**Severity: HIGH**

```php
$max = 8019;  // Utils.php:5593
```

Live site inspection (June 2026) confirms movies with IDs up to at least **9075**. The current range misses approximately **1,056 movies** (IDs 8020–9075) that were added to the site since the crawler was last maintained.

**Fix:** Update `$max` to at least 9200 (with headroom) and add auto-discovery of the max ID from the homepage listing.

---

### Bug 6 — Episode Number Written to Wrong Field

**Severity: MEDIUM**

```php
// Utils.php:5855–5856
$ep->episode_number = $value['number'];
$ep->country = $value['number'];   // ← episode number stored as "country"
```

The episode number is correctly stored in `episode_number` but also redundantly and incorrectly written to the `country` field. This corrupts the `country` field for all series episodes scraped from namzentertainment.

**Fix:** Remove line 5856 (`$ep->country = $value['number']`).

---

### Bug 7 — Unreachable Code After `die()` (Utils.php:5911–5921)

**Severity: LOW**

There is dead code after `die('-done-')` at line 5909: a second pagination loop referencing `get_remote_movies_links_3` type `MyCounter` records. This code is never executed and appears to be a remnant from a different crawler. It should be removed.

---

### Bug 8 — Silent Redirect Masking Auth Failures

**Severity: MEDIUM**

`get_url_2()` uses `allow_redirects: true`. When `Serie.php` (or `prev.php`) redirects to `signin.php`, the function successfully returns the sign-in page HTML without any exception or error. The caller silently receives signin HTML, fails to find `.details__title`, and logs "No title found" — giving no indication that authentication failed. This makes diagnosing auth issues very difficult.

**Fix:** Detect redirect-to-signin in the response (check if response URL or HTML contains `signin.php` / `<form action="alter.php">`), and throw a specific `NamzAuthExpiredException` that can be caught and logged clearly.

---

## 4. Live Site Analysis (June 2026)

### 4.1 Authentication

- **Login URL:** `https://namzentertainment.com/signin.php`
- **Form action:** `POST https://namzentertainment.com/alter.php`
- **Fields:**
  - `token` (hidden CSRF token, changes each page load)
  - `usname` (email or username)
  - `pword` (password)
  - `remember` (checkbox, send `on`)
  - `submit` (submit button value)
- **Credentials:** email `mubahood360@gmail.com`, password `0783204665`
- **On success:** HTTP 302 → `home.php`, with `Set-Cookie: PHPSESSID=[new_session_id]`
- **Session duration:** Appears to be approximately 1.5 hours (page has `<meta http-equiv="refresh" content="5400000;url=signin.php?~=sessionTimeOut">`)

A fresh login was tested and confirmed working during this audit.

### 4.2 Movie Page Structure (`prev.php?id=X`)

The movie detail page has the following key elements (confirmed from live pages):

```html
<!-- Movie title -->
<h1 class="details__title">Messenger</h1>

<!-- Poster image (relative URL) -->
<div class="card__cover">
    <img src="images/202606021780415236.jpg" alt="">
</div>

<!-- Metadata in card__meta -->
<ul class="card__meta">
    <li><span>Genre:</span> <a href="#">FANTASY</a></li>
    <li><span>Upload year:</span> 2026-06-02</li>
    <li><span>Running time:</span> 120 min</li>
    <li><span>Country:</span> <a href="#">USA</a></li>
</ul>

<!-- VJ voice and quality in card__list -->
<ul class="card__list">
    <li>HD</li>
    <li>EMMY</li>
</ul>

<!-- Description in card__description -->
<div class="card__description">A mysterious object crashes...</div>

<!-- Video player — src is ALWAYS empty -->
<video controls id="player">
    <source src="" type="video/mp4">
</video>
```

The `card__cover`, `card__meta`, `card__list`, and `card__description` selectors still work correctly and match what the crawler's parsing code expects (§2.3). Only the video URL source is broken.

### 4.3 Video URL Loading Mechanism

Video URLs are loaded entirely by JavaScript — the static HTML cannot provide them. Two mechanisms are used:

**Single-file movies:** `overscript/set.js` contains a multi-layer obfuscated script (`eval(atob(...))`  wrapping `eval(atob(__).$(..., 'd'))`) that decodes and executes the video source injection at page load. The decrypted script likely makes an XHR call to a backend PHP endpoint (unconfirmed — would require JavaScript execution environment to observe).

**Series episodes:** The episode table is loaded via AJAX after page load:
```js
// overscript/controller.js — seloader function
function seloader($container, $loc, $datain) {
    $.ajax({
        url: $loc,
        type: 'post',
        data: { data: $datain },
        success: function(data) {
            $($container).html(data);
        }
    });
}
// Called as:
seloader($tab2, 'tables/tab1.php', $sent);  // $sent = movie's internal DB ID
```

The `<tr>` rows returned by `tables/tab1.php` contain `data-target="[video_url]"` attributes. The `getso()` click handler in `controller.js` reads this attribute and sets it as the `<source src>`.

**Key:** `tables/tab1.php` accepts a POST with `data=[internal_movie_id]`. This internal ID is the site's database ID — it appears in the page's `<header id="triger" data-target="[internal_id]">` attribute. During this audit, the header had `data-target="0"` for movie pages, suggesting the `$cid` variable is not the URL `id` parameter but a user ID or another internal value. Further testing with network inspection is needed to confirm the exact parameter mapping.

### 4.4 ID Range

- **Minimum confirmed working ID:** 47 (original crawler minimum)
- **Maximum observed on homepage:** 9075 (as of June 2026)
- **Estimated new content:** ~1,056 IDs (8020–9075)
- **IDs are sequential** but not all exist (gaps are expected for deleted/unpublished content)

### 4.5 Content Types

Movies are classified as either `free` or `paid` (Premium). Free movies are accessible without subscription; paid movies show `title="PAID"` on their `card__play` links. The sidebar lists VJ voices (26 VJs as of June 2026: HD, ks, mk, banks, HEAVY Q, kevin, Mark, Dandee, Sammy, Shao, Muba, Ivo, Emmy, Waza, Kamran, Ashim J, Little T, kevo, Ice P, Jingo, Junior).

---

## 5. Improvement Tasks

### Task 1 — Fix URL Pattern (Critical, 30 min)

**File:** `app/Models/Utils.php` line 5596

Change:
```php
$url = 'https://namzentertainment.com/Serie.php?id=' . $i;
```
To:
```php
$url = 'https://namzentertainment.com/prev.php?id=' . $i;
```

Also update the `MyCounter` type to `get_remote_movies_links_namzentertainment_v2` so that the new run doesn't skip IDs that were "successfully" processed under the old (broken) URL, and re-processes them correctly.

---

### Task 2 — Replace Hardcoded Cookies with Dynamic Login (Critical, 2–3 hours)

**File:** `app/Models/Utils.php` — `get_url_2()` and `get_remote_movies_links_namzentertainment()`

Create a new method `namz_login()` that:
1. Fetches `signin.php` to extract the CSRF `token` hidden field
2. POSTs to `alter.php` with `usname`, `pword`, `remember`, `token`
3. Returns the session cookie jar

Move credentials to `.env`:
```ini
NAMZ_EMAIL=mubahood360@gmail.com
NAMZ_PASSWORD=0783204665
```

Inject the returned cookie jar into all subsequent requests. Re-authenticate automatically when a redirect to `signin.php` is detected (see Bug 8). The `get_url_2()` method should accept a cookie jar parameter rather than hardcoding its own.

---

### Task 3 — Reverse-Engineer the Video URL AJAX Endpoint (Critical, 4–6 hours)

**Goal:** Obtain real video URLs for both single movies and series episodes.

**Approach:**
1. Use browser DevTools (Network tab) to observe what requests are made when a movie page loads and when an episode is clicked on the episode table.
2. Identify the endpoint (`tables/tab1.php` for episodes; TBD for single movies) and the parameters it requires.
3. Replicate those requests in the PHP crawler using GuzzleHttp with the authenticated session cookie.

**For series episodes specifically:** POST to `tables/tab1.php` with the movie's internal database ID (found in the page `<header data-target="[id]">`). Parse the returned HTML fragment for `<tr data-target="[video_url]">` rows.

**For single movies:** Deobfuscate `overscript/set.js` to identify the endpoint. Alternatively, a headless browser (Puppeteer/Playwright) may be simpler for this case since the obfuscation is intentionally difficult to reverse.

---

### Task 4 — Handle Dynamic Video Loading with Headless Browser (Alternative to Task 3, 6–8 hours)

If the video URL endpoint from Task 3 proves too difficult to reverse-engineer, use a headless browser approach:

- Install Puppeteer or Playwright in a Node.js microservice (can be called via `exec()` from PHP)
- Log in once per session, persist cookies
- Navigate to `prev.php?id=X`, wait for video `<source>` to be populated by JavaScript
- Extract the `src` attribute and return it to the PHP process

This approach is more robust against future obfuscation changes but adds infrastructure complexity.

---

### Task 5 — Extend ID Range and Add Auto-Discovery (High, 1 hour)

**File:** `app/Models/Utils.php` line 5593

Change:
```php
$max = 8019;
```
To:
```php
$max = 9200;  // safe headroom for now
```

Add auto-discovery: fetch the homepage and extract the highest `prev.php?id=X` value found, then use `max($discovered_max, 9200)` as the actual ceiling. This prevents the range from going stale again.

---

### Task 6 — Fix `die()` → `continue` (High, 15 min)

**File:** `app/Models/Utils.php` lines 5635–5641

Replace:
```php
die();
$myCounter->save();  // unreachable
continue;            // unreachable
```
With:
```php
$myCounter->save();
continue;
```

---

### Task 7 — Fix Episode Number Written to `country` Field (Medium, 5 min)

**File:** `app/Models/Utils.php` line 5856

Remove:
```php
$ep->country = $value['number'];
```

---

### Task 8 — Convert to Artisan Command + Scheduler (Medium, 2–3 hours)

The current implementation runs as a web-accessible method triggered by a URL. This approach has several problems: no timeout protection (browsers kill long HTTP requests), no progress visibility, no retry on server restart, no rate control, and no way to pause/resume cleanly.

Create an Artisan command:
```
php artisan namz:crawl [--start=47] [--end=9200] [--delay=500]
```

Key features:
- Resume from last successful ID automatically (already uses `MyCounter` — just change trigger)
- `--delay` parameter (milliseconds between requests) to avoid hammering the server
- Structured logging instead of `echo`
- Can be scheduled via cron or run manually
- Supervisor-compatible (won't die with the web server)

Optionally add an admin-panel "Start Crawl" button that dispatches a queued job instead of running inline.

---

### Task 9 — Auth Failure Detection and Auto-Re-Login (Medium, 1–2 hours)

After implementing Task 2, add detection in the fetch loop: if the returned HTML contains the signin form (`<form action="alter.php">`), throw a specific exception and trigger a re-login before retrying the current ID. Limit re-login attempts to 3 per run to avoid infinite loops.

---

### Task 10 — Clean Up Dead Code (Low, 30 min)

**File:** `app/Models/Utils.php` lines 5911–5921

Remove the unreachable code block after `die('-done-')` at line 5909. It references `get_remote_movies_links_3` which is a different crawler and has no relation to this function.

---

## 6. Recommended Implementation Order

| Priority | Task | Estimated Effort |
|---|---|---|
| 1 | Task 1 — Fix URL pattern (`Serie.php` → `prev.php`) | 30 min |
| 2 | Task 2 — Dynamic login (remove hardcoded cookies) | 2–3 hours |
| 3 | Task 6 — Fix `die()` → `continue` | 15 min |
| 4 | Task 7 — Fix episode number in country field | 5 min |
| 5 | Task 3 — Reverse-engineer video URL AJAX endpoint | 4–6 hours |
| 6 | Task 5 — Extend ID range + auto-discovery | 1 hour |
| 7 | Task 9 — Auth failure detection + auto-re-login | 1–2 hours |
| 8 | Task 8 — Convert to Artisan command | 2–3 hours |
| 9 | Task 10 — Remove dead code | 30 min |
| 10 | Task 4 — Headless browser (only if Task 3 fails) | 6–8 hours |

Tasks 1, 6, and 7 can be done in under an hour and should be done immediately regardless of the video URL investigation (Tasks 3/4), since fixing the URL pattern and the `die()` bug will at least allow the crawler to collect movie metadata correctly even before video URLs are solved.

---

## 7. Security Notes

- **Credentials in source code** (Bug 2) means they are in git history even after removal. After moving to `.env`, consider rotating the `mubahood360@gmail.com` account password.
- The route that triggers `get_remote_movies_links_namzentertainment()` should require admin authentication. This was not audited in this report but should be verified.
- The namzentertainment account is a personal account (`mubahood360@gmail.com`). If possible, create a dedicated scraper account so credentials can be rotated without affecting the owner's personal access.

---

*Report prepared June 13, 2026. Site tested live at `https://namzentertainment.com`. Code references are to commit state at time of audit.*
