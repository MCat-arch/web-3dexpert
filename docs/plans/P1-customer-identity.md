# Phase P1 — Customer Identity & Guard Isolation

| | |
|---|---|
| **Depends on** | P0 |
| **Blocks** | P2, P3, P4, P5, P6 |
| **Blocked by** | Nothing |
| **Plan stability** | **Firm** — the escalation path below was verified in the code |

## Objective

Give the public its own authentication surface, structurally unable to reach
Filament. Decision **D1** in `notes.md`.

## Why this is a security phase, not a feature phase

Verified in `app/Models/User.php`:

```php
public function canAccessPanel(Panel $panel): bool
{
    return $this->canManageContent();   // true for role 'editor'
}
```

And in `database/migrations/2026_01_01_000011_add_role_to_users_table.php`:

```php
$table->string('role')->default('editor')   // ← the default
```

So a public registration written into `users` grants `/admin` access. That is the
legacy `reg.php` flaw — "anyone who registered became a superadmin" — which this
rebuild exists to remove. A separate table plus a separate guard removes the
possibility rather than defending against it with a role check.

**Build the isolation test first (Step 6) and watch it fail before writing the
registration flow.** Do not expose a registration route until it passes.

---

## Step 1 — Schema

```bash
php artisan make:migration create_customers_table --no-interaction
php artisan make:migration create_customer_password_reset_tokens_table --no-interaction
```

**`customers`**

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `name` | string | |
| `email` | string, unique | login identifier |
| `email_verified_at` | timestamp, nullable | gate for A5 |
| `password` | string | `hashed` cast |
| `phone` | string, nullable, index | reused by P7 verification |
| `is_active` | boolean, default true, index | staff deactivation (P8) |
| `remember_token` | rememberToken | |
| `timestamps` | | |
| `softDeletes` | | P6 depends on soft delete, not hard delete |

**`customer_password_reset_tokens`** — mirror the shape of the framework's
`password_reset_tokens` (`email` primary, `token`, `created_at`). A separate table
is required: the default is keyed by email alone and would let a reset token
issued for one provider be redeemed against the other.

---

## Step 2 — Guard, provider, broker

Edit `config/auth.php`. Add, do not replace:

```php
'guards' => [
    'web' => [...],                       // staff + Filament. UNCHANGED.
    'customer' => ['driver' => 'session', 'provider' => 'customers'],
],

'providers' => [
    'users' => [...],                     // UNCHANGED
    'customers' => ['driver' => 'eloquent', 'model' => App\Models\Customer::class],
],

'passwords' => [
    'users' => [...],
    'customers' => [
        'provider' => 'customers',
        'table' => 'customer_password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

---

## Step 3 — Model

```bash
php artisan make:model Customer --factory --no-interaction
```

`app/Models/Customer.php`:

- Extends `Illuminate\Foundation\Auth\User as Authenticatable`.
- `use HasFactory, Notifiable, SoftDeletes;`
- **Must implement `MustVerifyEmail`** (A5 depends on it).
- **Must NOT implement `Filament\Models\Contracts\FilamentUser`.** This is the
  structural half of D1 — without the contract there is no panel access path.
- Explicit `$fillable = ['name', 'email', 'password', 'phone']`. Never `$guarded = []`.
- `$hidden = ['password', 'remember_token']`.
- `casts()`: `email_verified_at` → `datetime`, `password` → `hashed`,
  `is_active` → `boolean`.
- No `role` column. Customers have no roles; authorization is per-resource
  ownership via Policies in later phases.

Relations are added by the phases that need them (`enrollments` in P3,
`eventRegistrations` in P5, `jobApplications` in P6). Do not add them now.

---

## Step 4 — Pin the Filament panel

`app/Providers/Filament/AdminPanelProvider.php` — set the guard explicitly rather
than relying on the default:

```php
->authGuard('web')
```

Being explicit is the point. A future default change must not silently widen panel
access.

---

## Step 5 — Auth flows

Namespace controllers under `app/Http/Controllers/Customer/Auth/`:

| Controller | Responsibility |
|---|---|
| `RegisterController` | show form, create customer, fire `Registered`, send verification |
| `LoginController` | authenticate on the `customer` guard, regenerate session, reject `is_active = false` |
| `LogoutController` | logout, invalidate, regenerate token |
| `EmailVerificationController` | notice, verify, resend |
| `PasswordResetController` | request link and reset, using the `customers` broker |

**FormRequests** in `app/Http/Requests/Customer/`: `RegisterRequest`,
`LoginRequest`, `PasswordResetRequest`. All validation lives here (`guide.md` §3).
Password rules: use `Illuminate\Validation\Rules\Password::defaults()` and set the
defaults once in `AppServiceProvider`.

**Routes** — `routes/web.php`. Note the file wraps public routes in an optional
`{locale?}` prefix group; put customer auth routes **inside** that group so both
locales work, and name them `customer.*`.

Guest routes: `register`, `login`, `password.request`, `password.reset`.
Authenticated (`auth:customer`): `logout`, `verification.*`, account shell.

**Middleware:** use the framework's `auth:customer`. Do not write a new middleware
class. Set the customer login redirect so unauthenticated access lands on the
customer login page, not the staff one.

**Notifications** in `app/Notifications/Customer/` — `VerifyEmail`,
`ResetPassword`. Both `implements ShouldQueue` (`guide.md` §5 rule 3). This is
where P0's mail and queue work gets its first real use.

**Views** in `resources/views/customer/auth/`. Reuse the existing layout. All
strings go through `lang/id` and `lang/en` — both files, identical key sets.

**Account shell:** one page listing nothing yet. Later phases add panels to it
(my courses in P4, my events in P5, my applications in P6).

---

## Step 6 — Rate limiting

Define named limiters in `AppServiceProvider::boot()` and apply via
`throttle:<name>`:

| Route | Limit | Key |
|---|---|---|
| login | 5 / minute | email + IP |
| register | 3 / minute | IP |
| password request | 3 / minute | email + IP |
| verification resend | 3 / minute | customer id |

Key login attempts on email **and** IP so one attacker cannot lock out a real
user by hammering their address.

---

## Step 7 — Tests

`tests/Feature/Customer/` — PHPUnit syntax (P0 Step 1 settled this).

**`CustomerGuardIsolationTest`** — the reason this phase exists. Write it first.

- `test_authenticated_customer_cannot_reach_admin_panel` — acting as a `Customer`
  on the `customer` guard, `GET /admin` is not 200.
- `test_customer_cannot_authenticate_on_web_guard`
- `test_staff_user_cannot_authenticate_on_customer_guard`
- `test_customer_model_does_not_implement_filament_user` — asserts the contract is
  absent. Cheap, and it fails loudly if someone adds it later.

**`CustomerRegistrationTest`** — creates an unverified customer; duplicate email
rejected; weak password rejected; **the new row lands in `customers`, not `users`**.

**`CustomerLoginTest`** — valid login; wrong password; `is_active = false`
rejected; session id regenerates.

**`CustomerEmailVerificationTest`** — unverified customer blocked from the gated
area; valid link verifies; tampered hash rejected.

**`CustomerPasswordResetTest`** — link issued and redeemable; a token from the
`users` broker is **not** valid for a customer.

**`CustomerRateLimitTest`** — login and register return 429 past the limit.

---

## Validation

```bash
php artisan migrate
php artisan route:list --name=customer
php artisan test --filter=Customer
php artisan test                      # full suite, no regressions
vendor/bin/pint --dirty --format agent
```

Manual check: register, receive the verification mail (queue worker must be
running), verify, log in, attempt `/admin`, log out.

---

## Acceptance

From `task.md` Phase P1:

- [ ] A customer session cannot reach `/admin` — asserted by test, not inspection.
- [ ] Unverified customers are blocked from purchasing, enrolling, and applying.
- [ ] `users` and `customers` share no credential surface.
- [ ] Rate limits return 429 under test.

## Hand-off to later phases

- `Customer` has no relations yet — P3/P5/P6 add theirs.
- `customers.phone` is deliberately present for P7's verification lookup.
- Soft deletes matter in P6: deleting a customer must leave applications readable.
- The account shell is the mount point for every later "my ..." panel.
