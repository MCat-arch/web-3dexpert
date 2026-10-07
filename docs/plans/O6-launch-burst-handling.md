# Phase O6 — Launch Burst Handling

| | |
|---|---|
| **Depends on** | O1 (baseline), P1 (identity), P5 (events) |
| **Blocks** | O7 |
| **Blocked by** | `[!]` A20 (verification gate), `[!]` A21 (free launch event) |
| **Plan stability** | **Expected revision** — mail provider limits must be read from the chosen provider |

## Objective

The **write path** at launch, which O2–O5 do not address. 500 users registering,
verifying, and enrolling in one event within minutes.

---

## Step 1 — The verification-mail burst

500 verification emails in minutes, through an external provider that throttles.

**Why this is on the critical path:** assumption **A5** requires a verified email
before enrolling. So a slow mail queue does not merely delay a welcome message — it
**blocks the launch action**. Mail becomes a hard dependency of enrollment.

- [ ] `[!]` Read the **actual** send-rate limit for the chosen provider. Common
      defaults are in the low tens per second, but do not plan from this document —
      exceeding the limit produces hard bounces, not just queueing.
- [ ] Rate-limit the mail job to that ceiling using `Redis::throttle` or a
      `RateLimited` job middleware. A worker pool that ignores the provider limit
      will burn through the burst and get throttled or blocked mid-launch.
- [ ] Size worker concurrency to saturate the ceiling and no more. Extra workers
      past the provider limit add contention without throughput.
- [ ] Set `$tries` and `$backoff` on the mail job. A transient provider error must
      retry, not vanish.
- [ ] Monitor the `failed_jobs` table with alerting. **A silently failing
      verification queue is indistinguishable from slow mail** — users just never
      receive anything, and nothing in the application reports a problem.
- [ ] Warm the provider's reputation before launch if it is a new sending domain.
      A cold domain sending 500 messages in five minutes looks exactly like spam.

---

## Step 2 — `[!]` A20: should verification gate enrollment at launch?

**This is a product decision and it materially changes launch risk.**

| Option | Trade-off |
|---|---|
| **Keep A5** — verify before enrolling | Clean data, no unverified accounts holding seats. But mail is on the critical path: if the queue backs up, nobody can enroll |
| **Relax for free events** — enroll first, verify before the event | Removes the external dependency from the launch path entirely. Costs: unverified accounts can hold seats, so capacity needs a reconciliation pass before the event |

**My recommendation: relax it for free events.** The launch profile is register →
enroll within one session. Putting a third-party mail round-trip between those two
actions means an outage at your mail provider becomes an outage of your launch, for
a benefit (verified addresses) you can still collect before the event itself.

If relaxed, verification is still required before: any paid purchase, any course
access, and attending the event.

- [ ] Decide with the user and record it in `notes.md`, updating A5 and A20.
- [ ] If relaxed, add a reconciliation step: before the event, identify unverified
      registrations and decide whether to release those seats.

---

## Step 3 — `[!]` A21: confirm the launch event is free

Decision **D19** recommends it. A paid launch event adds **500 synchronous Midtrans
Snap API calls**, each holding a PHP-FPM worker on an external HTTP request for
200–500 ms, plus 500 inbound webhooks.

The user confirmed course purchases are not part of launch. If the event is also
free, Midtrans is entirely off the launch critical path — a large simplification
bought for free.

- [ ] Confirm with the user.
- [ ] If the launch event **is** paid, this phase needs a section on Snap call
      concurrency, worker starvation on external calls, and webhook burst handling.
      Say so rather than treating it as covered.

---

## Step 4 — Event seat contention under real concurrency

P5 claims seats with a **conditional atomic `UPDATE`**:

```php
$claimed = Event::whereKey($id)
    ->where(fn ($q) => $q->whereNull('capacity')
                         ->orWhereColumn('seats_taken', '<', 'capacity'))
    ->increment('seats_taken');
```

This design is sound: the database arbitrates, no lock is held across PHP
execution, and `$claimed === 0` means the seat was taken. 500 concurrent claims
serialize on an InnoDB row lock at roughly 1 ms each — draining in about a second.

**The real hazard is transaction scope.** If the enrollment transaction does other
work while holding that row, lock-wait timeouts and deadlocks follow under 500
concurrent claims.

- [ ] Audit the P5 transaction: it must contain **only** the atomic claim and the
      registration insert. Nothing else.
- [ ] Confirm notifications dispatch **after** commit, never inside (P5 specifies
      this — verify it survived implementation).
- [ ] Confirm no media write, no settings read, and no external call sits inside
      that transaction.
- [ ] Check `innodb_lock_wait_timeout` and decide the behaviour on timeout — a
      clear "could not reserve, try again" beats a 500.

**Load-test this specifically:**

- [ ] 500 concurrent enrollments against **one** event with capacity well below 500.
- [ ] Assert afterwards: `seats_taken` equals `capacity` exactly, and the count of
      non-cancelled registrations equals `capacity`. Not "approximately".
- [ ] Assert rejected users got a clean rejection, not a timeout or a 500.
- [ ] Repeat with `capacity = null` (unlimited) to confirm no contention regression.

> **This is where P5's concurrency acceptance criterion is actually met.** P5's
> PHPUnit tests run sequentially, so they prove only that the logic is atomic.
> `notes.md` and the P5 plan both say so explicitly. Do not mark P5's concurrency
> criterion complete until this step passes.

---

## Step 5 — Registration write path

- [ ] Confirm D17's fix is in place: registration is **not** throttled by IP alone,
      and O1's shared-IP load run shows no 429s. CGNAT and venue WiFi put hundreds
      of real users behind one address.
- [ ] Confirm `customers.email` unique-constraint violations return a clean
      validation error under concurrency, not a 500 from a race between the
      uniqueness check and the insert.
- [ ] Confirm session writes go to Redis (P0) — 500 concurrent session creations on
      the database driver would contend with every other write.
- [ ] Watch MySQL connection count during the registration burst against
      `max_connections` from O2 Step 4.

---

## Step 6 — Queue health under sustained burst

- [ ] Confirm queue depth **drains** rather than grows during a sustained run. A
      growing backlog at 500 users means the worker count or the provider ceiling is
      the binding constraint — identify which.
- [ ] Separate queues by urgency if needed: verification mail is latency-critical at
      launch; a retention purge is not. One slow job type must not delay
      verification.
- [ ] Confirm failed jobs alert. Already in Step 1, repeated because it is the most
      common silent launch failure.

---

## Validation

```bash
php artisan queue:monitor default
php artisan queue:failed
```

Plus O1's load script extended with the single-event enrollment hotspot and the
shared-IP registration run.

## Acceptance

- [ ] 500 concurrent enrollments on one capacity-limited event **never oversell** —
      proven under real parallelism, with exact counts asserted.
- [ ] The verification queue drains within an acceptable window and respects the
      provider's documented limit.
- [ ] Failed jobs alert rather than disappear.
- [ ] No 429s in the shared-IP registration run.
- [ ] A20 and A21 are decided and recorded.
- [ ] MySQL connections stay within limits throughout the burst.
