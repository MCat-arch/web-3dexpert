# 🔴 Black-Hat Analysis Report — 3D Expert Web

**Date:** 2026-10-08
**Reviewer:** AI-assisted security/architecture review
**Scope:** All documentation (`guide.md`, `notes.md`, `task.md`, `docs/plans/`), existing codebase, and live views.

**Status color codes:**
- 🟥 **CRITICAL** — must fix before any further work
- 🟧 **HIGH** — blocks a phase or undermines a stated goal
- 🟨 **MEDIUM** — correct now, becomes a real problem later
- 🟩 **LOW** — polish / documentation truth

---

## 🟥 CRITICAL — Findings That Break the Live App Today

### C1. Views Reference Routes That Do Not Exist — Homepage 500s

**Severity:** 🔴 Critical (live production breakage)
**Source:** `routes/web.php` (37 lines — only `home` and `media.show`), vs. views that reference:

| Route referenced | File(s) | Exists? |
|---|---|---|
| `route('contact.create')` | `home.blade.php:81`, `partials/header.blade.php:48,93`, `portfolio/show.blade.php:131` | ❌ NO |
| `route('careers.index')` | `partials/header.blade.php:45,90` | ❌ NO |
| `route('portfolio.index')` | `portfolio/show.blade.php:27` | ❌ NO |
| `route('portfolio.show', …)` | `home.blade.php:127`, `portfolio/show.blade.php:15` | ❌ NO |

**Evidence:** `grep` across `routes/web.php` and `app/` showed **zero** registrations for `contact.*`, `careers.*`, or `portfolio.*`. The only routes are `GET /` and `GET /media/{path}`.

**Impact:** Every request to `/` (homepage) includes `partials/header.blade.php` which calls `route('contact.create')` and `route('careers.index')` — both throw `Symfony\Component\Routing\Exception\RouteNotFoundException`. The homepage **500s globally**.

**Contradiction with D12:** notes.md §3.6 (D12) explicitly says *"no contact form, no submission endpoint, and no `contact.create` route"* and that the views already do this correctly — "the views do it today". This is **unverified, and false**. The views still reference the dead route.

**Fix (same change):**
1. In `resources/views/partials/header.blade.php`, replace:
   - `route('contact.create')` → `$whatsapp` (the site's `company_whatsapp` URL) for the nav "Kontak" item, or use `route('home') . '#kontak'`.
   - `route('careers.index')` → either add a real careers route or link to `route('home') . '#kontak'` (legacy behavior).
2. In `resources/views/home.blade.php:81`, replace `route('contact.create')` → the WhatsApp URL (this is the "konsultasi gratis" CTA).
3. In `resources/views/portfolio/show.blade.php` (untracked, likely stale), either remove the view or create the portfolio routes that its references expect (`portfolio.index`, `portfolio.show`).
4. Run `php artisan route:list` and confirm no undefined-route references remain. Re-render `/` in a browser.

---

## 🟥 CRITICAL — Next-Breaking Bugs in the Plans

### C2. P0 Plan vs. D12 Directly Contradict Each Other

**Severity:** 🔴 Critical (follower of one doc breaks the other's guarantee)
**Sources:**
- `docs/plans/P0-infrastructure.md` Step 1: *"If it is dead code, delete the test, the request, the model, and its migration"* — for ContactFormTest/ContactRequest/ContactSubmission.
- `notes.md` §3.6 (D12): *"ContactFormTest is the guard, not a casualty... must be converted to PHPUnit and kept."*

**Impact:** Whoever implements P0 will look at only one doc and get the wrong answer. Following P0 as written deletes the test that ensures `/kontak` / `/contact` return 404 — **removing the D12 enforcement**.

**Fix:** Update `P0-infrastructure.md` to align with D12: convert `ContactFormTest` to PHPUnit and **keep** it as a security test. Then remove the dead artifacts (`ContactRequest`, `ContactSubmission`, `contact_submissions` migration, `Service::contactSubmissions()` relation, `lang/contact.php`) **in one change** as D12 dictates.

### C3. `ContentObserver` Not Registered on `MediaAsset` — Stale Media After Editor Edit

**Severity:** 🔴 Critical (editors see stale images, cache is supposed to invalidate)
**Source:** `app/Providers/AppServiceProvider.php` — the observer registration list includes 10 models but **not** `MediaAsset`.

**Impact:** When an editor replaces a hero image or a portfolio cover, `ContentObserver` is not triggered, so the homepage cache (`home.{locale}`) is **not invalidated**. Visitors see the previous hero/cover until the TTL expires.

**Fix:** Add `MediaAsset::class` to the `$model::observe(ContentObserver::class)` list in `AppServiceProvider`.

---

## 🟧 HIGH — Performance Issues

### H1. HomeController Loads All Portfolios + `coverImage` Loads ALL Images

**Severity:** 🟧 High (violates the stated P9 O(1) target and scales linearly)
**Source:** `app/Http/Controllers/HomeController.php`

```php
'portfolios' => Portfolio::published()->ordered()
    ->with('coverImage')   // <-- hasMany, not hasOne!
    ->get(),
```

The view does `->take(16)`, but the controller loads **every** published row. Furthermore, `coverImage` is defined as a `hasMany` relation, meaning `->with('coverImage')` eager-loads **all** images for every portfolio row — not just the cover. For 50 portfolios × 5 images = 250 rows loaded, when 16 covers would do.

**Fix:**
1. Replace `coverImage` with a `hasOne` using `ofMany(is_cover = true)` so `->with('coverImage')` loads only one image per portfolio.
2. Add a `->limit(16)` or pagination limit on the collection itself, not just the view's `take()` (the full collection still costs 12+ queries).

### H2. `CourseLessonPolicy::view()` — N+1 in Course Outline

**Severity:** 🟧 High (violates P9's O(1) course-detail requirement)
**Source:** `docs/plans/P3-course-catalog-entitlement.md` Step 3

```php
return $customer !== null
    && CourseEnrollment::query()
        ->where('course_id', $lesson->section->course_id)   // needs $lesson->section → 1 query
        ->where('customer_id', $customer->id)
        ->active()
        ->exists();                                          // another query
```

Rendered for 30 lessons in a course outline = **60 queries** just for the gate, on top of the course/section queries.

**Fix:** On the course detail page, first load the customer's active enrollment for that course in **one query**, then pass that boolean into the lesson serialization instead of re-checking per lesson. The gate still works for direct lesson URLs; the page just moves the check up.

### H3. Homepage Cache Invalidation Is Still Broken (P0 Step 5 Depends on a Later Fix)

**Severity:** 🟧 High (the README's claim of "homepage cached as a unit" is false)
**Source:** `docs/plans/P0-infrastructure.md` Step 5, verified in code.

- `ContentObserver` forgets `home.{locale}`.
- `HomeController` **never writes** that key → the cache is a no-op, and the observer is only "invalidating" keys that never existed.
- README still claims caching as a unit.

**Confirmed still broken in the current working tree.** P0's plan addresses this, but it needs to be done before claiming caching works.

---

## 🟧 HIGH — Security Issues

### H4. P9's CSP Step 3 Is Already Done — Plan Is Stale

**Severity:** 🟧 High (a phase plan that's wrong needs correction before it's followed)
**Source:** `app/Http/Middleware/SecurityHeaders.php`, `docs/plans/P9-hardening-signoff.md` Step 3.

The plan says to **add**:
- `frame-src https://www.youtube-nocookie.com`
- `img-src https://i.ytimg.com`

Both are **already present** in `SecurityHeaders.php` today (line 42 for frames, line 39 for images). Following P9 as written would double-add and possibly remove the existing line if someone blindly replaces it.

**Fix:** Update P9 Step 3 to: *"confirm `frame-src` / `img-src` already include these — verify, do not add again."*

### H5. Midtrans Snap Will Likely Be Blocked by the Strict CSP

**Severity:** 🟧 High (if the strict CSP is kept, Snap checkout breaks)
**Source:** `app/Http/Middleware/SecurityHeaders.php` (`script-src 'self'`), P9 Step 3 ("Do not add unsafe-inline or unsafe-eval").

Midtrans's Snap integration commonly injects an inline `<script>` and may use `eval` for rendering. With `script-src 'self'` and no nonce/hash, the checkout button **will be blocked**.

**Fix:** Decide deliberately in P9:
- Option A: **Deeplink-only flow** — redirect users to `app.midtrans.com/payment` directly (no Snap iframe). Then no CSP change needed beyond `default-src` / `form-action` to allow the Midtrans domain.
- Option B: Add a narrow CSP exception for the checkout page only (e.g. `script-src 'self' 'unsafe-inline' https://app.midtrans.com`) with a written justification, documented in `SecurityHeaders.php`.

---

## 🟨 MEDIUM — Correct-Now, Problem-Later

### M1. Orphaned Applicant Documents on Failed Submission

**Severity:** 🟨 Medium (PII accumulates in private storage)
**Source:** P6 Step 5 — files are stored in the transaction, then notifications dispatched after commit.

If validation fails after `store()` or the user abandons the form mid-payload, the file remains on disk with **no `job_application_documents` row**. Orphaned CVs multiply silently.

**Fix:** Add a scheduled cleanup (can piggyback P8's retention job) that deletes files with no corresponding DB row, older than e.g. 24h.

### M2. Concurrent Duplicate Enrollment Throws a 500

**Severity:** 🟨 Medium (bad UX, not a security breach)
**Source:** P3 `StartCoursePurchase` and P5 `EnrollInEvent` both rely on unique indexes to prevent duplicates, but do a pre-check **before** the claim.

Two concurrent requests from the **same** customer both pass the pre-check; the loser hits the unique index and Laravel throws a `QueryException` → 500. Should be caught and return a clean "already enrolled" response.

**Fix:** In both Actions, wrap the create in a try/catch for `UniqueConstraintViolationException` and return an idempotent "already exists" response.

### M3. No Seat-Counter Reconciliation

**Severity:** 🟨 Medium (drift accumulates silently)
**Source:** P5 Step 3 — `seats_taken` is a denormalized counter incremented/decremented in many places.

If a decrement is missed (refund path, admin override, partial transaction), `seats_taken` drifts from actual registrations and the event sells out early or thousands of seats appear free.

**Fix:** A nightly reconciliation job comparing `seats_taken` against `pending` + `confirmed` non-cancelled count, with an audit-log on mismatch.

### M4. `CourseLessonPolicy` Needs an Explicit Guard

**Severity:** 🟨 Medium (misconfigured guard returns wrong user)
**Source:** P3 code snippet — the Policy signature is `view(?Customer $customer, CourseLesson $lesson)`.

Laravel's `Gate::allows()` resolves the user from the **default guard**. If the admin (`web` guard) visits a course page, `$customer` is `null` (because the default auth user is a `User`, not a `Customer`), and the lesson stays locked even for a staff member previewing. Conversely, if the default is changed to `customer`, staff lose the view.

**Fix:** In the controller or policy, explicitly fetch `auth('customer')->user()` and pass it in; never rely on the default guard.

---

## 🟩 LOW — Documentation & Consistency

### L1. Dead Artifact List in D12 Is Incomplete

**Severity:** 🟩 Low
**Source:** `notes.md` §3.6 table.

Missing from the removal list:
- `resources/views/careers/` directory — contains `index.blade.php`?
- `resources/views/portfolio/show.blade.php` — untracked, likely stale
- `app/Http/Controllers/ContactController.php` — search showed it does **not** exist (already handled)
- `resources/views/pages/coming-soon.blade.php` — likely stale

### L2. P9 Table Lists `youtube.com` but the Middleware Uses `youtube-nocookie.com`

**Severity:** 🟩 Low (consistency)
**Source:** P9 Step 3 says `frame-src https://www.youtube-nocookie.com` — correct. Current middleware already has this. No action, just noting that the P9 table could be clearer.

### L3. README Claim of Homepage Caching Is False

**Severity:** 🟩 Low (documentation truth)
**Source:** `README.md:102` says "homepage cached as a unit, invalidated by ContentObserver".

Verified: HomeController doesn't write the key, so the claim is false today. P0 Step 5 fixes this; the README fix is part of that same change.

---

## ✅ What the Plans Get Right

These are worth calling out because they are the reason the plan is broadly sound despite the above:

| Area | What's right |
|---|---|
| **Entitlement gate** | P3's `LessonPayload` + Single Policy path is the correct pattern: one code path decides whether `youtube_id` is serialized. No Blade reads it directly. |
| **Event capacity** | Conditional atomic update (`whereColumn('seats_taken', '<', 'capacity')->increment('seats_taken')`) is the correct concurrency approach; P5 honestly states PHPUnit-sequential tests don't prove parallelism. |
| **P7 Uniform negative** | `whereNot('status', completed)` + verification-in-query makes all three negative cases `null` by construction. No distinguishable message can leak. |
| **D9 Payment-first** | Polymorphic `payments` built standalone before consumers is the right ordering. |
| **D1 Guard isolation** | Separate `customers` table + guard + structural non-implementation of `FilamentUser` is the correct way to close the legacy `reg.php` escalation path. |
| **Idempotent webhook** | Keyed on order_id + status transition, with `lockForUpdate` and `tries`/`backoff` — correct. |
| **P6 Document ownership** | `job_applications.customer_id = nullOnDelete` + snapshotted identity — exactly the stated requirement. |
| **HasYoutubeVideo reuse** | No second URL parser — correct DRY. |

---

## Priority Order Summary

1. 🔴 **Fix homepage 500** — remove/replace undefined `route('contact.create')` / `route('careers.index')` / `route('portfolio.*')` references. This is live-breaking today.
2. 🔴 **Register `MediaAsset` in `ContentObserver`** — editors currently cannot invalidate the media cache.
3. 🔴 **Resolve P0-vs-D12 contradiction** — update P0 to keep ContactFormTest.
4. 🟧 **Fix HomeController queries** — convert `coverImage` to `hasOne`, limit collection.
5. 🟧 **Batch entitlement checks** in course outline — eliminate per-lesson gate queries.
6. 🟧 **Decide Midtrans delivery** (deeplink vs Snap+CSP exception) before P2/P3.
7. 🟨 Catch `UniqueConstraintViolationException` in P3/P5 concurrent enrolls.
8. 🟨 Add orphaned-document cleanup to P6/P8.
9. 🟨 Seat-counter reconciliation in P5.
10. 🟥 Update P9 Step 3 to reflect that CSP already has `youtube-nocookie.com` / `i.ytimg.com`.