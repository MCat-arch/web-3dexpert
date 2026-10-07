# Phase P2 — Payment Foundation (Midtrans)

| | |
|---|---|
| **Depends on** | P0 (queue), P1 (a buyer must exist) |
| **Blocks** | P3, P5 |
| **Blocked by** | `[!]` Midtrans sandbox + production credentials |
| **Plan stability** | **Expected revision** — Midtrans response shapes should be confirmed against the live sandbox, not trusted from this document |

## Objective

One payment pipeline, built and proven before anything sells. Decisions **D9**
(polymorphic, own phase) and **D10** (methods, and the expiry trap) in `notes.md`.

Fulfilment is driven by the **verified webhook only**. A browser returning from
checkout proves nothing — `notes.md` risk 4.

---

## Step 1 — HTTP client decision

Midtrans publishes `midtrans/midtrans-php`. Using it is a dependency change and
`CLAUDE.md` requires approval.

**Recommendation: skip the SDK.** Snap is two REST calls (create transaction,
receive notification) and Laravel's `Http` client covers both with less
indirection and nothing to pin. Build `app/Support/Midtrans/SnapClient.php` as a
thin wrapper.

If the SDK is preferred, get approval first and note it in `notes.md`.

---

## Step 1b — `[!]` Decide the checkout delivery mode before writing any of it

**The current CSP will block Snap.** `SecurityHeaders.php:38` sets
`script-src 'self'` with no nonce and no hash, and the comment at line 20 records
that the absence of `unsafe-inline` / `unsafe-eval` is deliberate. Snap loads its
script from a Midtrans host and commonly injects inline script, so the checkout
button will silently fail to appear.

Decide now, not during the first sandbox transaction — discovering it then invites a
panicked site-wide `unsafe-inline`:

| Option | Trade-off |
|---|---|
| **A — Redirect / deeplink** | Send the buyer to Midtrans's hosted payment page. Needs only the Midtrans host in `form-action`. **CSP stays strict.** Costs the on-site checkout feel |
| **B — Snap with a scoped exception** | `script-src 'self' https://app.midtrans.com` plus `frame-src`, **limited to the checkout route**, justified in a comment. Keeps the embedded UX. If Snap truly needs inline script, use a per-request **nonce**, never a blanket `unsafe-inline` |

**Recommended: A.** The launch profile has no course purchases and D19 recommends a
free launch event, so payments sit off the critical path — this can be decided on
its merits rather than under launch pressure. Revisit for B later if the embedded
experience proves worth the CSP work.

- [ ] Decide, record in `notes.md`, and reflect it in P9 Step 3b.
- [ ] Never widen the CSP site-wide. A global `unsafe-inline` would strip XSS
      protection from every page including the Filament admin.
- [ ] Never add `unsafe-eval`.

## Step 2 — Configuration

Create `config/midtrans.php`:

```php
return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'snap_url' => env('MIDTRANS_IS_PRODUCTION', false)
        ? 'https://app.midtrans.com/snap/v1/transactions'
        : 'https://app.sandbox.midtrans.com/snap/v1/transactions',

    // D10. Config-driven so the list changes without code.
    'enabled_payments' => [
        'qris',
        'bca_va', 'bni_va', 'bri_va', 'permata_va', 'echannel',  // echannel = Mandiri Bill
        'gopay', 'shopeepay',
    ],

    // D10 expiry trap: per payable type, NOT one global value.
    'expiry' => [
        'event'  => ['unit' => 'minute', 'duration' => 60],
        'course' => ['unit' => 'hour',   'duration' => 24],
    ],
];
```

Add every `MIDTRANS_*` key to `.env.example` with empty values. **Never commit
keys.** Verify current per-method fee rates with Midtrans before launch; do not
hardcode rate assumptions anywhere in the codebase.

---

## Step 3 — Schema

```bash
php artisan make:migration create_payments_table --no-interaction
```

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `payable_type` / `payable_id` | morphs | polymorphic from day one (D9) |
| `order_id` | string, unique | the reference sent to Midtrans |
| `gross_amount` | decimal(15,2) | never float (`guide.md` §4) |
| `status` | string, index | `pending` `settlement` `expired` `cancelled` `failed` `refunded` |
| `payment_type` | string, nullable | what the buyer actually chose |
| `midtrans_transaction_id` | string, nullable, index | |
| `raw_response` | json, nullable | last payload received, for support |
| `expires_at` | timestamp, nullable | **consumers derive holds from this** |
| `paid_at` | timestamp, nullable | |
| `timestamps` | | |

Composite index on (`payable_type`, `payable_id`, `status`).

`order_id` must be unique and non-guessable — prefix plus random, e.g.
`CRS-<random>` / `EVT-<random>`. Never the primary key.

---

## Step 4 — The `Payable` contract

`app/Contracts/Payable.php`. This is the seam that lets P3 and P5 plug in without
touching payment code:

```php
interface Payable
{
    public function payableAmount(): string;        // decimal string
    public function payableDescription(): string;
    public function payableExpiryProfile(): string; // 'event' | 'course' → config key
    public function fulfilPayment(Payment $payment): void;  // grant entitlement
    public function revokePayment(Payment $payment): void;  // refund / expiry
}
```

`fulfilPayment()` is called **only** from the queued job, inside a transaction.
It must be safe to call twice — the job guarantees ordering, the implementation
guarantees idempotence.

---

## Step 5 — `Payment` model

`app/Models/Payment.php`: `morphTo` payable; status constants; `casts()` for
`gross_amount` → `decimal:2`, `raw_response` → `array`, `expires_at` / `paid_at`
→ `datetime`; scopes `scopePending()`, `scopeSettled()`; helper
`isSettled(): bool`.

---

## Step 6 — `SnapClient`

`app/Support/Midtrans/SnapClient.php`:

- `createTransaction(Payment $payment, Customer $customer): string` — returns the
  Snap token. POSTs to the configured Snap URL with HTTP Basic auth (server key as
  username, empty password), sending `transaction_details`
  (`order_id`, `gross_amount`), `enabled_payments` from config, `customer_details`,
  `item_details`, and `expiry` resolved from the payable's expiry profile.
- `verifySignature(array $payload): bool` —
  `hash('sha512', $order_id . $status_code . $gross_amount . $server_key)`
  compared to `signature_key` using `hash_equals()`. **Timing-safe comparison, not
  `===`.**

Set an explicit HTTP timeout and retry. A hung Midtrans call must not hold a web
request (`guide.md` §5 rule 3).

---

## Step 7 — `CreatePayment` action

`app/Actions/Payments/CreatePayment.php` (create `app/Actions/` now — `guide.md`
§2 sanctions it on first genuine need):

1. Generate `order_id`.
2. Create the `payments` row as `pending`, with `expires_at` computed from the
   payable's expiry profile.
3. Call `SnapClient::createTransaction()`.
4. Return the Snap token to the controller.

Wrap steps 1–2 in `DB::transaction()`. Step 3 is an external call — it must sit
**outside** the transaction, so a slow Midtrans response does not hold a row lock.

---

## Step 8 — Webhook

**Controller** `app/Http/Controllers/MidtransWebhookController.php`:

1. `verifySignature()`. On failure return 403 and log. **Nothing else happens.**
2. Resolve the payment by `order_id`. Unknown id → 404, never a 500.
3. Dispatch `ProcessMidtransNotification` with the validated payload.
4. Return 200 immediately.

No business logic in the controller. It validates and hands off.

**Route** — `routes/web.php`, **outside** the `{locale?}` group (it returns no
language) and outside `auth`:

```php
Route::post('webhooks/midtrans', MidtransWebhookController::class)
    ->name('webhooks.midtrans');
```

**CSRF exemption** in `bootstrap/app.php` via
`$middleware->validateCsrfTokens(except: ['webhooks/midtrans'])`. This must be the
**only** CSRF-exempt route added by this project — P9 re-verifies that.

Do not rate-limit by IP; Midtrans calls from its own ranges and legitimate retries
would be throttled. The signature check is the gate.

**Job** `app/Jobs/ProcessMidtransNotification.php` — `implements ShouldQueue`:

- `public int $tries = 5;` `public array $backoff = [10, 30, 60, 300];`
- Inside `DB::transaction()`, `lockForUpdate()` the payment row.
- **Idempotence:** if the payment is already in the target status, log and return.
  Duplicate notifications are normal Midtrans operation, not an error.
- Map `transaction_status` → internal status:

| Midtrans | Internal | Action |
|---|---|---|
| `capture` + `fraud_status=accept` | `settlement` | fulfil |
| `settlement` | `settlement` | fulfil |
| `pending` | `pending` | none |
| `deny` | `failed` | none |
| `cancel` | `cancelled` | revoke if previously fulfilled |
| `expire` | `expired` | revoke if previously fulfilled |
| `refund`, `partial_refund` | `refunded` | revoke |

- Persist `payment_type`, `midtrans_transaction_id`, `raw_response`, `paid_at`.
- Call `fulfilPayment()` / `revokePayment()` on the payable.
- Audit-log every transition (`guide.md` §7).

---

## Step 9 — Filament

Read-only `PaymentResource`: list, filter by status, view the payload. **No create,
no edit, no delete.** Payment state changes come from Midtrans or from the P8
refund action, never from a form.

---

## Step 10 — Tests

`tests/Feature/Payments/`.

**`MidtransWebhookTest`**
- `test_forged_signature_is_rejected` — 403, payment untouched.
- `test_unknown_order_id_returns_404_not_500`
- `test_settlement_fulfils_the_payable`
- `test_duplicate_settlement_notification_fulfils_exactly_once` — post the same
  payload twice, assert one fulfilment. **The most important test here.**
- `test_each_transaction_status_maps_correctly` — one case per table row.
- `test_capture_with_fraud_status_challenge_does_not_fulfil`
- `test_webhook_responds_before_processing` — assert the job is queued, not run
  inline.

**`PaymentFulfilmentTest`**
- `test_returning_from_checkout_grants_nothing` — simulate the browser redirect
  with no webhook; assert no entitlement. Guards `notes.md` risk 4.

**`CreatePaymentTest`**
- `test_expiry_comes_from_the_payable_profile` — an event payable gets the short
  window, a course payable the long one. Guards the D10 expiry trap.
- `test_order_id_is_unique_and_not_the_primary_key`

Use `Http::fake()` for Snap calls. Never hit the real sandbox from a test.

---

## Validation

```bash
php artisan migrate
php artisan test --filter=Payment
php artisan route:list --path=webhooks
php artisan test
vendor/bin/pint --dirty --format agent
```

Then one real sandbox transaction per enabled method, with the queue worker
running, confirming the notification arrives and the status lands correctly.
**Confirm the real payload shape matches Step 8's mapping and correct this file
where it does not.**

---

## Acceptance

From `task.md` Phase P2:

- [ ] A forged webhook is rejected on signature.
- [ ] Replaying a settlement notification twice fulfils exactly once.
- [ ] No web request blocks on a Midtrans call.
- [ ] A client-side return from checkout grants no entitlement — asserted by test.

## Hand-off

P3 and P5 implement `Payable`. Neither touches the webhook, the signature check,
or the status mapping. If a later phase needs to change payment code, that is a
signal the contract in Step 4 is wrong — fix the contract, not the consumer.
