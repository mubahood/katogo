# Google Ads Compliance Fix — LugaFlix (Ad ID 815113116202)

Date: July 6, 2026

## Why the ad is "Not eligible"

Two separate blockers were active. The budget blocker cleared with your $153 payment. The remaining blocker is the **Copyrighted Content** disapproval, and it is triggered by more than just the ad text: Google scans the headlines/descriptions, the attached videos, the landing page (lugaflix.store), and the Play/App Store listings **in combination**. Fixing only the copy is not enough.

## New compliant ad copy (paste these in)

### Headlines (max 30 characters)

| # | Headline | Chars |
|---|----------|-------|
| 1 | Luganda Movie Streaming App | 27 |
| 2 | Watch Movies in Luganda | 23 |
| 3 | Stream in Your Language | 23 |
| 4 | Movies Translated to Luganda | 28 |
| 5 | Made for Uganda, In Luganda | 27 |

### Descriptions (max 90 characters)

| # | Description | Chars |
|---|-------------|-------|
| 1 | Stream movies and series translated to Luganda. Download the app on Android or iPhone. | 86 |
| 2 | Enjoy entertainment in your mother tongue. New Luganda titles added every week. | 79 |
| 3 | Professional Luganda voice translation. Watch on your phone, tablet or computer. | 80 |
| 4 | Ad-supported streaming made for Uganda. Simple to set up — install the app and watch. | 85 |
| 5 | Join thousands watching movies translated to Luganda. Install the app and start today. | 86 |

Rules these follow — keep them for any future copy:
- No VJ names (VJ Junior, VJ Jingo, VJ Ice P, or any other artist name)
- No movie, series, studio, or platform names (no titles, no "Hollywood", no "Netflix")
- No ALL-CAPS "FREE" and no "absolutely free" / "no data needed" claims
- Sell the *service* (Luganda translation/streaming), not the *content* (specific films)
- Use "translated", not "dubbed" — simpler English that Ugandan audiences actually use

## Video assets — the biggest remaining risk

The 3 attached YouTube videos almost certainly contain movie footage, posters, or VJ audio. **Remove all three** and replace with:
- A screen-recorded app walkthrough (browsing UI, no recognizable film footage on screen)
- A simple animated logo/brand intro ("Watch in Luganda — download the app")
- Testimonial-style clips of real users (with their consent)

Any frame containing a recognizable film, actor, poster, or studio logo will re-trigger the disapproval.

## Image assets (9/20, landscape ones removed)

The removed landscape images were almost certainly movie posters/stills. Replace with brand-owned images only: app screenshots (blur or crop out poster art), logo banners, lifestyle photos of people watching a phone/TV. Recommended landscape size: 1200×628.

## Landing page fixes (already done in this repo)

- `resources/views/landing/movies.blade.php` — removed "Hollywood" from title, meta description, keywords, og tags, and page text
- `resources/views/landing/index.blade.php` — meta no longer says "Uganda's top VJs"
- `resources/views/landing/faq.blade.php` — removed "international movies"
- **New page**: `/copyright-policy` (Copyright & Takedown policy) + footer link — deploy this
- Route added in `routes/web.php`

### Still to fix — React frontend (separate repo/build)

The live lugaflix.store homepage is your React SPA. Its `index.html` meta tags currently say:

> "Watch 1,000+ international movies & series translated to Luganda by Uganda's top VJs. Free streaming... No download needed"

Replace title/description/og/twitter meta with:

- Title: `LugaFlix — Watch Movies in Luganda | lugaflix.store`
- Description: `Stream movies and series professionally translated to Luganda. Ad-supported streaming for Uganda. Watch on Android, iPhone or computer.`
- Also remove "international movies" and "VJ translated" from the keywords meta.

Also check the SPA's visible homepage for VJ names, movie posters of major releases, and "FREE" claims.

### Play Store / App Store listings

Google Ads pulls assets from your store listings too. Apply the same rules there: no VJ names, no movie titles/posters in screenshots, no "1,000+ international movies free" claims. If your store screenshots show poster art of major films, replace them.

## Step-by-step in the Ads account

1. Deploy the landing-page changes (this repo) and update the React SPA meta.
2. In the ad: replace all 5 headlines and 5 descriptions with the copy above.
3. Remove all 3 videos. Add at least 1 compliant video (or run without video temporarily).
4. Remove any remaining images showing movie posters/stills; upload brand-owned replacements.
5. Save. Saving edits automatically triggers a fresh policy review (usually 1 business day).
6. Do **not** file an appeal — without written licenses, a rejected appeal increases the risk of account-level suspension.
7. Check back in 24–48h. If still disapproved, the remaining trigger is almost always the destination (landing page or store listing) — re-check step 1 and the store listings.

## The honest long-term picture

Ad-copy hygiene restores serving, but the underlying exposure remains: Luganda-translated versions of films are derivative works under Uganda's Copyright and Neighbouring Rights Act, and Google can re-flag the domain, the app listing, or the account at any time. To make this durable:

1. Get **written agreements with your VJs** covering their voice work and the use of their names in marketing.
2. Pursue **content licensing** (start with independent/African distributors who license affordably, and public-domain or Creative Commons catalogs) so a growing share of the catalog is clean.
3. Keep the Copyright & Takedown page live and actually honor takedown requests — keep records.
4. Once you have formal VJ agreements, you MAY reintroduce VJ names in ads — but only then, and keep the agreements on file for an appeal.
