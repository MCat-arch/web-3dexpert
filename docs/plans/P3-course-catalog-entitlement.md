# Phase P3 — Course Catalog, Purchase & Entitlement

| | |
|---|---|
| **Depends on** | P1 (buyer), P2 (payment) |
| **Blocks** | P4 |
| **Blocked by** | `[!]` YouTube account — gates **launch**, not build. Use placeholder IDs |
| **Plan stability** | **Expected revision** — Filament 5.9 nested-resource ergonomics should be confirmed in practice |

## Objective

Sell courses and gate their content. Decisions **D5**–**D8** in `notes.md`.

> **This phase builds the paywall.** `notes.md` risk 1 is the highest-severity
> risk in the project, and it is not the payment code — it is the entitlement
> gate. Read `notes.md` §3.2 before writing any code here.

## The hard rule

Because video lives on unlisted YouTube (D5), **the YouTube ID must not appear in
any response to a user without an active entitlement.** Rendering the ID and
hiding the player with CSS or JavaScript is not a gate — page source is readable.

Therefore: a locked lesson returns metadata only (title, duration,
free-preview flag) and **no `youtube_id`, no body**. There is exactly **one**
code path that decides this. A second path is how paywalls leak.

---

## Step 1 — Schema

```bash
php artisan make:migration create_courses_table --no-interaction
php artisan make:migration create_course_sections_table --no-interaction
php artisan make:migration create_course_lessons_table --no-interaction
php artisan make:migration create_course_enrollments_table --no-interaction
```

**`courses`** — `id`, `title` json (translatable), `slug` unique, `description`
json nullable, `cover_media_id` nullable, `price` decimal(15,2),
`level` string nullable, `estimated_minutes` integer nullable,
`is_published` boolean default false index, `published_at` timestamp nullable,
timestamps, softDeletes.

**`course_sections`** — `id`, `course_id` cascadeOnDelete, `title` json,
`sort_order` integer. Index (`course_id`, `sort_order`).

**`course_lessons`** — `id`, `course_section_id` cascadeOnDelete, `title` json,
`content_type` string (`video` / `text` / `file`), `youtube_url` string nullable,
`youtube_id` string nullable, `body` json nullable (translatable rich text),
`attachment_media_id` nullable, `duration_seconds` integer nullable,
`is_free_preview` boolean default false, `sort_order` integer.
Index (`course_section_id`, `sort_order`).

**`course_enrollments`** — `id`, `course_id` cascadeOnDelete,
`customer_id` nullOnDelete, snapshot columns `student_name` / `student_email`,
`payment_id` nullable, `status` string (`pending` / `active` / `revoked`) index,
`enrolled_at`, `activated_at` nullable, `completed_at` nullable, timestamps.
**Unique (`course_id`, `customer_id`).**

**Why `status` rather than a separate order table:** the enrollment *is* the
payable. It is created `pending` at checkout and flipped to `active` by the P2
webhook. One table, and the unique index still prevents double purchase — a
`pending` row is reused if the buyer retries. Entitlement means `status = active`,
never merely "a row exists."

Snapshot columns exist so a deleted customer leaves a readable sales record,
matching the P6 pattern.

---

## Step 2 — Models

```bash
php artisan make:model Course --factory --no-interaction
php artisan make:model CourseSection --factory --no-interaction
php artisan make:model CourseLesson --factory --no-interaction
php artisan make:model CourseEnrollment --factory --no-interaction
```

**`Course`** — `use HasTranslations, SoftDeletes;` translatable `title`,
`description`. Relations: `sections()`, `lessons()` (hasManyThrough),
`enrollments()`. Scopes `scopePublished()`, `scopeOrdered()` to match the existing
convention (`Portfolio::published()->ordered()`). Helper `isFree(): bool`
(`price` equals 0).

**`CourseLesson`** — `use HasTranslations, HasYoutubeVideo;`

> **Reuse, do not rewrite.** `app/Concerns/HasYoutubeVideo.php` already resolves
> `youtube_id` once on save and covers all 7 URL shapes found in the legacy dump
> (`HasYoutubeVideoTest`). Writing a second parser here would be a `guide.md`
> violation ("Check for existing components to reuse").

**`CourseEnrollment`** — status constants; `scopeActive()`; `belongsTo` course,
customer, payment. **Implements `App\Contracts\Payable`** (P2 Step 4):

- `payableAmount()` → the course price
- `payableExpiryProfile()` → `'course'` (24h window; unlimited capacity means a
  long hold costs nobody anything — `notes.md` §3.4)
- `fulfilPayment()` → set `status = active`, stamp `activated_at`. **Idempotent:**
  return early if already active.
- `revokePayment()` → set `status = revoked` (refund path, P8)

---

## Step 3 — The gate (build this before any view)

**`app/Policies/CourseLessonPolicy.php`** — the single authority:

```php
public function view(?Customer $customer, CourseLesson $lesson): bool
{
    if ($lesson->is_free_preview) {
        return true;
    }

    return $customer !== null
        && CourseEnrollment::query()
            ->where('course_id', $lesson->section->course_id)
            ->where('customer_id', $customer->id)
            ->active()
            ->exists();
}
```

Register it in `AppServiceProvider`. Nullable `$customer` so guests resolve
through the same path rather than a separate guest branch.

> **Two corrections from `rekap.md` — read before implementing this.**
>
> **1. Never let the Gate resolve the user (M4).** `Gate::allows()` and
> `$this->authorize()` resolve the user from the **default guard**, which is `web`
> (a `User`). With D1's two-guard design that means `?Customer $customer` would be
> `null` for a signed-in customer, or receive a `User` — so the gate would fail
> closed for real purchasers and the bug would look like "my course disappeared".
>
> Always invoke it explicitly with the customer:
> ```php
> Gate::forUser(auth('customer')->user())->allows('view', $lesson)
> ```
> Add a test asserting a signed-in customer on the `customer` guard is authorized
> while the default guard is `web`. This is easy to get wrong and silent when wrong.
>
> **2. Do not call this per lesson when rendering an outline (H2).**
> `$lesson->section` lazy-loads (one query) and `exists()` is another — about 60
> queries for a 30-lesson outline, against P9's O(1) target. Worse,
> `Model::preventLazyLoading()` is enabled outside production, so the
> `$lesson->section` access **throws** in development rather than being slow.
>
> **Resolve entitlement once per course**, then pass the boolean down:
> ```php
> $unlocked = CourseEnrollment::where('course_id', $course->id)
>     ->where('customer_id', auth('customer')->id())
>     ->active()->exists();                      // ONE query for the page
>
> // per lesson — no query, and free preview is still per-lesson:
> LessonPayload::for($lesson, $unlocked || $lesson->is_free_preview);
> ```
> **This is not a second access path.** It is the same decision computed once
> instead of N times — entitlement is per *course*, so hoisting it is correct.
> `LessonPayload` already takes `$unlocked` as a parameter precisely so the caller
> owns that computation. The Policy remains the authority for a **single** lesson
> reached by direct URL, which is the case that actually needs per-lesson checking.
>
> Keep `is_free_preview` evaluated **per lesson** in the expression above. Hoisting
> that too would unlock a whole course from one preview flag.

**`app/Support/LessonPayload.php`** — the only thing that serializes a lesson:

```php
public static function for(CourseLesson $lesson, bool $unlocked): array
{
    $payload = [
        'id' => $lesson->id,
        'title' => $lesson->title,
        'content_type' => $lesson->content_type,
        'duration_seconds' => $lesson->duration_seconds,
        'is_free_preview' => $lesson->is_free_preview,
        'unlocked' => $unlocked,
    ];

    if (! $unlocked) {
        return $payload;        // no youtube_id, no body. Ever.
    }

    return $payload + [
        'youtube_id' => $lesson->youtube_id,
        'body' => $lesson->body,
        'attachment_url' => $lesson->attachmentUrl(),
    ];
}
```

**Rules for this file:**

- Every lesson that reaches a Blade view or a JSON response goes through it.
- No Blade template ever reads `$lesson->youtube_id` directly.
- `is_free_preview` is an explicit per-lesson opt-in (A15), so an unlocked preview
  is a deliberate choice and never the result of the gate failing open.

---

## Step 4 — Purchase flow

**`app/Actions/Courses/StartCoursePurchase.php`**

1. Authorize: course published, customer verified (A5), no `active` enrollment.
2. Find or create the `pending` enrollment (reuses an abandoned attempt).
3. Snapshot student name and email onto it.
4. Call P2's `CreatePayment` with the enrollment as the payable.
5. Return the Snap token.

Free courses (`price = 0`) skip P2 entirely and activate immediately.

**Entitlement is granted by `fulfilPayment()` from the queued webhook job — never
by the controller, never by the browser's return from checkout.** P2's
`PaymentFulfilmentTest` guards this; do not add a second grant path here.

**Controllers** `app/Http/Controllers/Course/`:

| Controller | Route |
|---|---|
| `CatalogController` | `GET /kursus` (+ `/en/courses`) |
| `CourseDetailController` | `GET /kursus/{course:slug}` |
| `PurchaseController` | `POST /kursus/{course:slug}/beli` → Snap token |

Inside the `{locale?}` group. Detail page shows the full outline with every lesson
locked except previews — via `LessonPayload`, so the ID is simply absent.

**Contact CTA (D12):** any "ask about this course" link points at the
`company_whatsapp` setting via the `Settings` accessor, matching the header and
service-card pattern. **No contact form, no enquiry box** — `guide.md` §10.9.

---

## Step 5 — Caching

Cache the published catalog with an invalidating observer **in the same change**
(`guide.md` §5 rule 1, and P0 Step 5 established the pattern):

- Key constant on `Course`, referenced by the observer. Writer and invalidator
  must not drift.
- `app/Observers/CourseContentObserver.php` on `Course`, `CourseSection`,
  `CourseLesson`.

> **Cache the catalog, never a lesson payload.** A cached fragment carrying a
> `youtube_id` under a key shared across entitlement states is the same leak as
> §3.2 by another route (`notes.md` §5). Entitlement-varying data is not cached in
> this phase at all.

---

## Step 6 — Filament

`CourseResource` with nested `CourseSection` and `CourseLesson` management —
Filament 5.9 relation managers, or nested repeaters if ordering proves easier
that way. Confirm the ergonomics in practice and correct this file.

Requirements:

- `youtube_url` editable, with `youtube_id` derived on save by `HasYoutubeVideo`.
  **Keep it editable** so a leaked video can be swapped without a deploy
  (`notes.md` §3.2).
- `is_free_preview` a visible toggle per lesson, not buried.
- Drag ordering for sections and lessons.
- `CourseEnrollmentResource` **read-only** — enrollments come from payments.

---

## Step 7 — Tests

`tests/Feature/Courses/`.

**`CourseEntitlementTest` — the highest-value test in the project.** Write it
first, watch it fail, then build the gate.

- `test_guest_response_contains_no_youtube_id`
- `test_signed_in_non_purchaser_response_contains_no_youtube_id`
- `test_purchaser_of_a_different_course_gets_no_youtube_id`
- `test_pending_enrollment_does_not_unlock` — payment started, never settled.
- `test_revoked_enrollment_does_not_unlock` — refund path.
- `test_active_purchaser_receives_the_youtube_id`
- `test_free_preview_lesson_exposes_its_id_without_purchase`
- `test_non_preview_lesson_in_the_same_course_stays_locked`

Assert on the **raw response body** (`assertDontSee($lesson->youtube_id)`), not on
a view variable. The failure being guarded against is the ID reaching the browser,
so the assertion must look where the browser looks.

**`CoursePurchaseTest`**
- `test_settlement_webhook_activates_exactly_one_enrollment`
- `test_duplicate_purchase_of_owned_course_is_blocked`
- `test_concurrent_duplicate_purchase_returns_idempotent_response_not_500` — the
  pre-check and the insert are not atomic (M2). Two concurrent requests from one
  customer both pass the check; the loser hits the `(course_id, customer_id)`
  unique index. **Catch `UniqueConstraintViolationException` in
  `StartCoursePurchase` and return the same "already enrolled" response** the
  pre-check would have returned. A double-click must not produce a 500.
- `test_signed_in_customer_is_authorized_while_default_guard_is_web` — the M4
  regression guard. Fails if anyone lets the Gate resolve the user from the
  default `web` guard instead of `auth('customer')`.
- `test_outline_does_not_query_per_lesson` — seed 30 lessons, assert the query
  count does not scale with lesson count. The H2 regression guard.
- `test_unverified_customer_cannot_purchase`
- `test_unpublished_course_cannot_be_purchased`
- `test_abandoned_pending_enrollment_is_reused_on_retry`
- `test_free_course_enrolls_without_payment`

**`CourseCatalogTest`** — published only; both locales; **no N+1** (assert query
count with `DB::listen`); publishing invalidates the cached catalog.

---

## Validation

```bash
php artisan migrate
php artisan test --filter=Course
php artisan test
php artisan route:list --path=kursus
vendor/bin/pint --dirty --format agent
```

Manual, with the queue worker running: create a course with one preview lesson and
one locked lesson, view it as a guest and **read page source** to confirm the
locked ID is absent, purchase through the sandbox, confirm unlock.

---

## Acceptance

From `task.md` Phase P3:

- [ ] No response to a non-entitled user contains a lesson's YouTube ID or body —
      asserted across guest, non-purchaser, and wrong-course purchaser.
- [ ] Purchase grants entitlement only via the verified webhook.
- [ ] Catalog renders with no N+1 (verified, not assumed).
- [ ] Publishing a course invalidates the cached catalog immediately.

## Hand-off

P4 builds the player and **must** read lesson content through `CourseLessonPolicy`
and `LessonPayload` only. If P4 finds itself needing a second access path, that is
a design failure to fix here, not to work around there.
