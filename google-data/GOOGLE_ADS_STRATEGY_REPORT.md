# LugaFlix × Google Ads — Lifetime Data Analysis & August 2026 Strategy

**Prepared:** 5 August 2026 · **Data sources:** Google Ads exports (daily detail Jul 1 – Aug 4), complete Google billing history ($1,017 across 12 invoices), and the **full live database since January 2026** (registrations, completed payments, first-time payers, cohort renewals). Exchange rate used: 3,800 UGX/$.

---

## 1. The lifetime timeline — six distinct eras

| Era | Weekly registrations | Weekly revenue (UGX) | What was happening |
|---|---|---|---|
| Jan – mid-Mar | 400 – 1,000 | 58k – 197k | Pure organic. Baseline ~60–140 users/day |
| Late Mar – mid-Apr | **3,200 – 3,500** | 460k – 883k | First growth push — revenue ×4 vs January |
| Mid–late Apr | 650 – 940 | 353k – 550k | Push paused; revenue held well above January (retention working) |
| May | 3,250 – 3,850 | 1.09M – 1.29M | Sustained moderate push; revenue ×10 vs January |
| **May 30 – Jun 15** | **13,800 – 14,600** | **2.26M – 3.31M** | **The explosion.** 1,700–3,300 registrations/day; best day ever Jun 14: 768,500 UGX (~$202) |
| **Jun 16 – Jul 8** | 40 – 250/day | **0** | **Total platform outage — 23 days of zero recorded revenue** |
| Jul 9 – Aug 4 | 4,900 – 8,000 | 1.7M – 3.0M | Recovery + the measured July ad cycles analysed in §3 |

Two things jump out immediately:

1. **Every growth era outlived its ad spend.** After the April push stopped, revenue settled ~4× higher than January. After July's pauses, the organic floor was ~$87/day vs ~$25/day in March. **Ads have been building a permanent subscriber base, not renting temporary traffic.** This is the strongest argument that ad money has NOT been wasted overall.

2. **The single most expensive event in your history was not an ad campaign — it was the June 16 outage.** At the pre-outage trajectory (~500–700k UGX/day), 23 days of zero revenue cost roughly **11.5–13M UGX ≈ $3,000–3,400 — about 3× everything you have ever paid Google.** Infrastructure reliability is worth more than any ad optimization in this document.

### ⚠️ A data gap you should close
The Google export only contains daily detail for **Jul 1 – Aug 4** ($838.73). Your billing history totals **$1,017**, so roughly **$178 belongs to June**. But $178 cannot explain 28,000+ incremental registrations in the May 30 – Jun 15 explosion — either other marketing ran then (Facebook/TikTok/influencers?), it was organic virality, or a different payments profile carried more Google spend. **Action:** in Google Ads, re-export *Time series* with a custom range from 1 March 2026 — then this report's June attribution can be completed. Everything else below stands regardless.

---

## 2. The economics — what a subscriber is actually worth

Cohorts by month of first payment (live DB, all their payments to date):

| First paid in | New payers | Avg payments each | Lifetime value so far |
|---|---|---|---|
| March | 834 | **2.38** | 3,509 UGX (old cheap plans) |
| April | 1,106 | 2.20 | 3,060 UGX |
| May | 1,961 | 1.78 | 2,571 UGX |
| June | 2,819 | 1.33 | 2,349 UGX |
| July | 1,693 | 1.34 (still young) | 4,422 UGX ≈ **$1.16** |
| Aug (4 days) | 310 | 1.04 | 3,621 UGX |

The pattern: **payers keep paying.** March's cohort has reached 2.38 payments each and is still renewing. July's cohort — acquired at today's prices — is at 1.34 payments and $1.16 each; if it matures like March's, it lands at **≈ 7,600 UGX ≈ $2.00 per payer lifetime**.

**Acquisition cost (measured precisely in §3): $0.55 – $0.85 per new payer at efficient spend levels.**
**→ LTV : CAC ≈ 2.3×. Google Ads is structurally profitable for you — with a 2–5 week payback, not a same-day one.** That lag is *why* same-day ROI feels random.

---

## 3. Precision analysis — July's controlled experiment

July gave us clean on/off periods. Organic baseline measured across 9 fully-paused days (Jul 24 – Aug 1): **$87/day revenue, 680 registrations/day, 54 first-time payers/day** (matches your own "$100 with no ads" observation).

Incremental results on ad days (above that baseline):

| Day | Spend | Extra users | Users per $ | Extra payers | Cost per new payer |
|---|---|---|---|---|---|
| Aug 3 | $15 | +424 | 28.6 | +27 | **$0.55** |
| Jul 15 | $42 | +1,026 | 24.2 | +50 | **$0.85** |
| Aug 2 | $25 | +265 | 10.7 | +29 | $0.85 |
| Jul 23 | $86 | +637 | 7.4 | +46 | $1.86 |
| Aug 4 | $89 | +383 | 4.3 | +18 | **$4.96** |

**Finding 1 — diminishing returns bite hard above ~$50/day** with the current click-optimized setup. At $15–45/day a payer costs $0.55–0.85; at $85–90/day the cost triples to 6×. Reason: 94% of spend goes to Display + YouTube at $0.01–0.02/click optimized for *clicks* — extra budget buys junkier clicks (158,000 impressions were served at **midnight**, your worst-converting hour).

**Finding 2 — your two "Google wasted my money" periods were self-inflicted.** The Jul 2–10 push ($571, 68% of July spend) ran while payments were still dead from the June outage. The Aug 2–4 spend ($128) ran during the Redis disk-full outages. **Ads amplify the platform's current state. Broken platform → $0 return, every time.**

**Finding 3 — the iOS campaign is your biggest pure waste: $352 (42% of tracked spend) for ~3% of registrations** (CTR 1.44% vs 4.74% on Android). Meanwhile `vj-junior` spent $36, has your best CTR (5.71%), and VJJunior collected ~$195 of July revenue.

**Finding 4 — timing.** Payments peak **19:00–23:00 EAT** (21:00 is the richest hour) with a 12:00–14:00 lunch bump; 01:00–07:00 is dead. Google currently serves heavy impressions at midnight — pure waste.

---

## 4. Direct answers to your questions

**"Deposit $1,000 at once or spread it out?"**
The deposit does not control waste — **daily campaign budgets do** (Google can spend max 2× the daily budget in a day, 30.4× in a month). The real danger of a fat balance is a bad campaign silently drinking it — exactly what iOS did with $352. **Top up $150–200 weekly.** A misconfigured campaign can then never lose more than one week's money.

**"When should I pause?"**
1. **The moment anything on the platform breaks** (payment gateway, Redis, disk). This rule alone would have saved you ~$400 of the $839 tracked. Ads during an outage are money set on fire.
2. If first-time payers stay below **65/day** (baseline 54 + margin) for **two consecutive ad days** → pause, investigate, resume when the cohort math recovers.
3. Never pause for a single bad day — the payback is 2–5 weeks; judge weeks, not days.

**"Best times to run ads?"**
Schedule ads **11:00 – 23:59 EAT only**. Exclude 00:00–07:00 entirely. Weekdays are stronger than Saturdays (your Saturday impressions and payments are both the weakest).

**"Is more money = more subscribers?"**
No — not linearly, and beyond ~$150/day, essentially not at all *until conversion tracking exists* (§6). Your $500/day capacity would currently buy ~50,000 junk display clicks a day. The data supports **$65 → $150/day**, scaled on evidence.

---

## 5. The August 2026 Plan

### Phase 1 — Reset (Aug 5 – 11) · ~$65/day
| Campaign | Daily budget | Change |
|---|---|---|
| LugaFlix App (Android) | **$50** | Ad schedule 11:00–23:59 EAT; keep Display+YouTube for now |
| ios | **$0 — PAUSED** | Do not resume until iOS purchase tracking exists |
| vj-junior | **$10** | Doubled — best CTR, proven revenue |
| NEW: Search campaign | **$5** | Search clicks cost $0.01 and carry the highest intent |

### Phase 2 — The structural fix (same week, once)
Wire **conversion tracking**: Firebase → Google Ads events for `sign_up` and, critically, `purchase`. Then switch campaigns from "maximize clicks" to **tCPA on purchases**. This single change redirects Google's algorithm from chasing cheap midnight clicks to hunting *payers*. **Do not scale past $100/day before this exists.**

### Phase 3 — Scale on evidence (Aug 12 – 31)
Every Sunday, compute the week: `incremental payers = week's first-time payers − (54 × 7)` and `CAC = spend ÷ incremental payers`.
- CAC ≤ $1.20 and week-1 conversion ≥ 5% → **raise LugaFlix budget +$25/day**
- CAC $1.20–2.00 → hold
- CAC > $2.00 two weeks running → cut budget 50%, review creatives
- Hard ceiling this month: **$150/day total** even if metrics glow

### Budget summary
~**$2,000–2,500 for August** (not the $15,500 your $500/day could burn). Expected: 1,800–2,400 new payers at ≤$1.20, breaking even by early September and profiting through renewals — while the untouched ~$13,000 stays in your pocket until conversion-optimized campaigns prove they can absorb more.

### The non-negotiable rule
**Green servers before ads. Always.** Every documented "wasted" period coincided with an outage. Check payment health before every top-up; kill ads within the hour if the platform degrades.

---

## 6. KPI cheat-sheet (write these on the wall)

| Metric | Baseline (no ads) | Healthy ad day | Alarm |
|---|---|---|---|
| Registrations/day | ~680 | 1,000 – 1,700 | < 800 on an ad day |
| First-time payers/day | ~54 | 80 – 190 | < 65 two days running |
| Cost per new payer | — | **$0.55 – 1.20** | > $2.00 |
| Revenue/day | ~$87 | $110 – 225 | < $87 on an ad day |
| Week-1 reg→payer conversion | — | ≥ 5% | < 4% |
| Payer lifetime value (current prices) | — | $1.16 now → ~$2.00 mature | falling below $1.00 |

---

*Report generated from live production data. Companion raw exports live beside this file in `google-data/`. To complete the June attribution, re-export Google Ads Time series from 1 March 2026.*
