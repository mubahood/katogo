# DMCA Response Log — Apple Inc. / Irdeto USA — Google Play Suspension of LugaFlix

**Company:** Solavia Group Limited (brand: LugaFlix) · Developer account: Newline Technologies Limited
**Notice received:** 5 August 2026 (Google Play notice to mama.ugx@gmail.com)
**Complainant:** Ian Pickles, Irdeto USA, Inc., on behalf of Apple Inc. / Apple Video Programming LLC
**Complaint reference:** Lumen 92532313 · DMCA · App `lugaflix.movies` suspended
**Claimed works:** "Before", "Cape Fear", "F1 The Movie" (Apple TV content)

---

## 1. Internal verification (2026-08-06)

The production catalog database was searched for the three claimed works. **The claim was verified as factually accurate:**

| Claimed work | Found in catalog | State at time of notice |
|---|---|---|
| F1 The Movie | movie #30613 (+3 inactive duplicates #79262, #79271, #79272) | **Active**, 12 views |
| Cape Fear (2026 series) | episodes #79960–#79963; series_movies #4869 | **Active**, 56 total views |
| Before (2024 series) | series_movies #3429 with 11 episodes (#40276, #40304–#40313); +#79114 | Episodes Active |

Decision: **no counter-notice will be filed** (the content was present; a sworn denial would be false).

## 2. Remedial actions taken (2026-08-06, completed same day)

### 2a. Claimed titles — removed
- All records above set `status = Inactive` and flagged `is_blocklisted = 1` (19 movie records)
- Series catalog rows "Cape Fear" and "Before" set `is_active = No` (removed from search/browse)
- All server-side caches purged (Redis cache + 23,008 API cache files) so no cached response can serve them

### 2b. Preventive sweep — all identifiable Apple TV Originals removed
A catalog sweep against a curated list of Apple TV Original titles found **~275 additional records**. All were deactivated and blocklisted the same day, including: Severance, Silo, Foundation, The Morning Show, Hijack, Slow Horses, See, Invasion (2021 series), Monarch: Legacy of Monsters, Presumed Innocent, The Last Thing He Told Me, Killers of the Flower Moon, Napoleon, Argylle, Wolfs, Greyhound, Ghosted, The Family Plan, The Gorge, Fountain of Youth, The Instigators, Luck, Emancipation, CODA, Finch, Sugar, Pachinko.
Patterns were curated to avoid false positives (e.g. "Invasion U.S.A." (1985), "See You On Venus", "Sugar Hill", "Spirited Away" are **not** Apple works and were not touched).

**Totals: 292 movie records deactivated + blocklisted · 23 series hidden.**

### 2c. Verification
- Database: zero records matching any blocklist pattern remain Active (query-verified)
- Live API: search tested for 10 blocked titles (F1, Cape Fear, Severance, Silo, Slow Horses, Before, See, Invasion, Foundation, CODA) — **all return zero blocked results**

## 3. Permanent prevention mechanism (deployed 2026-08-06)

Three layers ensure removed content cannot return:

1. **`content_blocklist` table** — 50+ patterns (exact / SQL-like / regexp) covering the claimed works and Apple TV Originals. Adding a pattern requires one row; no code change.
2. **Write-time enforcement** — a save hook on the movie model forces any record whose title matches the blocklist to `Inactive` + flagged, regardless of which pipeline writes it (content crawler, series auto-fixer, URL repair jobs, admin edits, replica sync). *Tested: an attempt to re-activate F1 The Movie was automatically reverted at save time.*
3. **Scheduled sweep** — `content:enforce-blocklist` runs **every 30 minutes** in production, deactivating any matching movie or series that reached the database through raw-SQL paths, and flushing caches when it acts. Output logged to `storage/logs/content-blocklist.log`.

Additionally, the search API was hardened with a visibility gate: blocklisted records can never appear in search results regardless of internal scoring (this closed a pre-existing gap where inactive titles could surface in search).

## 4. Timeline summary

| Date | Event |
|---|---|
| 2026-08-05 | Google Play notice received; app `lugaflix.movies` suspended |
| 2026-08-06 18:29 EAT | Claimed titles verified present; takedown #1 executed (claimed works + duplicates) |
| 2026-08-06 ~19:00 EAT | Preventive Apple sweep executed (256 further records); blocklist table + write-time hook + 30-minute enforcement deployed to production |
| 2026-08-06 ~19:30 EAT | Live API verified clean for all blocked titles; this log written |

## 5. Outstanding / for counsel

- Google reconsideration (Appeal Web Form) — six-month window from notice; response should state removal + prevention, **not** dispute the claim
- The notice warns that further violations may terminate the developer account (would also affect UGFlix, Muno, VJJunior)
- Broader catalog licensing posture is a business-level matter beyond this incident's scope; the blocklist mechanism can absorb further notices same-day
- Contact on notice: iioperations@irdeto.com · Lumen record: lumendatabase.org/notices/92532313

*Prepared automatically from production logs and database queries, 6 August 2026. All record IDs above are verifiable in `movie_models` / `series_movies` / `content_blocklist` tables.*
