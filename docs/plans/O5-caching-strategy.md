# Phase O5 — Caching Strategy

| | |
|---|---|
| **Depends on** | O1, P0 (which introduced the homepage cache) |
| **Blocks** | — |
| **Plan stability** | **Firm** on rules; TTLs need O1's hit-rate data |

## Objective

Make caching deliberate and consistent, and make sure no cache leaks data across
trust boundaries or hides live availability.

`optimasi.md` §4 asks for exactly one thing here: **pick one behaviour and make the
implementation, the comment, and the invalidation agree.** P0 chose it; this phase
verifies it and extends the pattern safely.

---

## Step 1 — Verify the homepage cache contract

P0 fixed the orphaned `home.{locale}` key — `ContentObserver:31` was forgetting a
key `HomeController` never wrote, while README claimed the payload was "cached as a
unit".

- [ ] Confirm the key is written and forgotten through **one shared constant**, not
      two string literals (`guide.md` §5 rule 5). This is the defect that caused the
      original bug.
- [ ] Confirm it is **per locale**. A single key would serve Indonesian content to
      `/en`.
- [ ] Confirm the code comment matches what the code does. The original comment
      described caching that had been removed — that mismatch is what made this bug
      survive.
- [ ] Confirm README matches the implementation (P0 was asked to correct it).
- [ ] Verify the cached payload rehydrates usable models, including relations like
      `coverImage`. The original failure was `__PHP_Incomplete_Class` from the
      database cache store; Redis should resolve it, but **assert it rather than
      assume it**.

---

## Step 2 — Stampede protection everywhere

P0 added this for the homepage. The same risk applies to every eagerly-invalidated
cache.

The failure mode: `ContentObserver` forgets the key the instant an editor saves.
With 500 requests in flight, all 500 miss simultaneously and all 500 run the full
payload. One admin edit during launch becomes a self-inflicted load spike.

- [ ] Use `Cache::flexible()` (stale-while-revalidate, Laravel 11+) so one worker
      refreshes while others serve slightly stale content, **or** `Cache::lock()` so
      exactly one regenerates and the rest wait briefly.
- [ ] Apply to every cache whose invalidation is observer-driven, not just the
      homepage.
- [ ] Keep eager invalidation. It is correct for editors — the original
      `ContentObserver` comment is right that a TTL-only cache would make editors
      wait out a timer. Eager invalidation plus a single-flight guard gives both.
- [ ] Test it: warm the cache, forget the key, fire concurrent requests, and assert
      the payload is generated **once**.

---

## Step 3 — Extend to the feature lists

Each with its invalidating observer **in the same change** (`guide.md` §5 rule 1):

| Cache | Invalidated by |
|---|---|
| Published course catalog | `Course`, `CourseSection`, `CourseLesson` saves |
| Published event list | `Event` saves |
| Published vacancy list | `JobVacancy` saves |
| Per-enrollment course progress | `CompleteLesson` |

- [ ] Define each key as a constant on the model that owns it, referenced by its
      invalidator.
- [ ] Per-enrollment progress is keyed per enrollment, so it carries no
      cross-tenant risk — unlike the two prohibitions below.

---

## Step 4 — Two hard prohibitions

**Never cache availability.**

`events.seats_taken` and any remaining-capacity figure must read live. A stale
count either oversells a capacity-limited event or hides seats that are actually
free. Cache the event's *content* — title, description, schedule — and read
availability on every request. It is one indexed read.

- [ ] Audit the event list and detail views: no cached fragment may contain a seat
      count or an "almost full" badge computed at cache time.

**Never share a cache key across entitlement states.**

A cached lesson fragment carrying a `youtube_id` to a non-purchaser is the P3
paywall leak arriving by a different route (`notes.md` §5, risk 1). The P3 gate is
server-side and correct; a careless cache bypasses it entirely.

- [ ] No lesson payload is cached in a shared key. Per-entitlement caching is
      possible but is not worth the risk for this content volume — prefer not
      caching it at all.
- [ ] After any caching added here, **re-run `CourseEntitlementTest`** and manually
      read page source as a guest. P9 repeats this check for exactly this reason.

---

## Step 5 — TTLs and memory

- [ ] Set TTLs as a backstop behind eager invalidation, not as the primary
      mechanism. If invalidation fails, a TTL bounds the staleness.
- [ ] Size Redis `maxmemory` with an eviction policy appropriate to mixed use —
      note that cache, session, **and** queue share this instance (P0). An eviction
      policy that discards queued jobs would be catastrophic; use separate databases
      or separate instances if that risk is real.
- [ ] Measure hit rate under O1's load runs. A low hit rate on the homepage means
      something is flushing too aggressively — most likely an observer attached to a
      model that changes often.

---

## Step 6 — `Settings` accessor

`app/Support/Settings.php` memoizes settings per request and flushes its own cache
key on write (`optimasi.md` §4 describes this correctly).

- [ ] Confirm the per-request memoization is still in place — it prevents repeated
      settings queries within one request, which matters because the header, footer,
      and several components each read settings.
- [ ] Confirm `Settings::flush()` is called by `ContentObserver` (it is) and that
      the settings cache key is also stampede-guarded, since every page reads it.

---

## Validation

```bash
php artisan test --filter=HomepageCache
php artisan test --filter=CourseEntitlement
php artisan tinker --execute 'Cache::forget("home.id"); /* then fire concurrent requests */'
```

- [ ] Hit rate recorded from O1's load runs, before and after.
- [ ] An editor save during a load run does **not** produce a latency spike.
- [ ] Concurrent requests after an invalidation generate the payload once.

## Acceptance

- [ ] One deliberate homepage cache behaviour, with code, comment, invalidation,
      and README all agreeing.
- [ ] Every eagerly-invalidated cache has stampede protection.
- [ ] No availability number is cached.
- [ ] No entitlement-varying payload shares a cache key — re-verified by test.
- [ ] Cache hit rate measured, not assumed.
