# 3D Expert Web — Laravel

Laravel application for the bilingual 3D Expert company-profile site. The public
surface includes Indonesian and English landing pages (`/` and `/en`), paginated
portfolio indexes and detail pages, customer accounts, free event enrollment, and
media delivery. Paid events are visible, but enrollment waits for P5b. Landing and
event content is managed in Filament at `/admin`; there is no separate service,
career, or contact page.

---

## ⚠️ Read this first

Use PHP 8.3+ with the PHP extensions required by Composer and configure MySQL or
MariaDB in `.env`. Media files are stored on the private `media` filesystem disk;
the database stores only their metadata and private storage path.

---

## Setup

> **Local vs production:** `.env` is the production profile. For local testing use the ignored `.env.local` file and run Artisan with `--env=local`. The full local and VM runbook is in [docs/vm-setup-and-admin.md](docs/vm-setup-and-admin.md); the panel guide is in [docs/admin-panel.md](docs/admin-panel.md).

Local test commands:

```bash
php artisan --env=local migrate --force
php artisan --env=local app:create-admin --name="Admin 3D Expert" --username=admin --email=smtp3dexpert@gmail.com --role=superadmin
php artisan --env=local serve
```

Open `http://localhost:8000/admin` and log in with `smtp3dexpert@gmail.com` or `admin`.

Production / VM setup:

```bash
# Install dependencies
composer install
npm ci

# Run only if .env does not already exist; configure MySQL/MariaDB, then:
cp .env.example .env
php artisan key:generate
php artisan migrate

# Choose either demo data or the legacy database migration:
php artisan db:seed
# OR:
php artisan app:migrate-legacy-data --dry-run   # report first
php artisan app:migrate-legacy-data

# Import media after choosing either data source.
php artisan app:import-media-assets
php artisan app:verify-migration

# Create the first administrator and build assets.
php artisan app:create-admin
npm run build
php artisan serve
```

The importer reads managed media from `storage/app/media` and public brand assets
from `public/images`, storing files on the private media disk. The retired
`public/legacy-assets` tree is not scanned. Keep the source files until import
and verification succeed. Existing BLOB rows remain readable; re-importing a
matching source file moves its content to private file storage.

### Packages

Runtime dependencies include Laravel and Filament. Alpine.js and Tailwind are
used by the landing-page assets. Translatable attributes use the local
`HasTranslations` trait; see [Decisions](#decisions).

---

## What this replaces

| Legacy | Lines | Replaced by |
|---|---|---|
| `index.php` + `index-en.php` | 1,889 | `HomeController` + `resources/views/home.blade.php` + `lang/{id,en}` |
| `detail.php` | 1,143 | `PortfolioController` + `resources/views/portfolio/*`; legacy detail URLs redirect to the matching public portfolio slug |
| `karir.php` | 1,241 | No public careers page; legacy URL redirects to the landing page |
| `partials/header.php` + `footer.php` | 655 | `layouts/app.blade.php` + `partials/*` |
| `upload.php` | 95 | No public upload endpoint; managed media is stored on a private filesystem disk |
| `reg.php` | 74 | Dedicated `customers` table and `customer` guard for event accounts; staff accounts remain CLI-created |
| `koneksi.php` | 13 | `config/database.php` + `.env` |
| `edit.php` + `editkarir.php` | 2,424 | Filament content-management panel at `/admin` |

## Fixes carried in this pass

Numbers reference the findings in `01-codebase-analysis-and-laravel-mapping.md`.

**Security**

| Legacy defect | Fix |
|---|---|
| Unrestricted upload → RCE (§3.1-5): `uniqid().'_'.$_FILES['gambar_utama']['name']` into web-served `assets/images/`, no extension or MIME check | Filament uploads validate MIME and extension, then store binary data in the database; `MediaController` serves only image/PDF extensions and verifies MIME. A stored `.php` is inert — `MediaSecurityTest` asserts this |
| `upload.php` had no auth at all (§3.1-6) | The legacy upload endpoint is removed; content uploads require an authorized Filament session |
| Open self-registration → superadmin (§3.1-7) | No registration route exists; `role` column + `EnsureUserCanManageContent` |
| No CSRF anywhere (§3.1-8) | Laravel's `web` group; `@csrf` on every form |
| Destructive actions over GET (§3.1-9) | No state-changing GET route exists |
| No session hardening (§3.1-10) | `SESSION_*` flags in `.env`; framework regenerates the id on login |
| DB credentials in source (§3.1-4, §3.2-13) | `.env`, with `*.sql`/`*.tar.gz`/`error_log`/`Kredensial*` in `.gitignore` |
| Publicly exposed submission flow | There is no standalone contact form; landing-page contact and consultation CTAs open the configured WhatsApp contact |
| No security headers | `SecurityHeaders` middleware: CSP, HSTS, nosniff, frame-ancestors |
| `die("Koneksi gagal: ".$e->getMessage())` leaked DB internals | `errors/500.blade.php` shows no exception detail |

**Correctness**

| Legacy defect | Fix |
|---|---|
| `likes` incremented on every GET, then used to pick the homepage hero (§3.2-15) | Split into `view_count` (atomic, timestamp-free) and `is_featured` (editorial). Tested |
| Prices as `varchar(50)` holding `'24.0002'` (§3.2-17) | `decimal(15,2)`; the migrator flags what will not parse instead of guessing. `LegacyPriceParsingTest` covers the real dump values |
| `features` JSON crammed in a `text` column | `service_features` rows, with `is_heading` preserving the `"…:"` convention |
| `mengapa_section` as two lists, deletes nulling columns into permanent orphans (§3.2-16) | `value_propositions`, one row per item, explicit `group`. Orphans skipped and reported |
| Magic singleton ids — `$features_id = 1`, `UPDATE … LIMIT 1` with no WHERE (§3.2-19) | `site_settings` key/value + `Settings` accessor |
| Test data live on public pages (§3.2-18) | `is_published` defaults false; the migrator imports suspicious rows unpublished and names them |
| YouTube URL parsed per loop iteration | `youtube_id` resolved once on save. `HasYoutubeVideoTest` covers all 7 URL shapes in the dump |
| Up to 9 PDO connections per page (§3.3-21) | One connection. **The homepage payload is not cached yet** — see `HomeController::__invoke`; caching Eloquent collections corrupts them on the `database` store, so it waits for Redis. `ContentObserver` invalidation is wired and tested |
| i18n by file duplication (§3.3-23) | Locale route prefix + translatable columns + `lang/{id,en}` |
| `echo "<script>alert()…"` for flow control (§3.3-24) | `redirect()->with()` + `<x-alert>`; no `ob_start()` anywhere |
| Filename routing (§3.3-25) | A `legacy_redirects` 301 map; destinations for removed pages now point to relevant landing anchors |
| No validation layer (§3.3-26) | Filament forms validate managed content and uploaded media |

**SEO** — `legacy_redirects` + `HandleLegacyRedirects` 301 every old URL (including
`detail.php?id=N` with tracking params attached), canonical + `hreflang` tags, and
`LocalBusiness` / `CreativeWork` / `JobPosting` structured data. `LegacyRedirectTest`
covers this. **This is what stops the cutover from discarding the domain's rankings.**

## Improvements retained on the landing page

- Portfolio index and detail pages are public in both locales; portfolio and
  service content also remain editable and visible as landing-page sections.
- **Accessibility**: skip link, focus-visible rings, ARIA on carousel/gallery/nav,
  `prefers-reduced-motion` honored. Legacy had none of this.

---

## Decisions

Your seven answers, and what each one did to the code.

| # | Your answer | Implementation |
|---|---|---|
| 1 | Keep design reviews separate | `design_reviews` table and `DesignReview` model, own homepage section. Not merged |
| 2 | `desain_rumah`/`galeri_rumah` exist | Migrated into `portfolios`, **unpublished**, tagged `legacy_source='desain_rumah'`. Its fixed spec columns become `portfolio_specs` rows, matching what `properties`/`property_details` already did |
| 3 | *My call* | Legacy `likes` was a page-view counter (incremented on GET, no identity, no dedup). Not imported as a number — used only to pick the initial `is_featured` row, so launch day looks unchanged. Then `view_count` + `is_featured` |
| 4 | All 4 `layanan_section` rows are real | All four migrated. Duplicate titles flagged `needs_review` and left unpublished — the titles are corrupted, not the rows (there are 4 matching PDFs in `assets/pdf/`) |
| 5 | Full bilingual | Translatable JSON columns throughout; `id` unprefixed, `en` under `/en` |
| 6 | Hosting undecided | Nothing host-specific. Needs PHP 8.3+, document root at `public/`. See below |
| 7 | Midtrans | P2 payment foundation; P5b will use it for paid event enrollment. No payment code is implemented yet |

**Why a local `HasTranslations` trait instead of `spatie/laravel-translatable`:** ~60
lines, no package API to drift against in an environment where it could not be
installed and tested, and transparent if you are still learning Eloquent. It is
deliberately method-compatible (`getTranslation`/`setTranslation`/`getTranslations`),
so swapping to the package later means deleting the trait. **Switch if you need
per-locale querying or fallback chains — do not grow that file.**

The Filament panel is installed at `/admin`. Admin accounts are created with
`php artisan app:create-admin`; the project administrator email is
`smtp3dexpert@gmail.com`. Editors can manage landing content, while only
admins/superadmins can change site settings. Public accounts use a separate
`customers` table and `customer` guard, so they cannot authenticate to Filament.
Verified customers can enroll in free events; paid-event enrollment is deferred
to P5b.

---

## What is not here

1. **LMS and payments.** Courses, payments, and learning-management workflows
   remain future work. The paid event path (P5b) is also deferred; paid events
   remain visible but cannot create unpaid registrations.
2. **Image conversions / CDN (phase 7).** `MediaController` streams original files;
   image transformation and CDN delivery remain future work.
3. **Sitemap.** Landing and portfolio routes are public; sitemap generation
   remains future work.

### Public route scope

`routes/web.php` registers public routes explicitly for each locale: `/` stays
Indonesian and `/en` serves English. The route table includes landing, portfolio
index/detail, media, and Filament routes. Unsupported public paths should return
404:

| URL | Expected |
|---|---|
| `/` | Indonesian homepage, `lang="id"` |
| `/en` | English homepage, `lang="en"` |
| `/portofolio` | Portfolio index, paginated |
| `/portofolio/{slug}` | Portfolio detail; legacy `detail.php?id=N` 301s to the matching slug |
| `/en/portofolio`, `/en/portofolio/{slug}` | Same, in English |
| `/acara`, `/acara/{slug}` and `/en/acara`, `/en/acara/{slug}` | Bilingual event catalog and detail pages; paid events are listed but not enrollable until P5b |
| `/akun/acara`, `/en/akun/acara` | Signed-in customer's registrations |
| `/daftar`, `/masuk` and `/en/daftar`, `/en/masuk` | Customer registration and sign-in; separate from staff accounts |
| `/kontak`, `/layanan`, `/karir` | 404 — no such pages. Contact is a WhatsApp link |

Public routes are registered **once per locale** rather than behind a `{locale?}`
prefix: an optional route parameter cannot be followed by a required segment, so
the prefix form served `/en/portofolio` but 404'd `/portofolio`. The default
locale keeps the canonical route names and other locales are namespaced
(`en.home`). In views use `$lroute('name')`, never `route('name')`, for anything
inside those groups — `BladeRouteIntegrityTest` enforces it.

### Tests

`php artisan test` runs. The suite was written in Pest syntax while Pest was not
installed, so a single file aborted the whole run with "Call to undefined
function it()" and nothing had ever executed. It is now PHPUnit throughout,
matching `CLAUDE.md`.

```bash
php artisan test --compact
```

### Queue worker

P1 customer verification and P5a event confirmations are queued; later payment
receipts and status notifications will use the same queue. **A worker must be
running or queued notifications are not delivered**.

```bash
php artisan queue:work            # development
php artisan queue:work --once     # one job, for verification
```

Verify a new environment with `App\Jobs\VerifyQueuePipeline`:

```bash
php artisan tinker --execute 'App\Jobs\VerifyQueuePipeline::dispatch();'
php artisan queue:work --once
```

In production the worker needs supervision (Supervisor, systemd, or the host's
process manager) so it restarts on failure and after deploys.

### Redis

Cache, session, and queue run on the `database` driver today. **Redis is required
for the ~500-concurrent-user target** — serving sessions, cache reads, and the job
queue from MySQL puts all three on one contended resource. The `predis/predis`
client is installed, so switching is three values in `.env`
(`CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`) once a server exists.

It also unblocks homepage payload caching, which cannot work on the `database`
store (see the Fixes table above).

### Required PHP extensions

Beyond Laravel's defaults this application needs `intl`, `zip` (Filament
exports), `curl` (Midtrans), `gd` and `exif` (image uploads), plus `pdo_sqlite`
and `sqlite3` for the test suite. `composer install` fails without `intl` and
`zip`; `php artisan test` fails without `pdo_sqlite`.

## Before you deploy

- `APP_DEBUG=false`, `APP_ENV=production`
- `SESSION_SECURE_COOKIE=true` (HTTPS)
- `php artisan config:cache route:cache view:cache`
- Document root → `public/`. **Not the project root** — that is what exposed the
  backup tarball, the SQL dump and the plaintext credentials file on the old site.
- Backups write **outside** the webroot
- Remove `LEGACY_DB_*` from `.env` once migration is verified
- Drop `images.unsplash.com` from the CSP in `SecurityHeaders` once those legacy
  external images are hosted locally

**Phase 0 of the implementation plan is still outstanding and independent of all of
this.** `Kredensial Akses.txt` is downloadable from the live site today.

## Commands

| Command | Purpose |
|---|---|
| `app:migrate-legacy-data [--dry-run] [--fresh]` | Migrate the legacy DB. Idempotent, transactional, reports everything it could not decide |
| `app:verify-migration` | Reconcile counts, check integrity, gate launch. Non-zero exit on a blocking problem |
| `app:create-admin` | Create an admin account (replaces `reg.php`) |

## Layout

```
app/
├── Concerns/         HasTranslations, HasYoutubeVideo
├── Console/Commands/ MigrateLegacyData (the risky one), VerifyMigration, CreateAdmin
├── Http/
│   ├── Controllers/  Home, Portfolio, Service, Career, Contact, Media
│   ├── Middleware/   SetLocale, SecurityHeaders, HandleLegacyRedirects, EnsureUserCanManageContent
│   └── Requests/     ContactRequest
├── Models/           Eloquent models for the site and current features
├── Observers/        ContentObserver (cache invalidation)
└── Support/          Settings, Locale
database/migrations/  Schema managed through Laravel migrations
lang/{id,en}/         Indonesian and English translations
resources/views/      Blade views and components
tests/                PHPUnit unit and feature tests
docs/                 config patches + migration runbook
```
