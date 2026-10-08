# Feature Notes — Courses/LMS, Events, Career, Progress Tracker, Admin

Reasoning and understanding captured **before** planning. Decisions here are
binding on `task.md`; assumptions are flagged and must be confirmed.

Last revised: 2026-10-08 (P5a implementation) · Stack verified: Laravel 13.17 ·
Filament 5.9 · PHP 8.4 · PHPUnit 12

---

## 1. What was asked

`3dexpert-web` is a company-profile site. Beyond the existing profile content
(navbar sections + career), five capabilities are required:

| # | Feature | Stated behaviour |
|---|---|---|
| 1 | **Course / LMS** | User creates an account and signs in to reach the LMS. Courses are **paid** |
| 2 | **Event** | User creates an account, signs in, enrolls in an event. Free and paid |
| 3 | **Career** | User signs in, completes a form to apply to a vacancy, attaches a PDF |
| 4 | **Progress tracker** | Dedicated navbar button. User inputs a project ID, receives progress + status. Only projects **not yet done** |
| 5 | **Admin** | Manages all site data |

Courses were added after the first pass and are described by the user as "one of
the most important." The phase order in `task.md` reflects that: courses sit as
early as their dependencies allow.

---

## 2. What already exists (verified, not assumed)

- `job_vacancies` table and `JobVacancy` model — **already built**, with
  translatable fields, `is_published`, `closes_at`, soft deletes. There is **no
  applications table**.
- `app/Concerns/HasYoutubeVideo.php` — **already built**, resolves `youtube_id`
  once on save and covers all 7 URL shapes found in the legacy dump
  (`HasYoutubeVideoTest`). **Course lessons reuse this; do not re-parse URLs.**
- `app/Concerns/HasTranslations.php` — local trait, method-compatible with
  `spatie/laravel-translatable`.
- Filament 5.9 panel at `/admin` on the `web` guard, backed by `User`.
- `users.role` with `superadmin` / `admin` / `editor`.
- No courses, projects, or payments tables. P5a has added event and event
  registration tables.
- P1 added public customer authentication on a separate guard; it does not
  share staff credentials.
- Cache/session/queue remain on the `database` driver. Queue jobs and queued
  customer/event notifications now exist.

### 2.1 Security landmine found during analysis

`User::canAccessPanel()` returns `canManageContent()`, which is true for role
`editor`. The `role` column **defaults to `'editor'`**.

Therefore any public registration written into `users` would grant Filament admin
access at `/admin` — a byte-for-byte reintroduction of the legacy `reg.php` flaw
(§3.1-7, "anyone who registered became a superadmin") that this rebuild exists to
eliminate. This is why D1 is not a preference.

---

## 3. Decisions taken

| ID | Decision | Rationale |
|---|---|---|
| **D1** | Public accounts live in a **separate `customers` table behind a separate `customer` guard** | Structural isolation. A customer session has no path to `/admin` because the guard resolves a different provider and model. Eliminates the §2.1 escalation class rather than defending against it with a role check. |
| **D2** | Progress tracker uses **project code + secondary verification**, rate-limited | A bare ID lookup is an enumeration oracle exposing every customer's project. A second factor the real customer already knows preserves the no-account UX the brief asked for. |
| **D3** | Applicant documents stored on the **private disk**, owned by the application record | See §3.1. |
| **D4** | Events support **both free and paid** | User's explicit choice. |
| **D5** | Course video delivered via **unlisted YouTube embeds** | User's explicit choice, leak risk accepted. See §3.2 — the accepted risk changes *how* the embed must be gated. |
| **D6** | Course access is **one-time purchase, lifetime** | User's choice. Enrollment is a permanent record: no expiry jobs, no renewal logic, no dunning. |
| **D7** | Course structure is **sections → lessons**, lesson content is video / rich text / attachment, **quizzes optional** per course | User's choice. Covers real course organization without building a gradebook. |
| **D8** | Courses are **company-authored**, managed by existing staff in Filament | User's choice. **No third auth surface** — instructors are not accounts. |
| **D9** | `payments` is **polymorphic and built as its own phase**, before its consumers | See §3.3. |
| **D10** | Enabled Midtrans methods: **QRIS + bank-transfer VA + GoPay/ShopeePay deeplinks** | User chose bank transfer, QRIS, and e-wallet, and asked for a recommendation on specifics. See §3.4 — the choice has a non-obvious interaction with event capacity. |
| **D11** | CV retention: **12 months after vacancy closure**, env-configurable, deletion-on-request always honoured | User was unsure and floated 6 months. See §3.5. |
| **D12** | **Contact is a direct WhatsApp link. No contact form, ever.** | User's correction — I failed to ask about this. See §3.6. Already implemented in the views; the remaining work is removing dead form artifacts. |
| **D13** | `job_vacancies.contact_url` is kept as an **optional per-vacancy HR question link**, empty by default, always subordinate to Apply | See §3.7. Protects the P6 pipeline from channel split while keeping candidates out of the sales inbox. |
| **D14** | **Fix the site-wide 500 errors as phase T0, before P0.** Triage, not optimization | Verified: `/` returns 500 and the broken call sites are in the layout, so no page renders. Nothing in P0–P9 is verifiable until this is fixed. §9.2, §9.5 |
| **D15** | `BCRYPT_ROUNDS=10`, not 12 | 4× faster hashing on a path that needs ~1,000 hashes at launch. Pure CPU, uncacheable. §9.3 B2 |
| **D16** | **Public media served by the web server; `MediaController` for private media only** | Streaming every image through PHP ties up a scarce worker for the whole transfer. The top traffic risk for this profile. §9.3 B1 |
| **D17** | **Registration must not be rate-limited by IP alone** | CGNAT and shared venue WiFi would 429 most of a 500-person launch. A correctness bug in my P1 plan, fixed in P1. §9.3 B3 |
| **D18** | **Measure before optimizing** — O1 establishes a baseline first | `optimasi.md` is explicitly a static audit with no load test. Several of its recommendations depend on real data (portfolio counts, response sizes) |
| **D19** | **Recommend the launch event be free** | Keeps 500 synchronous Midtrans calls off the launch critical path for no launch-day benefit. §9.1 |
| **D20** | **Locale routing uses explicit per-locale groups**, not a `{locale?}` prefix | The optional-parameter prefix cannot serve unprefixed sub-paths and would have broken every route in P1, P5, P6, P7. See §11 |

### 3.1 D3 in detail — the requirement was ownership, not storage

The stated requirement was *"admin must be able to access it anytime, even if the
user logs out or is already deleted."* That is a **lifecycle** constraint, not a
storage-medium one, and it is satisfied by making the file belong to the
`job_applications` record rather than to the `customers` record:

- `job_applications.customer_id` is `nullOnDelete`, **not** cascade.
- Applicant identity is **snapshotted** onto the application at submission
  (name, email, phone), so a deleted account leaves the application readable.
- Documents hang off the application, untouched by account deletion.

With ownership correct, the medium is a free choice. Private disk wins because
CV volume is unbounded and the files are large (the BLOB convention carries a
documented warning that it "increases database and backup sizes substantially");
CVs are dense PII and belong out of every DB dump and replica; and Laravel's
filesystem abstraction makes a later move to S3 a disk-config change while
hosting is still undecided (README decision #6).

A deliberate divergence from the BLOB convention, justified by payload size, PII
sensitivity, and retention lifetime.

### 3.2 D5 in detail — what "unlisted YouTube" obliges us to do

The user chose unlisted YouTube embeds over a managed platform and accepted that
an unlisted URL is shareable. That trade is theirs to make. But it narrows the
threat model to one thing we *can* control, and one we cannot:

**Cannot be prevented:** a paying student copying the URL and passing it on.
Accepted. Mitigation is deterrence and response, not prevention.

**Must be prevented — and this is the part that is easy to get wrong:**
leaking the video ID to users who never paid.

> **Hard rule.** The YouTube ID must not appear in any response sent to a user
> without an active entitlement. Rendering the ID and hiding the player with CSS
> or JavaScript is **not** a gate — page source is readable by anyone. The lesson
> payload carries `youtube_id` **only after** the server-side entitlement check
> passes. A locked lesson returns metadata (title, duration, free-preview flag)
> and no ID.

Supporting measures, none of which substitute for the rule above:

- Embed via `youtube-nocookie.com`, `rel=0`, modest branding.
- Overlay the student's own email on the player as a sharing deterrent.
- Keep `youtube_id` out of API responses, Blade data, and JS payloads for locked
  lessons.
- Lessons may be flagged **free preview** — those intentionally expose their ID,
  which is the whole point, and that must be an explicit per-lesson flag rather
  than an accident of the gate failing open.
- If a leak is detected, the response is to re-upload and swap the ID; keep
  `youtube_id` editable in Filament so this needs no deploy.

Reuse `HasYoutubeVideo` for URL normalization. Do not write a second parser.

### 3.3 D9 in detail — why payments is its own phase

Both paid events and paid courses need Midtrans. Payment integration is the
riskiest work in this brief: signature verification, retry semantics, duplicate
notifications, and partial failure are all easy to get subtly wrong, and the
failure mode is financial.

So `payments` is **polymorphic from the first migration** (`payable_type` /
`payable_id`) and is built and verified as a standalone phase against test
payables, before either consumer exists. Course purchase and paid event
enrollment then plug into a flow that already has its webhook idempotency proven.

This also means the phase-9 LMS payment work README anticipated is now simply
absorbed, not deferred.

### 3.4 D10 in detail — method selection, and the expiry trap

The user asked for bank transfer, QRIS, and "e-wallet maybe", and asked for a
recommendation. Recommended Snap `enabled_payments`:

| Method | Why include it |
|---|---|
| **QRIS** | One integration covers GoPay, OVO, DANA, ShopeePay, and every mobile-banking app. The universal Indonesian QR standard. Best path on **desktop** — buyer scans with their phone. |
| **Bank transfer VA** (BCA, BNI, BRI, Mandiri, Permata) | Trusted for higher-ticket purchases and not subject to e-wallet balance ceilings, which a course price can easily exceed. |
| **E-wallet deeplinks** (GoPay, ShopeePay) | Best path on **mobile**. A buyer already on their phone cannot conveniently scan a QR shown on that same phone; a deeplink opens the wallet app directly. This is why deeplinks are worth having *in addition to* QRIS, not instead of it. |

Fee shape differs by method and is worth knowing when pricing: QRIS is a low
percentage (cheapest on small amounts), VA is a flat per-transaction fee
(cheapest on large amounts), e-wallet channels are the most expensive
percentage. Confirm current rates with Midtrans — do not hardcode assumptions.

`enabled_payments` is Snap configuration, so the method list is changeable later
without touching application code.

> **The non-obvious part.** Payment expiry windows differ by method: QRIS and
> e-wallet expire in roughly 15–30 minutes, while a bank-transfer VA typically
> stays open for **24 hours**. The event seat-hold design in §4.4 assumed one
> bounded window. It is not one window — it depends on what the buyer picks.
>
> Left alone, a buyer choosing VA on a capacity-limited event holds a seat for a
> full day, blocking buyers who would have paid immediately. On a sold-out
> workshop that is a real revenue loss, and it is invisible until it happens.
>
> **Resolution:** set Midtrans `custom_expiry` per payable type rather than
> accepting the per-method default.
> - **Events** (capacity-limited): one short, uniform hold — recommend 1 hour —
>   applied to every method, so seat inventory behaves predictably regardless of
>   how the buyer pays.
> - **Courses** (unlimited capacity): the full default window is harmless, since
>   nothing is being held from anyone else. Let VA keep its 24 hours.
>
> This is why the seat-hold window is derived from the payment record's expiry
> rather than from a global constant.

### 3.5 D11 in detail — retention period

The user was unsure and suggested 6 months. UU PDP No. 27/2022 does not mandate
a fixed number; it requires that personal data be kept no longer than necessary
for the purpose it was collected for, and that erasure requests be honoured.
So the number is a business judgement, and the engineering obligation is to make
it **cheap to change** and to make deletion-on-request actually work.

**Recommendation: 12 months after the vacancy closes**, not 6. Six months is
legally safe — it is *more* conservative — but it tends to delete candidates
right when a similar role opens up, and re-advertising costs more than storage.
Twelve months covers a full hiring cycle.

Implementation:

- The period is an `.env` value, not a literal. Changing 12 to 6 is a one-value
  edit with no deploy of new logic.
- The purge is a scheduled job keyed on vacancy closure, not on application date.
- **Deletion on request is honoured at any time**, independent of the period.
  This is a right under UU PDP, not a feature toggle.
- Purges are audit-logged so the policy is demonstrable.

If the user prefers 6 months after reading this, it is a single config change —
flagged in `task.md` rather than treated as settled against their instinct.

### 3.6 D12 in detail — contact goes to WhatsApp, not a form

**This was a gap in my analysis, not the user's brief.** I asked four questions
about accounts, tracker access, CV storage, and event payment, and never asked how
contact works. The user corrected it and updated `README.md`, `task.md`, `docs/02`,
and the P0 plan themselves.

**The decision:** every contact and consultation CTA opens the configured WhatsApp
destination directly. There is no contact form, no submission endpoint, and no
`contact.create` route.

**Partially implemented — and I overstated this on first writing.** The earlier
version of this section claimed "the views do it today" without qualification.
That is wrong, and `rekap.md` C1 was right to call it out.

The **CTA buttons** are correct. The **nav links are not**:
`header.blade.php:48` and `:93` still call `route('contact.create')`, and
`home.blade.php:81` does too — which is a large part of why every page returns
HTTP 500 (T0). Fixing those call sites is T0 Step 2.

What *is* genuinely already correct:

- `company_whatsapp` is a `site_settings` key, read through the `Settings`
  accessor. It holds a **complete URL** and is used directly as an `href`.
- Every CTA already points at it: the header "Konsultasi" button
  (`partials/header.blade.php`, desktop and mobile), the hero "Booking Sekarang"
  CTA, the benefits CTA, the per-service consultation CTA, the FAQ CTA, the footer
  contact block, and `<x-floating-whatsapp>`.
- All of them use `target="_blank" rel="noopener noreferrer"`, which is correct for
  an outbound link.

**`ContactFormTest` is the guard, not a casualty.** It asserts `/kontak` and
`/contact` return 404 for both GET and POST. It *enforces* D12 and must be
converted to PHPUnit and kept. The P0 plan originally mischaracterized it; corrected.

**Dead artifacts to remove — a closed loop, verified by grep:**

| Artifact | Why it is dead |
|---|---|
| `app/Http/Requests/ContactRequest.php` | No controller or route consumes it |
| `app/Models/ContactSubmission.php` | Nothing writes a submission |
| `database/migrations/2026_01_01_000009_create_contact_submissions_table.php` | Table for rows that cannot be created |
| `lang/id/contact.php`, `lang/en/contact.php` | **`ContactRequest` is their only consumer** (`__('contact.name')` etc.) — removing it makes these fully unreferenced |
| `Service::contactSubmissions()` relation | Points at the dead model |

Remove them together in one change, or none of them. Deleting the model while
leaving the relation, or the request while leaving the translations, produces a
half-migrated state that is worse than either end.

Keep `lang/*/nav.php`'s contact label — it is the nav link text pointing at
WhatsApp, not form copy.

**Rule for every future feature:** a new page that needs "talk to us" links to a
configured WhatsApp destination — `company_whatsapp` for sales and consultation,
or the per-vacancy HR contact on careers pages (D13). It does not grow a form. The
**only** authenticated in-app submission flows in this project are the job
application (P6) and the quiz attempt (P4), both deliberate features with their own
auth and validation — not contact channels.

### 3.7 D13 in detail — `job_vacancies.contact_url`

**Verified:** the legacy `job_detail.contact` column held per-vacancy WhatsApp
URLs, and the migrator maps it straight to `contact_url`. But all three legacy
values are placeholders — `wa.me/6281234567891`, `wa.me/6281234567892`,
`wa.me/6282222222222`. **There is no real production data to preserve**, so this
was a design decision, not a migration one.

The legacy *intent* still matters: a **different number per vacancy** means the old
site deliberately routed candidates to different hiring contacts.

**Decision: keep the column, opt-in, subordinate to Apply.**

| Rule | Reason |
|---|---|
| Empty by default; renders nothing when null | Zero admin burden until HR wants it. A stale number is worse than no number |
| **Never falls back to `company_whatsapp`** | That is the sales/consultation number. A job candidate in the sales inbox is bad for the candidate and noisy for sales |
| Apply is the primary CTA; this link is visually secondary | See the channel-split risk below |
| Labelled as a **question** channel, not an application channel | e.g. "Ada pertanyaan tentang posisi ini?" — never "Lamar via WhatsApp" |
| Message prefilled with the role title via `wa.me/<number>?text=` | HR gets context immediately and can redirect anyone sending a CV back to the form |

**The risk this design exists to contain — channel split.** If a WhatsApp link sits
beside Apply with equal weight, candidates message instead of applying. CVs then
arrive over WhatsApp: outside P6's status pipeline, outside D11's retention period,
outside P8's audit log and erasure path. That is a **compliance regression**, not
merely an inconvenience — it quietly undoes the work those phases exist to do.
Demoting and relabelling the link is what keeps the structured funnel primary.

---

## 4. Architecture reframing

Mapped onto the layering in `guide.md` §1–3.

### 4.1 Identity

Two fully separate authentication surfaces. **No third one** — D8 keeps
instructors out of the auth model entirely.

```
users     + web guard      → Filament /admin   (staff: superadmin/admin/editor)
customers + customer guard → public features   (LMS, events, career, own projects)
```

`customers`: name, email (unique), `email_verified_at`, password, phone,
`is_active`, remember token, soft deletes. Its own password-reset broker and token
table — Laravel's default `password_reset_tokens` is keyed by email alone and
cannot be shared safely between two providers.

**Email verification is required before purchasing, enrolling, or applying.**
Without it, course entitlements and event capacity attach to unverified
addresses, and receipts go nowhere.

### 4.2 Payments (shared foundation)

`payments`: polymorphic `payable`, unique `order_id` (the reference sent to
Midtrans), `gross_amount` `decimal(15,2)`, status (`pending` / `settlement` /
`expired` / `cancelled` / `failed` / `refunded`), payment type, Midtrans
transaction id, raw response JSON, `paid_at`.

Webhook handling, per `guide.md` §5 rules 3–4:

1. **Verify the signature** — SHA512 of `order_id + status_code + gross_amount +
   server_key` — before trusting any field in the payload.
2. **Respond 200 immediately**; do the work in a queued job.
3. **Idempotent.** Midtrans retries; duplicate notifications are normal
   operation, not an error. Key on `order_id` plus status transition.
4. Entitlement is granted by the **webhook**, never by the browser returning from
   checkout. A client-side redirect is not proof of payment.

Free events skip checkout entirely.

### 4.3 Courses / LMS

Catalog and entitlement:

- `courses` — translatable title/description, slug, cover media, `price`
  `decimal(15,2)`, level, estimated duration, `is_published`, `published_at`,
  timestamps, soft deletes.
- `course_sections` — course, translatable title, ordering.
- `course_lessons` — section, translatable title, `content_type`
  (`video` / `text` / `file`), `youtube_id` (via `HasYoutubeVideo`), rich text
  body, attachment reference, duration, `is_free_preview`, ordering.
- `course_enrollments` — course, customer (`nullOnDelete`), snapshotted student
  identity, source payment, `enrolled_at`, `completed_at`. **No expiry** (D6).
  Unique on (course_id, customer_id).
- `lesson_completions` — enrollment, lesson, `completed_at`. Unique on
  (enrollment_id, lesson_id).

Quizzes (optional per course, D7):

- `quizzes` — attached to a lesson or a section, translatable title, pass mark.
- `quiz_questions` — quiz, translatable prompt, type, ordering.
- `quiz_options` — question, translatable label, `is_correct`.
- `quiz_attempts` — enrollment, quiz, score, passed, `attempted_at`.

Quizzes are **self-check**, not a gradebook: no staff grading queue, no
assignment uploads. That was the explicitly rejected option.

**Entitlement is the security boundary of this feature.** Every lesson read goes
through one authorization path — a `CoursePolicy` / `LessonPolicy` pair — and the
gate decides whether `youtube_id` and the lesson body are serialized at all
(§3.2). There must be exactly one such path; a second code route to lesson
content is how paywalls leak.

Progress is derived from `lesson_completions` against the course's lesson count.
It is cached per enrollment and invalidated on completion, never recomputed with
a count query inside a view loop.

### 4.4 Events

- `events` — translatable title/description, slug, cover media, `starts_at`,
  `ends_at`, `location_type` (online/offline/hybrid), venue, online URL,
  `capacity` (nullable = unlimited), `price` `decimal(15,2)` default 0,
  registration window, `is_published`, soft deletes.
- `event_registrations` — event, customer (`nullOnDelete`), snapshotted attendee
  identity, status (`pending` / `confirmed` / `cancelled` / `attended`), unique
  `ticket_code`, registration/confirmation/cancellation/attendance timestamps.
  P5a has no payment foreign key; P5b adds the payment association. A unique
  (event_id, customer_id) index is maintained by reusing a cancelled row on
  re-enrollment.

**Capacity is a concurrency problem, not a validation problem.** A naive
`count() < capacity` check oversells under load, and 500 concurrent users is the
stated target. Seat claiming happens inside a transaction with a row lock on the
event, or via a conditional atomic counter update. This gets an explicit
concurrency test.

P5a implements the free path. Paid events remain visible but cannot be enrolled
until P5b supplies checkout; this avoids unpaid pending registrations. P5b will
hold a seat until the **payment record's own expiry**, not a global constant —
because that expiry varies by payment method (§3.4). It will set a short uniform
`custom_expiry` so a bank-transfer buyer cannot hold a seat for 24 hours.

### 4.5 Career

`job_vacancies` already exists and is reused unchanged. Two new tables:

- `job_applications` — vacancy, customer (`nullOnDelete`), **snapshotted**
  applicant identity, cover letter, status (`submitted` / `screening` /
  `interview` / `offered` / `rejected` / `withdrawn`), reviewing staff user,
  admin-only notes, soft deletes. One active application per vacancy per
  customer.
- `job_application_documents` — application, type (`cv` / `portfolio` /
  `certificate` / `other`), disk, path, original filename, mime type, size,
  checksum.

Upload validation per `guide.md` §6: MIME **and** extension both checked,
size-capped, stored outside the webroot, served only through an
authorization-checked controller — never by direct URL.

### 4.6 Progress tracker

- `projects` — `project_code` (unique, **random and non-sequential**),
  snapshotted customer contact fields used for verification, title,
  `current_stage`, status (`in_progress` / `on_hold` / `completed` /
  `cancelled`), start date, estimated completion, `is_visible`.
- `project_updates` — project, stage name, status, customer-visible note,
  `occurred_at`, ordering.

Lookup: project code **plus** matching phone or email, rate-limited per IP and
per code.

**Response-shape rule:** a wrong code, a non-matching verification value, and an
already-completed project must all return the *same* response. A distinguishable
"already finished" message would confirm the code is valid — the exact
enumeration signal D2 exists to remove. The brief's "only projects not yet done"
is therefore a **uniform negative response**, not a different message.

**Confirmed (A1):** project data is entered and maintained by staff in Filament.
There is no external project-management system to sync from.

**Verified against the legacy dump:** there is **no project-tracking table**. The
legacy tables are `alur_section`, `features_section`, `hero_banner`, `job_detail`,
`layanan_section`, `location_section`, `mengapa_section`, `properties`,
`property_details`, `property_images`, `review_design`, `testimonial_section`,
`users`. The only `project` reference in the entire dump is
`features_section.project` — the homepage "projects completed" marketing counter,
not tracking data.

The tracker therefore starts from an empty table. The user mentioned "maybe there
will be some already existing data", which must live outside the legacy database —
most likely a spreadsheet or internal records. Manual Filament entry is required
regardless; a CSV import is a conditional task to be built once the actual format
is known, rather than speculatively against a guessed shape (A16).

### 4.7 Admin

New Filament resources: Courses (with nested sections/lessons/quizzes), Course
Enrollments, Events, Event Registrations, Job Applications (with authorized
document download), Projects, Project Updates, Payments (read-only), Customers
(view / deactivate only — never edit credentials).

The panel stays on the `web` guard with `User`. A test must assert a `customers`
session **cannot** reach `/admin`.

---

## 5. Cross-cutting concerns

**Caching** (`guide.md` §5): published course catalog, event listings, and
vacancy listings are cached with observer-driven invalidation registered in the
same change. Per-enrollment progress is cached and invalidated on completion.
Nothing that varies by entitlement is ever cached under a shared key — a cached
lesson payload leaking a `youtube_id` to a non-purchaser is the same failure as
§3.2, arriving by a different route.

**Queues** — P1 customer verification and P5a free-event confirmation use queued
notifications. Midtrans webhook processing, document post-processing,
expired-hold release, and retention purge remain future queued workloads. These
jobs require a supervised worker in the target environment; no persistent worker
was running during this validation.

**Rate limiting** — progress lookup, login, registration, password reset,
application submission, checkout initiation.

**Bilingual** — all new public content is translatable, matching the existing `id`
(unprefixed) / `en` (`/en`) convention. Note that lesson *video* is inherently
single-language per lesson; see assumption A12.

**Policies** — `CoursePolicy`, `LessonPolicy`, `EventRegistrationPolicy`,
`JobApplicationPolicy`, `ProjectPolicy`. No role conditionals in controllers
(`guide.md` §10.3).

**Contact (D12)** — no new page in P1–P9 gets a contact form. Course detail, event
detail, and vacancy detail all link consultation CTAs to `company_whatsapp` via the
`Settings` accessor, reusing the existing pattern. If a new page seems to need a
message box, it is a contact form wearing a different name.

---

## 6. Infrastructure prerequisites

Not feature work. Features built on top of these will appear to work in
development and fail under load.

1. **Redis** for cache, session, and queue. All three are on `database` today;
   adding queued jobs to a database queue while MySQL also serves sessions and
   cache compounds the bottleneck.
2. **Queue worker** — supervised process. None exists.
3. **Mail transport** — every feature here sends mail. Unconfigured.
4. **Test suite must execute.** Pest syntax, Pest not installed, conflicts with
   Laravel 13 / PHPUnit 12. Until fixed, *nothing below is verifiable* and no
   claim of "tested" is honest (`guide.md` §8). This matters more now: payment
   and entitlement code is not something to ship on manual inspection.
5. **Fix the orphaned `home.{locale}` cache** — `ContentObserver` invalidates a
   key `HomeController` never writes.

---

## 7. Assumptions requiring confirmation

Carried into `task.md` as `[!]` items. Defaults applied so planning can proceed.

| # | Assumption | Default applied |
|---|---|---|
| A1 | ~~Projects are staff-managed in Filament, no external sync~~ | **CONFIRMED** — Filament-managed, no sync. No legacy data exists |
| A2 | Events are capacity-limited, no waitlist | Capacity enforced, overflow rejected |
| A3 | Applicants can see their own application status | Basic status visible |
| A4 | One active application per vacancy per customer | Enforced by unique index |
| A5 | Email verification required before purchase/enroll/apply | Required |
| A6 | Progress tracker needs no customer account | Code + verification only |
| A7 | ~~CV retention is bounded, not indefinite~~ | **DECIDED (D11)** — 12 months after vacancy closure, env-configurable, scheduled purge, erasure-on-request always honoured |
| A8 | Paid-event seats expire if unpaid | Bounded hold, then released |
| A9 | Lessons are freely navigable, no sequential unlock | Free navigation |
| A10 | No completion certificates in this phase | Out of scope |
| A11 | Quizzes are self-check; no pass-gate blocking progress | Self-check only |
| A12 | Course *content* is single-language per course; UI is bilingual | One content language per course, bilingual chrome |
| A13 | No discount codes or vouchers in this phase | Out of scope |
| A14 | Refunds handled manually by staff via payment status | Manual, audit-logged |
| A15 | Some lessons may be marked free preview | Supported, explicit per-lesson flag |
| A16 | Pre-existing project data lives outside the legacy DB (spreadsheet or internal records) | Manual Filament entry built first; CSV import added only once the real format is supplied |
| A17 | Event seat holds use a short uniform expiry across all payment methods | 1 hour via Midtrans `custom_expiry`; courses keep per-method defaults |

---

## 8. Principal risks

1. **Entitlement bypass / video ID leak** — the paywall is only as strong as the
   single gate in §3.2. Any second code path to lesson content, or any cached
   payload shared across entitlement states, defeats it. Highest-severity risk in
   this brief.
2. **Guard isolation** — if the Filament panel is not explicitly pinned to `web`,
   D1's protection is theoretical. Verify and test before any customer can
   register.
3. **Webhook duplication** — a non-idempotent handler double-grants entitlement or
   double-confirms a seat. Financial failure mode.
4. **Entitlement granted on client redirect** — trusting the browser's return from
   checkout instead of the verified webhook. Classic and severe.
5. **Capacity oversell** — concurrent enrollment on an event's last seat.
6. **PII surface** — CVs, project customer contacts, and now student records
   materially raise this application's data-protection exposure. Retention policy
   required (A7).
7. **Unlisted-link sharing** — accepted by the user under D5. Deterrence and
   ID-rotation response only; not preventable.
8. **Scope** — five features, one of which is an LMS with payments. The phase
   order exists so each lands verified rather than all landing half-done.

---

## 9. Performance and launch capacity

Added 2026-10-08 after the user supplied `optimasi.md` and confirmed the launch
profile. The user's priority: optimization comes **after** the P0–P9 feature plan.
That ordering is respected below, with three documented exceptions (§9.5).

### 9.1 Launch profile (confirmed with the user)

| Question | Answer | Consequence |
|---|---|---|
| Server | **Undecided** — asked me to recommend a target | §9.6 specifies a minimum spec as assumption A18 |
| Arrival pattern | **Unsure** → plan for a simultaneous burst | Burst is the strict superset; surviving it covers every gentler pattern |
| Launch actions | **Register + verify + browse + enroll in one event.** Explicitly *not* buying courses | Midtrans is **off** the launch critical path — a major simplification |

So the launch critical path is:

```
homepage (read) → register (write + bcrypt) → verification email (queue + external)
→ verify (write) → login (bcrypt) → browse events (read) → enroll (write + single-row contention)
```

**Recommendation (D19): make the launch event free.** Paid would drag Midtrans
onto the critical path — 500 synchronous Snap API calls, each holding a PHP worker
on an external request — for no launch-day benefit. Charge for later events once
the payment path has real traffic behind it.

### 9.2 Verified current state

Everything here was checked against the code, not inferred:

| Finding | Evidence | Severity |
|---|---|---|
| **Every page returns 500** | `php artisan tinker` rendering `/` → `STATUS: 500`. Four live route names are called but unregistered: `contact.create`, `careers.index`, `portfolio.index`, `portfolio.show`. `header.blade.php:45,48,90,93` means the **layout** breaks, so no page renders | **Critical** |
| `BCRYPT_ROUNDS=12` | `.env:16` | **High** — see §9.3 B2 |
| Portfolio query unbounded | `HomeController::payload()` fetches all published non-featured portfolios; only `PORTFOLIO_VISIBLE = 16` render | High |
| Portfolio cover N+1 | `portfolio-card.blade.php:4` — `$portfolio->coverImage->first() ?? $portfolio->images->first()`. Both are `HasMany` on `Portfolio`; only `coverImage` is eager-loaded | High |
| All media streams through PHP | `MediaController::show()` — one indexed query, then either a disk read or a second unbuffered BLOB query | **High** — see §9.3 B1 |
| No conditional-request support | `MediaController` sets `Cache-Control: public, max-age=2592000` but **no `ETag`, no `Last-Modified`** | High |
| Media is mid-migration | `media_assets.content` is `LONGBLOB NOT NULL`; `storage_path` was added 2026-10-07 as nullable. Assets fall back to BLOB when it is null | Medium |
| `welcome.blade.php` is dead | 72 KB scaffold; root route uses `HomeController`. Its `route('login')`/`route('register')` calls are the 5th and 6th unregistered names — harmless because nothing renders it | Low |

`optimasi.md` found the first four. The media-delivery items and the bcrypt cost
are additions — the audit scoped itself to the landing page and did not cover the
registration or media-serving paths.

### 9.3 Bottleneck analysis, ranked for *this* profile

Ranked by impact × likelihood for 500 concurrent registering users, not in general.

**B1. PHP-FPM worker exhaustion from media streaming — the top traffic risk.**

Every image request executes PHP and queries MySQL, then streams bytes through
the PHP process. A PHP-FPM worker stays occupied for the whole transfer. On a slow
mobile connection that is **seconds per image**, and Indonesian mobile traffic is a
large share of this audience.

Workers are the scarce resource — realistically 30–40, not 500. Launch means 500
**first-time** visitors, so browser cache is empty and `max-age=2592000` helps
nobody yet. A page with ~20 assets generates ~10,000 asset requests, each holding a
worker. HTML requests then queue behind image transfers. This is effectively a
self-inflicted slowloris, caused entirely by legitimate traffic.

Fixes, in order of effect:

1. **Serve public marketing media directly from nginx/Apache, bypassing PHP.**
   Hero images, portfolio covers, logos and icons are public — the authorization
   `MediaController` provides has no value for them. This removes the dominant cost
   rather than tuning it.
2. If PHP must stay in the path, use **`X-Accel-Redirect`** (nginx) or
   `X-Sendfile` (Apache): PHP does the authorization check and hands the file to
   the web server, releasing the worker immediately.
3. Add **`ETag` and `Last-Modified` with 304 handling** so revalidation costs a
   header, not a re-download.
4. A CDN in front makes the first-visit problem disappear for everyone after the
   first requester per edge.

`MediaController` remains correct and necessary for genuinely private media — and
P6's applicant CVs are exactly that.

**B2. Bcrypt CPU saturation.**

`BCRYPT_ROUNDS=12` costs roughly 250–400 ms of CPU per hash. The launch path needs
~1,000 hash operations (500 registrations + 500 logins) ≈ **300 CPU-seconds**. On
4 vCPU that is ~75 seconds during which the CPU is fully consumed and *every other
request queues behind it* — including the cached homepage.

This cannot be cached, queued, or parallelized away. It is pure CPU, and adding
workers makes it worse.

**Fix: `BCRYPT_ROUNDS=10`** (D15). Cost 10 is 4× faster (~75 ms), cutting the
launch hashing budget to ~75 CPU-seconds. It was Laravel's default for years and
remains a defensible bcrypt work factor. **Do not go below 10.** Laravel rehashes
automatically on next login when the cost changes, so the change is safe for
existing accounts.

**B3. IP-based rate limiting would break the launch — a correctness bug in my own P1 plan.**

The original P1 plan specified registration at **3 per minute per IP**. That is
wrong for this launch:

- Indonesian mobile carriers use **CGNAT** heavily — hundreds of subscribers share
  one egress IP.
- A coordinated launch on venue or office WiFi puts everyone behind **one** NAT
  address.

Either way, registrant #4 onward receives HTTP 429. The failure is indistinguishable
from a successful launch going wrong, and the logs would show the app "working as
configured."

**Fix (D17):** registration must not be throttled by IP alone. Raise the IP ceiling
substantially, add a per-email-address limit, and rely on **email uniqueness plus
the existing verification requirement** for abuse control. If stronger protection is
wanted, use a challenge (Turnstile/reCAPTCHA) rather than an IP counter. Login
throttling stays keyed on email **and** IP, which is already correct. The delivered
P1 configuration uses separate limits of 3/minute per email and 100/minute per IP;
the 50-signup same-IP regression test enforces the launch floor. Revisit that ceiling
with measured traffic during O1/P9.

**B4. Cache stampede on the homepage.**

P0 deferred the homepage cache write until Redis is available (K5), so there is no
homepage cache stampede today. If O5 adds the write, it will be invalidated eagerly
by `ContentObserver`; under burst load, an editor save could then make **all 500
in-flight requests miss simultaneously**, each running the
full ~12-query payload.

**Fix:** regenerate under a single-flight guard — `Cache::flexible()`
(stale-while-revalidate, Laravel 11+) so one worker refreshes while others serve
slightly stale content, or `Cache::lock()` so exactly one regenerates. Eager
invalidation is right for editor experience; it needs stampede protection to be
safe at this scale.

**B5. Verification-email burst, with mail on the critical path.**

500 verification emails within minutes. Providers throttle (SES defaults to ~14/s;
others are comparable). Worse: **A5 requires verification before enrolling**, so a
slow mail queue directly blocks the launch action.

Two independent fixes:

1. Rate-limit the mail job to the provider's documented ceiling (`Redis::throttle`
   or a `RateLimited` job middleware) and size worker concurrency deliberately.
   Hitting a provider limit causes hard bounces, not just delay.
2. **Reconsider A5 for free events** — let a registered-but-unverified user enroll,
   and require verification before the event itself or before any paid content.
   That takes an external dependency off the launch path entirely. Flagged as A20;
   it is a product decision.

**B6. Unbounded portfolio query and the cover-image N+1.** Both confirmed in §9.2.
Multiplied by 500 concurrent requests, hydrating every published portfolio to render
16 is pure waste, and each cover-less portfolio adds a query.

**B7. Event seat contention — lower risk than it looks.** 500 concurrent claims on
one event row serialize on an InnoDB row lock. Because P5 uses a **conditional
atomic `UPDATE`** rather than `lockForUpdate()` plus application logic, each claim
is ~1 ms and 500 drain in roughly a second. The design holds.

The real hazard is transaction scope: if the enrollment transaction does other work
while holding that row, lock-wait timeouts and deadlocks follow. **Keep the
seat-claim transaction to the claim plus the registration insert, nothing else**,
with notifications dispatched after commit (already specified in P5).

**B8. Missing production basics.** `config:cache`, `route:cache`, `view:cache`,
`composer install --optimize-autoloader --no-dev`, opcache enabled with
`opcache.validate_timestamps=0`. Cheap, large, and entirely mechanical.

### 9.4 On `optimasi.md`'s other findings

The audit is careful and its self-imposed limits are correct — it ran no load test
and it explicitly separates **disk** duplication from RAM. Holding that line
matters:

- **15.64 MiB of duplicate assets is a disk-hygiene item, not a performance item.**
  It will not change latency or throughput. It belongs in cleanup, and the audit's
  warning not to delete on hash-match alone is right: identical bytes at different
  logical paths may both be referenced.
- **The import command's memory use is a separate question** from asset size on
  disk, exactly as the audit says. Profile the command; do not infer it from file
  sizes or bundle sizes.
- **Repeated scope names across models are not duplication.** `scopePublished()`
  applies different conditions per table. The audit explicitly declines to justify
  merging them, which is the correct call and matches `guide.md`'s stance against
  abstraction for line-count reasons.
- **The plan-status inconsistency it flags is real and my fault:** `docs/plans/README.md`
  lists P0–P9 as "Not started" while P9 recorded a completed `docs/02` cleanup.
  Fixed by scoping that note to the documentation task it actually was.

### 9.5 Three items that cannot wait for "after the plan"

The user's priority is optimization after P0–P9. These three do not fit that
ordering, for reasons of kind rather than importance:

1. **The 500 errors (§9.2) are triage, not optimization.** Nothing in P0–P9 can be
   verified against a site that does not render. This becomes phase **T0**, before P0.
2. **The rate-limiting flaw (B3) is a correctness bug inside P1**, not a tuning
   opportunity. It is fixed in P1 where the limiter is written.
3. **Stampede protection (B4) belongs with the cache that P0 introduces.** Adding a
   cache and its stampede guard in one change is the same discipline as adding a
   cache and its invalidation together (`guide.md` §5 rule 5).

Everything else — media delivery, bcrypt, query bounds, asset hygiene, load
testing — waits for the O phases as the user directed.

### 9.6 Recommended target spec (A18)

Hosting is undecided, so this is the minimum I would plan against for a 500-user
simultaneous burst, assuming public media is moved off PHP (B1):

| Component | Recommendation | Why |
|---|---|---|
| Compute | **4 vCPU / 8 GB**, VPS or managed | Bcrypt is CPU-bound; 4 cores keeps the launch hashing window to ~20 s at cost 10 |
| Web server | nginx + PHP-FPM 8.4, opcache on | Needed for `X-Accel-Redirect` and static file serving |
| PHP-FPM | `max_children` ≈ 32–40 | At ~40–60 MB each, ~2 GB resident. More workers will not beat a CPU ceiling |
| MySQL | 8.x, `max_connections` ≈ 64 | Must exceed `max_children` + queue workers + Filament sessions, with headroom |
| Redis | 512 MB–1 GB | Cache, session, queue (P0) |
| Queue workers | 4–8, mail jobs rate-limited | Sized for the verification burst, capped by the provider's limit |
| CDN | Recommended, not required | Makes B1's first-visit problem vanish |

> **Shared cPanel hosting cannot meet this.** No Redis, no Supervisor for queue
> workers, no PHP-FPM control, and hard CPU/process caps. If that is the hosting
> direction, 500 concurrent registrations is not a realistic target and the plan
> needs to state the achievable ceiling instead. Decide hosting before O1's
> baseline, or the baseline measures the wrong machine.

### 9.7 New assumptions

| # | Assumption | Default applied |
|---|---|---|
| A18 | Target spec is 4 vCPU / 8 GB with nginx, Redis, and media off PHP | Planned against; **needs hosting decision** |
| A19 | Launch arrival is a simultaneous burst | Burst assumed as the superset |
| A20 | Verification is required before enrolling even at launch (A5) | Kept, but **recommend relaxing for free events** to take mail off the critical path |
| A21 | The launch event is free | Recommended, keeps Midtrans off the critical path |
| A22 | Public marketing media may be served without authorization | Planned; private media keeps `MediaController` |
| A23 | `media_assets.content` can be emptied once `storage_path` is populated and verified | Planned as a distinct, reversible step |
| A24 | 500 is the launch peak, not a sustained steady state | Capacity planned for the peak |

---

## 10. Black-hat review (`rekap.md`) — adjudication

Reviewed 2026-10-08. Every claim was checked against the code before being
accepted. Most hold; two do not; one is correctly observed but misdiagnosed.

### 10.1 Adopted

| ID | Finding | Verified how | Action |
|---|---|---|---|
| **C1** | Views call unregistered routes; homepage 500s | Reproduced independently before `rekap.md` arrived | Already T0. Its §3.6 criticism of *this document* was fair — §3.6 corrected |
| **H2** | `CourseLessonPolicy::view()` is an N+1 in the course outline — `$lesson->section` plus an `exists()` per lesson, ~60 queries for 30 lessons | Read the P3 snippet; the lazy `section` access is real | **Adopted.** P3 now hoists the entitlement check to one query per course |
| **M4** | Policy guard resolution: `Gate` resolves the **default** guard (`web`/`User`), so a `?Customer` policy parameter never populates | Confirmed against D1's two-guard design | **Adopted — important.** P3 now passes `auth('customer')->user()` explicitly |
| **H4** | P9's CSP step is stale — `frame-src https://www.youtube-nocookie.com` and `img-src … https://i.ytimg.com` already exist | `SecurityHeaders.php:42` and `:39` | **Adopted.** P9 changed from "add" to "verify, do not re-add" |
| **H5** | Midtrans Snap will be blocked by `script-src 'self'` | `SecurityHeaders.php:38`, with a deliberate comment at line 20 against `unsafe-inline`/`unsafe-eval` | **Adopted.** Recorded as a decision in P2 and P9, including the deeplink alternative |
| **M1** | Applicant documents orphan on transaction rollback — the filesystem is not transactional | P6 Step 5 stores inside `DB::transaction()` | **Adopted.** P6 now cleans up on failure plus a sweep |
| **M2** | Concurrent duplicate enrollment returns 500: both requests pass the pre-check, the loser hits the unique index | Correct for both P3 and P5 | **Adopted.** Catch the unique violation and respond idempotently |
| **M3** | `seats_taken` is denormalized with no reconciliation; drift accumulates silently | Correct — P5 increments and decrements it from several paths | **Adopted.** Nightly reconciliation job added to P5 |

### 10.2 Corrected or rejected

**H1 — "`coverImage` is `hasMany`, so `->with('coverImage')` eager-loads ALL images
for every portfolio" — FALSE.**

```php
public function coverImage(): HasMany
{
    return $this->images()->where('is_cover', true);   // constrained
}
```

The relation filters on `is_cover`. It loads cover rows only, not every image.
The **unbounded portfolio query** in the same finding is real and is already O4
Step 1, but the "loads all images" diagnosis is wrong, and the proposed
`hasOne()->ofMany()` change is an ergonomics improvement (no `->first()` in Blade),
not a performance fix. Do not present it as one.

**C3 — "`MediaAsset` missing from `ContentObserver` → editors see stale images" —
right observation, wrong diagnosis, wrong severity.**

The gap is real and worse than reported: the observer list covers 10 models and
omits **both `MediaAsset` and `Faq`** — and `Faq::published()->ordered()->get()` is
in the homepage payload, so `rekap.md` found one of the two.

But it is **not critical today**, because **there is no homepage cache** — H3 in the
same report establishes that `HomeController` never writes the key. Nothing can go
stale when nothing is cached. It becomes real the moment P0 adds caching, which is
where it now belongs.

More importantly, **the prescribed fix would not solve the symptom it describes.**
If an editor replaces image *content* at the same `path`, the URL is unchanged, and
`MediaController` sends `Cache-Control: public, max-age=2592000` — so browsers keep
the old image for **30 days** regardless of any server-side invalidation. The actual
fix is cache-busting: a content-addressed URL or a `sha256`/`updated_at` query
parameter. `media_assets.sha256` already exists for this. Folded into P0 and O3.

**C2 — already fixed before the review.** P0 lines 72–75 carry the correction
keeping `ContactFormTest`. The report read a stale copy.

**C1's table is incomplete** — it misses `home.blade.php:198` for
`route('portfolio.index')`. T0's table is the authoritative list.

**L1 — partially right.** `resources/views/careers/` exists but is **empty**;
`pages/coming-soon.blade.php` exists and is empty. `ContactController` does not
exist, as the report itself notes.

### 10.3 What the review missed

- **`Faq` is also unobserved** (§10.2 above).
- **`Model::preventLazyLoading(! app()->isProduction())` is already enabled**
  (`AppServiceProvider:38`). This sharpens the cover-image finding considerably:
  in development the fallback `$portfolio->images->first()` does not merely cost an
  extra query, it **throws `LazyLoadingViolationException`** whenever a portfolio
  has no cover. In production it degrades to a silent N+1. O4 updated.
- **`img-src` still allows `https://images.unsplash.com`**, which README's deploy
  checklist says to remove once legacy external images are local. Already in P9.

---

## 11. D20 — locale routing (implemented in T0)

**The bug.** Public routes sat in one group prefixed `{locale?}`, with
`SetLocale` reading `$request->route('locale')` and `URL::defaults` re-injecting
it. Elegant, and it worked — for exactly one route.

An optional route parameter **cannot be followed by a required segment**. So
`{locale?}/portofolio` compiled with the locale effectively mandatory:
`/en/portofolio` served, `/portofolio` returned 404. Only `/` escaped, being the
one route with nothing after the parameter. That is why a latent flaw in the
core routing design stayed invisible through the entire rebuild — there was
never a second route to expose it.

Left alone it would have broken **every** route added by P1 (customer auth),
P5 (events), P6 (careers), and P7 (the progress tracker), each time presenting
as "the Indonesian version 404s" rather than as a routing bug.

**The fix.** Register the same route definitions once per locale:

- Default locale: no prefix, canonical names (`home`, `portfolio.index`).
- Other locales: path prefix plus name namespace (`en/portofolio`, `en.portfolio.index`).
- The locale is passed to the middleware (`locale:en`) instead of captured as a
  route parameter, so `SetLocale` needs no route lookup and no `URL::defaults`.
- `Locale::name()` / `Locale::route()` resolve the right variant;
  `Locale::canonicalName()` strips the namespace for the language switcher.
- Views use a shared `$lroute()` closure, matching the existing `$settings()`
  convention rather than introducing a new one.

**Why this shape:** it is explicit, it survives `route:cache` (a
request-dependent prefix would bake one locale into the cached table, which
matters because O2 adds route caching), and it removes optional segments from
the URL structure entirely.

**Cost:** route names are now locale-namespaced, so `route('home')` inside the
locale groups is a bug — it sends English visitors to Indonesian URLs. Use
`$lroute()` in views and `Locale::route()` in PHP.
`BladeRouteIntegrityTest::test_locale_routes_exist_in_every_locale` enforces it.

**Every phase that adds a public route must register it inside `$publicRoutes`
in `routes/web.php`, not as a standalone route.**

## 12. P5a — free event enrollment

P5a depends on P1, not P2. A verified customer enrolls through
`EnrollInEvent`: the database conditionally increments `seats_taken`, then the
registration is created and confirmed in the same transaction. The confirmation
notification is dispatched after commit and queued. Cancellation uses the
shared `CancelEventRegistration` action and returns one seat idempotently.

The event-list cache stores plain attribute arrays per locale and never stores
`capacity` or `seats_taken`. A single live query supplies availability for the
current page. Expected event-table queries: **2 on a cache miss** (catalog
snapshot plus live availability) and **1 on a cache hit** (live availability).
`EventObserver` invalidates both locale keys after save, delete, or restore. O5
still owns hit-rate measurement and stampede/load hardening (K9).

Paid events remain visible with their price and an unavailable message, and the
enrollment action rejects direct paid submissions without taking a seat (K8).
P5b remains blocked on P2.

The nightly `ReconcileEventSeatCounts` job locks each upcoming event, compares
the counter with non-cancelled registrations, and corrects drift. Until P8 adds
the durable `Audit` facility and staff notification channel, corrections emit a
structured `Log::alert` with both values (K10). This operational log is not a
claim of durable audit history.

The PHPUnit capacity tests prove sequential atomic-claim logic, not real
parallelism. Genuine concurrent-load verification remains in O6/P9.
