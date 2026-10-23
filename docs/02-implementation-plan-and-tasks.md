# 3D Expert Web → Laravel: Implementation Plan & Task Breakdown

**Companion to:** `01-codebase-analysis-and-laravel-mapping.md`
**Item references** like `(§3.1-5)` point to the numbered findings in that document.

> **Everything in this document is my recommendation**, not something derived from the
> existing code. Effort figures are estimates for **one part-time developer (≈15 h/week)**
> — adjust them to your own pace; they are a shape, not a promise.

---

## Summary

| Phase | Name | Est. | Blocking? |
|---|---|---|---|
| **0** | Emergency hardening of the live site | **2–4 h** | **Do today. Blocks nothing — do it in parallel.** |
| 1 | Scaffold & tooling | 1 week | yes |
| 2 | Schema & models | 1.5 weeks | yes |
| 3 | Data migration | 1.5 weeks | yes |
| 4 | Public site | 3 weeks | — |
| 5 | Admin panel | 2 weeks | — |
| 6 | Auth, roles & hardening | 1.5 weeks | yes |
| 7 | Media pipeline | 1 week | — |
| 8 | Test, SEO & launch | 2 weeks | yes |
| | **Company profile site live** | **≈ 3–3.5 months** | |
| 9 | LMS | 3–5 months | after 8 |

Phases 4 and 5 can overlap. Phase 7 can start once phase 5's models are stable.

**Definition of done for the whole project (phase 0–8):** `3dexpert.id` serves the same
content and the same pages as today, from Laravel, with every old URL 301-redirecting, no
credentials or backups in the webroot, an admin panel the client can use without you, and a
schema that phase 9 can build on without another migration.

---

## Phase 0 — Emergency hardening of the live site

**Why now:** `Kredensial Akses.txt` contains the admin password in plaintext and is
downloadable from the public webroot right now (§3.1-1). The rewrite takes months. This
takes an afternoon. It is independent of everything else in this document.

- [ ] **0.1** Download `backup-3dexpert.id-7-9-2025.tar.gz` (203 MB) to local/offsite storage, then **delete it from the webroot** (§3.1-1)
- [ ] **0.2** Delete `Kredensial Akses.txt` from the server entirely. Never store credentials in the webroot again — use a password manager (§3.1-1)
- [ ] **0.3** Delete `dexc8115_3dexperts.sql` from the webroot (keep a copy offsite — you need it for phase 3) (§3.1-1)
- [ ] **0.4** Delete the `.trash/` directory, including `.trash/3dexpert/.git/` (§3.1-3)
- [ ] **0.5** Move `error_log` out of the webroot, or deny it via `.htaccess` (§3.1-2)
- [ ] **0.6** Add an `.htaccess` that denies `*.sql`, `*.tar.gz`, `*.log`, `*.txt`, `.git`, and directory listing
- [ ] **0.7** **Rotate the admin password.** It is currently `admin123` and it is public (§3.1-1, §3.1-10)
- [ ] **0.8** **Rotate the database password** in cPanel and update `koneksi.php` (§3.1-4)
- [ ] **0.9** Add a session check to the top of `upload.php` — copy the four lines from `editkarir.php:5–8`. Right now it is a completely open upload endpoint (§3.1-6)
- [ ] **0.10** Rename or delete `reg.php`. Open registration grants superadmin (§3.1-7)
- [ ] **0.11** Add an extension allowlist to the `gambar_utama` upload in both branches of `edit.php` — reuse the `$allowed = ['jpg','jpeg','png','gif']` check already written for property images. This is the RCE path (§3.1-5)
- [ ] **0.12** Delete the debug `error_log("Redirect to: " ...)` line in `login.php:14` (§3.1-11)
- [ ] **0.13** Add `php_flag display_errors off` and confirm `log_errors` writes outside the webroot
- [ ] **0.14** **Audit for prior compromise:** list every file in `assets/images/` and `uploads/` with a non-image extension; check `users` for rows you did not create; scan for recently modified `.php` files. Given 0.5, 0.7 and 0.11, assume it *may* have been exploited and verify

**Acceptance:** requesting `/backup-3dexpert.id-7-9-2025.tar.gz`, `/Kredensial Akses.txt`,
`/dexc8115_3dexperts.sql`, `/error_log`, `/.trash/3dexpert/.git/HEAD` and `/upload.php` all
return 403/404. Uploading a `.php` file through the admin form is rejected.

---

## Phase 1 — Scaffold & tooling

- [ ] **1.1** Confirm the production PHP version. The `error_log` shows `/opt/alt/php84/`, so PHP 8.4 is available — good
- [ ] **1.2** Check the host supports Laravel: SSH access, Composer, and the ability to point the document root at `public/`. **If the hosting is shared cPanel with no document-root control, resolve this before writing any code** — it changes the deployment plan (see Risk R4)
- [ ] **1.3** `composer create-project laravel/laravel 3dexpert` — check the current stable version at install time rather than pinning from this document
- [ ] **1.4** Initialize git properly: real `.gitignore` (`.env`, `/vendor`, `/node_modules`, `/storage/*.key`, `/public/storage`), first commit, remote on GitHub/GitLab **private**
- [ ] **1.5** Configure `.env` for local; create `.env.example` with every key and no values (§3.1-4, §3.2-13)
- [ ] **1.6** Install and configure Tailwind + Vite; port `assets/css/style.css` (4,370 lines) into Tailwind config + a few component classes — do not copy it verbatim
- [ ] **1.7** Install Filament (`composer require filament/filament`) and create the first admin user via `php artisan make:filament-user`
- [ ] **1.8** Install `spatie/laravel-permission`, `spatie/laravel-medialibrary`, `pestphp/pest`
- [ ] **1.9** Install Laravel Debugbar and Laravel Pint (dev only)
- [ ] **1.10** Create a **staging environment** on a subdomain (`staging.3dexpert.id`) with its own database. Do not test against production data in production
- [ ] **1.11** Write the project `README.md`: setup steps, env keys, how to run migrations and seeds

**Acceptance:** `php artisan serve` shows the Laravel welcome page locally; `/admin` shows
the Filament login; staging is reachable; a clean clone + `composer install` + `npm install` +
`php artisan migrate` works from the README alone.

---

## Phase 2 — Schema & models

One migration per table. Use the mapping table in §4.3 of the analysis document.

- [ ] **2.1** `site_settings` — key/value singleton store, replacing `features_section`, `alur_section`, `location_section` and the `mengapa_section` main row (§3.2-19)
- [ ] **2.2** `hero_slides` — media library relation, `sort_order`, `is_active`
- [ ] **2.3** `services` + `service_features` — `price`/`original_price` as `decimal(15,2)`, features as rows not JSON, PDF via media library (§3.2-14, §3.2-17)
- [ ] **2.4** `value_propositions` — `group` enum (`list1`/`list2`), `sort_order`, `is_active`; no nullable-column deletes (§3.2-16)
- [ ] **2.5** `testimonials` — `type` enum if you accept merging `review_design` in; `is_highlighted`, `rating`, `is_published`, `sort_order`
- [ ] **2.6** `portfolios` — `slug` (unique, indexed), `is_published`, `is_featured`, `view_count`, `description`, soft deletes (§3.2-15, §3.3-25)
- [ ] **2.7** `portfolio_images` + `portfolio_specs` — keep the existing FK cascade pattern, add `sort_order`
- [ ] **2.8** `job_vacancies` — `slug`, `is_published`, `closes_at`, soft deletes (§3.2-18)
- [ ] **2.9** `users` — add `role`, `email_verified_at`; run the spatie/permission migrations (§3.1-7)
- [ ] **2.10** `legacy_redirects` — `old_path`, `new_path`, for the 301 map (§4.4)
- [ ] **2.11** Eloquent models with relationships, `$casts`, `$fillable`, and query scopes (`published()`, `featured()`, `ordered()`)
- [ ] **2.12** Model factories + a `DatabaseSeeder` with realistic demo data, so a fresh clone has something to look at
- [ ] **2.13** **Before finalizing:** export the *live* database and diff it against the dump. The two have drifted (§3.2-12) — check specifically whether `desain_rumah` / `galeri_rumah` exist with real rows, and whether `properties.likes` and `layanan_section.pdf_blob` exist. Adjust phase 3 accordingly

**Acceptance:** `php artisan migrate:fresh --seed` builds the whole schema from empty and
populates it. Every model relationship is exercised by at least one Pest test.

---

## Phase 3 — Data migration

The highest-risk phase, because you do not yet know the real production schema (§3.2-12).

- [ ] **3.1** Export the production database (`mysqldump`) and restore it locally as `3dexpert_legacy`
- [ ] **3.2** Write an inventory script: row count per table, plus every column that is empty, all-null, or holds obvious test data. You will find more than the four duplicate `layanan_section` rows and the `asddddddddddddddMARKETING EXECUTIVE` job (§3.2-18)
- [ ] **3.3** **Decide the cleanup rules with the client, in writing, before migrating:** which of the four `layanan_section` rows is canonical; which jobs are real; whether the `mengapa_section` orphan rows carry anything. Do not silently drop client data
- [ ] **3.4** `php artisan make:command MigrateLegacyData` — reads `3dexpert_legacy` on a second DB connection, writes the new schema. **Idempotent** (safe to re-run) and **wrapped in a transaction**
- [ ] **3.5** Migrate the content singletons into `site_settings`
- [ ] **3.6** Migrate `properties` → `portfolios`, **generating a unique slug per row from the title**, and record every `detail.php?id={id}` → `/portofolio/{slug}` pair into `legacy_redirects` (§4.4)
- [ ] **3.7** Migrate `property_images` / `property_details`. Note that `property_images.image_url` currently holds **external Unsplash URLs** (`dexc8115_3dexperts.sql:318`) as well as local paths — handle both, and decide whether to download the Unsplash images locally
- [ ] **3.8** Migrate `layanan_section` → `services`: parse the JSON `features` text (strip the literal `\r\n`), and **convert `'24.0002'` / `'98.6002'` to decimals — flag every value that does not parse cleanly for manual review rather than guessing (§3.2-17)
- [ ] **3.9** Migrate `mengapa_section` → `value_propositions`, skipping all-null orphan rows, preserving order (§3.2-16)
- [ ] **3.10** Migrate `testimonial_section` and `review_design` → `testimonials`
- [ ] **3.11** Migrate `job_detail` → `job_vacancies` with slugs, marking the test rows unpublished rather than deleting them
- [ ] **3.12** Migrate `hero_banner`: copy the five `hero_*.jpg` files from `assets/images/` into the media library
- [ ] **3.13** Migrate `users`. **The bcrypt hashes carry over as-is** — Laravel's `Hash::check()` verifies `$2y$` hashes, so existing admins keep their passwords. Assign roles explicitly; nobody gets superadmin by default (§3.1-7)
- [ ] **3.14** Decide on `properties.likes`: seed it into `view_count`, or discard it and set `is_featured` manually. It is a page-view counter, not likes (§3.2-15)
- [ ] **3.15** Write a verification command: row counts old vs new, and a spot-check report on 10 random portfolios
- [ ] **3.16** Run the whole migration on **staging** against a fresh production export, and have the client review the result page by page

**Acceptance:** the command runs twice in a row with identical results; row counts reconcile
or every discrepancy is explained in writing; the client signs off on staging content.

---

## Phase 4 — Public site

- [ ] **4.1** Blade layout from `partials/header.php` + `partials/footer.php` (§2.7)
- [ ] **4.2** Extract Blade components: hero carousel, service card, portfolio card, testimonial grid, job card, CTA, WhatsApp button
- [ ] **4.3** Set up localization: `lang/id/`, `lang/en/`, a `SetLocale` middleware, `{locale?}` route prefix, and a language switcher. **This replaces `index-en.php` — do not create a second set of views** (§3.3-23)
- [ ] **4.4** Homepage: all sections from `index.php`, one controller, **one** DB connection, eager-loaded queries (§3.3-21)
- [ ] **4.5** Portfolio index (new — the current site has no listing page, only the homepage grid and `detail.php`) with pagination and filters
- [ ] **4.6** Portfolio detail at `/portofolio/{portfolio:slug}` — a gallery lightbox, specs table, related items. **No write operations in the GET handler** (§3.2-15)
- [ ] **4.7** Services page from `services` + `service_features`, with the PDF as a signed download (§3.2-14)
- [ ] **4.8** Careers index + detail, porting the client-side filters from `karir.php`'s 313 lines of inline JS into Alpine.js (§3.3-22)
- [ ] **4.9** Link contact and consultation CTAs to the configured WhatsApp contact; do not add a standalone contact form or `contact.create` route
- [ ] **4.10** SEO per page: title, meta description, canonical, Open Graph, JSON-LD (`Organization`, `LocalBusiness`); `sitemap.xml`; `robots.txt`
- [ ] **4.11** Move every inline `<style>` and `<script>` block into Vite-compiled assets — that is 2,900+ lines of inline CSS and 800+ of inline JS across the six page files (§3.3-22)
- [ ] **4.12** Cache the content sections (`Cache::remember`), invalidated by model observers on save (§3.3-28)
- [ ] **4.13** Responsive check at 375 / 768 / 1280 / 1920 px, and a Lighthouse pass targeting ≥90 on performance and accessibility

**Acceptance:** every page the current site serves has an equivalent, in both languages,
rendering migrated data; Debugbar shows one DB connection and no N+1 queries; Lighthouse
performance ≥ 90.

---

## Phase 5 — Admin panel

Replaces `edit.php` (1,856 lines) and `editkarir.php` (568 lines) entirely.

- [ ] **5.1** Filament panel setup: branding, dark theme to match the existing admin look, navigation groups
- [ ] **5.2** Dashboard widgets: portfolio count, published jobs
- [ ] **5.3** `PortfolioResource` — CRUD, image gallery with drag-reorder, repeatable specs, publish toggle
- [ ] **5.4** `ServiceResource` — features as a repeater, PDF upload, decimal price inputs
- [ ] **5.5** `TestimonialResource` — YouTube URL with a live preview, rating, highlight toggle. **Port the existing business rule: at least 2 highlighted** (from `edit.php`'s `if ($highlightedCount < 2)`) as a form validation rule
- [ ] **5.6** `JobVacancyResource` — CRUD, publish toggle, `closes_at`
- [ ] **5.7** `HeroSlideResource` — upload with sort order
- [ ] **5.8** `ValuePropositionResource` — grouped by `list1`/`list2`, reorderable
- [ ] **5.9** Settings page for the singletons (`site_settings`) — replaces the magic `$features_id = 1` (§3.2-19)
- [ ] **5.10** `UserResource` — role assignment, gated so only a superadmin can reach it (§3.1-7)
- [ ] **5.11** An `en`/`id` toggle on every translatable field, so the client maintains both languages in one form (§3.3-23)
- [ ] **5.12** Install `spatie/laravel-activitylog` — an audit trail of who changed what. There is none today
- [ ] **5.13** **Write a short admin handover guide (Indonesian)** with screenshots, and walk the client through it live

**Acceptance:** the client performs every content edit they currently do in `edit.php` without
touching code and without your help. Redirect-after-POST with flash messages everywhere — no
`alert()`, no `ob_start()` (§3.3-24).

---

## Phase 6 — Auth, roles & hardening

- [ ] **6.1** Install Laravel Breeze for public/student auth (needed by phase 9 regardless)
- [ ] **6.2** Define roles: `superadmin`, `admin`, `editor`, and `student` for later. Define permissions per resource (§3.1-7)
- [ ] **6.3** Laravel Policies on every model; register them in `AuthServiceProvider`
- [ ] **6.4** Rate-limit login (`throttle:5,1`) (§3.1-10)
- [ ] **6.5** Confirm session config: `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_DRIVER=database` (§3.1-10)
- [ ] **6.6** Enable email verification and password reset (Breeze provides both)
- [ ] **6.7** Security headers via middleware: CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, HSTS
- [ ] **6.8** Verify CSRF is active on every form — Laravel does this by default, but confirm no route was excluded (§3.1-8)
- [ ] **6.9** Confirm every destructive action is `DELETE` or `POST`, never `GET` (§3.1-9)
- [ ] **6.10** `FormRequest` classes for every write endpoint, with type and length rules (§3.3-26)
- [ ] **6.11** `APP_DEBUG=false` in production; error logging to `storage/logs` (outside the webroot by definition) (§3.1-2)
- [ ] **6.12** Run a self-audit against the OWASP Top 10, and re-verify §3.1 items 5–11 are structurally impossible in the new codebase

**Acceptance:** a logged-out user reaching any admin route is redirected; an `editor` cannot
reach user management; a forged cross-site POST is rejected; a `.php` upload is rejected by
MIME *and* extension *and* stored outside the webroot.

---

## Phase 7 — Media pipeline

- [ ] **7.1** Configure `spatie/laravel-medialibrary` disks. **Store uploads outside `public/`** and serve them through controlled routes (§3.1-5)
- [ ] **7.2** Move the 162 MB of `assets/pdf/` to object storage or a non-public disk; serve via temporary signed URLs (§3.3-27, §3.4-31)
- [ ] **7.3** Image conversions: thumb / card / hero, WebP, `srcset` responsive images. The current `assets/images/` is 14 MB unoptimized (§3.3-27)
- [ ] **7.4** Validate uploads on MIME **and** extension **and** size, with randomly generated stored filenames (§3.1-5)
- [ ] **7.5** Cache headers and, if budget allows, a CDN in front of media (Cloudflare's free tier is enough)
- [ ] **7.6** Lazy-load below-the-fold images
- [ ] **7.7** Queue the image conversions so uploads stay fast (`QUEUE_CONNECTION=database` is fine to start)

**Acceptance:** no user-uploaded file is reachable by guessing a URL; a 4 MB source photo
serves at under 200 KB on a phone; uploading a renamed `.php` fails.

---

## Phase 8 — Test, SEO & launch

- [ ] **8.1** Pest feature tests: every public page renders 200; auth gates hold; CRUD works per role
- [ ] **8.2** Pest unit tests for slug generation, price parsing and the data-migration transforms
- [ ] **8.3** GitHub Actions: run Pint and Pest on every push
- [ ] **8.4** **Build the 301 redirect map** from `legacy_redirects` + the static list in §4.4 of the analysis document. Verify every currently-indexed URL resolves — pull the list from Google Search Console, not from memory (§4.4)
- [ ] **8.5** `sitemap.xml` submitted; record current Search Console rankings **before** cutover so you can detect a drop
- [ ] **8.6** Full staging UAT with the client, page by page, both languages, on desktop and phone
- [ ] **8.7** Configure production: `.env`, `php artisan config:cache route:cache view:cache`, `APP_DEBUG=false`, HTTPS, storage symlink
- [ ] **8.8** **Set up automated backups that write OFF the webroot** — DB daily, media weekly, retention 30 days. This is what §3.1-1 was a symptom of
- [ ] **8.9** Error monitoring (Sentry free tier) and uptime monitoring
- [ ] **8.10** **Write the cutover runbook**, including the rollback step: keep the old code in a non-served directory and the old DB untouched for 30 days
- [ ] **8.11** Cut over during low traffic. Smoke-test the redirects, the forms and the admin login within 15 minutes
- [ ] **8.12** Watch Search Console and Sentry daily for two weeks
- [ ] **8.13** Only after two clean weeks: archive the old code offsite and delete it from the server

**Acceptance:** the new site serves production traffic; every old URL 301s; Search Console
shows no crawl-error spike after 14 days; a documented rollback exists and has been tested on
staging.

---

## Phase 9 — LMS

**Do not start this until phase 8 has been live and stable for at least two weeks.**
Sketched here so phases 2 and 6 do not paint you into a corner; scope it properly when you
get there.

**9a. Course catalogue (3 weeks)** — `courses`, `course_modules`, `lessons` migrations and
models; Filament resources; public catalogue and course detail pages; free preview lessons.

**9b. Enrollment & payment (4 weeks)** — student registration (Breeze is already in place from
6.1); `enrollments`, `orders`, `order_items`, `coupons`; Midtrans or Xendit integration;
webhook handling with signature verification and idempotency; invoice email. **Handle money
carefully: never trust a client-side price, always re-derive the total server-side, and make
webhook processing idempotent — a gateway will retry.**

**9c. Learning experience (4 weeks)** — student dashboard; lesson player; `lesson_completions`
progress tracking; **protected video delivery** (Mux / Cloudflare Stream / Vimeo Pro with
signed playback — not unlisted YouTube, which cannot enforce entitlement) (§3.4-31);
downloadable resources behind signed URLs.

**9d. Assessment & certificates (3 weeks)** — `quizzes`, `questions`, `question_options`,
`quiz_attempts`, scoring and attempt limits; PDF certificate generation with a verifiable
serial; a public verification page.

**9e. Polish (3 weeks)** — course reviews; instructor profiles; email notifications via queue;
student progress reports for admins; refund handling.

**Decide before you start 9b:** who issues the receipts and who handles refunds, and whether
you need to charge VAT. These are business questions that change the schema, and they are
cheaper to answer now than after launch.

---

## Risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| **R1** | **The live schema differs from the dump**, so phase 3 breaks. Already confirmed: `desain_rumah`/`galeri_rumah` missing, `likes` errors in the live log (§3.2-12) | **High** | High | Task 2.13 — export and diff production *before* finalizing migrations. Budget an extra week on phase 3 |
| **R2** | **SEO drops after cutover** — the company profile site exists to be found | Medium | **High** | Tasks 8.4, 8.5, 8.12. Record rankings before cutover; 301 everything; never launch without the map |
| **R3** | **The site was already compromised** via the open `upload.php` or the unvalidated image upload (§3.1-5, §3.1-6) | Medium | **High** | Task 0.14 — audit before you trust the data you are migrating |
| **R4** | **The hosting cannot run Laravel** (no SSH, no Composer, no document-root control) | Medium | High | Task 1.2 — verify in week 1, before writing code. If shared cPanel can't do it, budget for a VPS |
| R5 | Phase 9 scope creeps into phases 4–5 | High | Medium | Hard freeze: no LMS table, model or route before 8.13 is signed off |
| R6 | Client can't use the new admin panel and asks you to keep editing content | Medium | Medium | Tasks 5.14 and 8.6 — handover guide plus a live walkthrough, before cutover |
| R7 | Solo developer, no code review | High | Medium | CI from task 8.3 from day one; small commits; ask for a review on the auth and payment code specifically |
| R8 | Data loss during cutover | Low | **High** | Tasks 8.8 and 8.10 — backups off-webroot, old DB untouched for 30 days, rollback tested on staging |

---

## Decisions to make before you start

Each of these changes the schema or the plan. Answer them now, in writing, with the client.

1. **Merge `review_design` into `testimonials`?** (§4.3) Two tables, both just a YouTube URL. Merge unless they're editorially distinct to the client.
2. **Do `desain_rumah` / `galeri_rumah` exist in production with real rows?** (§4.3) If yes, they migrate into `portfolios`. If no, they're dead code. Task 2.13 answers this.
3. **Keep likes at all?** (§3.2-15) The current counter is page views. Either a real like feature, a `view_count`, or an `is_featured` flag. The homepage only needs the third.
4. **Which of the four `layanan_section` rows is real?** (§3.2-18) Client decision, task 3.3.
5. **Full i18n, or Indonesian only?** Right now only the homepage has an English version. Committing to two languages roughly doubles the content-entry effort in phase 5 — worth knowing before you build the toggle.
6. **Hosting target: shared cPanel or VPS?** (R4) Decides the deployment plan.
7. **Payment gateway: Midtrans or Xendit?** Not needed until 9b, but the answer affects whether phase 2 needs anything.

---

## Suggested working order

**Week 1:** phase 0 complete (day 1), then 1.1–1.2 and 2.13 — verify the hosting and the real
schema before you write anything you might have to throw away.

**Weeks 2–3:** rest of phase 1, start phase 2.

**Weeks 4–6:** phase 2 complete, phase 3 started. Expect phase 3 to surprise you.

**Weeks 7–14:** phases 4 and 5 in parallel, one page and its admin resource at a time — so
the client can review real progress rather than a half-built shell.

**Weeks 15–17:** phases 6 and 7.

**Weeks 18–20:** phase 8, then cutover.

**Then stop, watch it for two weeks, and only then open phase 9.**

---

## Implementation change log — 2026-10-23

This section records the five-item follow-up batch after the main plan. It is kept here,
next to the original tasks, so the plan and the actual implementation do not drift into
separate documents.

| # | Area | What was implemented | Regression evidence |
|---|---|---|---|
| 1 | Site settings | `HasTranslations::getTranslations()` now treats SQL `NULL`, JSON `null`, and legacy plain strings as empty/fallback translation maps. `EditSiteSetting` normalises the form state before Filament fills it. The settings edit page no longer crashes on old rows. | `tests/Feature/Admin/SiteSettingAdminTest.php` covers SQL `NULL`, `"null"`, plain text, a normal map, and the create page. |
| 2 | Non-desktop header | The mobile panel now has a clipping grid child and an inner element that owns padding and the border. The actual mobile anchors are styled with `.mobile-nav-item.nav-link`, so `Portofolio` is no longer clipped and no border line remains while the panel is collapsed. Below 768px the compact-header consultation CTA is hidden because logo + language switcher + CTA + hamburger needed about 427px; the same CTA remains at the bottom of the opened panel. | Browser checks at 390/768/1024/1440 px confirmed no horizontal overflow, a zero-height collapsed panel, and a fully visible `Portofolio` link. Header markup is in `resources/views/partials/header.blade.php`. |
| 3 | Site icons | Added 3D Expert-branded `favicon.ico`, 16/32/48 px PNGs, Apple touch icon, 192/512 px Android icons, and `site.webmanifest`. The public layout and Filament panel both use the new icon. The generator is `deploy/generate-brand-icons.php`, using the existing logo/mark. | `tests/Feature/PublicPagesTest.php` checks the files, links, and manifest; layout and `AdminPanelProvider` wiring verified in the browser. |
| 4 | Payments | One polymorphic `Payment` flow now serves **courses and paid events**. `EventRegistration` implements the same `Payable` contract as `CourseEnrollment`. The generic page is `payments.show` (`/pembayaran/{order_id}`); the old fake QR page and `CoursePaymentController` were removed. | `tests/Feature/Payments/PaymentFlowTest.php`, plus updated event/course feature tests. |
| 5 | Career form | Added optional `portfolio_url` after the motivation/cover letter, validated as `http`/`https` only, persisted on `job_applications`, shown in the admin application detail, and translated for ID/EN. | `tests/Feature/Careers/JobApplicationTest.php`: save, reject `javascript:`, render both locales. |

### Payment gateway behavior

- **One settlement path.** `SettlePayment` locks the payment row, marks it `settlement`,
  calls `fulfilPayment()` on the payable, and writes an audit entry. Duplicate Midtrans
  notifications therefore cannot grant access twice.
- **One expiry path.** `ExpirePayment` locks the row and calls `expirePayment()` on the
  payable. Events release the held seat through `CancelEventRegistration`; courses mark
  a still-pending enrollment as `revoked`.
- **Real QRIS only.** `MidtransQrisService` calls the Core API `/charge` endpoint with
  `payment_type=qris` and the customer's stored profile. The UI renders the QR image URL
  returned by Midtrans. There is no generated fallback QR, no emoji, no hardcoded NMID,
  and no public simulation button.
- **Signed webhook.** `POST /pembayaran/notifikasi` is not locale-prefixed and is excluded
  from CSRF. The controller verifies
  `SHA512(order_id + status_code + gross_amount + server_key)`, rejects amount mismatches,
  and is idempotent.
- **Local testing route.** `POST /pembayaran/{order_id}/simulasi` remains only for
  `local`/`testing` and returns 404 in production or when `MIDTRANS_IS_PRODUCTION=true`.
  It is never rendered as a button.
- **No gateway keys.** Checkout still creates the pending order and holds the seat. The
  payment page shows a contact-admin message instead of inventing a QR code.

### 15-minute payment expiry

`config/payments.php` defines:

```dotenv
PAYMENT_EXPIRY_MINUTES=15
```

When a payment is created, `expires_at = now() + PAYMENT_EXPIRY_MINUTES`. The same value
is sent to Midtrans as `custom_expiry` so the gateway and the application agree. Expiry
is enforced from three places:

1. `php artisan payments:expire`, scheduled every minute in `routes/console.php`.
2. The payment page itself, so opening an overdue order renders the expired state even
   if the scheduler is briefly behind.
3. Midtrans `expire`, `cancel`, `deny`, and `failure` notifications.

For **paid events**, the pending registration counts toward `seats_taken` from the moment
it is created. This is deliberate: otherwise two people could pay for the same last seat.
The seat is released only by `CancelEventRegistration`, which is also called by the expiry
path. The sweep is idempotent, so running it twice cannot release two seats. When a customer
cancels a pending registration manually, the pending payment attempt is marked `cancelled`
at the same time, so an old QR cannot be scanned into a seat that has already been released.

If Midtrans settles **after** the local expiry/cancel, the webhook records
`raw_response.late_settlement = true` and returns HTTP 200 without confirming the
registration or activating the course. Staff must reconcile/refund that payment from the
admin panel; a non-200 response would only make Midtrans retry forever.

**Operational requirement:** the scheduler cron must be running. Without it, expiry still
happens lazily when the customer opens the payment page, but an abandoned order could hold
an event seat longer than 15 minutes.

### Midtrans sandbox test guide

1. Create a sandbox account at <https://dashboard.sandbox.midtrans.com> and copy the
   **Server Key**, **Client Key**, and **Merchant ID** from *Settings → Access Keys*.
2. Set these in `.env` (never commit real keys):

   ```dotenv
   MIDTRANS_SERVER_KEY=SB-Mid-server-...
   MIDTRANS_CLIENT_KEY=SB-Mid-client-...
   MIDTRANS_MERCHANT_ID=...
   MIDTRANS_IS_PRODUCTION=false
   PAYMENT_EXPIRY_MINUTES=15
   ```

3. Expose the local app with a tunnel (ngrok, Cloudflare Tunnel, or similar). In the
   Midtrans sandbox dashboard set **Payment Notification URL** to:

   ```text
   https://<your-tunnel-host>/pembayaran/notifikasi
   ```

4. Register for a paid event or buy a course. The payment page should show a real QRIS
   image from `api.sandbox.midtrans.com`. If it shows the contact-admin panel instead,
   check the server key, the tunnel, and `storage/logs/laravel.log`.
5. Simulate payment with the Midtrans QRIS sandbox simulator:
   <https://simulator.sandbox.midtrans.com/qris/index>. Use the order ID and amount shown
   on the payment page. A successful simulation posts a signed `settlement` notification.
6. Confirm the event registration becomes `confirmed` (seat stays occupied) or the course
   enrollment becomes `active`. Check `Program → Pembayaran` in the admin panel for the
   settlement and raw gateway payload.
7. Test expiry with `PAYMENT_EXPIRY_MINUTES=1`, create another order, wait, then run
   `php artisan payments:expire`. The pending registration must become `cancelled` and the
   event's `seats_taken` must decrease. For a course, the enrollment must become `revoked`.
8. Do not enable a public "simulate payment" button for production. The route above is
   testing-only and already self-disables outside `local`/`testing`.
