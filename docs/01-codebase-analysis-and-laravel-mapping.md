# 3D Expert Web — Codebase Analysis & Laravel Rewrite Mapping

**Analyzed:** `3D_Expert_Web.zip` (contents of the live webroot, dated Jul–Sep 2025)
**Scope of code:** 13 PHP files, 8,251 lines total; 1 SQL dump; ~176 MB of static assets
**Target:** Rewrite as a Laravel application, hardened and ready to host an LMS

> **How to read this document.** Section 1–3 are **findings from the code**, and every claim
> carries its file and location so you can verify it. Section 4–6 are **my recommendations** —
> they are not derived from the existing code and you should treat them as proposals to
> accept, modify or reject. The two are kept separate on purpose.

---

## 1. What the code actually is

A hand-written PHP company profile site for an architecture/3D-design firm, with a
self-built mini-CMS. No framework, no `composer.json`, no autoloader, no migrations,
no tests, no `.env`.

| File | Lines | Role |
|---|---|---|
| `index.php` | 928 | Homepage (ID) |
| `index-en.php` | 961 | Homepage (EN) — near-duplicate of `index.php` |
| `detail.php` | 1,143 | Portfolio detail page |
| `karir.php` | 1,241 | Public careers listing |
| `edit.php` | 1,856 | Admin panel — ~15 unrelated CRUD handlers in one file |
| `editkarir.php` | 568 | Admin panel for job vacancies |
| `login.php` | 707 | Login form + handler |
| `reg.php` | 74 | Open self-registration |
| `upload.php` | 95 | PDF upload into DB (BLOB) |
| `koneksi.php` | 13 | PDO connection |
| `logout.php` | 10 | Session destroy |
| `partials/header.php`, `partials/footer.php` | 655 | Shared chrome |

**Request flow:** filesystem routing. `detail.php?id=5` → include `partials/header.php`
→ `require 'koneksi.php'` → run queries inline → emit HTML → `include 'partials/footer.php'`.
Admin writes happen at the top of the same file that renders the page, then flow control is
handed back to the browser with `echo "<script>alert(...); location.href='...'</script>"`.

**Database:** 13 tables (`dexc8115_3dexperts.sql`). Content is stored in the DB rather than
hardcoded — that is the codebase's biggest structural asset, because it means the rewrite is
mostly a remodelling job, not a content re-entry job.

---

## 2. The plus side

These are real and they matter — a lot of hand-rolled PHP of this vintage gets these wrong.

**2.1 SQL injection is genuinely handled.** Every query I found uses PDO prepared statements
with bound parameters. There is no string-concatenated SQL anywhere in the 13 files.
`koneksi.php:9` sets `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION`. Even the one place that
interpolates into SQL — `edit.php`, the `mengapa_section` list handler,
`"UPDATE mengapa_section SET $column = NULL WHERE id = ?"` — guards the column name with
`in_array($column, ['list1','list2'])` first. That is the correct pattern.

**2.2 Passwords are properly hashed.** `reg.php:28` uses
`password_hash($password, PASSWORD_BCRYPT)`; `login.php:24` verifies with
`password_verify()`. No MD5, no SHA1, no plaintext. The stored hash in the dump
(`dexc8115_3dexperts.sql:415`) is a `$2y$10$` bcrypt hash, confirming this is what runs
in production.

**2.3 Output escaping is applied fairly consistently.** `htmlspecialchars()` appears
10–25 times per page file (25× in `edit.php`, 17× in `index.php`, 12× in `editkarir.php`).
I grepped for unescaped echoes of text-bearing DB columns (`title`, `judul`, `keterangan`,
`description`, `requirement`, `location`, `departemen`, `features`, …) and found **zero**
hits. The only unescaped interpolations are numeric IDs in `href`/`value` attributes
(e.g. `detail.php:995`, `edit.php:1262`), which are low risk.

**2.4 Content is data, not markup.** Ten tables exist purely to drive page sections:
`hero_banner`, `features_section`, `testimonial_section`, `review_design`,
`layanan_section`, `mengapa_section`, `alur_section`, `location_section`, `job_detail`,
`properties`. Nothing editable is baked into HTML. **This is why the Laravel migration is
tractable** — the tables map onto Eloquent models almost one-for-one.

**2.5 The portfolio module is properly normalized.** `properties` → `property_images` /
`property_details`, both with real foreign keys and `ON DELETE CASCADE`
(`dexc8115_3dexperts.sql:257–312`). This is the one part of the schema that needs no
rethinking, only renaming.

**2.6 Some upload validation exists.** The hero-banner branch of `edit.php` checks
`mime_content_type()` against an allowlist of four image types; the property-image branch
checks an extension allowlist plus a 5 MB size cap; the gallery loop calls `getimagesize()`
and `continue`s on failure. The intent was there — see §3.1 item 5 for where it wasn't
applied.

**2.7 A layout concept already exists.** `partials/header.php` and `partials/footer.php`
are extracted and included by every public page, with a `$pageTitle` override
(`partials/header.php`, the `<title>` line). This maps directly onto a Blade layout.

---

## 3. The minus side

### 3.1 Security — must be fixed regardless of whether you rewrite

These are the urgent ones. Items 1–4 are exposed **right now** on the live domain.

1. **A 203 MB full backup sits in the webroot.** `backup-3dexpert.id-7-9-2025.tar.gz` is
   directly downloadable by anyone who guesses or finds the filename. Alongside it:
   `dexc8115_3dexperts.sql` (19 KB, contains the admin bcrypt hash at line 415) and
   **`Kredensial Akses.txt`, which contains the admin login in plaintext**
   (`user: admin2 / pass: admin123`). Three files, full compromise.

2. **`error_log` is in the webroot and readable.** It leaks absolute server paths
   (`/home/dexc8115/public_html/`, `/home/ginf3617/public_html/digiweb.gingersun.id/`),
   the PHP version and include path, DB schema errors and full stack traces.

3. **`.trash/3dexpert/.git/` is in the webroot**, including `objects/pack/*.pack` — the
   entire git history is downloadable.

4. **DB credentials are hardcoded in `koneksi.php:1–5`** and shipped with the code.
   No environment separation.

5. **Arbitrary file upload → remote code execution.** In both the insert and the update
   branch of `edit.php`, the main image is saved as
   `uniqid() . '_' . $_FILES['gambar_utama']['name']`, moved to `"assets/images/"`,
   with **no extension check and no MIME check**. `assets/images/` is web-served, so a
   `.php` upload becomes executable code on the server. Note the inconsistency: the gallery
   loop right below it *does* call `getimagesize()`, and the hero-banner handler *does* check
   MIME — `gambar_utama` checks neither. This is the single most serious code-level defect.

6. **`upload.php` has no authentication at all.** The file starts with
   `require 'koneksi.php'` and goes straight into processing `$_POST` / `$_FILES`
   (`upload.php:4–20`) and serving `?view_pdf=` (`upload.php:24–40`). No
   `session_start()`, no `$_SESSION` check. Any anonymous visitor can overwrite the PDF
   attached to any service.

7. **`reg.php` is open self-registration, and every account is a superadmin.** `reg.php`
   has no auth gate, no invite, no approval. And `edit.php:6` / `editkarir.php:5` gate only
   on `isset($_SESSION["user_id"])` — there is no role column in `users`
   (`dexc8115_3dexperts.sql:397–406`). So anyone who registers gets full write access to
   the entire site.

8. **No CSRF protection anywhere.** Not one token in any of the 13 files. Every admin
   form in `edit.php` and `editkarir.php` is forgeable.

9. **Destructive actions are exposed over GET:** `edit.php?hapus={id}`,
   `edit.php?hapus_gambar={id}`, `edit.php?mode=delete&id={id}`,
   `editkarir.php?delete={id}`. A crawler, a browser prefetch, or a single `<img>` tag on
   any page an admin visits can delete records.

10. **No session hardening.** `login.php:26–28` sets `$_SESSION` without
    `session_regenerate_id()` — session fixation. No `httponly` / `secure` / `samesite`
    cookie flags are set anywhere. No login rate limiting or lockout, so the password is
    brute-forceable (and per item 1, it is `admin123`).

11. **Debug logging left in production.** `login.php:14`:
    `error_log("Redirect to: " . $redirect_to);` — the comment on the line above literally
    says `// Debug: Log redirect_to value (hapus setelah testing)`. This is what fills
    `error_log` with `Redirect to: edit` entries.

### 3.2 Correctness & data integrity

12. **The code and the schema have drifted apart, in both directions.** `edit.php` reads
    and writes `desain_rumah` and `galeri_rumah` (the delete, insert and update handlers at
    the top of the file) — **neither table exists in the SQL dump**. In the other direction,
    `error_log:1` records a live fatal: `Unknown column 'likes' in 'ORDER BY'` at
    `index.php:66`, even though `properties.likes` *is* in the dump. Conclusion: the dump and
    the deployed database are not the same schema, and at least one homepage query was
    crashing in production.

13. **`koneksi.php` points at a different database than the dump.** The connection targets
    `ginf3617_dgiweb` / user `ginf3617_digiweb`; the dump is `dexc8115_3dexperts`. The
    `error_log` shows both `/home/dexc8115/` and `/home/ginf3617/` paths. One codebase is
    being run on two deployments with no configuration switch — the credentials are edited
    by hand per server.

14. **`upload.php` writes a column that doesn't exist.** It does
    `UPDATE layanan_section SET pdf_blob = ?` (`upload.php:11`), but `layanan_section`
    (`dexc8115_3dexperts.sql:138–146`) has no `pdf_blob` column. Separately: storing PDFs
    as MySQL BLOBs is the wrong call — there are 162 MB of PDFs in `assets/pdf/`.

15. **`detail.php` counts page views and calls them likes.** `detail.php` runs
    `UPDATE properties SET likes = likes + 1 WHERE id = ?` on every GET request, then
    `index.php:66` picks the featured property with `ORDER BY likes DESC`. So the homepage
    hero is chosen by raw page-view count, inflated by every bot and every refresh. It also
    makes a GET request non-idempotent.

16. **`mengapa_section` is a table abused as two lists.** The first row is the "main"
    record; every row after it holds a value in either `list1` or `list2` and is explicitly
    skipped (`edit.php`: `foreach ($rows as $index => $row) { if ($index === 0) continue; ... }`).
    "Delete" is implemented as `SET $column = NULL`, so deleted items leave permanent
    all-null orphan rows. The table can never be cleaned up.

17. **Wrong column types.** `layanan_section.features` is a `text` column holding a JSON
    array (with `\r\n` literals baked in — `dexc8115_3dexperts.sql:155`).
    `layanan_section.original_price` and `.price` are `varchar(50)` holding `'24.0002'` and
    `'98.6002'` — prices as strings, so they cannot be sorted, summed or formatted, and the
    stray trailing `2` is invisible corruption.

18. **Test data is live data.** `layanan_section` holds four near-identical
    "Pembangunan Lengkap" rows (ids 1–4, `dexc8115_3dexperts.sql:155`).
    `job_detail:3` is `'asddddddddddddddMARKETING EXECUTIVE'` in department
    `'Marketingssssssssssss'` (`dexc8115_3dexperts.sql:125`) — on a public careers page.
    There is no draft/published state and no soft delete, so keyboard-mashing during testing
    ships straight to visitors.

19. **Singleton rows addressed by magic number.** `edit.php` hardcodes `$features_id = 1;`.
    `alur_section` and `location_section` are read with `LIMIT 1` and written with
    `UPDATE alur_section SET link_url = ? LIMIT 1` — no `WHERE`. If row 1 is ever deleted,
    the admin panel silently stops saving.

20. **`session_start()` is called twice** in `edit.php` — once at the top, again inside the
    `location_section` handler — producing a PHP notice on every save.

### 3.3 Architecture & maintainability

21. **Up to 9 database connections per page load.** `index.php` and `index-en.php` each
    contain 9 occurrences of `koneksi.php`, using `include` / `require` (not `_once`).
    Each one re-executes `new PDO(...)`, opening a fresh connection. `edit.php` does it 3×.

22. **Controller and view are the same file.** `edit.php` is 1,856 lines containing roughly
    15 independent CRUD handlers, plus 451 lines of inline `<style>` and 107 of inline
    `<script>`. `detail.php` carries 841 lines of inline CSS; `karir.php` 794 CSS + 313 JS;
    `login.php` 466 lines of CSS for a single form. There is a `style.css` (4,370 lines), so
    the styling lives in two places at once.

23. **i18n by copy-paste.** `index-en.php` is a duplicate of `index.php` — a
    whitespace-insensitive diff shows ~406 differing lines out of ~930. Every content or
    layout change must be made twice, correctly, in both. Only the homepage was ever
    duplicated, so the rest of the site has no English version at all.

24. **Flow control via injected JavaScript.** About eight times in `edit.php`, the response
    to a successful write is
    `echo "<script>alert('...'); location.href='" . $_SERVER['PHP_SELF'] . "';</script>"`
    instead of an HTTP redirect with a flash message. This is why `edit.php:2` and
    `login.php:2` need `ob_start()`. It cannot be consumed by anything other than a browser
    — no API, no mobile app, no test.

25. **URLs are filenames.** `detail.php?id=5`, `karir.php`, `index-en.php`. For a company
    profile site whose entire job is to be found on Google, this costs you: no keyword slugs,
    no clean hierarchy, no canonical structure, and a language variant on a separate filename
    instead of a proper locale path.

26. **No validation layer.** `$_POST['judul']`, `$_POST['price']`, `$_POST['id']` etc. go
    directly into queries with no required/type/length checks (`edit.php`, insert and update
    handlers). `$_POST['id']` is not even cast to `int` in the update branch, although the
    delete branch does `(int) $_GET['hapus']`.

27. **176 MB of unoptimized assets served straight from disk** — 162 MB `assets/pdf/`,
    14 MB `assets/images/`. No resizing, no WebP, no cache headers, no CDN.

28. **No caching.** Every page view re-queries every content section from MySQL.

29. **Nothing to run a build or a test against.** No `composer.json`, no `package.json`,
    no migrations, no seeders, no test suite, no CI, no `.htaccess`.

### 3.4 LMS readiness — the part that decides the rewrite

30. **There is nothing here to build an LMS on.** No roles or permissions, no student
    accounts (the `users` table is admin-only and has 1–4 rows), no enrollment, no orders or
    payments, no progress tracking, no quizzes, no certificates, no queue, no mail, no API.
    The existing auth model — one flat table, open registration, every user a superadmin — is
    the exact inverse of what an LMS needs.

31. **Paid content cannot be protected in this structure.** Every piece of media is either a
    public YouTube URL stored in the DB (`testimonial_section.youtube_url`,
    `review_design.youtube_url`, `mengapa_section.video_url`, `location_section.video_url`)
    or a file under `assets/`, addressable directly by URL with no access check. The moment
    someone pays for a course, you need signed/expiring delivery and per-user entitlement
    checks — which this file layout cannot express.

### Verdict

The foundations that are hardest to retrofit are **already right**: parameterized SQL,
bcrypt passwords, consistent escaping, and content in the database. The problems are
structural — no framework primitives (CSRF, validation, routing, authorization, migrations),
no separation of concerns, and no access-control model at all.

A rewrite is the right call, and the reason is item 30/31 rather than any of the bugs above.
Every one of items 1–29 could be patched in place. But an LMS needs roles, enrollment,
payment, protected media and a job queue, and bolting those onto 8,251 lines of mixed
controller-and-view PHP with no test suite would cost more than rebuilding. **Do the rewrite
— but fix §3.1 items 1–6 on the live site this week, independently, because the rewrite will
take months and the plaintext credential file is downloadable today.**

---

## 4. Proposed Laravel architecture *(my recommendation — not from the codebase)*

> Nothing in this section comes from the existing code. These are my proposals; push back on
> any of them.

### 4.1 Stack

| Layer | Choice | Why |
|---|---|---|
| Framework | Laravel, latest stable | Verify the current version when you scaffold — Laravel 12 was current as of my knowledge; 13 may have shipped since |
| PHP | 8.2+ | Framework requirement |
| DB | MySQL 8 / MariaDB 10.6+ | Matches current hosting |
| Views | Blade + Tailwind CSS + Alpine.js | `edit.php` already uses Tailwind utility classes (`form-input w-full text-gray-200`, `text-red-500`), so this continues an existing choice |
| Admin panel | **Filament** | The single biggest leverage point for a solo developer: replaces all 2,424 lines of `edit.php` + `editkarir.php`, and gives you resource CRUD, media handling, roles UI and later course management almost for free |
| Auth | Laravel Breeze (public/student) + Filament's own (admin) | CSRF, hashing, throttling, email verification, password reset — all built in |
| Roles | `spatie/laravel-permission` | Fixes §3.1 item 7 properly |
| Media | `spatie/laravel-medialibrary` | Image conversions, responsive images, non-webroot storage |
| Files | S3-compatible object storage (or local disk **outside** webroot + signed routes) | Gets 162 MB of PDFs out of `assets/` and makes paid-content gating possible |
| i18n | Laravel localization + `{locale}` route prefix | Kills `index-en.php` |
| Cache/queue | Redis (file/database cache is fine to start) | Content caching now, jobs and email later |
| Tests | Pest | There is currently no test of any kind |

### 4.2 How a request will work

```
Route (routes/web.php)            →  named, slug-based, locale-prefixed
  └─ Middleware                   →  CSRF, auth, role, locale, throttle
      └─ Controller (thin)        →  no SQL, no HTML
          └─ FormRequest          →  validation + authorization, before the action runs
          └─ Service / Action     →  business logic, transactions
              └─ Eloquent Model   →  relationships, casts, scopes
          └─ Blade view           →  layout + components, zero queries
              └─ redirect()->with('success', ...)   →  replaces echo'd <script>alert()
```

Every §3.1 and §3.3 defect above is closed by a framework primitive rather than by
discipline: `@csrf` (item 8), `Route::delete` + method spoofing (item 9), automatic session
regeneration on login (item 10), `FormRequest` (item 26), `config/database.php` + `.env`
(items 4, 13), a single shared connection per request (item 21), migrations (item 12),
`Route::get('/portofolio/{portfolio:slug}')` (item 25).

### 4.3 Table mapping

**Company profile — port these:**

| Current | New | Changes |
|---|---|---|
| `hero_banner` | `hero_slides` | Media library instead of a filename string; add `sort_order`, `is_active` |
| `features_section` | `site_settings` (singleton) | Kills the `$features_id = 1` magic number (item 19) |
| `alur_section`, `location_section` | `site_settings` (singleton) | Same |
| `testimonial_section` | `testimonials` | Add `is_published`; keep `is_highlighted`, `rating` |
| `review_design` | `testimonials` with `type` enum | *My call:* both tables are just a YouTube URL + ordering; merging them removes a table. Reject this if they're editorially distinct to you. |
| `layanan_section` | `services` + `service_features` | `price`/`original_price` → `decimal(15,2)` (item 17); the JSON `features` text column → one row per feature; PDF → media library, not a BLOB (item 14) |
| `mengapa_section` | `value_propositions` | One row per item with a `group` enum (`list1`/`list2`) + `sort_order`; the main description moves to `site_settings`. Fixes the null-orphan design (item 16) |
| `job_detail` | `job_vacancies` | Add `slug`, `is_published`, `closes_at`, soft deletes (item 18) |
| `properties` | `portfolios` | Add `slug` (item 25), `is_published`, `description`; `likes` → see below |
| `property_images` | `portfolio_images` | Keep the FK cascade — it's already correct |
| `property_details` | `portfolio_specs` | Keep as key/value; add `sort_order` |
| `users` | `users` + `roles`/`permissions` | Add `role`, `email_verified_at`; **close open registration for admins** (item 7) |

**Deliberately dropped:**

- `desain_rumah`, `galeri_rumah` — no such tables in the dump, and their fields
  (`judul`, `style`, `ukuran_tanah`, `jumlah_kamar_tidur`, `lokasi`, `keterangan`) are the
  same concept as `properties` + `property_details`. **Inference:** this is an earlier version
  of the portfolio module that was superseded, and the `edit.php` handlers are dead code.
  **Verify against the live database before you drop them** — if those tables exist in
  production with real rows, they need migrating into `portfolios` instead.
- `properties.likes` — currently a page-view counter, not likes (item 15). Split it:
  `view_count`, incremented from a queued job, never in a GET handler; and a real
  `portfolio_likes` pivot (user or session keyed) if you actually want likes. Or drop likes
  entirely and pick the featured item with an `is_featured` flag, which is what the homepage
  really needs.

**New, for the LMS phase — none of this exists today:**

```
courses            (slug, title, description, price, level, instructor_id, is_published)
course_modules     (course_id, title, sort_order)
lessons            (module_id, title, slug, type: video|pdf|text|quiz, duration, is_preview)
lesson_contents    (lesson_id, storage path / video id / body)
enrollments        (user_id, course_id, status, enrolled_at, expires_at)
lesson_completions (enrollment_id, lesson_id, completed_at)   → progress
orders             (user_id, total, status, payment_gateway_ref)
order_items        (order_id, course_id, price_at_purchase)
coupons            (code, type, value, valid_until, usage_limit)
quizzes            (lesson_id, passing_score, attempt_limit)
questions          (quiz_id, body, type)
question_options   (question_id, body, is_correct)
quiz_attempts      (user_id, quiz_id, score, submitted_at)
certificates       (enrollment_id, serial, issued_at)
course_reviews     (user_id, course_id, rating, body, is_published)
```

Payment gateway: Midtrans or Xendit (both have maintained Laravel packages and handle
Indonesian payment methods). Video: Mux, Cloudflare Stream, or Vimeo Pro — **not**
unlisted YouTube, which offers no entitlement enforcement (item 31).

### 4.4 The one thing that must not be lost: SEO

The current site is indexed under `index.php`, `karir.php`, `detail.php?id=N`. When the
routes change, every one of those URLs must 301-redirect to its new equivalent, or the site
drops out of Google and the rewrite has made the business worse. This is the most commonly
skipped step in a rewrite of a company profile site. Concretely:

```
/index.php            → /
/index-en.php         → /en
/karir.php            → /karir
/detail.php?id=1      → /portofolio/{slug-of-id-1}
/login.php            → /admin/login
/reg.php              → 410 Gone
/upload.php           → 410 Gone
```

Build the `id → slug` map during the data migration and keep it in a `legacy_redirects`
table, so it survives after the old code is gone.

---

## 5. What the rewrite looks like, end to end *(my recommendation)*

Nine phases. Details, tasks and acceptance criteria are in the companion document
`02-implementation-plan-and-tasks.md`.

| # | Phase | What it delivers | Depends on |
|---|---|---|---|
| **0** | **Emergency hardening of the live site** | The four exposed files removed, credentials rotated, `upload.php` and `reg.php` closed. **Do this first — days, not weeks.** | nothing |
| 1 | Scaffold & tooling | Laravel installed, `.env`, Tailwind, Filament, git, staging | 0 |
| 2 | Schema & models | Migrations, Eloquent models, relationships, factories | 1 |
| 3 | Data migration | An idempotent artisan command that moves the live DB into the new schema, cleaning duplicates and test rows | 2 |
| 4 | Public site | Blade layouts + components, homepage, portfolio, services, careers, i18n, slug routes | 2, 3 |
| 5 | Admin panel | Filament resources replacing `edit.php` + `editkarir.php` | 2, 3 |
| 6 | Auth, roles & hardening | Breeze, spatie/permission, policies, throttling, security headers | 5 |
| 7 | Media pipeline | PDFs and images off the webroot, image conversions, signed URLs | 5 |
| 8 | Test, SEO & launch | Pest suite, 301 map, sitemap, staging sign-off, cutover, backups off-webroot | 4–7 |
| **9** | **LMS** | Courses, enrollment, payment, progress, quizzes, certificates | 8 |

**Do not start phase 9 until phase 8 is live and stable.** The company profile site is the
thing that currently earns; the LMS is new revenue built on top of it. Shipping them together
doubles the surface area of the first release and delays the security fix that actually
matters.

---

## 6. Honest trade-offs

**What the rewrite buys you:** every defect in §3.1 and §3.3 closed by framework default
rather than by remembering; a real authorization model; a schema that can carry an LMS;
testability; and a codebase a second developer could join.

**What it costs:** two to four months of part-time work before the visitor sees anything new.
The site works today. Nothing in phases 1–8 is a feature — it is all rebuilding what already
exists, better. Be honest with your stakeholders about that, and use phase 0 to buy the
safety you need in the meantime.

**Where the risk actually is:** (a) the data migration, because the live schema does not match
the dump (§3.2 items 12–14) and you will not know the real shape until you export production;
(b) SEO, if the 301 map is skipped; (c) scope creep from phase 9 leaking into phases 4–5.

**What I would reconsider:** if the LMS were *not* on the roadmap, I would not recommend a
rewrite. I would patch §3.1, normalize the three broken tables, and leave it. The LMS is what
makes rebuilding cheaper than repairing.
