# Phase P0 — Infrastructure Prerequisites

| | |
|---|---|
| **Depends on** | — |
| **Blocks** | Every other phase |
| **Blocked by** | Nothing |
| **Plan stability** | **Firm** — every item below was verified against the repo |

## Objective

Make the project verifiable and able to carry load before any feature is built.
Four things are broken or missing today: the test suite cannot execute, cache /
session / queue all run on MySQL, there is no queue worker or mail transport, and
the homepage cache is invalidated but never written.

Nothing in P1–P9 should start before this passes. Payment and entitlement code
must not ship on manual inspection.

---

## Step 1 — Make the test suite execute

**Verified state:** `composer.json` requires `phpunit/phpunit ^12.5.12`. Pest is
**not** installed (`composer show pestphp/pest` → not found). But the test files
use Pest syntax — `tests/Feature/PublicPagesTest.php` opens with `beforeEach(...)`
and `it('renders the homepage', ...)`. So the suite is unrunnable.

**Decision: convert to PHPUnit.** Not Pest. Reasons: `CLAUDE.md` states "This
project uses PHPUnit. Create tests with `php artisan make:test --phpunit`";
PHPUnit 12 is already installed and needs no approval; adding Pest would be a
dependency change (`CLAUDE.md` forbids this without approval) and README already
records that available Pest releases conflict with this Laravel 13 / PHPUnit 12 set.

**Files to convert (11):**

```
tests/Feature/ContactFormTest.php       ← see note below
tests/Feature/ExampleTest.php
tests/Feature/LegacyRedirectTest.php
tests/Feature/LocalizationTest.php
tests/Feature/MediaSecurityTest.php
tests/Feature/PublicPagesTest.php
tests/Unit/ExampleTest.php
tests/Unit/HasTranslationsTest.php
tests/Unit/HasYoutubeVideoTest.php
tests/Unit/LegacyPriceParsingTest.php
tests/Unit/SlugGenerationTest.php
```

**Conversion mapping:**

| Pest | PHPUnit 12 |
|---|---|
| `it('does x', function () {...})` | `public function test_does_x(): void {...}` |
| `beforeEach(function () {...})` | `protected function setUp(): void { parent::setUp(); ... }` |
| `uses(RefreshDatabase::class)` | `use RefreshDatabase;` inside the class |
| `expect($a)->toBe($b)` | `$this->assertSame($b, $a)` |
| `expect($a)->toBeTrue()` | `$this->assertTrue($a)` |
| `expect($a)->toContain($b)` | `$this->assertStringContainsString($b, $a)` |
| `$this->get(...)` | unchanged — Laravel's test methods are identical |

Each file becomes a class extending `Tests\TestCase` (Feature) or
`PHPUnit\Framework\TestCase` / `Tests\TestCase` (Unit), namespaced
`Tests\Feature` / `Tests\Unit`.

**Contact-flow decision (D12): no standalone contact form.** Landing-page contact
and consultation CTAs lead to the configured WhatsApp contact. Do not add or link
to a `contact.create` route, and do not rate-limit an outbound WhatsApp link as
though it were a form submission.

> **Correction: `ContactFormTest` is the guard, not a casualty.** Its body is
> `$this->get('/kontak')->assertNotFound()` and `$this->post('/contact', [])
> ->assertNotFound()` — it asserts the contact form **does not exist**. It
> enforces D12 rather than contradicting it. **Convert it and keep it.** Without
> it, nothing stops a future change from adding a contact route.

**Dead artifacts to remove — one change, all together** (verified by grep; this is
a closed loop with no external consumers):

```
app/Http/Requests/ContactRequest.php
app/Models/ContactSubmission.php
database/migrations/2026_01_01_000009_create_contact_submissions_table.php
lang/id/contact.php
lang/en/contact.php
Service::contactSubmissions()        ← relation in app/Models/Service.php
```

`ContactRequest` is the **only** consumer of `lang/*/contact.php` (`__('contact.name')`,
`__('contact.spam_detected')`, …). Removing the request makes both translation
files fully unreferenced, so they go in the same change. Removing one without the
other leaves a half-migrated state that is worse than either end.

**Keep** `lang/*/nav.php`'s contact label — that is the nav link text pointing at
WhatsApp, not form copy.

Dropping the `contact_submissions` table is a destructive migration. Check whether
it holds any rows first (`php artisan db:table contact_submissions`); if the legacy
import put real enquiries there, export them before dropping.

**Validation**

```bash
php artisan test --compact
```

Must execute and report real pass/fail counts. A suite that reports "no tests
executed" is still a failure of this step.

---

## Step 2 — Redis for cache, session, and queue

**Verified state:** `.env` sets `CACHE_STORE=database`, `SESSION_DRIVER=database`,
`QUEUE_CONNECTION=database`, and `REDIS_CLIENT=phpredis` with host/port set. But
`php -m` shows **no redis extension installed**. The Redis config is aspirational.

**Resolve the client first.** Options, in order of preference:

1. **Install the `phpredis` extension** for PHP 8.4 (Windows: the matching
   `php_redis.dll`, enabled in `php.ini`). No Composer change, fastest client.
2. **`composer require predis/predis`** and set `REDIS_CLIENT=predis`. Pure PHP,
   no extension needed. **This is a dependency change — `CLAUDE.md` requires user
   approval. Ask before running it.**
3. **Defer:** keep `database` locally, Redis in production only. Acceptable
   short-term, but it means load behaviour is never exercised in development.
   Document the divergence if chosen.

Confirm a Redis server is actually reachable before switching drivers.

**Then set:**

```
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

Mirror the change in `.env.example`. Keep `database` documented as the fallback.

**Validation**

```bash
php -m | grep -i redis                 # or confirm predis in composer.lock
php artisan config:show cache.default
php artisan config:show queue.default
php artisan config:show session.driver
php artisan tinker --execute 'Cache::put("p0", "ok", 60); echo Cache::get("p0");'
```

---

## Step 3 — Queue worker

**Verified state:** `app/Jobs` does not exist. No class implements `ShouldQueue`.
No worker runs. This is the first queued work in the project.

**Do:**

1. Create `app/Jobs/` with a trivial `PingJob` used only to prove the pipeline,
   then delete it once Step 3 validation passes.
2. Document how the worker runs in development and production in `README.md`
   under a new "Queue worker" heading. `composer.json` already defines a `dev`
   script — check whether a `queue:listen` entry belongs there alongside it.
3. Decide the production supervision mechanism and write it down (Supervisor,
   systemd, or the host's process manager). Hosting is undecided per README
   decision #6, so record the requirement rather than the vendor.

**Validation**

```bash
php artisan queue:work --once --verbose
```

A dispatched `PingJob` must be picked up and completed.

---

## Step 4 — Mail transport

**Verified state:** `config/mail.php` exists, transport unconfigured. Every
feature in P1–P8 sends mail: verification, receipts, enrollment confirmations,
application status changes.

**Do:**

1. Configure `MAIL_*` in `.env` and mirror the keys in `.env.example`.
2. Set `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` to real company values.
3. For local development prefer `log` or Mailpit so nothing escapes; use the real
   transport only in staging and production.
4. Send one real message to confirm the credentials work.

**Validation**

```bash
php artisan tinker --execute 'Mail::raw("P0 transport check", fn ($m) => $m->to("you@example.com")->subject("P0"));'
```

---

## Step 5 — Fix the orphaned homepage cache

**Verified state — this is a real defect, not a missing optimization:**

- `app/Observers/ContentObserver.php:31` calls `Cache::forget('home.'.$locale)`.
- `app/Http/Controllers/HomeController.php` **never writes that key.** It returns
  `view('home', $this->payload())` directly.
- Its own comment explains why caching was removed: serializing Eloquent
  collections into the **database** cache store restored them as incomplete
  objects when the model class was not loaded before unserialization.
- README.md:102 nonetheless claims "homepage cached as a unit, invalidated by
  `ContentObserver`". That claim is false.
- `payload()` issues roughly 12 queries per homepage request.

**Why Step 2 unblocks this:** the incomplete-object failure is characteristic of
the database cache store. On Redis, Laravel serializes and resolves model classes
through Composer autoload normally. So re-enable the cache *after* Step 2, not
before.

**Do:**

1. In `HomeController`, wrap `payload()` in
   `Cache::remember('home.'.app()->getLocale(), $ttl, fn () => $this->payload())`.
   Keep the key format **identical** to what `ContentObserver` forgets — that
   mismatch is the whole bug.
2. Define the key in a constant on `HomeController` and have `ContentObserver`
   reference that constant, so writer and invalidator cannot drift again
   (`guide.md` §5 rule 5).
3. Keep a TTL as a backstop even though the observer invalidates eagerly.
4. **If** the round-trip test in this step fails on Redis, fall back to caching
   primitive arrays and adapt `home.blade.php` to consume them. Do not ship a
   cache that can return `__PHP_Incomplete_Class`.
5. Correct the README claim to describe whatever was actually implemented.

### The observer list is incomplete — fix it in this same step

`AppServiceProvider:47-54` registers `ContentObserver` on ten models:

```
HeroSlide, Service, ServiceFeature, ValueProposition, Testimonial,
DesignReview, Portfolio, PortfolioImage, PortfolioSpec, SiteSetting
```

**Two models in the homepage payload are missing: `Faq` and `MediaAsset`.**
`HomeController::payload()` includes `Faq::published()->ordered()->get()`, so
editing an FAQ would not invalidate the cache this step is about to introduce.

This has **no effect today** — there is no cache to go stale — which is exactly why
it must be fixed *with* the cache rather than after it.

- [ ] Add `Faq::class` to the observer list.
- [ ] Add `MediaAsset::class` — but see the caveat below, because it does not fix
      what it appears to fix.

> **Observing `MediaAsset` does not solve stale images.** If an editor replaces
> image *content* at the same `path`, the URL is unchanged — and `MediaController`
> sends `Cache-Control: public, max-age=2592000`, so **browsers keep the old image
> for 30 days** no matter what the server invalidates. Server-side invalidation is
> irrelevant to the symptom.
>
> The real fix is **cache-busting**: a content-addressed URL, or appending the
> asset's `sha256` (already a column, already indexed) or `updated_at` as a query
> parameter. Add the observer for payload correctness; track the cache-busting fix
> in O3, where media delivery is restructured anyway.

> **Working-tree warning.** `git status` shows `resources/views/home.blade.php`,
> `resources/views/partials/header.blade.php`, and `bootstrap/app.php` as
> **modified and uncommitted**, plus untracked `resources/views/pages/` and
> `resources/views/portfolio/`. Establish what that work is and commit or stash it
> **before** editing `home.blade.php`. Do not overwrite it.

**Tests**

- `HomepageCacheTest::test_payload_is_cached_after_first_request`
- `HomepageCacheTest::test_cached_payload_rehydrates_usable_models` — assert a
  model method and a relation (`coverImage`) work on the **cached** result. This
  is the regression guard for the original failure.
- `HomepageCacheTest::test_saving_content_invalidates_the_cache` — save a model
  observed by `ContentObserver`, assert the key is gone.

**Validation**

```bash
php artisan test --filter=HomepageCacheTest
php artisan tinker --execute 'DB::listen(fn ($q) => print($q->sql.PHP_EOL)); app(App\Http\Controllers\HomeController::class)();'
```

Second request must issue no content queries.

---

## Step 6 — Factory coverage

Every model gets a factory as it is created, from here on. Check what exists in
`database/factories/` and fill gaps for models already referenced by converted
tests (`Portfolio`, `Testimonial` at minimum — `PublicPagesTest` uses
`Testimonial::factory()->highlighted()` and `Portfolio::factory()->featured()`,
so those states must exist).

```bash
php artisan make:factory --no-interaction {Model}Factory
```

---

## Acceptance

From `task.md` Phase P0:

- [ ] `php artisan test` runs the suite and the result is trustworthy.
- [ ] `php artisan queue:work` processes a dispatched smoke-test job.
- [ ] Cache hit on the homepage is demonstrable, and an editor save invalidates it.
- [ ] `php artisan config:show cache.default queue.default session.driver` all report Redis.

Plus, specific to this plan:

- [ ] The `home.{locale}` key is written and forgotten by the same constant.
- [ ] README no longer claims anything P0 did not implement.
- [ ] `ContactFormTest` is converted to PHPUnit and **passing** — it is D12's regression guard.
- [ ] The dead contact artifacts are removed together, and `contact_submissions` was checked for rows before being dropped.
- [ ] The uncommitted working-tree changes are committed, stashed, or explained.

## Final step

```bash
vendor/bin/pint --dirty --format agent
```
