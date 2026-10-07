# Phase P9 — Hardening, Load & Sign-off

| | |
|---|---|
| **Depends on** | P0–P8 |
| **Blocks** | Production launch |
| **Blocked by** | Every `[!]` in `task.md` must be closed |
| **Plan stability** | **Expected revision** — the load-test targets depend on the final hosting choice |

## Objective

Prove the thing works under the conditions it was specified for, and that no
phase quietly regressed another. This is where the 500-concurrent-user
requirement stops being an aspiration.

**Re-verify, do not re-assert.** Every item here is something to *run*, not
something to conclude from having built it carefully.

---

## Step 1 — Code and schema integrity

```bash
vendor/bin/pint --dirty --format agent      # then --format agent over everything new
php artisan migrate:fresh                   # forward, clean
php artisan migrate:rollback --step=50       # and back, cleanly
php artisan migrate
```

Rolling back must not error on foreign keys. Every `nullOnDelete` relation added
in P3/P5/P6 is a candidate for a bad `down()`.

**Locale parity:** `lang/id` and `lang/en` must carry identical key sets. The
existing suite has a precedent for asserting this — extend it rather than
eyeballing the files.

---

## Step 2 — Query audit

For every page added in P1–P8, record the query count:

| Page | Target |
|---|---|
| Homepage | cached; 0 content queries on hit |
| Course catalog | O(1) |
| Course detail (outline) | O(1) |
| Lesson player | O(1) |
| My courses dashboard | O(1) — **not** one per enrollment |
| Event list / detail | O(1) |
| Vacancy list / detail | O(1) |
| Progress lookup | O(1) |

Method: `DB::listen()` in a test, or Boost's `database-query`. Seed 20+ rows per
listing — an N+1 is invisible at 2 rows.

**Write the numbers into `notes.md`.** "No N+1" with no number attached is an
assertion, not a measurement.

---

## Step 3 — Content Security Policy

**Corrected 2026-10-08 — the YouTube directives are already present.** Verifying
beats re-adding: a blind "add this line" edit risks duplicating or replacing the
working value.

Current state in `app/Http/Middleware/SecurityHeaders.php`:

| Line | Directive | Status |
|---|---|---|
| 33 | `default-src 'self'` | present |
| 38 | `script-src 'self'` | present — **no `unsafe-inline`, no `unsafe-eval`**, deliberately (see the comment at line 20) |
| 39 | `img-src 'self' data: https://i.ytimg.com https://images.unsplash.com` | **YouTube thumbnails already allowed** |
| 42 | `frame-src https://www.youtube-nocookie.com` | **already allowed** |
| 46 | `form-action 'self'` | present |

- [ ] **Verify**, do not re-add, `frame-src https://www.youtube-nocookie.com` and
      `img-src … https://i.ytimg.com`. P4's lesson player needs both and both exist.
- [ ] **Remove `https://images.unsplash.com`** from `img-src` once legacy external
      images are hosted locally. README's deploy checklist already calls for this
      and it is overdue.
- [ ] After any CSP change, load every page with the console open. A blocked
      resource fails **silently** in CSP terms — nothing appears in your logs.

### Step 3b — Midtrans and `script-src 'self'` — decide, do not discover

`script-src 'self'` with no nonce and no hash **will block Snap's checkout
button.** Snap loads its script from a Midtrans host and commonly injects inline
script. The CSP comment at line 20 says the absence of `unsafe-inline` and
`unsafe-eval` is intentional, so this is a deliberate collision, not an oversight.

Discovering it during P2's first sandbox transaction wastes a day and tempts a
panicked `unsafe-inline`. Decide it in P2, before writing checkout code:

| Option | Trade-off |
|---|---|
| **A — Redirect / deeplink flow** | Send the buyer to Midtrans's hosted payment page instead of embedding Snap. Needs only the Midtrans host in `form-action` (and `default-src` if applicable). **The CSP stays strict.** Costs an on-site checkout experience |
| **B — Snap with a narrow exception** | Add `script-src 'self' https://app.midtrans.com` plus `frame-src`, scoped to the checkout route only, with a written justification in `SecurityHeaders.php`. Keeps the embedded UX. If Snap genuinely needs `unsafe-inline`, prefer a per-request **nonce** over a blanket allowance |

- [ ] `[!]` Decide in P2 and record it in `notes.md`.
- [ ] If B, scope the relaxation to the checkout route — **never site-wide**. A
      global `unsafe-inline` to fix one button discards XSS protection on every
      page, including the Filament admin.
- [ ] Never add `unsafe-eval`.

Recommended: **Option A for launch.** The launch profile has no course purchases
and D19 recommends a free launch event, so payments are off the critical path
entirely — which makes this a decision you can take calmly on its merits rather
than under launch pressure.

---

## Step 4 — Security re-verification

Run these as checks, not assumptions:

- [ ] **No state-changing GET route exists.** `php artisan route:list` and read
      every GET: nothing enrolls, completes, purchases, cancels, or deletes.
- [ ] **Every new form carries `@csrf`.** Grep the new Blade files.
- [ ] **The Midtrans webhook is the only CSRF-exempt route.** Read
      `bootstrap/app.php` and confirm the exception list has exactly one entry.
- [ ] **Every upload path validates `mimes` *and* `mimetypes`.**
- [ ] **No applicant document is reachable under `public/`.** Try a direct URL.
- [ ] **The entitlement gate still holds after all caching added anywhere.**
      Re-run `CourseEntitlementTest`, then manually read page source on a locked
      lesson as a guest. A cached fragment carrying a `youtube_id` is the same
      leak as P3 §3.2 by a different route.
- [ ] **A customer session still cannot reach `/admin`** — re-run
      `CustomerGuardIsolationTest` after P8's panel changes.
- [ ] **Progress-tracker negative responses are still byte-identical.**
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true` in the
      production env.
- [ ] `LEGACY_DB_*` removed from `.env` once migration is verified (README).
- [ ] Document root is `public/`, **not** the project root. This is what exposed
      the backup tarball, the SQL dump, and `Kredensial Akses.txt` on the old site.

Consider running `/security-review` over the full diff as a second pass.

---

## Step 5 — Load test (the actual requirement)

The 500-concurrent-user target has never been measured. Everything before this
point is design intent.

**Surfaces to test**, anonymous unless noted:

1. Homepage — cached, highest traffic
2. Course catalog
3. Course detail
4. Event list
5. Vacancy list
6. Progress lookup (POST, rate-limited — expect 429s and confirm they are the
   limiter working, not the app failing)
7. Lesson player (authenticated — needs a session pool)

**What to record:** p50 / p95 / p99 latency, error rate, throughput, and for each
run: MySQL connection count, Redis hit rate, queue depth, CPU and memory.

**What to look for specifically:**

- **Session store under load.** P0 moved sessions to Redis. Confirm it, because
  authenticated pages hit the session on every request.
- **Cache hit rate.** A homepage missing cache under load means the P0 fix or an
  observer is flushing too aggressively.
- **Queue depth drains.** If notifications pile up faster than the worker clears
  them, one worker is not enough — size it now, not after launch.
- **Event capacity under genuine parallelism.** P5's tests proved the *logic* is
  atomic; they ran sequentially. **This is where oversell is actually disproven.**
  Hammer one event at capacity 1 with concurrent enrollments and assert exactly
  one registration exists afterward.
- **Midtrans is stubbed.** Do not load-test against the sandbox.

Record the results and the hardware they ran on in `notes.md`. A number without
its environment is not reproducible.

---

## Step 6 — Browser and layout

Check every new page at **375px, 768px, 1280px, 1920px**:

catalog, course detail, lesson player, event list, event detail, vacancy list,
vacancy detail, application form, progress lookup and result, all customer auth
pages, and the account dashboard panels.

- [ ] No horizontal scroll at 375px.
- [ ] Console clean — no errors, no CSP violations, no 404s.
- [ ] No missing image, PDF, icon, or video request.
- [ ] Keyboard navigation works on the lesson player, the FAQ accordion, and all
      new forms.
- [ ] Focus-visible rings present. The project already committed to this in its
      accessibility work; new pages must not regress it.

---

## Step 7 — Documentation truth pass

`README.md` currently contains claims that P0–P8 invalidated. Fix all of them:

- [ ] Remove **"no public registration"** — P1 added it.
- [ ] Move **LMS** and **Midtrans** out of "What is not here" — P2–P4 built them.
- [ ] Correct or confirm the **homepage caching** claim per what P0 actually did.
- [ ] Update the **Decisions** table: #7 (Midtrans) is no longer "phase 9".
- [ ] Describe the **queue worker** as a deployment requirement.
- [ ] Document **Redis** as required infrastructure, not optional.
- [ ] Update the **Layout** tree with `Actions/`, `Jobs/`, `Policies/`,
      `Contracts/`, and the new models. **It is already wrong today**, independent
      of this project: it lists `Controllers/ Home, Portfolio, Service, Career,
      Contact, Media`, but only `Controller.php`, `HomeController.php`, and
      `MediaController.php` exist. It also lists `Requests/ ContactRequest`, which
      P0 deletes under D12.
- [ ] ~~Remove the `docs/02` contact-submission leftovers~~ — **done 2026-10-08.**
      The "recent contact submissions" widget was dropped from 5.2 and the
      "Contact-submission viewer with CSV export" item removed entirely; 5.12–5.14
      renumbered to 5.11–5.13. Item 4.9 remains as the standing WhatsApp rule.
- [ ] Note the **private disk** for applicant documents and its retention policy.
- [ ] Update the **test-suite** paragraph — P0 resolved the Pest/PHPUnit blocker.

Then verify `task.md`: every P0–P9 box is `[x]`, and **no `[!]` remains**.

---

## Step 8 — Final acceptance

From `task.md` Phase P9:

- [ ] All five features work end to end in both locales.
- [ ] Entitlement gating, guard isolation, webhook idempotency, capacity
      concurrency, and document authorization each covered by a passing test.
- [ ] No response to a non-entitled user carries paid course content.
- [ ] The 500-user target is **demonstrated by measurement**, not assumed.
- [ ] Every assumption A1–A17 is confirmed or explicitly re-decided.
- [ ] No claim of verification is made that was not actually performed.

---

## The last one matters most

`guide.md` §10.6: *never claim verification you did not perform.*

If the load test did not run, say the load test did not run. If concurrency was
only proven sequentially, say so. A launch decision made on an honest "we did not
test this" is recoverable. One made on an inaccurate "tested and passing" is not.
