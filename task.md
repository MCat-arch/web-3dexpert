# 3D Expert Laravel Landing Page Parity Tasks

## Goal

Reproduce the legacy landing page content and section order from `../index.php` in Laravel. Visual design may be modernized, but content, resource usage, interactions, and overall ordering must remain equivalent.

## Agreed Landing Page Order

1. Header/navigation
2. Hero
3. Features
4. Portfolio
5. Testimonials
6. Benefits
7. Review desain
8. Location
9. Mengapa Memilih Kami
10. Services
11. Order flow
12. FAQ
13. Floating WhatsApp
14. Footer

## Working Rules

- Treat the legacy `index.php` as the content source of truth.
- Keep the Laravel architecture: controllers, Blade components, Eloquent models, translations, validation, and private media storage.
- Do not silently remove legacy content because it is malformed. Mark it for review and preserve it where possible.
- Each phase must end with an executable validation step.
- Do not begin the next phase until the current phase passes its acceptance criteria.

## Status Legend

- `[ ]` Not started
- `[-]` In progress
- `[x]` Complete
- `[!]` Blocked or requires a content decision

---

## Phase 0 - Baseline and Content Inventory [x]

- [x] Create a legacy-to-Laravel section checklist from `index.php`.
- [x] Record all legacy section titles, body copy, buttons, links, video URLs, FAQ items, benefit items, and asset references.
- [x] Record the expected order listed above as a parity test contract.
- [x] Identify which content comes from the legacy database and which content is hardcoded in PHP.
- [x] Identify content decisions that cannot be inferred safely, especially duplicate services and malformed prices.

Acceptance:

- Every visible legacy landing-page block has a target Laravel location. See `docs/landing-page-parity-inventory.md`.
- Every missing block has an explicit implementation task below.

## Phase 1 - Data and Resource Readiness

- [ ] Verify the Laravel destination database contains the migrated rows.
- [ ] Restore all hero images, logo files, icons, portfolio files, uploads, and PDF brochures.
- [ ] Link each service to its correct brochure file.
- [ ] Add the legacy company contact values, including WhatsApp, to `site_settings`.
- [ ] Import all 25 legacy benefit items and their icon paths.
- [ ] Create a FAQ data source containing all 10 legacy FAQ questions and answers.
- [ ] Decide how duplicate or malformed services should be corrected, then publish the approved services.
- [ ] Ensure migrated media paths resolve through the Laravel media controller.

Acceptance:

- No migrated content references a missing local file.
- All required data is available to the landing-page controller without hardcoded legacy database queries.

## Phase 2 - Header, Hero, and Features

- [ ] Match the legacy header/navigation destinations and anchor behavior.
- [ ] Restore the header consultation CTA and link it to the configured WhatsApp contact.
- [ ] Restore the language switcher behavior for Indonesian and English.
- [ ] Render the hero carousel with all legacy hero images.
- [ ] Match the legacy hero headline, subtitle, booking CTA, and carousel behavior.
- [ ] Recreate the Features section immediately after the Hero.
- [ ] Render the Features heading, subheading, Projects counter, and Clients counter.
- [ ] Restore the `Semua Desain` and `Desain Terbaik` buttons.

Acceptance:

- Header -> Hero -> Features is the first visible page sequence.
- Hero and Features content matches the legacy Indonesian page.
- English content has an equivalent translation.

## Phase 3 - Portfolio, Testimonials, and Benefits

- [ ] Render the Portfolio section immediately after Features.
- [ ] Preserve the featured portfolio behavior.
- [ ] Preserve the legacy visible-item limit and load-more behavior without dropping records from the controller query.
- [ ] Ensure all portfolio cards have valid detail links and cover images where available.
- [ ] Render Testimonials after Portfolio.
- [ ] Preserve two highlighted testimonials at the top.
- [ ] Render all remaining testimonials with their titles and ratings.
- [ ] Render the complete Benefits section after Testimonials.
- [ ] Render all legacy benefit titles and icons.
- [ ] Restore the Benefits booking/consultation CTA.

Acceptance:

- Portfolio -> Testimonials -> Benefits order matches the legacy page.
- No benefit item from the legacy page is missing.
- No published portfolio renders a broken cover image.

## Phase 4 - Reviews, Location, and Why Choose Us

- [ ] Render Review desain after Benefits.
- [ ] Render all migrated design reviews without an unintended hard limit.
- [ ] Render Location after Review desain.
- [ ] Restore office name, address, Google Maps link, and location video.
- [ ] Render Mengapa Memilih Kami after Location.
- [ ] Restore the description, YouTube video, list 1, and list 2 content.
- [ ] Preserve the legacy section anchor used by navigation.

Acceptance:

- Review -> Location -> Mengapa Memilih Kami order matches the legacy page.
- Location and Why Choose Us remain visible when their migrated settings exist.

## Phase 5 - Services, Order Flow, FAQ, and Floating WhatsApp

- [ ] Render Services after Mengapa Memilih Kami.
- [ ] Render all four legacy service cards, including prices, discount text, and features.
- [ ] Restore the per-service brochure CTA.
- [ ] Restore the per-service WhatsApp consultation behavior.
- [ ] Render Order flow after Services.
- [ ] Restore the order-flow link and thumbnail/play treatment.
- [ ] Add the complete 10-item FAQ section after Order flow.
- [ ] Restore FAQ accordion behavior: one open item at a time and outside-click close.
- [ ] Restore the FAQ consultation CTA.
- [ ] Add the floating WhatsApp button and legacy WhatsApp icon.

Acceptance:

- Services -> Order flow -> FAQ -> Floating WhatsApp order matches the legacy page.
- All four brochures resolve through controlled Laravel routes.
- All 10 FAQ items are present in Indonesian and English.

## Phase 6 - Footer and Shared Content

- [ ] Render Footer after the floating WhatsApp control.
- [ ] Restore company logo, office address, and contact details.
- [ ] Restore Facebook, Instagram, YouTube, TikTok, and WhatsApp links.
- [ ] Restore footer quick links to the landing-page anchors.
- [ ] Verify the footer is present on Indonesian and English pages.

Acceptance:

- Footer is the final page block.
- No footer contact or social item is silently omitted when its setting exists.

## Phase 7 - Routing, Localization, and Compatibility

- [ ] Make default Indonesian routes work without an unwanted `/id` prefix.
- [ ] Keep `/en` routes working for the English landing page.
- [ ] Keep legacy redirects for `index.php`, `index-en.php`, `detail.php?id=N`, and `karir.php`.
- [ ] Ensure portfolio, service, career, and brochure routes use slugs correctly.
- [ ] Link contact and consultation CTAs to the configured WhatsApp contact; do not generate a `contact.create` route.
- [ ] Verify all header/footer anchors resolve to a real section.
- [ ] Verify all translated strings exist in both locale files.

Acceptance:

- Legacy URLs redirect correctly.
- New public URLs return successful responses.
- No route generates a missing-parameter or model-binding error.
- Every contact CTA opens the configured WhatsApp destination; no contact-form route is required.

## Phase 8 - Validation and Sign-off

- [ ] Run PHP syntax checks.
- [ ] Run Laravel view compilation.
- [ ] Run the Laravel test suite.
- [ ] Smoke-test every landing-page route and media endpoint.
- [ ] Check desktop layout at 1280px and 1920px.
- [ ] Check mobile layout at 375px and 768px.
- [ ] Compare section order against the agreed list.
- [ ] Compare content counts: hero, portfolio, testimonials, benefits, reviews, services, FAQ, and footer links.
- [ ] Check browser console for JavaScript errors.
- [ ] Confirm no missing image, PDF, icon, or video resource requests.
- [ ] Record remaining content decisions before production launch.

Final acceptance:

- The Laravel landing page contains the same content blocks as the legacy `index.php` in the agreed order.
- Design differences are intentional and documented.
- All local resources render from Laravel-managed paths.
- All known data discrepancies are resolved or explicitly approved for review.

## Current Known Gaps

- The legacy landing page contains 25 benefits; the migrated Laravel data currently contains fewer.
- The legacy landing page contains 10 hardcoded FAQ items; Laravel does not yet have a FAQ source.
- Services were migrated with `needs_review` and may be hidden by `is_published`.
- Legacy contact/social values were hardcoded in `partials/footer.php` and must be populated in Laravel settings.
- Legacy page order differs from the current Laravel `home.blade.php` order.
- Legacy default navigation uses homepage anchors; Laravel currently mixes route links and locale-prefixed routes.

---
---

# Platform Features — Courses/LMS, Events, Career, Progress Tracker, Admin

## Goal

Add five capabilities: public customer accounts, a paid course platform (LMS),
event enrollment (free and paid), job applications with document upload, and a
project progress tracker.

| Document | Role |
|---|---|
| `notes.md` | Reasoning, decisions D1–D19, assumptions A1–A24, risks, performance analysis (§9) |
| `guide.md` | How code gets written in this repo |
| **`docs/plans/`** | **One detailed plan per phase — read the matching file before starting a phase.** Its README carries the agreed implementation order |

This file stays the single progress tracker. The plans expand these checklists;
they do not replace them.

## Implementation order (decided 2026-10-08)

**Launch early, then keep building.** The launch profile is register → verify →
browse → enroll in one **free** event, so launch needs neither payments nor the LMS.

```
Wave 1 — launch critical:   T0 → P0 → P1 → P5a (free events)
Wave 2 — launch readiness:  O1 → O2 → O3 → O4 → O5 → O6 → O7 → LAUNCH
Wave 3 — post-launch:       P7 → P2 → P5b → P3 → P4 → P6
Wave 4 — consolidation:     P8 → P9 → revisit O4/O5/O7 for the Wave 3 tables
```

Wave 2 optimizes only what launch uses — the 500-user burst *is* launch day, and
bcrypt cost, PHP-served images, and the IP rate-limit bug all sit on the
register/browse/enroll path. Scope O4 and O5 to the tables that exist at that
point; both are revisited in Wave 4. Full reasoning and sizes in
`docs/plans/README.md`.

Phases use a `P` prefix so they never collide with the landing-parity phases
above. The LMS payment work README previously deferred to "phase 9" is absorbed
here, not deferred.

## Phase Dependencies

```
T0 (triage: site returns 500 — fix first)
 │
P0 (infra)
 ├── P1 (identity) ──┬── P5a (events, FREE path — needs NO payments)
 │                   ├── P6 (career)
 │                   └── P2 (payments) ──┬── P3 (courses) ── P4 (delivery)
 │                                       └── P5b (events, PAID path)
 └── P7 (progress tracker — needs neither identity nor payments)

P8 (admin consolidation) depends on P1–P7 · P9 (hardening) is last
 │
O1–O7 (performance & launch capacity — see the section after P9)
```

**P5 splits.** Free enrollment (`P5a`) depends only on P1 — the capacity claim,
registration records, and confirmation mail involve no payment code. Only the paid
path (`P5b`) needs P2. This matters for sequencing: the launch profile is *free
event enrollment*, so launch does not require payments at all.

Courses are the stated priority and sit as early as their dependencies allow.
`P7` is independent and may be pulled forward at any point for a quick
demonstrable win. `P5` and `P6` are independent of each other.

## Why payments (P2) comes before its consumers

Both paid courses and paid events need Midtrans. Payment integration is the
riskiest work in this brief and its failure mode is financial. `payments` is
polymorphic from the first migration and is verified standalone against test
payables, so course purchase and paid enrollment plug into a flow whose webhook
idempotency is already proven. See `notes.md` §3.3.

## Working Rules (in addition to those above)

- Read `guide.md` before starting any phase.
- Each phase ends with an executable validation step and must pass before the
  next begins.
- Every `[!]` item is a blocked decision — resolve it with the user, do not guess.
- A cache is never added without its invalidation in the same change.
- No role conditionals in controllers; authorization is a Policy.
- Entitlement and payment state are granted by verified server-side events only,
  never by a browser redirect.

---

## Phase T0 - Triage: Restore Page Rendering [ ]

**The site currently returns HTTP 500 on every page.** Verified by rendering `/`:
`STATUS: 500`. This is not optimization — it is a broken application, and nothing
in P0–P9 can be validated against it. Decision D14.

Four route names are called from Blade but are not registered. `header.blade.php`
is in the layout, so the failure is site-wide, not homepage-only.

| Route called | Call sites | Resolution |
|---|---|---|
| `contact.create` | `home.blade.php:81`, `header.blade.php:48,93`, `portfolio/show.blade.php:131` | **Replace with the `company_whatsapp` link (D12).** No contact route is ever created |
| `careers.index` | `header.blade.php:45,90` | `[!]` Point at P6's `/karir`, or a landing anchor until P6 ships. Decide which |
| `portfolio.index` | `home.blade.php:198`, `portfolio/show.blade.php:27` | `[!]` README says public portfolio pages were deliberately removed, but uncommitted work adds `portfolio/show.blade.php`. Resolve the contradiction |
| `portfolio.show` | `home.blade.php:127`, `portfolio/show.blade.php:15` | `[!]` Same decision as above |

- [ ] Resolve the uncommitted working tree first: `bootstrap/app.php`, `home.blade.php`, `partials/header.blade.php` are modified; `resources/views/pages/` and `resources/views/portfolio/` are untracked. Commit or stash before editing — do not overwrite this work.
- [ ] Replace every `route('contact.create')` call with the configured WhatsApp link per D12.
- [ ] `[!]` Decide the destination for `careers.index` and record it.
- [ ] `[!]` Decide whether public portfolio index/detail pages exist. If yes, register the routes and controllers; if no, replace the links with landing anchors and remove `portfolio/show.blade.php`.
- [ ] Confirm `welcome.blade.php` is unreferenced, then delete it with its dead `route('login')` / `route('register')` calls. Root uses `HomeController`.
- [ ] Add a guard test so this class of break cannot recur: assert every `route(...)` name used in Blade resolves.

Acceptance:

- `/` and `/en` return 200.
- `php artisan route:list` contains a registered name for every `route(...)` call in every rendered Blade view.
- The guard test fails if an unregistered route name is introduced.
- No `[!]` above remains unresolved.

## Phase P0 - Infrastructure Prerequisites [ ]

Nothing below this line is verifiable until the test suite executes. This is not
polish: payment and entitlement code must not ship on manual inspection.

- [ ] Resolve the test-runner blocker: port `tests/` to PHPUnit 12 syntax, or land a Pest release compatible with Laravel 13 / PHPUnit 12. Pick one and record which in `notes.md`.
- [ ] Confirm `php artisan test` executes and reports real results.
- [ ] Move `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` to Redis; keep `database` documented as the local fallback.
- [ ] Add a supervised queue worker and document how it is run in development and production.
- [ ] Configure and verify a mail transport; send one real test message.
- [ ] Fix the orphaned `home.{locale}` cache: either cache the `HomeController` payload as an array, or remove the dead `ContentObserver` key. Do not leave invalidation pointing at a key nothing writes.
- [ ] Add stampede protection in the **same change** as the cache (§9.3 B4): `Cache::flexible()` for stale-while-revalidate, or `Cache::lock()` so exactly one worker regenerates. Eager invalidation plus 500 concurrent readers means one editor save causes 500 simultaneous full-payload rebuilds.
- [ ] Add the **missing observer registrations** in the same change: `AppServiceProvider` observes 10 models but omits `Faq` (which is in the homepage payload) and `MediaAsset`. Harmless today because no cache exists — which is exactly why it must land with the cache.
- [ ] Note for O3: observing `MediaAsset` does **not** fix stale images. Replacing content at the same `path` leaves the URL unchanged, and `Cache-Control: max-age=2592000` keeps browsers on the old file for 30 days. The real fix is cache-busting via the existing `sha256` column.
- [ ] Correct the README claim that the homepage is "cached as a unit" to match whatever P0 actually implements.
- [ ] Add a factory for every new model as it is created.

Acceptance:

- `php artisan test` runs the suite and the result is trustworthy.
- `php artisan queue:work` processes a dispatched smoke-test job.
- Cache hit on the homepage is demonstrable, and an editor save invalidates it.
- `php artisan config:show cache.default queue.default session.driver` all report Redis.

## Phase P1 - Customer Identity and Guard Isolation [ ]

Decision D1. The Filament escalation path in `notes.md` §2.1 must be proven
closed **before** any public account can be created.

- [ ] Create the `customers` table: name, email (unique), `email_verified_at`, password, phone, `is_active`, remember token, timestamps, soft deletes.
- [ ] Create a dedicated customer password-reset token table and broker. Do not share `password_reset_tokens` between two providers.
- [ ] Add the `customer` guard and `customers` provider to `config/auth.php`.
- [ ] Create the `Customer` model: `$fillable` explicit, `password` hashed cast, `Notifiable`. It must **not** implement `FilamentUser`.
- [ ] Pin the Filament panel explicitly to the `web` guard in its panel provider.
- [ ] Build register, login, logout, email-verification, and password-reset flows for the `customer` guard.
- [ ] Require verified email before purchase, enrollment, or application (assumption A5).
- [ ] Rate-limit login and password-reset keyed on **email + IP**.
- [ ] **Registration must NOT be throttled by IP alone (D17).** Indonesian carrier CGNAT and shared venue WiFi put hundreds of users behind one address — a 3/min IP limit would 429 most of a 500-person launch. Use a high IP ceiling plus a per-email limit, and rely on email uniqueness + the verification requirement for abuse control. A challenge (Turnstile) is preferable to a tight IP counter.
- [ ] Write `RegistrationRateLimitTest::test_many_registrations_from_one_ip_are_not_blocked` — simulate 50 registrations from a single IP and assert none receive 429. This is the regression guard for D17.
- [ ] Add `customer` auth middleware and a "my account" shell page.
- [ ] Write `CustomerGuardIsolationTest`: an authenticated customer receives 403/redirect at `/admin`, and a `User` cannot authenticate against the `customer` guard.
- [ ] Write tests for register, login, verification gating, and password reset.

Acceptance:

- A customer session cannot reach `/admin` — asserted by test, not by inspection.
- Unverified customers are blocked from purchasing, enrolling, and applying.
- `users` and `customers` share no credential surface.
- Rate limits return 429 under test.

## Phase P2 - Payment Foundation (Midtrans) [ ]

Decision D9. Built and verified standalone, before any consumer exists.

- [ ] `[!]` Obtain Midtrans sandbox and production credentials. **Methods are decided (D10)** — only the keys are outstanding.
- [ ] Create `payments`: polymorphic `payable`, unique `order_id`, `gross_amount` `decimal(15,2)`, status (`pending`/`settlement`/`expired`/`cancelled`/`failed`/`refunded`), payment type, Midtrans transaction id, raw response JSON, `expires_at`, `paid_at`, timestamps.
- [ ] Add Midtrans configuration to `config/` and `.env.example`; never commit keys.
- [ ] Set Snap `enabled_payments` to QRIS, bank-transfer VA (BCA, BNI, BRI, Mandiri, Permata), and GoPay + ShopeePay deeplinks (D10). Keep it config-driven so the list changes without code.
- [ ] Verify current Midtrans fee rates per method before launch; do not hardcode assumed rates anywhere.
- [ ] Make `custom_expiry` a per-payable-type setting, not a global constant — payment methods have wildly different default windows (QRIS ~15–30 min vs VA ~24 h). See `notes.md` §3.4.
- [ ] Persist the payment's own `expires_at` so consumers derive their hold windows from the payment record rather than guessing.
- [ ] Build `CreatePayment` action producing a Snap transaction for any payable.
- [ ] Build the webhook endpoint: **verify the SHA512 signature** of `order_id + status_code + gross_amount + server_key` before trusting any field in the payload.
- [ ] Respond 200 immediately; process the notification in a queued job.
- [ ] Make webhook processing idempotent, keyed on `order_id` plus status transition. Duplicate notifications are normal operation.
- [ ] Set `$tries` and `$backoff` on the processing job; assume it runs at least twice.
- [ ] Define the fulfilment contract payables implement, so entitlement is granted by the webhook job and never by a controller.
- [ ] Exclude the webhook route from CSRF and rate-limit it by signature validity, not by IP.
- [ ] Add audit logging for every payment state change (`guide.md` §7).
- [ ] Add Filament read-only Payments resource with status history.
- [ ] Write `MidtransWebhookTest`: forged signature rejected; duplicate settlement notification fulfils exactly once; every status transition handled; unknown `order_id` rejected without a 500.
- [ ] Write `PaymentFulfilmentTest`: returning from checkout in the browser grants nothing on its own.

Acceptance:

- A forged webhook is rejected on signature.
- Replaying a settlement notification twice fulfils exactly once.
- No web request blocks on a Midtrans call.
- A client-side return from checkout grants no entitlement — asserted by test.

## Phase P3 - Course Catalog, Purchase and Entitlement [ ]

Decisions D5–D8. **The entitlement gate built here is the paywall.** `notes.md`
§3.2 and risk 1 apply to every task below.

The YouTube account is still outstanding, but it **does not block this phase**:
every task and test below runs against placeholder video IDs. The account is only
needed to populate real content, so it gates course *launch*, not course *build*.

- [ ] Create `courses`: translatable title/description, slug, cover media, `price` `decimal(15,2)`, level, estimated duration, `is_published`, `published_at`, timestamps, soft deletes.
- [ ] Create `course_sections`: course, translatable title, ordering.
- [ ] Create `course_lessons`: section, translatable title, `content_type` (`video`/`text`/`file`), `youtube_id`, rich text body, attachment reference, duration, `is_free_preview`, ordering.
- [ ] Create `course_enrollments`: course, customer (`nullOnDelete`), snapshotted student identity, source payment, `enrolled_at`, `completed_at`. No expiry (D6). Unique on (course_id, customer_id).
- [ ] Reuse `HasYoutubeVideo` on `CourseLesson` for URL normalization. Do not write a second parser.
- [ ] Add `scopePublished()` / `scopeOrdered()` to match existing model conventions.
- [ ] Build `CoursePolicy` and `LessonPolicy` as the **single** authorization path to lesson content.
- [ ] Enforce the hard rule: a locked lesson's serialized payload contains **no** `youtube_id` and no body. Gate server-side; never hide content with CSS or JS.
- [ ] Implement `is_free_preview` as an explicit per-lesson opt-in, so an unlocked preview is never the result of the gate failing open.
- [ ] Build the purchase flow on P2's `CreatePayment`; implement the fulfilment contract so the webhook job creates the enrollment.
- [ ] Catch `UniqueConstraintViolationException` on `(course_id, customer_id)` and return the same idempotent "already enrolled" response the pre-check gives. The pre-check and the insert are not atomic; a double-click must not 500.
- [ ] **Never let the Gate resolve the user.** `Gate::allows()` uses the default `web` guard, so a `?Customer` policy parameter arrives null for a real purchaser and the course appears locked. Always `Gate::forUser(auth('customer')->user())`. Cover it with a test.
- [ ] **Resolve entitlement once per course** when rendering an outline, then pass the boolean into `LessonPayload` — keeping `is_free_preview` evaluated per lesson. A per-lesson policy call is ~2 queries each (`$lesson->section` lazy-loads), so a 30-lesson outline costs ~60 queries, and `preventLazyLoading` makes it **throw** outside production.
- [ ] Reject duplicate purchase of a course the customer already owns.
- [ ] Build the public catalog and course detail pages, bilingual, with a published-catalog cache and its invalidating observer in the same change.
- [ ] Ensure no cache key mixes entitlement states — a cached payload must never carry a `youtube_id` to a non-purchaser.
- [ ] Queue the purchase receipt and enrollment-confirmation notifications.
- [ ] Add Filament resources for Courses with nested Sections and Lessons, and a read-only Enrollments view. Keep `youtube_id` editable so a leaked video can be swapped without a deploy.
- [ ] Translate all strings into `lang/id` and `lang/en`.
- [ ] Write `CourseEntitlementTest`: **a locked lesson's response body contains no YouTube ID** for a guest, for a signed-in non-purchaser, and for a purchaser of a *different* course. This is the highest-value test in the project.
- [ ] Write `CoursePurchaseTest`: settlement webhook grants exactly one enrollment; duplicate purchase blocked; unverified customer blocked.
- [ ] Write `FreePreviewTest`: a preview lesson is reachable without purchase and a non-preview lesson in the same course is not.

Acceptance:

- No response to a non-entitled user contains a lesson's YouTube ID or body — asserted by test across guest, non-purchaser, and wrong-course-purchaser.
- Purchase grants entitlement only via the verified webhook.
- Catalog renders with no N+1 (verified, not assumed).
- Publishing a course invalidates the cached catalog immediately.

## Phase P4 - Course Delivery, Progress and Quizzes [ ]

Decision D7. Quizzes are self-check, not a gradebook (assumption A11).

- [ ] Create `lesson_completions`: enrollment, lesson, `completed_at`. Unique on (enrollment_id, lesson_id).
- [ ] Build the lesson player page, reading content through the P3 entitlement gate only.
- [ ] Build course navigation across sections and lessons with free navigation (assumption A9 — no sequential unlock).
- [ ] Mark a lesson complete idempotently; a repeat call must not create a second row.
- [ ] Derive progress from `lesson_completions` against the course lesson count; cache per enrollment and invalidate on completion. Never recompute with a count query inside a view loop.
- [ ] Build the "my courses" dashboard with per-course progress.
- [ ] Overlay the student's own email on the player as a sharing deterrent (`notes.md` §3.2).
- [ ] Create `quizzes`: attached to a lesson or section, translatable title, pass mark.
- [ ] Create `quiz_questions`, `quiz_options`, and `quiz_attempts` per `notes.md` §4.3.
- [ ] Build quiz attempt submission and scoring; record the attempt, do not block progress on the result.
- [ ] Add Filament management for Quizzes, Questions, and Options nested under the course.
- [ ] Mark a course complete when all its lessons are complete.
- [ ] Translate all strings into `lang/id` and `lang/en`.
- [ ] Write `LessonProgressTest`: completion is idempotent; progress percentage is accurate; course completion triggers at the right point.
- [ ] Write `QuizAttemptTest`: scoring is correct; a failed attempt does not revoke access or block navigation.
- [ ] Verify no N+1 on the player page and the dashboard.

Acceptance:

- A purchaser can navigate a course, complete lessons, and see accurate progress.
- Marking a lesson complete twice produces one record.
- The player page reads content through the P3 gate and no other path.
- Quiz results are recorded without gating progress.

## Phase P5 - Events: Free and Paid Enrollment [ ]

Decision D4. Paid path reuses P2.

- [ ] Create `events`: translatable title/description, slug, cover media, `starts_at`, `ends_at`, `location_type` (online/offline/hybrid), venue, online URL, `capacity` (nullable = unlimited), `price` `decimal(15,2)` default 0, registration window, `is_published`, timestamps, soft deletes.
- [ ] Create `event_registrations`: event, customer (`nullOnDelete`), snapshotted attendee identity, status (`pending`/`confirmed`/`cancelled`/`attended`), unique `ticket_code`, source payment, timestamps.
- [ ] Add a unique index on (event_id, customer_id) for non-cancelled rows.
- [ ] Add `scopePublished()` / `scopeUpcoming()` / `scopeOrdered()`.
- [ ] Build `EnrollInEvent` action: seat claim inside `DB::transaction()` with a row lock on the event, or a conditional atomic counter update. A `count() < capacity` check is not acceptable.
- [ ] Catch `UniqueConstraintViolationException` on the `(event_id, customer_id)` index and return an idempotent "already registered" response — **releasing the seat just claimed**, or a double-click permanently consumes two seats for one attendee.
- [ ] Add `ReconcileEventSeatCounts`, scheduled nightly: compare `seats_taken` against the live non-cancelled registration count, correct drift, and **audit-log both values**. The counter is decremented from five paths; one missed decrement makes an event sell out early, silently and permanently.
- [ ] Reject enrollment outside the registration window, when unpublished, or when full (assumption A2 — no waitlist).
- [ ] Free events confirm directly and skip checkout.
- [ ] Paid events hold a seat until the **payment record's `expires_at`** — never a global constant. Release it by scheduled job when unpaid (assumptions A8, A17).
- [ ] Set a short uniform `custom_expiry` for event payments (recommend 1 hour) so a bank-transfer buyer cannot hold a capacity-limited seat for 24 hours. See `notes.md` §3.4.
- [ ] Build the public event list and detail pages, bilingual, with cache and invalidating observer.
- [ ] Build the customer's "my events" view.
- [ ] Queue registration-confirmation and payment-receipt notifications; dispatch after commit, never inside the transaction.
- [ ] Add `EventRegistrationPolicy`.
- [ ] Add Filament resources for Events and Event Registrations, including an attendance action.
- [ ] Write `EventEnrollmentTest`: happy path, duplicate rejected, closed window rejected, unverified customer rejected.
- [ ] Write `EventCapacityConcurrencyTest`: concurrent claims on the final seat never oversell.
- [ ] Write `PaidEventHoldTest`: an unpaid hold expires and returns the seat to inventory.

Acceptance:

- A verified customer enrolls in a free event and receives a queued confirmation.
- Capacity is never exceeded under concurrent load — asserted by test.
- An unpaid hold expires and the seat returns to inventory.
- Event list renders with no N+1.

## Phase P6 - Career: Applications and Documents [ ]

Decision D3. `job_vacancies` already exists and is reused unchanged.

- [ ] Create `job_applications`: vacancy, customer (`nullOnDelete`), snapshotted applicant identity (name, email, phone), cover letter, status (`submitted`/`screening`/`interview`/`offered`/`rejected`/`withdrawn`), `status_changed_at`, reviewing `users` id, admin-only notes, timestamps, soft deletes.
- [ ] Create `job_application_documents`: application, type (`cv`/`portfolio`/`certificate`/`other`), disk, path, original filename, mime type, size, checksum, timestamps.
- [ ] Add a unique index enforcing one active application per vacancy per customer (assumption A4).
- [ ] Configure the private disk for applicant documents; confirm it resolves outside the webroot.
- [ ] Build the application `FormRequest`: validate MIME **and** extension, cap file size, cap file count.
- [ ] Build `SubmitJobApplication` action: transactional, snapshots applicant identity, stores documents, dispatches notifications after commit.
- [ ] Handle orphaned files: **the filesystem is not transactional.** A rollback after `store()` leaves a CV on disk with no row pointing at it — retained personal data that D11's retention purge and P8's erasure path cannot reach, because they do not know it exists. Delete stored paths on failure, **and** add a scheduled sweep for files with no matching row older than ~24 h (never younger, or the sweep races a live upload).
- [ ] Build the document download route: staff-authorized controller only, never a direct URL.
- [ ] Build the public vacancy list and detail pages plus the application form, bilingual.
- [ ] Render `contact_url` as an optional secondary HR **question** link (D13): only when non-null, visually subordinate to Apply, labelled as a question channel, with the role title prefilled via `wa.me/<number>?text=`. **Never fall back to `company_whatsapp`** — that is the sales number.
- [ ] Build the applicant's own status view (assumption A3).
- [ ] Queue "application received" and "status changed" notifications.
- [ ] Cache the published-vacancy list with its invalidating observer.
- [ ] Add `JobApplicationPolicy`: a customer sees only their own; only staff read documents and notes.
- [ ] Add Filament resources for Job Applications with authorized document download and a status-change action.
- [ ] Write `JobApplicationTest`: happy path, duplicate rejected, closed vacancy rejected, oversized and wrong-type uploads rejected.
- [ ] Write `ApplicationDocumentSecurityTest`: a document is unreachable by direct URL and by an unauthorized customer; a disguised `.php` upload is rejected or inert.
- [ ] Write `ApplicationSurvivesCustomerDeletionTest`: after the customer is deleted, the application and its documents remain readable by staff.

Acceptance:

- A verified customer applies with a PDF and the file is retrievable by staff only.
- Deleting the customer account leaves the application and documents intact — asserted by test. This is the stated requirement.
- No applicant document is reachable without an authorization check.
- Vacancy list renders with no N+1.

## Phase P7 - Progress Tracker [ ]

Decision D2. Independent of P1 and P2 — may be pulled forward. The most exposed
surface on the site: unauthenticated and it reads customer data.

- [x] A1 confirmed: projects are staff-maintained in Filament, no external sync. Verified separately that the legacy dump contains **no** project-tracking table, so this starts from an empty table.
- [ ] Create `projects`: `project_code` (unique, random, non-sequential), customer contact fields used for verification, title, `current_stage`, status (`in_progress`/`on_hold`/`completed`/`cancelled`), start date, estimated completion, `completed_at`, `is_visible`, timestamps.
- [ ] Create `project_updates`: project, stage name, status, customer-visible note, `occurred_at`, ordering.
- [ ] Generate `project_code` as a random non-sequential value; never expose the primary key.
- [ ] Add indexes for `project_code` and for the verification lookup.
- [ ] Build the lookup form: project code **plus** matching phone or email.
- [ ] Implement the uniform negative response: wrong code, failed verification, and already-completed project all return the **same** output. No distinguishable "already finished" message — that would confirm a code is valid.
- [ ] Rate-limit the lookup per IP **and** per submitted code; log repeated failures.
- [ ] Add the navbar entry point in both locales.
- [ ] Add Filament resources for Projects and Project Updates. Manual entry is the primary path (A1).
- [ ] `[!]` A16 — if pre-existing project data is supplied, obtain the actual file and its columns, then add a Filament CSV importer matched to that shape. Do **not** build an importer against a guessed format.
- [ ] Translate all strings into `lang/id` and `lang/en`.
- [ ] Write `ProgressTrackerTest`: valid lookup succeeds; wrong code, bad verification, and completed project are **byte-identical** responses; rate limit triggers.

Acceptance:

- A valid code plus matching verification returns stages and status.
- The three negative cases are indistinguishable from each other.
- Scripted code guessing yields no information and is throttled.
- Staff can create a project and publish an update the customer view reflects.

## Phase P8 - Admin Consolidation, RBAC and Retention [ ]

- [ ] Add the Customers resource: view and deactivate only. Never edit credentials.
- [ ] Review every new Filament resource against the `superadmin`/`admin`/`editor` tiers; decide per resource which tier may read, write, and export.
- [ ] Restrict applicant documents, project customer contacts, student records, and payment records to the appropriate tier — these are the PII surfaces.
- [ ] Add audit logging for publish/unpublish, status transitions, role changes, payment changes, enrollment grants, and failed authorization attempts (`guide.md` §7).
- [x] A7 decided (D11): **12 months after vacancy closure**, env-configurable. See `notes.md` §3.5 — the user floated 6 months; 12 is recommended because 6 tends to delete candidates right as a similar role opens. Changing it is a one-value `.env` edit.
- [ ] Implement the retention period as an `.env` value, never a literal in code.
- [ ] Implement the scheduled retention purge, keyed on **vacancy closure date**, not application date.
- [ ] Implement erasure-on-request, honoured at any time independent of the retention period. This is a UU PDP right, not a feature toggle.
- [ ] Audit-log every purge and every erasure request so the policy is demonstrable.
- [ ] Build a manual refund path: staff set payment status, entitlement is revoked, and the action is audit-logged (assumption A14).
- [ ] Add staff-facing exports for enrollments, registrations, and applications, gated by tier and audit-logged.
- [ ] Write `AdminAuthorizationTest`: an `editor` cannot reach the resources reserved for `admin`/`superadmin`.
- [ ] Write `RefundRevokesEntitlementTest`.

Acceptance:

- Every new resource has an explicit, tested authorization tier.
- PII access is logged.
- A refund revokes course access.
- The retention purge runs on schedule and removes only what A7 permits.

## Phase P9 - Hardening, Load and Sign-off [ ]

- [ ] Run `vendor/bin/pint --dirty --format agent` clean across all new code.
- [ ] Verify every migration rolls back cleanly, then re-applies.
- [ ] Confirm `lang/id` and `lang/en` key sets are identical.
- [ ] Audit every new page for N+1 using Boost `database-query` or `DB::listen`; record the query count per page in `notes.md`.
- [ ] Update the CSP in `SecurityHeaders` for the Midtrans Snap script and the `youtube-nocookie.com` frame source. Add each deliberately, with a written reason.
- [ ] Re-verify the entitlement gate after any caching added in P9: confirm no cached fragment carries a `youtube_id` across entitlement states.
- [ ] Confirm no state-changing GET route was introduced.
- [ ] Confirm every new form carries `@csrf` and every new upload path validates MIME and extension.
- [ ] Confirm the Midtrans webhook is the only CSRF-exempt route added.
- [ ] Load-test the public surfaces (homepage, course catalog, event list, vacancy list, progress lookup) toward the 500-concurrent-user target; record results.
- [ ] Verify cache hit rates and that queue depth drains under that load.
- [ ] Check layouts at 375px, 768px, 1280px, and 1920px.
- [ ] Check the browser console for errors on every new page.
- [ ] Confirm no `[!]` item remains unresolved.
- [ ] Update `README.md`: describe public customer accounts and the LMS, remove the "no public registration" statement that P1 invalidates, and move the LMS and Midtrans items out of "What is not here".

Final acceptance:

- All five features work end to end in both locales.
- Entitlement gating, guard isolation, webhook idempotency, capacity concurrency, and document authorization are each covered by a passing test.
- No response to a non-entitled user carries paid course content.
- The 500-user target is demonstrated by measurement, not assumed.
- Every assumption A1–A15 is either confirmed or explicitly re-decided.
- No claim of verification is made that was not actually performed.

---
---

# Performance & Launch Capacity (O phases)

## Goal

Carry ~500 concurrent users who **register, verify, browse, and enroll in one
event** at launch. Full analysis, ranked bottlenecks, and the recommended target
spec are in `notes.md` §9. Per-phase build instructions are in `docs/plans/`.

**Priority:** the user placed optimization *after* the P0–P9 feature plan. That is
respected. Three items were pulled earlier because they are a different kind of
problem, not because they are more important (`notes.md` §9.5):

1. **T0** — the site-wide 500s. Triage, not optimization.
2. **P1** — the IP rate-limiting flaw (D17). A correctness bug, not tuning.
3. **P0** — homepage cache stampede protection. Belongs with the cache it guards.

## What launch actually exercises

```
homepage (read) → register (bcrypt) → verification email (queue + external)
→ verify → login (bcrypt) → browse events (read) → enroll (single-row contention)
```

Course purchases are **not** part of launch, so Midtrans stays off the critical
path. D19 recommends the launch event be free to keep it that way.

## Phase Dependencies

```
O1 (baseline) ──┬── O2 (request cost) ── O3 (media) ── O4 (queries) ── O5 (caching)
                └── O6 (burst handling)
O7 (rehearsal) last — requires all of O1–O6
```

`O1` must run first (D18): `optimasi.md` is an explicitly static audit with no load
test, and several of its recommendations depend on real data.

## Phase O1 - Measurement Baseline [ ]

- [ ] `[!]` **Decide hosting before measuring** (A18). Recommended minimum: 4 vCPU / 8 GB, nginx + PHP-FPM 8.4, MySQL 8, Redis. Shared cPanel hosting cannot meet this target — no Redis, no Supervisor, no PHP-FPM control. Measuring the wrong machine wastes the whole phase.
- [ ] Seed a representative dataset: real published-portfolio count, 25 benefits, events, courses, and enough rows that an N+1 is visible. An N+1 is invisible at 2 rows.
- [ ] Record the actual published-portfolio count and homepage response size — `optimasi.md` asks for this before choosing a pagination strategy.
- [ ] Stand up a load-test harness (k6 or Artillery) modelling the launch path above, not just homepage GETs.
- [ ] Capture the baseline: p50/p95/p99 latency, error rate, throughput, and per-run MySQL connections, Redis hit rate, queue depth, CPU, memory.
- [ ] Count queries per page with `DB::listen` or Boost `database-query`, and write the numbers into `notes.md`.
- [ ] Profile `app:import-media-assets` memory **separately** from asset size on disk — `optimasi.md` is explicit that disk figures do not explain the command's RAM use.

Acceptance:

- A baseline exists on the chosen target spec, with numbers recorded.
- Every later O phase can be stated as a measured delta, not an assertion.

## Phase O2 - Request-Path Cost [ ]

Cheap, mechanical, large. Do this before structural work.

- [ ] Set `BCRYPT_ROUNDS=10` (D15). ~4× faster than 12; ~1,000 launch hashes drop from ~300 to ~75 CPU-seconds. **Never below 10.** Laravel rehashes on next login, so existing accounts are unaffected.
- [ ] Verify opcache is enabled with adequate memory and `opcache.validate_timestamps=0` in production.
- [ ] Add `config:cache`, `route:cache`, `view:cache` and `composer install --optimize-autoloader --no-dev` to the deploy procedure, and document it.
- [ ] Confirm no `env()` call exists outside `config/` — config caching freezes those to null.
- [ ] Size PHP-FPM `max_children` to the chosen spec (~32–40 at 4 vCPU / 8 GB) and set MySQL `max_connections` above `max_children` + queue workers + Filament sessions.
- [ ] Re-measure against the O1 baseline and record the delta.

Acceptance:

- Registration and login latency measurably improved against baseline.
- Production caches verified present after a deploy, not assumed.

## Phase O3 - Media & Static Asset Delivery [ ]

**The top traffic risk for this profile** (`notes.md` §9.3 B1). Every image
currently executes PHP and queries MySQL, then streams bytes while holding a
PHP-FPM worker. Launch means 500 first-time visitors with empty browser caches, so
the existing 30-day `Cache-Control` helps none of them.

- [ ] Serve **public** marketing media (hero, portfolio covers, logos, icons) directly from nginx/Apache, bypassing PHP entirely (D16, A22). The authorization `MediaController` provides has no value for public images.
- [ ] Keep `MediaController` for genuinely private media. P6's applicant CVs are exactly that and must stay behind it.
- [ ] Where PHP must remain in the path, use `X-Accel-Redirect` (nginx) or `X-Sendfile` (Apache) so PHP authorizes and hands off, releasing the worker immediately.
- [ ] Add `ETag` and `Last-Modified` with 304 handling to `MediaController`. It currently sends neither, so any revalidation is a full re-download.
- [ ] Finish the BLOB→disk migration: `storage_path` was added 2026-10-07 as nullable, and `content` is still `LONGBLOB NOT NULL`. Populate and verify `storage_path` for every asset.
- [ ] `[!]` Then make `content` nullable and empty it (A23), as a separate reversible step. Until this happens the database carries every byte with none of the benefit.
- [ ] Choose one canonical URL per logo/icon (`optimasi.md` §5). Verify every reference against the database and views **before** moving or deleting any file.
- [ ] Evaluate a CDN. It makes the first-visit problem disappear after the first requester per edge.
- [ ] Re-measure: asset requests should no longer occupy PHP workers.

Acceptance:

- Public images are served without executing PHP — verified in access logs.
- A revalidated asset returns 304, not 200 with a body.
- PHP-FPM worker occupancy under load is dominated by HTML, not images.

## Phase O4 - Query & Data Access [ ]

- [ ] Bound the homepage portfolio query to what actually renders. `PORTFOLIO_VISIBLE` is 16; the query currently fetches every published non-featured portfolio. Choose pagination or load-more **after** O1 supplies the real row count.
- [ ] Fix the cover-image N+1: `portfolio-card.blade.php:4` falls back to `$portfolio->images->first()`, which is never eager-loaded. Either eager-load it or select the image from a relation that is already loaded.
- [ ] Verify the fix with data that **includes portfolios without a cover** and count queries. Ideal data hides this bug.
- [ ] Keep the total portfolio count as its own cheap query only if the UI needs it.
- [ ] Consider collapsing the three value-proposition queries (benefit / process / deliverable) into one grouped in PHP — **only if** O1 profiling shows it matters.
- [ ] Audit indexes on every table added in P1–P7 against the queries that actually run: composite indexes for filter+sort pairs, not single-column indexes per filter.
- [ ] Re-measure query counts per page and record them.

Acceptance:

- No page issues a query per rendered row.
- Homepage query count and response size improved against the O1 baseline.

## Phase O5 - Caching Strategy [ ]

- [ ] Confirm the homepage cache contract P0 chose is implemented, invalidated, and locale-correct — `optimasi.md` §4 asks for exactly one deliberate behaviour, with comment and implementation agreeing.
- [ ] Verify stampede protection is active on every eagerly-invalidated cache, not just the homepage.
- [ ] Add caches for the published event list, course catalog, and vacancy list, each with its invalidating observer in the same change.
- [ ] **Never cache availability.** `events.seats_taken` and remaining-capacity numbers must read live; a stale count either oversells or hides open seats.
- [ ] **Never share a cache key across entitlement states.** A cached fragment carrying a lesson `youtube_id` to a non-purchaser is the P3 paywall leak by another route.
- [ ] Define every cache key as a constant on the model that owns it, referenced by its invalidator.
- [ ] Measure cache hit rate under load, and confirm an editor save invalidates without a latency spike.

Acceptance:

- Cache hit rate measured, not assumed.
- An editor save during load does not cause a latency spike.
- No availability number and no entitlement-varying payload is cached.

## Phase O6 - Launch Burst Handling [ ]

The write path, which O2–O5 do not touch.

- [ ] Rate-limit the verification-mail job to the provider's documented ceiling (`Redis::throttle` or a `RateLimited` job middleware). Exceeding it causes hard bounces, not just delay.
- [ ] Size queue worker concurrency for a 500-email burst, bounded by that ceiling.
- [ ] `[!]` **Decide A20:** keep verification-before-enrollment (A5), or relax it for free events. Keeping it puts an external mail dependency directly on the launch critical path. Relaxing it removes that dependency; verification would then be required before the event itself or before any paid content. Product decision.
- [ ] `[!]` **Decide A21:** confirm the launch event is free (D19). Paid would add 500 synchronous Midtrans calls to the critical path.
- [ ] Keep the P5 seat-claim transaction minimal — the atomic claim plus the registration insert, nothing else. Extra work inside that transaction while holding the event row causes lock-wait timeouts under 500 concurrent claims.
- [ ] Load-test the single-event enrollment hotspot specifically: 500 concurrent claims on one row, asserting `seats_taken` never exceeds `capacity`. **This is where P5's concurrency acceptance criterion is actually met** — its PHPUnit tests only prove the logic is atomic, since they run sequentially.
- [ ] Configure failed-job handling and alerting. A silently failing verification queue is indistinguishable from slow mail.
- [ ] Verify queue depth drains under sustained burst rather than growing.

Acceptance:

- 500 concurrent enrollments on one capacity-limited event never oversell — proven under real parallelism.
- The verification queue drains within an acceptable window and respects the provider limit.
- Failed jobs alert rather than disappear.

## Phase O7 - Launch Rehearsal & Sign-off [ ]

- [ ] Full-path load test at target concurrency: homepage → register → verify → login → browse → enroll.
- [ ] Compare every number against the O1 baseline and record both.
- [ ] Confirm the P3 entitlement gate still holds after all O5 caching (re-run `CourseEntitlementTest` and read page source as a guest).
- [ ] Confirm T0's route-guard test and D17's registration rate-limit test still pass.
- [ ] Monitoring in place before launch: error rate, p95 latency, queue depth, failed jobs, CPU, MySQL connections.
- [ ] Write a launch runbook: who watches what, the rollback procedure, and the queue-backlog response.
- [ ] Separate cleanup (do **not** mix with runtime optimization, per `optimasi.md` §6): evaluate the 15.64 MiB of duplicate assets and the dead `welcome.blade.php`. Validate every path against the database before deleting, and re-compare hashes afterwards.
- [ ] Record the final measured ceiling honestly. If 500 was not demonstrated, state the number that was.

Final acceptance:

- The launch profile is demonstrated by measurement on the real target spec.
- No optimization claim rests on anything other than a before/after number.
- The achievable ceiling is written down, whatever it turned out to be.

---
---

## Open Decisions

Resolved 2026-10-07:

- [x] **P2 methods** — QRIS + bank-transfer VA (BCA, BNI, BRI, Mandiri, Permata) + GoPay/ShopeePay deeplinks (D10).
- [x] **P7 / A1** — Staff-entered in Filament, no external sync. Legacy dump verified to contain no project data.
- [x] **P8 / A7** — 12 months after vacancy closure, env-configurable (D11).

Still outstanding:

**Blocks T0 — the site is down until these are answered:**

- `[!]` **T0** — Destination for `careers.index`. P6's `/karir`, or a landing anchor until P6 ships?
- `[!]` **T0** — Do public portfolio index/detail pages exist? README says they were deliberately removed, but uncommitted work adds `portfolio/show.blade.php`. The links must point somewhere real either way.

**Blocks later phases:**

- `[!]` **P2** — Midtrans sandbox + production credentials. Blocks P2 and everything downstream.
- `[!]` **P2** — **Checkout delivery mode: redirect/deeplink or embedded Snap?** The current CSP (`script-src 'self'`, deliberately without `unsafe-inline`) will block Snap. Recommended: redirect for launch, since payments are off the launch critical path. Decide before writing checkout code, not during the first sandbox transaction.
- `[!]` **O1 / A18** — **Hosting decision.** Recommended minimum is 4 vCPU / 8 GB with nginx + Redis. Shared cPanel cannot reach the 500-user target. O1's baseline is meaningless on the wrong machine, so decide before O1.
- `[!]` **P3** — Which YouTube account hosts course video. Gates course *launch*, not course *build*.
- `[!]` **P7 / A16** — Whether pre-existing project data exists and in what format. Gates only the optional CSV importer.
- `[!]` **O3 / A23** — Approval to empty `media_assets.content` once `storage_path` is verified populated.
- `[!]` **O6 / A20** — Keep verification-before-enrollment, or relax it for free events? Keeping it puts mail on the launch critical path.
- `[!]` **O6 / A21** — Confirm the launch event is free (D19).
- `[ ]` **P8 / D11** — Confirm 12 months CV retention, or revert to 6. One `.env` value; not a blocker.

**Nothing blocks P0 or P1.** T0 has two open decisions but the `contact.create`
fix (the majority of the breakage) is already settled by D12 and can proceed now.
