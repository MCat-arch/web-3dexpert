# Phase P6 — Career: Applications & Documents

| | |
|---|---|
| **Depends on** | P1 (applicant) |
| **Blocks** | — |
| **Blocked by** | Nothing |
| **Plan stability** | **Firm** — `job_vacancies` already exists and was read directly |

## Objective

Let verified customers apply to published vacancies and attach a CV. Decision
**D3** in `notes.md`. Independent of P2–P5; needs only P1.

## The requirement that drives the design

The user's words: *"I need the admin to be able to access it anytime even if the
user logout or already deleted."*

That is a **lifecycle** requirement, not a storage one. It is satisfied by making
the file belong to the **application**, not to the customer:

- `job_applications.customer_id` is `nullOnDelete` — **never** cascade.
- Applicant identity is **snapshotted** at submission.
- Documents hang off the application, untouched by account deletion.

`ApplicationSurvivesCustomerDeletionTest` is the acceptance test for this
sentence. Write it early.

---

## Step 0 — Reuse what exists

`job_vacancies` and `App\Models\JobVacancy` are **already built**:

```php
$table->json('title');                        // translatable
$table->string('slug')->unique();
$table->string('location')->index();
$table->string('department')->index();
$table->string('employment_type')->index();
$table->json('requirement')->nullable();      // translatable
$table->json('description')->nullable();      // translatable
$table->string('contact_url')->nullable();
$table->boolean('is_published')->default(false)->index();
$table->timestamp('closes_at')->nullable()->index();
$table->timestamps();
$table->softDeletes();
```

**Do not create a new vacancy table or alter this one.** `closes_at` already
exists and is the key the P8 retention purge is computed from.

Confirm `JobVacancy` has `scopePublished()` / `scopeOpen()`; add them if missing,
matching the existing convention.

### `contact_url` — decided (D13)

`contact_url` already exists and the migrator fills it from legacy
`job_detail.contact`. All three legacy values are placeholders
(`wa.me/6281234567891`, `…892`, `…6282222222222`), so **no real data is at stake**.

**D12 does not forbid this phase's application form.** The job application is an
authenticated, validated, file-handling feature the user explicitly asked for
("sign in/sign up and then complete the form… might need to attach file like
pdf"). It is not a contact channel. The no-forms rule governs general
contact/consultation CTAs, which on this page means the "ask about this role" link.

**Decision: keep it as an optional, subordinate HR question link.** Implement
exactly these rules:

- [ ] Render the link **only** when `contact_url` is non-null. Empty renders nothing.
- [ ] **Never fall back to `company_whatsapp`.** That is the sales/consultation
      number; routing a candidate there is wrong for both sides. No fallback, no
      default, no "if empty use the general one".
- [ ] Apply is the **primary** CTA. This link is visually secondary — not an
      equal-weight button beside it.
- [ ] Label it as a **question** channel: "Ada pertanyaan tentang posisi ini?" /
      "Questions about this role?" Never "Apply via WhatsApp".
- [ ] Prefill the message with the role title using `wa.me/<number>?text=<urlencoded>`,
      so HR knows which vacancy it concerns and can redirect anyone sending a CV
      back to the form.
- [ ] Validate `contact_url` in the Filament form as a URL, and label the field so
      staff know it is for questions, not applications.

**Why demotion matters — channel split.** An equal-weight WhatsApp link means
candidates message instead of applying, and CVs then arrive outside the pipeline:
no status tracking, no D11 retention window, no P8 audit log or erasure path. That
is a compliance regression, not just lost convenience.

**Tests** (add to `JobApplicationTest` or its own small case):

- `test_contact_link_is_absent_when_contact_url_is_null`
- `test_contact_link_never_falls_back_to_company_whatsapp` — set `contact_url` null,
  set `company_whatsapp`, assert the sales number does **not** appear on the
  vacancy page.
- `test_contact_link_prefills_the_role_title`

---

## Step 1 — Private disk

`config/filesystems.php` — add a disk for applicant documents:

```php
'applications' => [
    'driver' => 'local',
    'root' => storage_path('app/private/applications'),
    'visibility' => 'private',
    'throw' => false,
],
```

**Confirm it resolves outside `public/`.** The legacy site's RCE came from
uploads landing in a web-served directory; README's deploy notes exist because the
project root was once the document root.

Laravel's filesystem abstraction means a later move to S3 is a config change
(`notes.md` §3.1) — relevant while hosting is undecided.

---

## Step 2 — Schema

```bash
php artisan make:migration create_job_applications_table --no-interaction
php artisan make:migration create_job_application_documents_table --no-interaction
```

**`job_applications`** — `id`, `job_vacancy_id` cascadeOnDelete,
`customer_id` **nullOnDelete**, snapshot `applicant_name` / `applicant_email` /
`applicant_phone`, `cover_letter` text nullable, `status` string index
(`submitted` / `screening` / `interview` / `offered` / `rejected` / `withdrawn`),
`status_changed_at` nullable, `reviewed_by` → `users.id` nullOnDelete,
`internal_notes` text nullable, `submitted_at`, timestamps, softDeletes.

**Unique (`job_vacancy_id`, `customer_id`)** (A4). A withdrawn application must be
reusable on reapply — reuse the row rather than relying on a partial index.

`internal_notes` is staff-only and must never reach an applicant-facing response.

**`job_application_documents`** — `id`, `job_application_id` cascadeOnDelete,
`type` string (`cv` / `portfolio` / `certificate` / `other`), `disk` string,
`path` string, `original_filename` string, `mime_type` string,
`size_bytes` unsigned integer, `checksum` string nullable, timestamps.

Store `disk` explicitly so a future migration to S3 can proceed file by file
without a guess about where older rows live.

---

## Step 3 — Models

```bash
php artisan make:model JobApplication --factory --no-interaction
php artisan make:model JobApplicationDocument --factory --no-interaction
```

`JobApplication` — status constants, `scopeActive()`, relations to vacancy,
customer, documents, reviewer. `JobApplicationDocument` — `belongsTo` application,
plus `streamResponse()` returning a download from its recorded disk.

Add `jobApplications()` to `Customer` (P1 deliberately left relations out).

---

## Step 4 — Validation (the trust boundary)

`app/Http/Requests/JobApplicationRequest.php` — all input validated here
(`guide.md` §3).

```php
'cover_letter' => ['nullable', 'string', 'max:5000'],
'documents'    => ['required', 'array', 'max:5'],
'documents.*'  => ['file', 'max:5120', 'mimes:pdf', 'mimetypes:application/pdf'],
```

**Both `mimes` and `mimetypes`.** `mimes` checks the extension; `mimetypes`
checks the sniffed content type. The legacy upload checked neither — that is
§3.1-5, the RCE. Checking one is not checking both.

Cap count and size. `authorize()` returns the policy decision: verified customer,
vacancy published and open, no active application.

If formats beyond PDF are wanted later, widen deliberately and re-test
`ApplicationDocumentSecurityTest`.

---

## Step 5 — `SubmitJobApplication`

`app/Actions/Careers/SubmitJobApplication.php`:

1. Re-check eligibility (vacancy open, no active application).
2. `DB::transaction()`:
   - create or reuse the application row
   - **snapshot** applicant name, email, phone from the customer
   - store each file on the `applications` disk with a generated name — **never
     the user-supplied filename on disk**; keep the original only in
     `original_filename` for display
   - record mime, size, checksum
3. Dispatch notifications **after commit**.

> **The filesystem is not transactional (M1).** If the transaction rolls back after
> `store()` — a later insert fails, a constraint trips, the request dies — the DB
> row is gone but **the file remains on the private disk with nothing pointing at
> it**. Orphaned CVs then accumulate silently: personal data retained with no
> record, which is precisely what D11's retention policy and P8's erasure path
> exist to prevent, and they cannot reach a file they do not know about.
>
> Two layers, both needed:
>
> 1. **Clean up on failure.** Collect the stored paths and delete them in a `catch`
>    (or `DB::afterRollback`) around the transaction. This handles the common case
>    deterministically.
> 2. **Sweep the stragglers.** A scheduled job deleting files on the `applications`
>    disk with no matching `job_application_documents` row and older than ~24 hours.
>    Layer 1 cannot catch a killed process; this does. It can piggyback P8's
>    retention schedule.
>
> The 24-hour floor matters: never delete a file younger than the longest plausible
> in-flight request, or the sweep will race a legitimate upload.
>
> **Alternative worth considering:** store files **after** the transaction commits,
> keeping only the metadata insert inside it. That removes the rollback case
> entirely, at the cost of a window where a row exists with no file — which is the
> easier failure to detect and repair, since the row tells you what is missing.

A generated storage name removes path traversal and extension-confusion in one
step.

---

## Step 6 — Document download

`app/Http/Controllers/ApplicationDocumentController.php`:

```php
public function show(JobApplicationDocument $document): StreamedResponse
{
    Gate::authorize('viewDocuments', $document->application);

    return Storage::disk($document->disk)->download(
        $document->path,
        $document->original_filename,
    );
}
```

- Route under the staff-authenticated area. **No signed public URL, no direct
  disk URL.** `MediaController` is the existing precedent for streaming through an
  authorization check.
- Force `Content-Disposition: attachment` so a PDF never renders inline in a
  context that could execute.
- Authorization is on the **application**, not the document, so one rule governs
  notes and files alike.

---

## Step 7 — Policy

`app/Policies/JobApplicationPolicy.php`:

| Ability | Rule |
|---|---|
| `view` | the owning customer, or staff |
| `viewDocuments` | **staff only** — never the applicant, never another customer |
| `viewNotes` | staff only |
| `updateStatus` | staff only |
| `withdraw` | the owning customer only |

Registered in `AppServiceProvider`. No role conditionals in controllers
(`guide.md` §10.3). Tiering across `editor` / `admin` / `superadmin` is refined in
P8 — get ownership right here.

---

## Step 8 — Public surface

| Controller | Route |
|---|---|
| `VacancyListController` | `GET /karir` |
| `VacancyDetailController` | `GET /karir/{vacancy:slug}` |
| `JobApplicationController` | `GET`/`POST` `/karir/{vacancy:slug}/lamar` |
| `MyApplicationsController` | `GET /akun/lamaran` |

Inside the `{locale?}` group. Applying requires `auth:customer` + verified.

**Legacy note:** `karir.php` currently 301s to the landing page via
`legacy_redirects`. Reintroducing a real careers page means revisiting that
mapping — point `karir.php` at `/karir` instead of the anchor, and verify
`LegacyRedirectTest` still passes.

Applicants see their own status (A3) but **never** `internal_notes`.

Cache the published vacancy list with its invalidating observer in the same change.

**Notifications** (queued): "application received" and "status changed".

---

## Step 9 — Filament

`JobApplicationResource`: list and filter by vacancy and status; view the
snapshot, cover letter, and documents; a status-change action stamping
`status_changed_at` and `reviewed_by`; authorized document download reusing the
Step 6 controller.

A deleted customer's application must still display fully — that is the
requirement, and it works because of the snapshot columns.

---

## Step 10 — Tests

`tests/Feature/Careers/`.

**`ApplicationSurvivesCustomerDeletionTest`** — the user's stated requirement.
Write it first.
- `test_application_remains_readable_after_customer_is_deleted`
- `test_documents_remain_downloadable_after_customer_is_deleted`
- `test_snapshot_identity_survives_deletion` — name/email/phone still present.

**`ApplicationDocumentSecurityTest`**
- `test_document_is_not_reachable_by_direct_url`
- `test_another_customer_cannot_download_a_document`
- `test_the_owning_applicant_cannot_download_via_the_staff_route`
- `test_staff_can_download`
- `test_php_file_renamed_to_pdf_is_rejected` — `mimetypes` must catch it.
- `test_oversized_file_is_rejected`
- `test_more_than_five_files_is_rejected`
- `test_stored_filename_is_generated_not_user_supplied`

**`JobApplicationTest`**
- `test_verified_customer_can_apply_with_a_pdf`
- `test_duplicate_active_application_is_rejected`
- `test_withdrawn_application_can_be_resubmitted`
- `test_closed_or_unpublished_vacancy_rejects_application`
- `test_unverified_customer_is_rejected`
- `test_internal_notes_never_appear_in_an_applicant_response`

---

## Validation

```bash
php artisan migrate
php artisan test --filter=Career
php artisan test --filter=LegacyRedirect     # karir.php mapping still correct
php artisan test
vendor/bin/pint --dirty --format agent
```

Manual: apply with a PDF; confirm the file is **not** reachable under `public/`;
download as staff; delete the customer and confirm the application and file remain.

---

## Acceptance

From `task.md` Phase P6:

- [ ] A verified customer applies with a PDF and the file is retrievable by staff only.
- [ ] Deleting the customer account leaves the application and documents intact —
      asserted by test. **This is the stated requirement.**
- [ ] No applicant document is reachable without an authorization check.
- [ ] Vacancy list renders with no N+1.

## Hand-off

P8 adds the retention purge (D11: 12 months after `job_vacancies.closes_at`,
env-configurable), erasure-on-request, and the PII access tiering. The `disk`
column and `closes_at` are what make both implementable.
