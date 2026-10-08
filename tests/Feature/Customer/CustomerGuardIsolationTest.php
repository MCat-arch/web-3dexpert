<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reason P1 exists (decision D1).
 *
 * App\Models\User::canAccessPanel() returns canManageContent(), which is true
 * for role 'editor' -- and 'editor' is the column default. So a public account
 * written into `users` would be handed the Filament panel at /admin. That is
 * byte-for-byte the legacy reg.php flaw: "anyone who registered became a
 * superadmin".
 *
 * These tests assert the separation is STRUCTURAL, not a role check someone can
 * later get wrong. If any of them fails, public registration must not ship.
 */
class CustomerGuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_customer_model_does_not_implement_filament_user(): void
    {
        // Cheap, and it is the whole mechanism: without this contract there is
        // no code path from the customer guard into the panel. Fails loudly if
        // anyone adds it for convenience.
        $this->assertNotInstanceOf(FilamentUser::class, new Customer);
    }

    public function test_the_customer_model_has_no_role_column(): void
    {
        // Customers are authorised by ownership, not tier. A role column here
        // would recreate the coupling D1 breaks.
        $this->assertNotContains('role', array_keys(Customer::factory()->make()->getAttributes()));
    }

    public function test_an_authenticated_customer_cannot_reach_the_admin_panel(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get('/admin');

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_the_filament_panel_is_pinned_to_the_web_guard(): void
    {
        $this->assertSame('web', filament()->getPanel('admin')->getAuthGuard());
    }

    public function test_a_customer_cannot_authenticate_on_the_web_guard(): void
    {
        $customer = Customer::factory()->create(['password' => 'rahasia-sekali']);

        $this->assertFalse(auth('web')->attempt([
            'email' => $customer->email,
            'password' => 'rahasia-sekali',
        ]));
    }

    public function test_a_staff_user_cannot_authenticate_on_the_customer_guard(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-sekali']);

        $this->assertFalse(auth('customer')->attempt([
            'email' => $user->email,
            'password' => 'rahasia-sekali',
        ]));
    }

    public function test_the_two_guards_resolve_different_models(): void
    {
        $this->assertSame(User::class, config('auth.providers.users.model'));
        $this->assertSame(Customer::class, config('auth.providers.customers.model'));
    }

    public function test_the_customer_password_broker_uses_its_own_token_table(): void
    {
        // Sharing password_reset_tokens would let a token issued for one
        // provider be redeemed against the other wherever an address exists in
        // both tables -- staff takeover through the public reset form.
        $this->assertSame(
            'customer_password_reset_tokens',
            config('auth.passwords.customers.table'),
        );

        $this->assertNotSame(
            config('auth.passwords.users.table'),
            config('auth.passwords.customers.table'),
        );
    }
}
