# Phase O7 — Launch Rehearsal & Sign-off

| | |
|---|---|
| **Depends on** | O1–O6, and P9 |
| **Blocks** | Launch |
| **Plan stability** | **Expected revision** — targets follow the hosting decision |

## Objective

Rehearse the launch on the real target, prove the numbers, and write down what is
actually true — including the parts that are not.

---

## Step 1 — Full-path rehearsal

Run O1's load script end to end on the production-equivalent environment, at
50 / 100 / 250 / 500:

```
homepage → register → verify → login → browse events → enroll
```

- [ ] Record p50/p95/p99 per endpoint, error rate, and throughput at each level.
- [ ] Record CPU, memory, PHP-FPM workers, MySQL connections, Redis hit rate, and
      queue depth for each run.
- [ ] Put every number beside its O1 baseline. **An optimization with no before/after
      pair is an assertion, not a result.**
- [ ] Include the shared-IP registration run (D17) and the single-event enrollment
      hotspot (O6 Step 4).
- [ ] Watch the curve shape, not just the 500 figure. If p95 is flat to 250 and
      collapses at 400, the real ceiling is ~300 and that is the number to report.

---

## Step 2 — Correctness re-verification after optimization

Optimization changes caching, asset paths, and query shapes — every one of which
can silently break a security boundary. Re-run, do not re-assert:

- [ ] **`CourseEntitlementTest`** — then manually load a locked lesson as a guest
      and **read page source**. O5's caching is the most plausible way the P3
      paywall regresses.
- [ ] **`CustomerGuardIsolationTest`** — a customer session still cannot reach `/admin`.
- [ ] **`BladeRouteIntegrityTest`** (T0) — still passing, run against an uncached
      route table.
- [ ] **`RegistrationRateLimitTest`** (D17) — many registrations from one IP still
      pass.
- [ ] **Progress-tracker negative responses** still byte-identical.
- [ ] **Applicant documents** still unreachable by direct URL — O3 moved asset
      serving, so confirm private media did **not** move with it.
- [ ] **`MidtransWebhookTest`** — idempotency intact.

O3 is the highest-risk phase for this list. Moving public assets out of
`MediaController` must not have taken any private path with it.

---

## Step 3 — Monitoring before launch, not after

If it is not monitored at launch, a problem is discovered from user complaints.

- [ ] Error rate and uncaught exceptions, with alerting.
- [ ] p95 latency per key endpoint.
- [ ] Queue depth **and** failed-job count, alerting on both.
- [ ] CPU, memory, disk.
- [ ] MySQL connections against `max_connections`, and slow-query log enabled.
- [ ] Redis memory and evictions — eviction of queued jobs would be severe (O5 Step 5).
- [ ] Mail provider bounce and complaint rate. A 500-message burst from a new
      sending domain is the classic way to get throttled mid-launch.
- [ ] Confirm `APP_DEBUG=false`. Debug output under load leaks internals and is slow.

---

## Step 4 — Launch runbook

Write it down. Decisions made calmly beforehand are better than decisions made
during an incident.

- [ ] Who watches which dashboard, and for how long after launch.
- [ ] **Rollback procedure**, tested at least once. Not described — tested.
- [ ] Queue-backlog response: when to add workers, when to accept the delay.
- [ ] What to do if the mail provider throttles. If A20 kept verification on the
      critical path, this is the most likely launch-day failure and needs a
      pre-agreed answer.
- [ ] What to do if the launch event oversells or errors — including how to
      reconcile seats manually.
- [ ] Who can put the site in maintenance mode, and what that does to in-flight
      registrations.
- [ ] A deliberate decision on whether to deploy anything on launch day. Default: no.

---

## Step 5 — Cleanup, kept separate

`optimasi.md` §6 asks explicitly that cleanup not be mixed with runtime
optimization. Honour that — these items change nothing about performance:

- [ ] Evaluate the 15.64 MiB of duplicate assets (35 hash groups; 27 public files
      with private twins, ~2.81 MiB). **Disk hygiene, not performance** — it will not
      move latency or throughput.
- [ ] Validate every path against `media_assets` rows and view references **before**
      deleting anything. The audit's warning stands: hashes match across `hero` and
      `portfolio`, and identical bytes at different logical paths may both be live.
- [ ] Re-compare hashes after any cleanup.
- [ ] Confirm `welcome.blade.php` and `pages/coming-soon.blade.php` were removed in
      T0; if not, do it here.
- [ ] Record the import-command memory profile from O1 Step 5 as its own operational
      finding, not as a web-request optimization.

---

## Step 6 — Record what is actually true

- [ ] Update `notes.md` §9 with the measured results, replacing predictions with
      numbers. If §9.3's bottleneck ranking was wrong, **correct it** — the
      measurement wins.
- [ ] Replace assumptions A18–A24 with confirmed facts or explicit re-decisions.
- [ ] Update `docs/plans/README.md` statuses. `optimasi.md` flagged this file as
      inconsistent with reality once already.
- [ ] Record the **measured ceiling**, whatever it is.

---

## Final acceptance

- [ ] The launch profile is demonstrated by measurement on the real target spec.
- [ ] Every optimization claim has a before/after number.
- [ ] Every security boundary re-verified **after** optimization, by test.
- [ ] Monitoring and alerting live before launch.
- [ ] A tested rollback procedure exists.
- [ ] No `[!]` remains in `task.md`.
- [ ] The achievable ceiling is written down honestly.

---

## The one that matters most

`guide.md` §10.6: **never claim verification you did not perform.**

If the load test reached 300 concurrent and not 500, the number is 300. If
concurrency was only proven sequentially, say so. If hosting arrived too late to
rehearse on production hardware, record that the baseline came from staging.

A launch decision made on an honest "we tested to 300 and expect trouble past that"
is recoverable — you can stage the rollout, cap registrations, or add capacity. One
made on an inaccurate "tested and passing" is not, because nobody is watching for
the thing that was never tested.
