# Implementation Plans

One file per phase. Each plan is the concrete build instruction for that phase:
files to create, classes, migration columns, tests, and executable validation.

**Read first:** `guide.md` (how we build) and `notes.md` (what and why — decisions
D1–D19, assumptions A1–A24, the performance analysis in §9).
**Track progress in:** `task.md` — these plans expand its checklists, they do not
replace them.

## Implementation order (decided 2026-10-08)

**Launch early, then keep building.** The launch profile is register → verify →
browse → enroll in one **free** event, so launch needs neither payments nor the
LMS. Build the minimum, load-test it, launch, then continue.

### Wave 1 — Launch critical

| # | Phase | Why | Size |
|---|---|---|---|
| 1 | **T0** Triage | Site returns HTTP 500. Nothing can be built or verified against it | S |
| 2 | **P0** Infrastructure | Tests cannot run; no queue, mail, or Redis | M |
| 3 | **P1** Customer identity | Gates everything. Also closes the `/admin` escalation path and fixes the D17 rate-limit bug | M |
| 4 | **P5a** Events — free path only | **Needs no payments.** This is the launch action | M |

### Wave 2 — Launch readiness, then launch

| # | Phase | Why | Size |
|---|---|---|---|
| 5 | **O1** Baseline | `[!]` Needs the hosting decision first. Everything after is measured against this | M |
| 6 | **O2** Request-path cost | Bcrypt 12→10 is the single biggest launch-path win | S |
| 7 | **O3** Media delivery | Images through PHP exhaust workers. Top traffic risk | M–L |
| 8 | **O4** Queries | Homepage is step 1 of the launch path: unbounded portfolio query + N+1 | S–M |
| 9 | **O5** Caching | Homepage and event-list caching, with stampede guards | S–M |
| 10 | **O6** Burst handling | Mail burst and the single-event seat hotspot | M |
| 11 | **O7** Rehearsal | Prove the numbers → **LAUNCH** | M |

> **Scope O4 and O5 to what exists in Wave 2.** There is no course catalog or
> vacancy list yet, so their index audits and cache lists cover only the customer,
> event, and landing tables. Both get revisited in Wave 4.

### Wave 3 — Post-launch features

| # | Phase | Note |
|---|---|---|
| 12 | **P7** Progress tracker | Cheapest win in the plan; needs only P0 and touches nothing else |
| 13 | **P2** Payment foundation | Deferred safely — nothing before this needed it |
| 14 | **P5b** Events — paid path | Plugs into P2 via the `Payable` contract |
| 15 | **P3** Course catalog & entitlement | Largest phase. Contains the paywall gate |
| 16 | **P4** Course delivery & progress | |
| 17 | **P6** Career applications | |

### Wave 4 — Consolidation

| # | Phase | Note |
|---|---|---|
| 18 | **P8** Admin, RBAC, retention | Needs P1–P7 present |
| 19 | **P9** Hardening & sign-off | |
| 20 | **O4 / O5 / O7 revisit** | Re-audit indexes and caches for the Wave 3 tables; re-run the full-path load test |

### Why optimization still sits after features

The user's priority is features first, optimization second. Wave 2 does not break
that — it optimizes only the features launch actually uses. The 500-user burst
**is** launch day, and bcrypt cost, PHP-served images, and the IP rate-limit bug all
sit directly on the register/browse/enroll path. Deferring those past launch would
mean launching into the one load profile they fail under.

---

## Triage — do this first

| Phase | Plan | Status |
|---|---|---|
| **T0** | [Triage: Restore Page Rendering](T0-triage-restore-rendering.md) | **Not started — the site returns HTTP 500 on every page** |

T0 is not optimization. It is a broken application: four route names are called
from Blade and never registered, and two of the call sites are in the layout, so
nothing renders. Nothing downstream can be validated until it is fixed (D14).

## Features (P phases)

| Phase | Plan | Depends on | Status |
|---|---|---|---|
| P0 | [Infrastructure Prerequisites](P0-infrastructure.md) | T0 | Not started |
| P1 | [Customer Identity & Guard Isolation](P1-customer-identity.md) | P0 | Not started |
| P2 | [Payment Foundation (Midtrans)](P2-payment-foundation.md) | P1 | Not started |
| P3 | [Course Catalog, Purchase & Entitlement](P3-course-catalog-entitlement.md) | P2 | Not started |
| P4 | [Course Delivery, Progress & Quizzes](P4-course-delivery-progress.md) | P3 | Not started |
| P5 | [Events: Free & Paid Enrollment](P5-events.md) | P2 | Not started |
| P6 | [Career: Applications & Documents](P6-career-applications.md) | P1 | Not started |
| P7 | [Progress Tracker](P7-progress-tracker.md) | P0 | Not started |
| P8 | [Admin Consolidation, RBAC & Retention](P8-admin-rbac-retention.md) | P1–P7 | Not started |
| P9 | [Hardening, Load & Sign-off](P9-hardening-signoff.md) | P0–P8 | Not started |

## Performance & launch capacity (O phases)

Second priority, after the feature plan — the user's explicit ordering. Target:
~500 concurrent users registering, verifying, browsing, and enrolling in one event.
Analysis in `notes.md` §9.

| Phase | Plan | Depends on | Status |
|---|---|---|---|
| O1 | [Measurement Baseline](O1-measurement-baseline.md) | P9 | Not started |
| O2 | [Request-Path Cost](O2-request-path-cost.md) | O1 | Not started |
| O3 | [Media & Static Asset Delivery](O3-media-static-delivery.md) | O1 | Not started |
| O4 | [Query & Data Access](O4-query-data-access.md) | O1 | Not started |
| O5 | [Caching Strategy](O5-caching-strategy.md) | O1, P0 | Not started |
| O6 | [Launch Burst Handling](O6-launch-burst-handling.md) | O1, P1, P5 | Not started |
| O7 | [Launch Rehearsal & Sign-off](O7-launch-rehearsal.md) | O1–O6, P9 | Not started |

## Dependency graph

```
T0 (triage — site is down)
 │
P0 (infra)
 ├── P1 (identity) ──┬── P5a (events, FREE path — needs NO payments)
 │                   ├── P6 (career)
 │                   └── P2 (payments) ──┬── P3 (courses) ── P4 (delivery)
 │                                       └── P5b (events, PAID path)
 └── P7 (progress tracker — needs neither identity nor payments)

P8 (admin) ← P1–P7 · P9 (hardening) last
 │
O1 (baseline) ──┬── O2 (request cost) ── O3 (media) ── O4 (queries) ── O5 (caching)
                └── O6 (burst handling)
O7 (rehearsal) last
```

`P7` needs only `P0` and can be pulled forward for an early demonstrable result.
`P5` and `P6` are independent of each other. `O1` must precede every other O phase
(D18) — `optimasi.md` is explicitly a static audit with no load test, and several
of its recommendations depend on data it did not have.

## Three items deliberately pulled out of the O phases

Optimization comes after features, with these exceptions — they are a different
*kind* of problem, not a higher priority (`notes.md` §9.5):

1. **T0** — the 500 errors. Triage.
2. **P1** — registration must not be rate-limited by IP alone (D17). A correctness
   bug: CGNAT and shared venue WiFi would 429 most of a 500-person launch.
3. **P0** — homepage cache stampede protection. Belongs with the cache it guards,
   same as invalidation does.

## Plan stability

These were written up front, before any phase was built. Later plans are therefore
**predictions, not instructions**: what P2 learns about Midtrans will change P3 and
P5; what O1 measures may reorder O2–O6 entirely.

Each file carries a **Plan stability** field:

- **Firm** — grounded in verified facts about this codebase. Build it as written.
- **Expected revision** — directionally right; re-read and update after its
  dependency phase completes.

Revise the file when reality diverges. A plan nobody updated is worse than no plan,
because it gets followed.

## On the Status column

`optimasi.md` correctly flagged that this table once said "Not started" for every
phase while a plan file recorded completed work. **The statuses here are a
checklist, not evidence.** Do not infer from them that code does or does not exist —
verify against the codebase. Update this column as phases complete, and keep it
honest when they partially complete.

## Working agreement

- Do not start a phase until its dependency phases pass their acceptance criteria.
- Every `[!]` in `task.md` is a blocked decision. Resolve it with the user.
- Update `task.md` checkboxes as you go, and the Status column above per phase.
- Record anything surprising in `notes.md`, not in a new file.
- Never claim verification you did not perform (`guide.md` §10.6).
