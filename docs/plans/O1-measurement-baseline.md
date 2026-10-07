# Phase O1 — Measurement Baseline

| | |
|---|---|
| **Depends on** | T0 (a 500 cannot be benchmarked), P0–P9 complete |
| **Blocks** | O2–O7 |
| **Blocked by** | `[!]` Hosting decision (A18) |
| **Plan stability** | **Firm** on method; targets depend on the hosting choice |

## Objective

Establish what the application actually does under load, before changing anything.
Decision **D18**.

`optimasi.md` is explicit about its own limit: *"tidak dilakukan load test,
profiling PHP, penghitungan query pada database aktif"* — no load test, no PHP
profiling, no query counting against a live database. Several of its
recommendations (notably the portfolio pagination strategy) depend on data it did
not have. This phase supplies it.

**Nothing in O2–O7 may claim an improvement without a baseline to compare against.**

---

## Step 0 — Decide hosting first `[!]`

A baseline on the wrong machine is worse than none, because every later decision
inherits it.

Recommended minimum for a 500-user simultaneous burst (`notes.md` §9.6):

| Component | Target |
|---|---|
| Compute | 4 vCPU / 8 GB |
| Web | nginx + PHP-FPM 8.4, opcache on |
| PHP-FPM | `max_children` 32–40 |
| Database | MySQL 8, `max_connections` ≈ 64 |
| Cache/queue/session | Redis, 512 MB–1 GB |
| Queue workers | 4–8, mail rate-limited |

> **Shared cPanel hosting cannot meet this.** No Redis, no Supervisor, no PHP-FPM
> control, hard CPU and process caps. If that is the hosting direction, say so now:
> the goal changes from "survive 500 concurrent" to "find and document the real
> ceiling", and P0's Redis step needs its documented fallback instead.

- [ ] Decide hosting and record it in `notes.md`, replacing assumption A18.
- [ ] Provision a staging environment matching production. Benchmarking a Windows
      dev box tells you about the dev box.

---

## Step 1 — Representative seed data

An N+1 is invisible at two rows. The audit's own validation list asks for the real
published-portfolio count before choosing a pagination strategy.

- [ ] Seed the **real** published-portfolio count (query production, don't guess).
- [ ] Seed all 25 benefits, the 10 FAQs, 4 services, and real testimonial counts.
- [ ] Seed portfolios **without** a cover image. `optimasi.md` §3 is explicit that
      ideal data hides the cover-fallback N+1 — the fallback only fires when
      `coverImage` is empty.
- [ ] Seed courses with sections and lessons, several events, and vacancies.
- [ ] Seed ~500 customers so login and enrollment paths run against a realistic table.
- [ ] Record every count in `notes.md`. The numbers are the baseline's context.

---

## Step 2 — Query counting

Per page, with the seeded data:

```php
DB::listen(fn ($q) => logger()->info($q->sql));
```

Or Boost's `database-query`. Record counts for: homepage (cached and uncached),
course catalog, course detail, lesson player, my-courses dashboard, event list,
event detail, vacancy list, vacancy detail, progress lookup, register, login,
enroll.

- [ ] Record the count **and** the response size for each.
- [ ] Note specifically: how many extra queries appear when portfolios lack covers.
- [ ] Note how many queries the homepage issues **per asset request** — `MediaController`
      runs one indexed lookup, then possibly a second BLOB query.

---

## Step 3 — Load-test harness

Model the **launch path**, not just homepage GETs:

```
homepage → register → (verify) → login → browse events → enroll
```

- [ ] Choose a tool (k6 or Artillery) and commit the script so runs are reproducible.
- [ ] Parameterize concurrency so 50 / 100 / 250 / 500 are separate runs. The shape
      of the curve matters more than the single 500 number.
- [ ] Stub Midtrans. Never load-test a payment sandbox.
- [ ] Use unique emails per virtual user, or the unique index will mask the real
      write cost behind constraint violations.
- [ ] Include a run where **all virtual users share one source IP** — that is the
      CGNAT and venue-WiFi case, and it is how D17's fix gets verified under load.

---

## Step 4 — Capture the baseline

Per run, record:

| Metric | Source |
|---|---|
| p50 / p95 / p99 latency, per endpoint | load tool |
| Error rate and status distribution | load tool |
| Throughput (req/s) | load tool |
| CPU per core, memory | server |
| PHP-FPM active/idle workers, queue depth | `php-fpm` status page |
| MySQL connections, slow queries, lock waits | `SHOW STATUS`, slow log |
| Redis hit rate, memory, evictions | `INFO` |
| Laravel queue depth and failed jobs | `queue:monitor`, `failed_jobs` |

- [ ] Record all of it in `notes.md`, **with the hardware it ran on.** A number
      without its environment is not reproducible.
- [ ] Identify the top three limiting resources from the data. If they are not
      roughly what `notes.md` §9.3 predicts, **believe the measurement and update
      §9.3** — not the other way round.

---

## Step 5 — Profile the import command separately

`optimasi.md` is careful here and it is worth preserving the distinction: the
15.64 MiB of duplicate assets on disk does **not** explain why
`app:import-media-assets` exhausts memory, and neither do the recorded bundle sizes
(CSS 56,493 B; JS 76,952 B).

- [ ] Run the import with memory profiling and record peak usage against input size.
- [ ] Confirm whether the documented 1 MB chunking is actually in effect.
- [ ] Check `max_allowed_packet` against the largest asset.
- [ ] Record the finding. It is an operational issue, not a request-path one, and
      it should not be bundled into runtime optimization work.

---

## Validation

- [ ] The load script is committed and a second person can reproduce a run.
- [ ] Baseline numbers for 50/100/250/500 are recorded in `notes.md`.
- [ ] The top three limiting resources are named, with evidence.
- [ ] The shared-IP run is recorded — it is the D17 regression baseline.

## Acceptance

- [ ] A baseline exists on the **chosen target spec**, not a dev machine.
- [ ] Every O2–O7 claim can be stated as a measured delta.
- [ ] If the measurements contradict `notes.md` §9.3's ranking, §9.3 is corrected.

## The point of this phase

Optimizing without a baseline produces changes nobody can defend. Several items in
O2–O5 are near-certain wins on first principles — bcrypt cost, bounding an
unbounded query, taking image streaming out of PHP. But "near-certain" is not
"measured", and `guide.md` §10.6 forbids claiming verification that was not
performed. This phase is what makes the rest of the optimization work honest.
