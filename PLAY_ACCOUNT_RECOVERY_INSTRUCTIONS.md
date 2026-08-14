# Google Play Account Recovery — Working Instructions

**Account:** Newline Technologies Limited (ID: 5021040539783560830)
**Owner emails:** mama.ugx@gmail.com (account owner), mubahood360@gmail.com
**Written:** August 11, 2026
**For:** Claude (Chrome) — follow these steps in Play Console. Read the whole file before clicking anything.

---

## 1. The situation, in plain words

On Aug 11, 2026, Google temporarily removed all apps on this account ("All Apps Removed — Multiple Violations"). This happened because three movie apps (Lugaflix, MunoApp, VJ Junior) were suspended earlier in August over a copyright takedown (Irdeto/Apple, Lumen #92532313), the appeal was denied, and Google then swept the whole account as a precaution.

Two facts verified directly in the console on Aug 11:

1. **Account-level Policy Status says: "No issues found with your developer account."** There is no active strike against the account itself. The removal email explicitly says it "does not have an immediate impact on your Google Play Developer account."
2. **School Dynamics (schooldynamics.ug) has no violation of its own** — its only listed "issue" links to the same account-wide email, and its App content page shows "You're all caught up."

Google's own email states the fix: *"Once you've reviewed your apps, made any necessary changes, and confirmed that they comply... you can republish them on Google Play by submitting an update for those apps through your Play Console."*

**So the recovery is NOT an appeal. It is: audit each keeper app → confirm it's clean → submit a version update → wait for approval → repeat.**

---

## 2. Hard rules — do not break these

1. **Do NOT submit the account-level appeal.** The appeal form is only for "if you believe our decision is incorrect." The decision was not incorrect, and a rejected appeal burns goodwill and time. The account has no strike to appeal.
2. **Do NOT file any DMCA counter-notice** for the movie apps. A counter-notice is a sworn statement that the takedown was a mistake. It wasn't. Filing one would be false and exposes the owner to direct legal action.
3. **Never touch these apps — do not update, appeal, or resubmit them:**
   - Lugaflix (`lugaflix.movies`) — suspended, copyright
   - MunoApp (`com.munoapp.free`) — suspended, copyright
   - VJ Junior (`vjjunior.movies`) — suspended, copyright
   - Hit the diamond (`com.gameinsight.gplay.island`) — suspended, appears to be someone else's game (Game Insight package name); leave it alone permanently
   - All four Nzuri Trust packages (`com.nzuritrust.app`, `com.nzuri.trust.app`, `com.nzuri.trust`, `com.trust.nzuri.app`) — this cluster has a documented "repeated app rejections" enforcement violation. Resubmitting any of them now is the single fastest way to get the account terminated. If Nzuri Trust is ever revived, it's a separate future project requiring an accurate Financial Features Declaration and a new logo, done once, on one package, with human legal review first.
4. **Never create a new package name for a previously rejected or removed app.** Google logged that pattern against this account (Nzuri Trust x4). It reads as circumvention.
5. **Submit ONE app at a time.** Wait for it to be approved and live before starting the next. A rejection mid-batch would hit every pending app.
6. **Don't rush declarations.** Every form answer must be literally true. An inaccurate declaration (the Nzuri Trust mistake) is itself a violation.

---

## 3. Resubmission order (keepers only)

Work through this list top to bottom, one at a time. These are community/business apps with no individual violation on record (except Staff Performance and Abanoonya, which have specific known fixes noted below).

| # | App | Package | Known issue to fix first |
|---|-----|---------|--------------------------|
| 1 | School Dynamics - Uganda | schooldynamics.ug | None — verified clean Aug 11 |
| 2 | Muteesa 1 Royal University | ug.ac.mru.mru_app | None on record |
| 3 | YMCA Mobile App | yci.ac.ug | None on record |
| 4 | UGNEWS24 | com.ugnews24 | None on record |
| 5 | Musenene Family App | musenene.com | None on record |
| 6 | Al Suk - South Sudan | alsukssd.com | None on record |
| 7 | DTEHM Members App | com.dtehm.insurance | Insurance app — verify Financial Features Declaration is accurate before submitting |
| 8 | Budget Dynamics | com.budget.dynamics | Finance category — same check as above |
| 9 | GlobalHealth Pay | globalhealthrescue.com | Payments/health — same check, plus health claims review |
| 10 | Pro-Outfits, FengwoBay, Staff Performance, Afriinventions (za), Abanoonya Pro | various | See notes below; lowest priority |

**Staff Performance:** removed in 2024 only because review credentials were never provided. Fix = App content → App access → provide working demo login credentials (get them from Muhindo), then resubmit.

**Abanoonya Pro:** dating app flagged for inaccurate content rating. Fix = redo the content rating questionnaire honestly (declare it as a dating app, 18+), and confirm the app has user blocking/reporting before resubmitting. If unsure, skip this app.

---

## 4. Per-app procedure (repeat for each app in order)

For each app, do steps A–D in Play Console. Step E needs Muhindo's developer.

**A. Check policy status.**
Open the app → Monitor and improve → Policy and programs → Policy status.
- If the only issue shown is "Removed due to multiple violations (Aug 11, 2026)" → proceed.
- If ANY other specific violation is listed → STOP on this app, write down exactly what it says, report back, and move to the next app.

**B. Check App content declarations.**
App → Monitor and improve → Policy and programs → App content.
- "Need attention" tab must be empty ("You're all caught up").
- On the "Actioned" tab, spot-check: Privacy policy URL (must load and be real), App access (credentials if login required), Data safety (must match what the app actually collects), Content rating, Target audience.
- Anything missing or wrong: fix it if it's factual and you have the information; otherwise note it and ask Muhindo.

**C. Check store listing.**
Grow users → Store presence → Main store listing.
- Description must not overpromise or mention third-party brands.
- Screenshots/icon must be the app's own artwork.

**D. Confirm no copyrighted or third-party content.**
This is a judgment check: if the app streams, plays, or displays any movies, music, or branded content the company doesn't own, STOP and flag it. (School apps, university apps, news apps with own content are fine.)

**E. Submit the update (requires a new build from the developer).**
Google requires a new release to trigger re-review. The developer must:
1. Bump `versionCode` (and versionName) in the existing codebase — no other changes needed if the app is clean.
2. Build a signed AAB.
3. In Play Console: Test and release → Production → Create new release → upload AAB → add release note "Compliance review update" → Save → Send for review.

Then WAIT. Review typically takes 1–7 days. Do not submit the next app until this one shows "Live" (or is rejected — in which case record the exact rejection reason and stop everything until it's understood).

---

## 5. What to say if a form asks for an explanation

If any resubmission flow asks for comments/notes to the review team, use this (it is true):

> Our account received an account-wide temporary removal on Aug 11, 2026 following copyright suspensions of three unrelated apps, which we have permanently discontinued. This app is an independent product ([one line: e.g., "a school administration system used by Ugandan schools"]) containing only our own content. We have reviewed it against the Developer Program Policies, confirmed all declarations are accurate, and are resubmitting it in accordance with the instructions in Google's Aug 11 notification email.

Adjust the one-line description per app. Never claim the movie apps were a mistake or misidentified.

---

## 6. What NOT to expect

- There is no single form that "restores the account" — the account isn't suspended. Restoration happens app by app, via updates, exactly as Google's email says.
- The three movie apps and their install bases (35K+ combined) are gone from Play permanently. Distribution for LugaFlix continues off-Play (movies.mruodel.com/app, APKPure, Aptoide) — separate project, separate rules, and the catalog/copyright question still applies there.
- If at any point Google sends a NEW email about the account (not an app), stop all submissions and show it to Muhindo immediately.

---

## 7. Success criteria

1. School Dynamics live again on Play — first proof the path works.
2. Keeper apps restored one by one over the following weeks.
3. Zero new violations logged.
4. Movie apps and Nzuri Trust packages untouched.

That's the whole plan. Slow is fast here: one clean submission at a time rebuilds the account's standing; one careless submission ends it.
