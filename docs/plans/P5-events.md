# Phase P5 — Events: Free & Paid Enrollment

| | |
|---|---|
| **Depends on** | **Free path: P1 only.** Paid path: P1 + P2 |
| **Blocks** | — |
| **Blocked by** | Free path: nothing once P1 passes. Paid path: P2 |
| **Plan stability** | **Expected revision** — confirm real Midtrans expiry behaviour from P2 before trusting Step 4 |

## Objective

Let verified customers enroll in events, free or paid. Decisions **D4** (both),
**D10** (the expiry trap), assumptions **A2** (capacity, no waitlist),
**A8**/**A17** (bounded holds).

Independent of P3/P4 — may be built in parallel with them.

---

## Step 1 — Schema

```bash
php artisan make:migration create_events_table --no-interaction
php artisan make:migration create_event_registrations_table --no-interaction
```

**`events`** — `id`, `title` json (translatable), `slug` unique,
`description` json nullable, `cover_media_id` nullable,
`starts_at` / `ends_at` timestamps index, `location_type` string
(`online` / `offline` / `hybrid`), `venue` string nullable,
`online_url` string nullable, `capacity` unsigned integer **nullable**
(null = unlimited), `seats_taken` unsigned integer default 0,
`price` decimal(15,2) default 0, `registration_opens_at` /
`registration_closes_at` timestamps nullable,
`is_published` boolean default false index, timestamps, softDeletes.

**`seats_taken` is a denormalized counter and that is deliberate** — see Step 3.

**`event_registrations`** — `id`, `event_id` cascadeOnDelete,
`customer_id` nullOnDelete, snapshot `attendee_name` / `attendee_email` /
`attendee_phone`, `status` string index
(`pending` / `confirmed` / `cancelled` / `attended`),
`ticket_code` string unique, `payment_id` nullable, `registered_at`,
`confirmed_at` nullable, timestamps.

**Unique (`event_id`, `customer_id`)** — a cancelled row must be reusable on
retry, so either reuse the existing row or include status in the index strategy.
Decide explicitly and write the choice here; a partial unique index is not
portable across MySQL versions, so prefer reuse.

`ticket_code` is random and non-sequential, like P2's `order_id` and P7's
`project_code`. Never derived from the primary key.

---

## Step 2 — Models

```bash
php artisan make:model Event --factory --no-interaction
php artisan make:model EventRegistration --factory --no-interaction
```

**`Event`** — `HasTranslations`, `SoftDeletes`. Scopes `scopePublished()`,
`scopeUpcoming()`, `scopeOrdered()` matching the existing convention. Helpers
`isFree()`, `hasCapacity()`, `registrationIsOpen()`.

**`EventRegistration`** — status constants, `scopeConfirmed()`,
**implements `App\Contracts\Payable`** (P2 Step 4):

- `payableAmount()` → the event price
- `payableExpiryProfile()` → **`'event'`** — the short window. This is the single
  most important line in this phase; see Step 4.
- `fulfilPayment()` → `status = confirmed`, stamp `confirmed_at`. Idempotent.
- `revokePayment()` → cancel and **release the seat**.

---

## Step 3 — `EnrollInEvent` (the concurrency-critical part)

`app/Actions/Events/EnrollInEvent.php`.

**Capacity is a concurrency problem, not a validation problem.** This:

```php
if ($event->registrations()->count() < $event->capacity) { /* claim */ }
```

oversells. Two requests both read 49 of 50 and both claim seat 50. At the stated
500-concurrent-user target this is not hypothetical.

**Use a conditional atomic update** — one statement, the database arbitrates:

```php
$claimed = Event::query()
    ->whereKey($event->id)
    ->where(function ($q) {
        $q->whereNull('capacity')
          ->orWhereColumn('seats_taken', '<', 'capacity');
    })
    ->increment('seats_taken');

if ($claimed === 0) {
    throw new EventFullException();
}
```

`$claimed` is the affected row count: `1` means the seat is yours, `0` means it
was taken between your read and your write. No lock held across application code,
and it degrades well under load.

(`lockForUpdate()` on the event row is the alternative and also correct, but it
serializes every enrollment attempt on a popular event. Prefer the conditional
update; if you switch, record why here.)

**Full flow:**

1. Authorize: published, registration window open (A2 — reject when full, no
   waitlist), customer verified (A5), no existing active registration.
2. `DB::transaction()`: claim the seat atomically, create the registration,
   snapshot attendee identity, generate `ticket_code`.
3. **Free event** → `status = confirmed` immediately, no checkout.
4. **Paid event** → `status = pending`, call P2's `CreatePayment`, return the
   Snap token. The **webhook** confirms it, never the browser.
5. Dispatch notifications **after commit** (`guide.md` §3) — never inside the
   transaction.

**Seat release** must decrement `seats_taken` on cancellation, expiry, and
refund. Every path that abandons a `pending` registration releases its seat, or
the event silently sells out at less than capacity.

**Catch the unique-index violation (M2).** Step 1 of the flow pre-checks for an
existing registration, but the check and the insert are not atomic. Two concurrent
requests from the same customer both pass, and the loser hits the
`(event_id, customer_id)` unique index and throws
`UniqueConstraintViolationException`. Catch it and return the same idempotent
"already registered" response. **Release the seat you just claimed in that path** —
otherwise a double-click permanently consumes two seats for one attendee.

**Reconcile the counter nightly (M3).** `seats_taken` is denormalized and is
incremented and decremented from several places: enrollment, cancellation, hold
expiry, admin cancel, and refund. A single missed decrement drifts it permanently —
and the failure is silent and one-directional, so the event sells out early and
nobody knows why.

`app/Jobs/ReconcileEventSeatCounts.php`, scheduled nightly:

- Compare `seats_taken` against the live count of non-cancelled registrations.
- On mismatch: correct it, **audit-log the correction with both values**, and alert.
  A drift is evidence of a bug in one of the release paths — fixing the number
  without recording it hides the cause.
- Run it against upcoming events only; past events do not matter.
- Idempotent: a second run on a correct counter changes nothing.

---

## Step 4 — The expiry trap (`notes.md` §3.4)

Payment methods expire at wildly different times: QRIS and e-wallets in roughly
15–30 minutes, bank-transfer VA in about **24 hours**.

Left at defaults, a buyer choosing bank transfer holds a capacity-limited seat for
a full day while people who would have paid immediately are turned away. On a
sold-out workshop that is direct revenue loss, and it is invisible until it
happens.

**Implementation:**

- `payableExpiryProfile()` returns `'event'`, which P2's config maps to a short
  uniform window (recommend 1 hour) applied to **every** method.
- The hold deadline is read from `payments.expires_at` — **never** a constant in
  this phase. P2 persists it precisely so this phase does not guess.
- Courses keep the 24-hour window because they have unlimited capacity and a long
  hold costs nobody anything. Same mechanism, different profile.

**`app/Jobs/ReleaseExpiredEventHolds.php`** — scheduled (every few minutes in
`routes/console.php`):

- Finds `pending` registrations whose payment `expires_at` has passed.
- Cancels them and decrements `seats_taken`, in a transaction.
- Idempotent: a second run over the same row changes nothing.
- A late settlement webhook arriving **after** release must not silently
  double-book. Decide the behaviour — reject and flag for staff refund is the
  safer default — and test it.

---

## Step 5 — Public surface

**Controllers** `app/Http/Controllers/Event/`:

| Controller | Route |
|---|---|
| `EventListController` | `GET /acara` |
| `EventDetailController` | `GET /acara/{event:slug}` |
| `EnrollmentController` | `POST /acara/{event:slug}/daftar` |
| `MyEventsController` | `GET /akun/acara` |

Inside the `{locale?}` group. Enrollment behind `auth:customer` + verified.
"My events" mounts into the P1 account shell.

**Contact CTA (D12):** any "ask about this event" link points at the
`company_whatsapp` setting via the `Settings` accessor. **No contact form, no
enquiry box** — `guide.md` §10.9.

**Caching** — published event list cached with its invalidating observer in the
same change (`guide.md` §5 rule 1). **Do not cache `seats_taken`** or any
remaining-capacity number: a stale count either oversells or hides open seats.
Cache the listing content, read availability live.

**Notifications** (queued): registration confirmation with the ticket code, and
payment receipt for paid events.

---

## Step 6 — Filament

`EventResource` with the registration window, capacity, and price. Show
`seats_taken` read-only — it is maintained by the atomic claim, and a staff edit
would corrupt inventory.

`EventRegistrationResource` with an **attendance** action (`confirmed` →
`attended`) and a cancel action that releases the seat through the same code path
as the job, not a duplicate.

---

## Step 7 — Tests

`tests/Feature/Events/`.

**`EventEnrollmentTest`**
- `test_verified_customer_enrolls_in_a_free_event`
- `test_duplicate_enrollment_is_rejected`
- `test_enrollment_outside_the_window_is_rejected`
- `test_unpublished_event_cannot_be_enrolled`
- `test_unverified_customer_is_rejected`
- `test_full_event_is_rejected_with_no_waitlist`
- `test_unlimited_capacity_event_never_rejects`

**`EventCapacityTest`** — the honest version:
- `test_claim_fails_when_capacity_is_reached` — capacity 1, two sequential
  attempts, exactly one succeeds.
- `test_seats_taken_never_exceeds_capacity_across_many_claims`
- `test_cancellation_releases_a_seat`
- `test_expired_hold_releases_a_seat`
- `test_release_job_is_idempotent`

> **Be honest about what this proves.** PHPUnit runs sequentially, so these tests
> verify the *logic* is atomic — that a conditional update affects zero rows when
> full. They do **not** prove behaviour under genuine parallel load. Real
> concurrency verification belongs to P9's load test, and the acceptance criterion
> is only met once that runs. Do not report concurrency as proven on the strength
> of these tests alone.

**`PaidEventHoldTest`**
- `test_paid_enrollment_holds_a_seat_until_payment_expiry`
- `test_hold_window_comes_from_the_payment_record_not_a_constant`
- `test_bank_transfer_does_not_get_a_24_hour_hold_on_an_event` — the D10
  regression guard.
- `test_settlement_after_release_does_not_double_book`

**`ConcurrentEnrollmentTest`** (M2)
- `test_duplicate_concurrent_enrollment_returns_idempotent_response_not_500`
- `test_duplicate_concurrent_enrollment_does_not_leak_a_seat` — assert
  `seats_taken` incremented exactly once. A double-click must not permanently
  consume two seats for one attendee.

**`SeatReconciliationTest`** (M3)
- `test_drifted_counter_is_corrected`
- `test_correction_is_audit_logged_with_both_values`
- `test_reconciliation_is_a_noop_when_the_counter_is_correct`

---

## Validation

```bash
php artisan migrate
php artisan test --filter=Event
php artisan schedule:list
php artisan test
vendor/bin/pint --dirty --format agent
```

Manual, queue worker running: free enrollment end to end; paid enrollment via
sandbox bank transfer, confirming the hold expires on the **short** window, not 24
hours.

---

## Acceptance

From `task.md` Phase P5:

- [ ] A verified customer enrolls in a free event and receives a queued confirmation.
- [ ] Capacity is never exceeded under concurrent load — **logic asserted here,
      load verified in P9.**
- [ ] An unpaid hold expires and the seat returns to inventory.
- [ ] Event list renders with no N+1.
