# Feature Implementation Guide — 3D Expert Web (Laravel)

The authority for *how* a feature gets built in this repository. Read this before
planning any feature.

**Stack as installed** (verify with `composer show --direct`, do not assume):
Laravel 13.17 · Filament 5.9 · PHP 8.4 · PHPUnit 12 · MySQL/MariaDB · Tailwind + Alpine.

Companion documents:

| File | Role |
|---|---|
| `CLAUDE.md` | Code style and tooling rules (Pint, Artisan, Boost). Non-negotiable. |
| `README.md` | What exists today and why. Architecture decisions already made. |
| `notes.md` | Per-feature reasoning and understanding, written before planning. |
| `task.md` | Phased implementation checklist, appended per feature. |
| `docs/plans/` | One file per phase: the concrete build instruction — files, classes, migration columns, tests, validation commands. Start here when implementing. |

---

## 0. Why this file was rewritten

The previous `guide.md` described a different application — a hand-rolled PHP
front-controller project with a custom `Router`, `Config`, `Database`, `View`, and
`Controller` base class, static model methods wrapping raw PDO, and manual CSRF
helpers.

Its *principles* are sound and are preserved below. Its *mechanics* are not
portable: in Laravel, every one of those custom classes is a framework service
that is already tested, already cached, and already hardened. Re-implementing
them here would be a regression, not a port.

**Translation table — what carried over and what was replaced:**

| Original guide said | This project does | Why |
|---|---|---|
| Custom `Router` in `bootstrap/app.php` | `routes/web.php` with named routes | Route caching, model binding, URL generation |
| `app/Core/Config.php`, `Database.php`, `View.php` | `config/`, Eloquent, Blade | Framework-provided and tested |
| Static model methods wrapping PDO | Eloquent models + query scopes | Relations, eager loading, N+1 avoidance |
| "Thick model" holding business logic | Model = data/relations; **Action class** = business logic | Models stay testable; multi-step flows get a named home |
| `Csrf::check()` called per form | `web` middleware group + `@csrf` | Automatic, impossible to forget |
| `Auth::enforceAuth()` inside each method | Route middleware + **Policies** | Declarative, testable, centrally auditable |
| Role check written inline in controller | `Gate` / Policy + `EnsureUserCanManageContent` | One place to change, one place to review |
| `password_hash()` + `session_regenerate_id()` | `Auth::login()` | Handles regeneration and rehashing |
| Hand-written `nexus.sql` schema | `database/migrations/` | Reviewable, reversible, reproducible |
| Role-specific profile tables, not one fat `users` table | **Kept** | Correct, and it matters for the LMS phase |
| Audit logs + notifications on meaningful actions | **Kept** — see §7 | Correct |
| Server-rendered views, minimal JS | **Kept** | Matches Blade + Alpine |
| Domain-oriented feature grouping | **Kept** | Matches `app/Filament/Resources/*` |
| SQL never in views; DB logic never in controllers | **Kept, enforced** | See §3 |

---

## 1. Request lifecycle

Know where your code belongs by knowing what runs before it.

```
Request
  → public/index.php                  (never edit)
  → bootstrap/app.php                 middleware + exception registration
  → routes/web.php                    route match, locale prefix, model binding
  → middleware                        SetLocale, SecurityHeaders, auth, policies
  → FormRequest                       validation + authorization
  → Controller                        orchestration ONLY
  → Action / Service                  business logic, transactions
  → Model (Eloquent)                  data access, relations, scopes
  → Observer                          cache invalidation, side effects
  → View (Blade)                      rendering ONLY
  → Response                          SecurityHeaders applied
404 → HandleLegacyRedirects           consults legacy_redirects, 301s old URLs
```

**Locale:** the default locale (`id`) is served with **no prefix**; English is
`/en`. Never introduce an `/id` prefix — it would break every indexed URL.

---

## 2. Directory contract

Stick to the existing structure. Do not create new top-level folders under
`app/` without approval, with the two exceptions noted.

```
app/
├── Concerns/          Reusable model traits (HasTranslations, HasYoutubeVideo)
├── Console/Commands/  Artisan commands (migration, verification, admin creation)
├── Filament/          Admin panel resources — the ONLY content-management UI
├── Http/
│   ├── Controllers/   Thin. Orchestration only.
│   ├── Middleware/    Cross-cutting request concerns
│   └── Requests/      FormRequest validation + authorization
├── Models/            Eloquent models: relations, casts, scopes
├── Observers/         Side effects on model events (cache invalidation)
├── Policies/          Authorization rules per model      ← EXISTS, currently empty
├── Support/           Cross-cutting helpers (Settings, Locale, MediaUpload)
├── Actions/           Business logic for multi-step flows ← CREATE WHEN NEEDED
└── Jobs/              Queued work                         ← CREATE WHEN NEEDED
```

`Actions/` and `Jobs/` are the Laravel homes for what the original guide called
"thick model" logic and "notifications after successful action". Create them the
first time a feature genuinely needs one — not preemptively.

---

## 3. Layer responsibilities

Enforced. A review rejects violations.

### Controller — orchestration only

A controller method should read like a table of contents. If it exceeds ~15
lines or contains a conditional business rule, the logic belongs in an Action.

- **Never** write a query with more than a scope chain. No `DB::` raw SQL.
- **Never** validate inline — use a `FormRequest`.
- **Never** check a role with `if ($user->role === 'admin')` — use a Policy.
- Return `View`, `RedirectResponse`, or a Resource. Declare the return type.

### FormRequest — the trust boundary

All external input is validated here, before the controller runs. `authorize()`
returns the policy decision. This is the only place input is trusted after.

### Action — business logic

A single-purpose invokable class for a flow that touches multiple models,
needs a transaction, or encodes a business rule.

```php
final class PublishPortfolio
{
    public function __construct(private Settings $settings) {}

    public function __invoke(Portfolio $portfolio): Portfolio
    {
        return DB::transaction(function () use ($portfolio) {
            // ... multi-step work, atomic
        });
    }
}
```

Rules: one public entry point, explicit types, wrap multi-write flows in
`DB::transaction()`, and dispatch side effects (mail, notifications) **after**
commit — never inside the transaction.

### Model — data and relations

Relations, casts, accessors, and **query scopes**. The established convention in
this codebase is scopes for every reusable filter:

```php
Portfolio::published()->featured()->ordered()->with('coverImage')->first();
```

Follow it. A new model with a `is_published` / `sort_order` column gets
`scopePublished()` and `scopeOrdered()` to match its siblings. No business logic
that spans entities — that is an Action.

### Blade — rendering only

No queries. No `Model::` calls. Everything arrives from the controller. Extract
repeated markup into `<x-component>`. Escape by default; `{!! !!}` requires a
written justification.

---

## 4. Data layer

- **Every** schema change is a migration. Never edit a shipped migration that has
  run anywhere — add a new one.
- Name columns explicitly; add indexes for every column you filter or sort on.
  A `where('is_published', true)->orderBy('sort_order')` query needs a composite
  index, not two single-column indexes.
- Money is `decimal(15,2)`, never float, never varchar. The legacy site stored
  `'24.0002'` in a `varchar(50)`; see `LegacyPriceParsingTest`.
- Translatable text uses the local `HasTranslations` trait (JSON columns). It is
  deliberately method-compatible with `spatie/laravel-translatable`. **Do not grow
  that file** — if you need per-locale querying or fallback chains, swap to the
  package.
- Media is stored on the private `media` filesystem disk and streamed by
  `MediaController`. The database holds media metadata and the disk path. Uploads
  go through `Support/MediaUpload`, which validates MIME and extension.
- Every new model gets a factory. Seeders for reference data only.

### N+1 is a defect, not a performance nit

Any collection render must eager-load what the view touches. `HomeController`
does this correctly with `->with('coverImage')`. Verify with Boost's
`database-query` or `DB::listen` before declaring a feature done.

---

## 5. Caching and queues — the 500-user requirement

This project targets ~500 concurrent users. That target drives design, not
cleanup afterwards.

### Current state (verified, and it is not what README claims)

- `HomeController` is **not cached**. It issues ~12 queries per request.
- `ContentObserver` forgets `home.{locale}` — **a key nothing writes.** The
  invalidation is wired to a cache that does not exist.
- `CACHE_STORE`, `QUEUE_CONNECTION`, and `SESSION_DRIVER` are all `database`.
  Redis is configured in `.env` but unused.
- `app/Jobs` does not exist; there is no `ShouldQueue` anywhere.

That combination means every request costs a session read, cache reads, and the
full content query set — all against MySQL. It will not hold 500 concurrent users.

### Rules for new features

1. **Read-heavy public data is cached, and the cache is invalidated by an
   observer** — never by TTL alone. Editors must see their change immediately.
   Register the observer when you add the cache, in the same change.
2. **Cache the rendered payload, not Eloquent collections.** Serialized models
   can restore as incomplete objects when the class is not yet loaded. Cache
   arrays or rendered HTML fragments. The comment in `HomeController` documents
   exactly this trap.
3. **Anything slow or failure-prone is queued**: email, notifications, media
   processing, external API calls, payment callbacks. A web request must never
   wait on a third party.
4. **Jobs are idempotent and retry-safe.** Set `$tries` and `$backoff`. Assume
   every job runs at least twice.
5. **Name every cache key in a constant** on the class that owns it, so the
   invalidator and the writer cannot drift apart.
6. State the expected query count in `notes.md` when a feature adds queries to a
   public page.

Moving cache/session/queue to Redis is a prerequisite for the traffic target and
is tracked as infrastructure work, not per-feature work.

---

## 6. Security

The legacy site had an unauthenticated upload endpoint that allowed RCE and
shipped plaintext credentials in the webroot. Do not regress.

- **Authorization is a Policy**, registered and tested. Role strings never appear
  in a controller conditional.
- **CSRF** is automatic via the `web` group; every form carries `@csrf`.
- **No state-changing GET routes.** Ever.
- **Uploads** validate MIME *and* extension, then store on the private media disk.
  `MediaController` serves only image/PDF extensions and re-verifies MIME.
  `MediaSecurityTest` asserts a stored `.php` is inert — keep it passing.
- **Mass assignment:** explicit `$fillable`. Never `$guarded = []`.
- **CSP and security headers** come from `SecurityHeaders`. Adding an external
  asset means editing that middleware deliberately, with a note of why.
- Errors shown to users never contain exception detail.

---

## 7. Observability for meaningful actions

Carried over from the original guide, and correct: state transitions that matter
to a human get a record.

Audit-log and/or notify on: publish/unpublish, role change, payment state change,
destructive admin action, and failed authorization attempts. Use queued
notifications (§5 rule 3).

---

## 8. Testing

**Known blocker:** the suite under `tests/` is written in Pest syntax, but Pest
is not installed and available releases conflict with this Laravel 13 / PHPUnit 12
set. `php artisan test` therefore does not execute them. **Until that is resolved,
"the tests pass" is not evidence of anything.** Resolving it — porting to PHPUnit
or landing a compatible Pest — is prerequisite work, not optional polish.

When writing tests:

- Feature tests over unit tests. Test behavior through HTTP.
- Use factories and their states. Never hand-build models.
- Every new feature covers: the happy path, one validation failure, and one
  authorization denial.
- Security behavior gets an explicit regression test.
- `php artisan make:test --phpunit {Name}` — no directory in the name.

---

## 9. Definition of done

A feature is not done until all of these hold:

- [ ] `notes.md` records the reasoning, the data flow, and the decisions taken.
- [ ] `task.md` phases for this feature are all `[x]` or explicitly `[!]`.
- [ ] Migrations run forward **and** roll back cleanly.
- [ ] No N+1 on any page the feature touches (verified, not assumed).
- [ ] Cache strategy stated, and invalidation wired in the same change.
- [ ] Slow or external work is queued.
- [ ] Authorization enforced by a Policy and covered by a test.
- [ ] Both `lang/id` and `lang/en` carry every new key.
- [ ] `vendor/bin/pint --dirty --format agent` is clean.
- [ ] Tests written; if the suite still cannot execute, say so plainly rather
      than implying verification.

---

## 10. Non-negotiables

1. Never edit a migration that has already run.
2. Never put a query in a Blade template.
3. Never check a role inline in a controller.
4. Never add a state-changing GET route.
5. Never add a cache without wiring its invalidation in the same change.
6. Never claim verification you did not perform.
7. Never change dependencies without approval (`CLAUDE.md`).
8. Never introduce an `/id` locale prefix.
9. Never add a contact form. Contact and consultation CTAs link directly to the
   `company_whatsapp` setting (decision D12 in `notes.md`). `ContactFormTest`
   enforces this — keep it passing.
