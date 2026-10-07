# Phase T0 — Triage: Restore Page Rendering

| | |
|---|---|
| **Depends on** | — |
| **Blocks** | **Everything.** P0–P9 and O1–O7 |
| **Blocked by** | Two route-destination decisions (Step 3, Step 4) |
| **Plan stability** | **Firm** — the failure was reproduced, not inferred |

## Objective

The application returns **HTTP 500 on every page**. Fix that. This is triage, not
optimization — decision D14.

## The failure, reproduced

```bash
php artisan tinker --execute 'try { $r = app("Illuminate\Contracts\Http\Kernel")->handle(Illuminate\Http\Request::create("/", "GET")); echo "STATUS: ".$r->getStatusCode(); } catch (\Throwable $e) { echo "THREW: ".$e->getMessage(); }'
# → STATUS: 500
```

Four route names are called from Blade and are not registered. Only `home` and
`media.show` exist (plus Filament/Livewire/Boost internals).

**Why it is site-wide, not homepage-only:** `partials/header.blade.php` is part of
the layout and calls two of them. Every page that renders the header fails.

| Route called | Call sites |
|---|---|
| `contact.create` | `home.blade.php:81`, `header.blade.php:48`, `header.blade.php:93`, `portfolio/show.blade.php:131` |
| `careers.index` | `header.blade.php:45`, `header.blade.php:90` |
| `portfolio.index` | `home.blade.php:198`, `portfolio/show.blade.php:27` |
| `portfolio.show` | `home.blade.php:127`, `portfolio/show.blade.php:15` |

`welcome.blade.php` also calls `route('login')` and `route('register')`, which are
likewise unregistered — harmless only because nothing renders that view.

`optimasi.md` §1 found this independently. It listed four names; `login` and
`register` bring the real total to six.

---

## Step 1 — Resolve the working tree first

```bash
git status --short
```

Expected:

```
 M bootstrap/app.php
 M resources/views/home.blade.php
 M resources/views/partials/header.blade.php
?? resources/views/pages/
?? resources/views/portfolio/
```

**This is uncommitted work and it is the probable source of the breakage** — the
new `portfolio/` views and the modified header reference routes that were never
registered.

- [ ] Establish what this work was meant to do before changing it.
- [ ] Commit it (even on a scratch branch) or stash with `-u`.
- [ ] **Do not overwrite or discard it.** It is the only copy.

Steps 3 and 4 cannot be decided without understanding this work, because it reveals
whether public portfolio pages were being added deliberately.

---

## Step 2 — `contact.create` → WhatsApp (settled)

No decision needed. D12 governs: contact is a direct WhatsApp link and no contact
route is ever created.

Replace all four call sites with the configured WhatsApp destination, matching the
pattern already used elsewhere in these same files:

```blade
@if ($whatsapp = $settings('company_whatsapp'))
    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">…</a>
@endif
```

- [ ] `home.blade.php:81` — `home.blade.php:23` already assigns `$whatsapp`; reuse it.
- [ ] `header.blade.php:48` and `:93` — lines 57 and 97 already do this correctly in the same file. Match them.
- [ ] `portfolio/show.blade.php:131` — only if that view survives Step 4.
- [ ] Wrap each in a null check. An unset `company_whatsapp` must degrade to a hidden CTA, never a broken link.

This alone fixes the majority of the breakage, including the header.

---

## Step 3 — `careers.index` `[!]`

**Decision required.** P6 introduces a real careers page at `/karir`, but P6 is
seven phases away and the header is broken now.

| Option | Consequence |
|---|---|
| Point at a landing-page anchor now, switch to `/karir` in P6 | Unblocks immediately; needs revisiting in P6 |
| Register a minimal `careers.index` route now | More work now, no revisit later; but it duplicates P6's scope |
| Remove the nav item until P6 | Simplest; loses a nav entry the legacy site had |

Recommended: **anchor now, switch in P6**, and add a note to the P6 plan so the
switch is not forgotten. `HandleLegacyRedirects` already maps `karir.php`, so the
legacy URL keeps working regardless.

- [ ] Decide, implement, and record the choice in `notes.md`.
- [ ] If anchoring, add the switch to P6's task list.

---

## Step 4 — `portfolio.index` / `portfolio.show` `[!]`

**Decision required, and there is a documented contradiction to resolve.**

`README.md` states public portfolio pages were deliberately removed: *"Portfolio
cards in the landing page; legacy detail URLs now redirect to `/#portofolio`"* and
`/portofolio/some-slug` → 404 is listed as expected behaviour, covered by tests.

But the uncommitted work adds `resources/views/portfolio/show.blade.php` — a
portfolio detail view. So either the README decision was reversed, or this view was
exploratory.

| Option | Work required |
|---|---|
| **No public portfolio pages** (keep README's decision) | Replace both links with `/#portofolio` anchors; delete `portfolio/show.blade.php`; existing tests stay valid |
| **Public portfolio pages** (reverse the decision) | Register routes, add a controller with slug binding, keep the view, **update the tests that currently assert 404**, add `hreflang`/canonical, and extend the sitemap |

The second option is a genuine feature, not a bug fix. If that is wanted, it
belongs in its own phase rather than inside triage.

Recommended for T0: **take the first option** — restore rendering with anchors, and
if public portfolio pages are desired, plan them deliberately afterwards.

- [ ] Decide and record in `notes.md`.
- [ ] If keeping README's decision, confirm `PublicPagesTest`'s 404 assertions still pass.

---

## Step 5 — Delete `welcome.blade.php`

72 KB Laravel scaffold. The root route uses `HomeController`, so it is unreachable.

- [ ] Confirm nothing references it: `grep -rn "welcome" routes/ app/ resources/views/`.
- [ ] Delete it, along with its dead `route('login')` / `route('register')` calls.
- [ ] Check `resources/views/pages/coming-soon.blade.php` — `optimasi.md` §6 reports
      it empty. Confirm, then delete or populate it.

`optimasi.md` is right that neither file is provably safe to delete on size alone.
Verify references first.

---

## Step 6 — The guard test (prevents recurrence)

This class of bug reached a working tree and broke every page. A test stops it
recurring.

`tests/Feature/BladeRouteIntegrityTest.php`:

```php
public function test_every_route_name_used_in_blade_is_registered(): void
{
    $views = /* recursively glob resources/views for *.blade.php */;
    $missing = [];

    foreach ($views as $view) {
        preg_match_all(
            "/route\(\s*'([a-z][a-zA-Z0-9._-]*)'/",
            file_get_contents($view),
            $matches,
        );

        foreach ($matches[1] as $name) {
            if (! Route::has($name)) {
                $missing[] = basename($view).' → '.$name;
            }
        }
    }

    $this->assertSame([], $missing);
}
```

Notes on its limits — state them in a comment so nobody over-trusts it:

- It only catches **literal** route names. `route($someVariable)` is invisible to it.
- It does not verify route **parameters**, so a correct name with a missing required
  parameter still fails at runtime. `php artisan route:list` plus a smoke test
  covers that.
- Run it against the **uncached** route table, or a stale `route:cache` will skew it.

- [ ] Write the test and watch it **fail** on the current tree before fixing anything.
- [ ] Make it pass by completing Steps 2–5.

---

## Validation

```bash
php artisan route:list
php artisan view:clear && php artisan route:clear
php artisan test --filter=BladeRouteIntegrityTest
php artisan test --filter=PublicPagesTest
php artisan test
```

Then render every public page and confirm 200:

```bash
php artisan tinker --execute 'foreach (["/", "/en"] as $u) { echo $u." → ".app("Illuminate\Contracts\Http\Kernel")->handle(Illuminate\Http\Request::create($u, "GET"))->getStatusCode().PHP_EOL; }'
```

Open both locales in a browser with the console visible. A 200 with a broken layout
is still a failure.

---

## Acceptance

- [ ] `/` and `/en` return **200**.
- [ ] Every literal `route(...)` name in every Blade view resolves — asserted by test.
- [ ] No contact route exists; every contact CTA opens WhatsApp (D12).
- [ ] Both `[!]` decisions are recorded in `notes.md`.
- [ ] The uncommitted work was committed or stashed, never discarded.
- [ ] `php artisan test` passes with no new failures.

## Why this is T0 and not part of P0

P0's acceptance includes "cache hit on the homepage is demonstrable". A homepage
that returns 500 cannot demonstrate a cache hit. Every phase after this one
validates against a rendering site, so this goes first — before infrastructure,
before features, before optimization.
