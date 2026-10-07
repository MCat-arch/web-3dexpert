# Phase O2 — Request-Path Cost

| | |
|---|---|
| **Depends on** | O1 (baseline) |
| **Blocks** | — |
| **Blocked by** | Nothing once O1 is done |
| **Plan stability** | **Firm** — `BCRYPT_ROUNDS=12` verified at `.env:16` |

## Objective

Reduce the fixed CPU and bootstrap cost of every request. Cheap, mechanical, and
large — do it before structural work.

---

## Step 1 — Bcrypt cost (the headline)

**Verified:** `.env:16` → `BCRYPT_ROUNDS=12`.

At cost 12 a bcrypt hash takes roughly **250–400 ms of CPU**. The launch path needs
about 1,000 hash operations — 500 registrations plus 500 logins — which is
**~300 CPU-seconds**. On 4 vCPU that is ~75 seconds of complete CPU saturation,
during which *every other request queues behind it*, including a fully cached
homepage.

This cannot be cached, queued, or parallelized away. It is pure CPU. Adding
PHP-FPM workers makes it worse — more context switching against the same ceiling.

**Change to cost 10** (D15):

```
BCRYPT_ROUNDS=10
```

- 2^10 vs 2^12 → **~4× faster**, ~75 ms per hash.
- Launch hashing budget drops from ~300 to ~75 CPU-seconds.
- Cost 10 was Laravel's default for years and remains a defensible work factor.
- **Never go below 10.**

**Existing accounts are safe.** Laravel's `password` cast and `Hash::needsRehash()`
rehash on the next successful login, so the cost change is transparent.

- [ ] Set `BCRYPT_ROUNDS=10` in `.env` and `.env.example`.
- [ ] Measure a single hash before and after:
      `php artisan tinker --execute '$s=microtime(true); Hash::make("x"); echo round((microtime(true)-$s)*1000)."ms";'`
- [ ] Re-run O1's register and login load runs and record the delta.
- [ ] Confirm an account created at cost 12 still logs in, and that its hash is
      rehashed afterwards.

> **If the measured launch profile turns out to be spread rather than burst** (A19),
> cost 11 becomes affordable. Decide from O1's data, not from this document.

---

## Step 2 — Opcache

Without opcache, every request recompiles every PHP file touched. With Laravel and
Filament that is hundreds of files.

- [ ] Confirm opcache is enabled: `php -i | grep opcache.enable`.
- [ ] `opcache.memory_consumption` ≥ 256 MB (Filament is large).
- [ ] `opcache.max_accelerated_files` ≥ 20000 — the default 10000 is too low for
      Laravel plus Filament plus vendor.
- [ ] **Production only:** `opcache.validate_timestamps=0`. This stops a `stat()`
      per file per request, but means **a deploy must restart PHP-FPM or the old
      code keeps serving**. Write that into the deploy procedure.
- [ ] Keep `validate_timestamps=1` in development.

---

## Step 3 — Framework caches and autoloader

- [ ] Add to the deploy procedure, in this order:
      ```bash
      composer install --no-dev --optimize-autoloader
      php artisan config:cache
      php artisan route:cache
      php artisan view:cache
      php artisan event:cache
      ```
- [ ] **Audit for `env()` outside `config/`.** `config:cache` freezes those to
      `null`, which fails silently and at runtime:
      ```bash
      grep -rn "env(" app/ routes/ resources/views/ | grep -v "config/"
      ```
      Every hit must move into a config file. This is the single most common way
      `config:cache` breaks a deploy.
- [ ] Verify the caches are actually present after a deploy. Assuming they are is
      how a production site runs uncached for a month.
- [ ] Note that `route:cache` makes T0's Blade route-integrity test read the cached
      table — clear it before running that test.

---

## Step 4 — PHP-FPM and connection sizing

Size against the spec O1 settled on. For 4 vCPU / 8 GB:

| Setting | Value | Reasoning |
|---|---|---|
| `pm` | `dynamic` or `static` | `static` if memory allows; avoids spawn latency during a burst |
| `pm.max_children` | 32–40 | ~40–60 MB resident each ≈ 2 GB. More will not beat a CPU ceiling |
| `pm.max_requests` | 500–1000 | Recycles workers, bounding any leak |
| MySQL `max_connections` | ~64 | Must exceed `max_children` + queue workers + Filament sessions + headroom |

**500 concurrent users is not 500 concurrent requests in flight.** Browsing
generates far fewer simultaneous PHP requests than registering does. O1's data
shows which regime you are actually in — size from that, not from the user count.

- [ ] Set `max_children` from measured per-worker memory, not a guess.
- [ ] Confirm MySQL `max_connections` exceeds the sum above.
- [ ] Check `request_terminate_timeout` is shorter than any upstream proxy timeout,
      so a stuck worker is reclaimed rather than accumulating.
- [ ] Re-measure worker saturation under O1's 500-user run.

> O3 is what makes this sizing viable. While every image streams through PHP,
> workers are consumed by byte transfer and no `max_children` value is enough.

---

## Step 5 — Response compression

- [ ] Enable gzip or brotli at the web server for HTML, CSS, JS, JSON, SVG.
- [ ] **Do not compress images or PDFs** — already compressed, wasted CPU.
- [ ] Confirm Vite production assets are built and fingerprinted (`npm run build`),
      and that the manifest is in place. A missing manifest is a hard 500.

---

## Validation

```bash
php -i | grep -E "opcache.(enable|memory_consumption|max_accelerated_files|validate_timestamps)"
php artisan config:show hashing
grep -rn "env(" app/ routes/ resources/views/ | grep -v config/
php artisan about
```

Then re-run O1's load script at the same concurrencies and record the deltas.

## Acceptance

- [ ] Hash time measured before and after; register and login latency improved
      against the O1 baseline.
- [ ] Opcache verified enabled and sized, with the deploy-restart requirement
      documented.
- [ ] Framework caches verified present post-deploy.
- [ ] No `env()` call outside `config/`.
- [ ] PHP-FPM and MySQL limits set from measured memory, with the arithmetic recorded.
- [ ] An account hashed at cost 12 still logs in and is rehashed.

## Expected outcome

The largest single win here is bcrypt — a one-line change with ~4× effect on the
launch path's dominant CPU cost. It is also the easiest to miss, because no
query-level profiler will ever show it: the time is spent in a hash function, not
in the database.
