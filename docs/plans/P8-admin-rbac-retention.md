# Phase P8 — Admin Consolidation, RBAC & Retention

| | |
|---|---|
| **Depends on** | P1–P7 |
| **Blocks** | P9 |
| **Blocked by** | `[ ]` D11 — confirm 12 months vs the 6 the user first suggested |
| **Plan stability** | **Expected revision** — the resource inventory depends on what P1–P7 actually built |

## Objective

Each preceding phase shipped its own Filament resources. This phase makes the
admin surface coherent: one deliberate authorization tier per resource, audit
logging on everything that matters, and a retention policy that actually runs.

**The PII surface grew a lot.** Course purchases, event attendees, CVs, and
project contacts all landed in the last seven phases. This is where that gets
governed rather than assumed.

---

## Step 1 — Resource inventory and tiering

Existing tiers in `User`: `superadmin` → `admin` → `editor`, with
`canManageContent()`, `canManageUsers()` (superadmin only), and
`canManageSettings()` (superadmin + admin).

Go through **every** resource and decide read / write / export per tier. Record
the decision here as a table, so P9 can verify against it:

| Resource | editor | admin | superadmin |
|---|---|---|---|
| Portfolios, Services, Testimonials, FAQs, Hero, Value Props, Design Reviews | read/write | read/write | read/write |
| Courses, Sections, Lessons, Quizzes | read/write | read/write | read/write |
| Events | read/write | read/write | read/write |
| Job Vacancies | read/write | read/write | read/write |
| Projects, Project Updates | read/write | read/write | read/write |
| **Course Enrollments** | read | read | read |
| **Event Registrations** | read + attendance | read/write | read/write |
| **Job Applications** (incl. documents + notes) | **none** | read/write | read/write |
| **Payments** | **none** | read | read |
| **Customers** | **none** | read + deactivate | read + deactivate |
| Site Settings | none | read/write | read/write |
| Users | none | none | read/write |
| **Audit log** | none | read | read |
| **Exports** | none | yes, logged | yes, logged |

The rows in bold are the PII and money surfaces. An `editor` manages content; they
have no business reading a CV or a payment record. Adjust with the user if their
org works differently — but make it an explicit decision, not a default.

Implement with Policies, registered in `AppServiceProvider`, plus Filament's
`canViewAny` / `canCreate` / `canDelete` delegating to them. **No role
conditionals in controllers or resources** (`guide.md` §10.3).

---

## Step 2 — Customers resource

`CustomerResource`: list, filter, view. Actions limited to **deactivate /
reactivate** (`is_active`) and soft delete.

**Never editable:** password, email. A staff member must not be able to take over
a customer account by changing its email and triggering a reset — that is a
privilege-escalation path through the admin panel rather than the login form.

Show related enrollments, registrations, and applications read-only, each
respecting the Step 1 tier. An `editor` viewing a customer must not see their
applications.

---

## Step 3 — Audit log

```bash
php artisan make:migration create_audit_logs_table --no-interaction
php artisan make:model AuditLog --no-interaction
```

`audit_logs` — `id`, `user_id` nullOnDelete (staff actor),
`customer_id` nullOnDelete (when the subject is a customer),
`action` string index, `auditable_type` / `auditable_id` morphs nullable,
`context` json nullable, `ip_address` string nullable,
`created_at` only (append-only; no `updated_at`, no soft delete).

`app/Support/Audit.php` — one entry point: `Audit::record($action, $subject, $context)`.

**Log these** (`guide.md` §7):

- publish / unpublish of any content
- course enrollment granted or revoked
- payment state transition (P2 already calls this — verify it does)
- event registration confirmed, cancelled, marked attended
- job application status change
- **any access to an applicant document** — who downloaded whose CV, and when
- role change, user create/delete
- customer deactivation
- retention purge and erasure request
- failed authorization attempts
- every export

Never log secrets: no passwords, no tokens, no Midtrans keys, and no P7
verification values.

Audit writes must not fail the action they describe. Log-and-continue on write
failure rather than aborting a payment because the audit row did not insert.

---

## Step 4 — Retention (D11)

**Decision:** 12 months after `job_vacancies.closes_at`, env-configurable. The
user first suggested 6; `notes.md` §3.5 records why 12 is recommended. **Confirm
before shipping** — either way it is one `.env` value.

```
APPLICATION_DOCUMENT_RETENTION_MONTHS=12
```

Read it through `config/`, never `env()` outside config (config caching would
freeze a stale value).

**`app/Jobs/PurgeExpiredApplicationDocuments.php`** — scheduled daily in
`routes/console.php`:

1. Find applications whose vacancy's `closes_at` is older than the retention
   window. **Key on vacancy closure, not application date** — a late applicant to
   an old vacancy should not get a longer retention than an early one.
2. Delete the files from their recorded `disk` (P6 stores `disk` per row precisely
   so this works after a storage migration).
3. Delete the `job_application_documents` rows.
4. **Keep the application record** — status history and the hiring decision are
   business records; the CV is the personal data. Deleting the document satisfies
   the policy without destroying the audit trail.
5. Audit-log each purge.
6. Idempotent: a second run finds nothing and does nothing.

Dry-run flag first. Deleting the wrong window is unrecoverable.

**Erasure on request** — `app/Actions/Privacy/EraseApplicantData.php`:

- Honoured **at any time**, independent of the retention window. This is a right
  under UU PDP No. 27/2022, not a feature toggle.
- Deletes documents, clears the snapshot contact fields, keeps the anonymized
  application row.
- Audit-logged with who requested and who actioned it.
- Give staff a Filament action for it. A right nobody can exercise through the UI
  is not implemented.

---

## Step 5 — Refunds (A14)

Manual, staff-initiated. No automated refund API call in this phase.

`app/Actions/Payments/RefundPayment.php`:

1. Authorize: `admin` or `superadmin` only.
2. Set the payment status to `refunded`.
3. Call `revokePayment()` on the payable — the P2 contract already defines this,
   and P3/P5 already implement it. **Do not write separate revoke logic here.**
4. Audit-log with the actor and a required reason.

Effects: a course enrollment becomes `revoked` and the P3 gate stops unlocking
lessons; an event registration cancels and releases its seat through P5's
existing release path.

The actual money movement happens in the Midtrans dashboard. This records and
enforces the consequence.

---

## Step 6 — Exports

Enrollments, registrations, applications. Gated by the Step 1 tier,
**audit-logged every time**, and queued if large.

Exports are the quietest PII leak in any admin panel: one click produces a
spreadsheet that leaves every access control behind. Logging who exported what is
the only control that survives the file.

Never include `internal_notes` or document contents in an export.

---

## Step 7 — Tests

`tests/Feature/Admin/`.

**`AdminAuthorizationTest`** — one case per bold row in Step 1:
- `test_editor_cannot_view_job_applications`
- `test_editor_cannot_download_an_applicant_document`
- `test_editor_cannot_view_payments`
- `test_editor_cannot_view_customers`
- `test_admin_can_view_but_not_edit_payments`
- `test_only_superadmin_can_manage_users`
- `test_editor_cannot_change_site_settings`

**`CustomerResourceTest`**
- `test_staff_cannot_change_a_customer_email_or_password`
- `test_deactivated_customer_cannot_log_in`

**`RefundRevokesEntitlementTest`**
- `test_refund_revokes_course_access` — re-assert the P3 gate locks after refund.
- `test_refund_cancels_event_registration_and_releases_the_seat`
- `test_editor_cannot_issue_a_refund`

**`RetentionPurgeTest`**
- `test_documents_older_than_the_window_are_deleted`
- `test_documents_inside_the_window_are_kept`
- `test_window_is_measured_from_vacancy_closure_not_application_date`
- `test_application_record_survives_the_purge`
- `test_purge_is_idempotent`
- `test_purge_is_audit_logged`

**`ErasureRequestTest`**
- `test_erasure_works_before_the_retention_window_expires`
- `test_erasure_removes_documents_and_contact_fields`
- `test_erasure_is_audit_logged`

**`AuditLogTest`**
- `test_document_download_is_logged_with_actor_and_subject`
- `test_audit_write_failure_does_not_abort_the_underlying_action`
- `test_no_secret_values_are_written_to_the_audit_log`

---

## Validation

```bash
php artisan migrate
php artisan test --filter=Admin
php artisan test --filter=Retention
php artisan test --filter=Refund
php artisan schedule:list
php artisan test
vendor/bin/pint --dirty --format agent
```

Manual: log in as an `editor` and confirm the bold rows in Step 1 are genuinely
unreachable — not merely hidden from the navigation. A hidden resource whose URL
still responds is not access control.

---

## Acceptance

From `task.md` Phase P8:

- [ ] Every new resource has an explicit, tested authorization tier.
- [ ] PII access is logged.
- [ ] A refund revokes course access.
- [ ] The retention purge runs on schedule and removes only what D11 permits.

Plus: erasure-on-request works independently of the retention window.
