# Phase P5 — Events: Free & Paid Enrollment

| | |
|---|---|
| **Depends on** | **Free path: P1 only.** Paid path: P1 + P2 |
| **Blocks** | — |
| **Blocked by** | Free path: nothing once P1 passes. Paid path: P2 |
| **Plan stability** | **Expected revision** — confirm real Midtrans expiry behaviour from P2 before trusting Step 4 |

## Objective

Let verified customers enroll in events, free or paid. The launchable scope is
**P5a, verified free enrollment**; paid enrollment is **P5b**, blocked by P2.
Decision **D4** (both),
**D10** (the expiry trap), assumptions **A2** (capacity, no waitlist),
**A8**/**A17** (bounded holds).

Independent of P3/P4 — may be built in parallel with them.

## Delivery status

- **P5a — implemented and targeted-tested.** Free enrollment, event listing,
  customer registrations, Filament management, queue confirmation, safe cache,
  and nightly seat reconciliation are in place.
- **P5b — not started; blocked by P2.** Paid events stay visible but direct
  enrollment is rejected without claiming a seat (K8).
- P5a uses structured `Log::alert` for corrected counter drift. P8 owns durable
  audit rows and staff alerts (K10).
- P5a caches plain event-list attributes only; capacity and `seats_taken` are read
  live. O5 still owns cache measurement and stampede/load hardening (K9).
- Sequential tests validate atomic-update logic; they do not prove real
  parallel-load behavior. That remains O6/P9 work.

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
`ticket_code` string unique, `registered_at`, `confirmed_at` nullable, timestamps.
P5b adds a nullable payment association after P2's `payments` table exists.

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

**`EventRegistration`** — status constants, `scopeConfirmed()`. The P5a free path
does not implement `App\Contracts\Payable`; P5b adds the payment contract after
P2 Step 4:

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
3. **Free event (P5a)** → `status = confirmed` immediately, no checkout.
4. **Paid event (P5b only)** → `status = pending`, call P2's `CreatePayment`,
   return the Snap token. The **webhook** confirms it, never the browser. Until
   then the action rejects paid events before any seat claim (K8).
5. Dispatch free-registration notifications **after commit** (`guide.md` §3) — never inside the
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
- On mismatch: correct it, **record both values in the operational alert**, and
  alert. P5a uses structured `Log::alert`; durable audit and staff notifications
  are added in P8 (K10).
  A drift is evidence of a bug in one of the release paths — fixing the number
  without recording it hides the cause.
- Run it against upcoming events only; past events do not matter.
- Idempotent: a second run on a correct counter changes nothing.

---

## Step 4 — The expiry trap (`notes.md` §3.4, P5b only)

This step is deferred until P2 provides the payment contract and persisted
`expires_at`. P5a creates no paid pending holds.

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

**Notifications** (queued): P5a registration confirmation with the ticket code.
The paid-event receipt is P5b work.

P5a caches plain published-event listing attributes per locale and invalidates on
event save/delete/restore. `capacity` and `seats_taken` stay live. O5 verifies
stampede protection, measures hit rates, and audits all other caches.

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
- `test_a_verified_customer_can_enroll_in_a_free_event`
- `test_a_duplicate_enrollment_is_rejected_and_does_not_take_a_second_seat`
- `test_enrollment_outside_the_registration_window_is_rejected`
- `test_an_unpublished_event_detail_page_is_not_found`
- `test_an_unpublished_event_cannot_be_enrolled_in_directly`
- `test_an_unverified_customer_cannot_enroll`
- `test_a_full_event_rejects_enrollment`
- `test_an_event_with_unlimited_capacity_never_rejects`
- `test_paid_events_remain_visible_but_cannot_be_enrolled_until_p5b`
- `test_event_list_cache_excludes_seat_availability_and_reads_it_live`
- `test_saving_an_event_invalidates_the_cached_public_listing`
- `test_deleting_and_restoring_an_event_invalidates_both_locale_listings`
- `test_event_listing_query_count_does_not_grow_with_the_number_of_events`
- `test_the_confirmation_notification_is_queued_and_sent`

**P5a capacity tests** — the honest version:
- `test_the_last_seat_goes_to_exactly_one_person` — capacity 1, two sequential
  attempts, exactly one succeeds.
- `test_seats_taken_never_exceeds_capacity_across_many_claims`
- `test_the_claim_affects_no_rows_once_the_event_is_full`
- `test_cancelling_returns_the_seat`
- `test_cancelling_twice_releases_only_one_seat`
- `test_a_cancelled_registration_can_be_reused_on_re_enrollment`
- Expiry/release-job tests belong to P5b and must not be counted as P5a coverage.

> **Be honest about what this proves.** PHPUnit runs sequentially, so these tests
> verify the *logic* is atomic — that a conditional update affects zero rows when
> full. They do **not** prove behaviour under genuine parallel load. Real
> concurrency verification belongs to O6/P9's load test, and the acceptance
> criterion is only met once that runs. Do not report concurrency as proven on the
> strength of these tests alone.

**P5b `PaidEventHoldTest`**
- `test_paid_enrollment_holds_a_seat_until_payment_expiry`
- `test_hold_window_comes_from_the_payment_record_not_a_constant`
- `test_bank_transfer_does_not_get_a_24_hour_hold_on_an_event` — the D10
  regression guard.
- `test_settlement_after_release_does_not_double_book`

**Genuine parallel enrollment tests** remain outstanding for O6/P9. Sequential
tests do not prove simultaneous duplicate submissions or final-seat contention.

**P5a `SeatReconciliationTest`** (M3)
- `test_drifted_counter_is_corrected_and_alerted_with_both_values`
- `test_a_correct_counter_is_left_unchanged_without_an_alert`
- `test_past_events_are_not_reconciled`

---

## Validation

```bash
php artisan migrate
php artisan test --filter=Event
php artisan schedule:list
php artisan test
vendor/bin/pint --dirty --format agent
```

P5a manual validation: free enrollment end to end with a queue worker.
P5b manual validation, after P2: paid enrollment via sandbox bank transfer,
confirming the hold expires on the **short** window, not 24 hours.

### P5a validation record

| Check | Result |
|---|---|
| Customer, event, and public-page regressions | 115 tests, 340 assertions; no failures. Four homepage output-buffer tests are reported as risky. |
| Full `php artisan test` | 176 passed (460 assertions), 8 output-buffer risky tests, no failures. |
| Changed PHP formatting | `vendor/bin/pint --test` passed on the changed event/auth PHP files. |
| Event-list query bound | Two event-table queries on cache miss; one live availability query on cache hit, asserted. |
| Event routes | `php artisan route:list --path=acara` lists all 8 Indonesian/English event, enrollment, and customer-events routes. |
| Reconciliation schedule | `php artisan schedule:list` shows `ReconcileEventSeatCounts` daily at midnight. |
| Forward migrations | All migrations pass on isolated SQLite memory database. |
| Migration rollback | Blocked by the pre-existing role-column/index rollback defect; see K11 in `keputusan.md`. |
| Queue delivery with a running worker | Not manually exercised in this environment; automated notification-dispatch tests pass. |

---

## Acceptance

From `task.md` Phase P5:

- [x] A verified customer enrolls in a free event and receives a queued confirmation (P5a).
- [ ] Capacity is never exceeded under genuine concurrent load — sequential
      atomic-claim tests cover logic; load is verified in O6/P9.
- [ ] An unpaid paid-event hold expires and the seat returns to inventory (P5b).
- [x] Event list renders without N+1 growth; availability remains live (P5a).
