# Phase O4 — Query & Data Access

| | |
|---|---|
| **Depends on** | O1 (the real portfolio count drives Step 1's strategy) |
| **Blocks** | — |
| **Plan stability** | **Firm** on the defects; the pagination strategy needs O1's data |

## Objective

Stop fetching and hydrating data that is never rendered. Both defects below were
found by `optimasi.md` and confirmed in the code.

---

## Step 1 — Bound the homepage portfolio query

**Verified:** `HomeController::payload()` fetches **every** published non-featured
portfolio:

```php
'portfolios' => Portfolio::published()->ordered()
    ->with('coverImage')
    ->where('is_featured', false)
    ->get(),
```

while `HomeController::PORTFOLIO_VISIBLE = 16` governs what renders. `loading="lazy"`
on the images defers the browser's *download* — it does not reduce the models
hydrated or the HTML rendered server-side. At 500 concurrent requests, hydrating
every portfolio to show 16 is pure waste, repeated 500 times.

**Choose the strategy from O1's measured count, not from this document:**

| Measured count | Strategy |
|---|---|
| ≤ ~30 | `limit(PORTFOLIO_VISIBLE)` and drop "load more" — the catalog fits |
| ~30–150 | `limit()` + a real load-more endpoint returning the next page |
| > 150 | Proper pagination, and reconsider whether the homepage should carry the full catalog at all |

- [ ] Read the real published count from O1.
- [ ] Implement the matching strategy. If load-more is kept, make it **actually
      work** — `optimasi.md` §2 notes the remainder is currently fetched for a
      behaviour that may not be wired up.
- [ ] Keep `portfolioCount` as its own cheap `count()` **only if the UI shows it**.
      It already exists separately, which is correct.
- [ ] Verify no portfolio silently disappears from the page. `task.md`'s landing
      rules forbid dropping records to make a query cheaper.

---

## Step 2 — Fix the cover-image N+1

**Verified:** `portfolio-card.blade.php:4`

```php
$cover = $portfolio->coverImage->first() ?? $portfolio->images->first();
```

Both `coverImage()` and `images()` are `HasMany` on `Portfolio`. `HomeController`
eager-loads **only** `coverImage`. So every portfolio **without** a cover triggers a
lazy load of `images` — one extra query each.

> **Correcting a claim from `rekap.md` H1:** it states that because `coverImage` is
> `hasMany`, `->with('coverImage')` eager-loads *every* image for every portfolio
> ("50 portfolios × 5 images = 250 rows"). **That is false.** The relation is
> constrained:
>
> ```php
> public function coverImage(): HasMany
> {
>     return $this->images()->where('is_cover', true);
> }
> ```
>
> It loads cover rows only. The eager load is already cheap, and converting it to
> `hasOne()->ofMany()` is an **ergonomics** improvement — it would remove the
> `->first()` in Blade — not a performance fix. Do it if you like the API; do not
> book it as an optimization.
>
> The genuine defects in that finding are the **unbounded collection** (Step 1) and
> the **unloaded `images` fallback** (this step). Both are real. The row-count
> arithmetic behind them is not.

Two valid fixes:

1. **Eager-load the fallback:** `->with(['coverImage', 'images'])`. Simple, but
   loads every image row for every portfolio — potentially worse than the N+1 it
   fixes if portfolios have many images.
2. **Constrain the eager load** so one relation serves both cases — e.g. eager-load
   `images` ordered so the cover sorts first, and select from that single loaded
   relation. Fewer rows, one query.

Prefer (2) when portfolios carry many images; (1) is acceptable when they carry
few. **Decide from O1's data.**

- [ ] Implement the chosen fix.
- [ ] **Seed portfolios without covers and count queries.** `optimasi.md` §3 is
      explicit: ideal data hides this bug entirely.
- [ ] Add a guard test: render the homepage with 20 portfolios, half lacking covers,
      and assert the query count does not scale with row count.

### `preventLazyLoading` is already enabled — which changes the diagnosis

`AppServiceProvider:38` already has:

```php
Model::preventLazyLoading(! app()->isProduction());
Model::preventAccessingMissingAttributes(! app()->isProduction());
```

That is good practice and already correctly scoped to non-production. But it
sharpens this bug considerably:

**In development, `$portfolio->images->first()` does not cost an extra query — it
throws `LazyLoadingViolationException`** whenever a portfolio has no cover. In
production it degrades to a silent N+1.

So the cover-less case is not merely slow, it is a **hard error in development**.
Two implications:

1. The fallback has almost certainly never been exercised with cover-less data —
   otherwise someone would have hit the exception. Treat it as untested code.
2. Once O1's seed data includes cover-less portfolios, this will throw immediately
   in development. **That is the bug surfacing, not a new problem.** Fix the eager
   loading; do not disable `preventLazyLoading` to make the error go away.

- [ ] Keep `preventLazyLoading` enabled. Do not weaken it to accommodate this fix.

---

## Step 3 — Value-proposition queries

The homepage runs three separate queries for the benefit, process, and deliverable
groups. They could be one query grouped in PHP.

- [ ] **Only act if O1 profiling shows it matters.** Three indexed queries on a
      small table is not obviously worse than one query plus PHP grouping, and
      `optimasi.md` §2 explicitly conditions this on profiling showing benefit.
- [ ] If merged, keep the grouping logic in the model or controller — not in Blade
      (`guide.md` §3).

This is the one item where the audit's own caution should be respected over the
instinct to consolidate.

---

## Step 4 — Index audit for P1–P7 tables

Every table added in the feature phases, checked against the queries that actually
run:

| Table | Likely query shape | Index |
|---|---|---|
| `customers` | login by email | unique on `email` (already) |
| `course_enrollments` | entitlement check: course + customer + status | composite |
| `course_lessons` | section + sort order | composite |
| `lesson_completions` | enrollment + lesson | unique composite (already) |
| `events` | published + upcoming + sort | composite |
| `event_registrations` | event + customer, event + status | composites |
| `job_applications` | vacancy + status | composite |
| `projects` | `project_code` + status | composite |
| `payments` | `order_id` (unique), payable + status | unique + composite |

- [ ] **Composite indexes for filter+sort pairs, not one single-column index per
      filter.** A `where('is_published', true)->orderBy('sort_order')` query uses a
      composite on both; two separate indexes leave the sort unindexed.
- [ ] Verify with `EXPLAIN` on the real queries, not by inspection.
- [ ] Check the entitlement query specifically — it runs on **every lesson view**
      and is the hottest query in the LMS.
- [ ] Do not add indexes speculatively. Each one slows writes, and the launch
      profile is write-heavy.

---

## Step 5 — Re-measure

- [ ] Re-run O1's query counts per page and record the deltas.
- [ ] Confirm no page issues a query per rendered row.
- [ ] Re-run the load script; record homepage response-size change from Step 1.

---

## Acceptance

- [ ] The homepage portfolio query is bounded, with the strategy justified by the
      measured count.
- [ ] The cover-image N+1 is fixed and **verified with cover-less data**.
- [ ] A guard test fails if query count scales with portfolio count.
- [ ] Composite indexes verified with `EXPLAIN` on real queries.
- [ ] Every change recorded as a measured delta against the O1 baseline.

## Note on scope discipline

`optimasi.md` declines to justify merging helpers or scopes purely to reduce line
count, and notes that repeated scope names across models are not duplication —
`scopePublished()` applies different conditions per table. That is correct and
matches `guide.md`'s stance. **This phase changes queries that measurably cost
something. It is not a refactor.**
