# Phase P7 — Progress Tracker

| | |
|---|---|
| **Depends on** | P0 only |
| **Blocks** | — |
| **Blocked by** | `[!]` A16 — only the optional CSV import |
| **Plan stability** | **Firm** — A1 confirmed, and the legacy dump was inspected directly |

## Objective

A customer enters a project code plus a verification value and sees that
project's progress. Decision **D2**, assumptions **A1**, **A6**, **A16**.

**Needs neither P1 nor P2.** It is the fastest route to something demonstrable and
can be pulled forward at any time.

## Why this is the most security-sensitive public surface

It is unauthenticated and it reads customer data. A bare `?id=` lookup would let
anyone walk `1, 2, 3…` and harvest every customer's project name and status. That
is what D2's second factor exists to stop.

---

## Step 0 — Confirmed facts

**A1 confirmed:** staff maintain project data in Filament. No external
project-management system to sync from.

**Verified against `../dexc8115_3dexperts.sql`:** there is **no project-tracking
table**. The legacy tables are `alur_section`, `features_section`, `hero_banner`,
`job_detail`, `layanan_section`, `location_section`, `mengapa_section`,
`properties`, `property_details`, `property_images`, `review_design`,
`testimonial_section`, `users`. The only `project` reference in the whole dump is
`features_section.project` — the homepage "projects completed" marketing counter.

So this starts from an empty table. The user's "maybe there will be some already
existing data" lives outside the legacy database. Manual entry is built first;
the importer waits for a real file (A16).

---

## Step 1 — Schema

```bash
php artisan make:migration create_projects_table --no-interaction
php artisan make:migration create_project_updates_table --no-interaction
```

**`projects`** — `id`, `project_code` string unique index,
`customer_name` string, `customer_phone` string nullable index,
`customer_email` string nullable index, `title` string,
`current_stage` string nullable, `status` string index
(`in_progress` / `on_hold` / `completed` / `cancelled`),
`started_at` date nullable, `estimated_completion_at` date nullable,
`completed_at` date nullable, `is_visible` boolean default true index,
timestamps, softDeletes.

Contact fields are **snapshots on the project**, not a `customers` relation —
this feature works without an account (A6), and the customer may never register.

**`project_updates`** — `id`, `project_id` cascadeOnDelete, `stage_name` string,
`status` string, `note` text nullable (**customer-visible**),
`occurred_at` date, `sort_order` integer, timestamps.
Index (`project_id`, `sort_order`).

`note` is shown to customers. There is deliberately **no** internal-notes column
here — if staff need private commentary, add an explicitly separate column and
exclude it from the payload, the way P6 handles `internal_notes`.

**Composite index on (`project_code`, `status`)** — the lookup filters both.

---

## Step 2 — Code generation

`project_code` must be **random and non-sequential**. Never derived from the
primary key.

- Format: a short readable prefix plus random characters, e.g. `3DX-7K2M9QX4`.
- Generate on create in the model's `creating` hook; retry on unique collision.
- Avoid ambiguous characters (`0`/`O`, `1`/`I`/`l`) — customers read these off a
  message and type them by hand.
- Long enough that guessing is useless even with rate limiting: 8+ random
  characters from a reduced alphabet.

This is the same discipline as P2's `order_id` and P5's `ticket_code`.

---

## Step 3 — The lookup (get this exactly right)

`app/Http/Requests/ProjectLookupRequest.php` — validate **shape only**:

```php
'project_code' => ['required', 'string', 'max:32'],
'verification' => ['required', 'string', 'max:191'],   // phone OR email
```

Never validate existence here. A validation error that says "code not found" is
the oracle this design exists to remove.

**`app/Support/ProjectLookup.php`** — one query, which is what makes the uniform
response natural rather than something to remember:

```php
public function find(string $code, string $verification): ?Project
{
    return Project::query()
        ->where('project_code', $code)
        ->where('is_visible', true)
        ->whereNot('status', Project::STATUS_COMPLETED)   // "only not yet done"
        ->where(function ($q) use ($verification) {
            $q->where('customer_phone', $verification)
              ->orWhere('customer_email', $verification);
        })
        ->with('updates')
        ->first();
}
```

**All three negative cases collapse into `null` by construction:**

| Case | Result |
|---|---|
| Wrong code | `null` |
| Right code, wrong verification | `null` |
| Right code and verification, project **completed** | `null` |

The controller then has exactly one negative branch, so there is no way to
accidentally render a different message for a completed project. The brief's
"only projects not yet done" is implemented as a **uniform negative**, not a
distinct "already finished" message — a distinguishable response would confirm
the code is valid.

**`ProjectLookupController`** — on `null`, return the same status code, the same
template, and the same wording every time. Review the rendered output for all
three cases and diff it.

Normalize the verification input before comparing (trim, lowercase email, strip
spaces and punctuation from phone numbers) or real customers will fail on
`0812-3456` versus `08123456`. Store phone numbers normalized too, so the
comparison is apples to apples.

---

## Step 4 — Rate limiting

Two named limiters, both applied:

| Limiter | Limit | Key |
|---|---|---|
| per IP | 10 / minute | client IP |
| per code | 5 / hour | submitted `project_code` |

The per-code limit is the one that matters: it stops someone trying many
verification values against one known code. The per-IP limit alone would let a
distributed attempt through.

Log repeated failures with the submitted code so staff can see a harvesting
attempt. **Do not log the verification value** — it is a phone number or email.

---

## Step 5 — Public surface

| Controller | Route |
|---|---|
| `ProjectLookupController@show` | `GET /lacak-proyek` |
| `ProjectLookupController@find` | `POST /lacak-proyek` |

Inside the `{locale?}` group. No auth. POST for the lookup — it carries personal
data that should not land in browser history, server logs, or a referrer header.

**Navbar:** the brief asks for a dedicated button. Add it to
`resources/views/partials/header.blade.php` in both locales.

> `header.blade.php` is **modified and uncommitted** in the working tree. Resolve
> that before editing (P0 Step 5 raises the same warning).

Result view: project title, `current_stage`, status, estimated completion, and the
ordered `project_updates` with their customer-visible notes. Nothing else from the
row — not the contact fields the customer just proved they know.

**Do not cache lookup results.** They are per-customer data keyed by a secret.

---

## Step 6 — Filament

`ProjectResource` — manual entry is the primary path (A1). `ProjectUpdate` as a
relation manager with drag ordering. Show `project_code` read-only with a copy
action; staff send it to the customer.

**`[!]` A16 — CSV import.** If pre-existing project data is supplied, get the
actual file and its real columns first, then add a Filament CSV importer matched
to that shape. **Do not build an importer against a guessed format** — it will be
wrong and will need rewriting against the real file anyway.

---

## Step 7 — Tests

`tests/Feature/ProgressTracker/`.

**`ProgressTrackerTest`**
- `test_valid_code_and_matching_phone_returns_progress`
- `test_valid_code_and_matching_email_returns_progress`
- `test_updates_render_in_order`

**`ProgressTrackerNegativeResponseTest`** — the security contract:
- `test_wrong_code_and_bad_verification_and_completed_project_are_identical` —
  capture all three response bodies and assert they are **equal to each other**.
  Not "all return 200" — byte-identical.
- `test_invisible_project_is_not_found`
- `test_cancelled_project_behaviour_is_decided_and_tested` — `whereNot(completed)`
  currently lets `cancelled` through. Decide whether a cancelled project is
  visible to the customer; if not, add it to the exclusion and test it. **Flag to
  the user if unsure — this is a product decision, not a technical one.**

**`ProgressTrackerRateLimitTest`**
- `test_per_ip_limit_returns_429`
- `test_per_code_limit_returns_429_across_different_ips`

**`ProjectCodeTest`**
- `test_code_is_not_derived_from_the_primary_key`
- `test_code_is_unique_across_many_projects`
- `test_code_avoids_ambiguous_characters`

---

## Validation

```bash
php artisan migrate
php artisan test --filter=ProgressTracker
php artisan test --filter=ProjectCode
php artisan route:list --path=lacak
php artisan test
vendor/bin/pint --dirty --format agent
```

Manual: create a project, look it up with the right and wrong verification, mark
it completed and confirm the response is indistinguishable from a wrong code.

---

## Acceptance

From `task.md` Phase P7:

- [ ] A valid code plus matching verification returns stages and status.
- [ ] The three negative cases are indistinguishable from each other.
- [ ] Scripted code guessing yields no information and is throttled.
- [ ] Staff can create a project and publish an update the customer view reflects.

## Open question for the user

Should a **cancelled** project be visible to the customer, or treated like a
completed one and hidden? The brief only said "only the project that not done
yet", which does not settle it. Ask before guessing.
