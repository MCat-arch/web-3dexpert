<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_renders_in_both_locales(): void
    {
        $this->get('/masuk')->assertOk();
        $this->get('/en/masuk')->assertOk();
    }

    public function test_a_customer_can_sign_in(): void
    {
        $customer = Customer::factory()->create(['password' => 'rahasia-yang-panjang']);

        $this->post('/masuk', [
            'email' => $customer->email,
            'password' => 'rahasia-yang-panjang',
        ])->assertRedirect('/akun');

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $customer = Customer::factory()->create(['password' => 'rahasia-yang-panjang']);

        $this->post('/masuk', ['email' => $customer->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_an_unknown_email_gives_the_same_error_as_a_wrong_password(): void
    {
        // Distinguishing the two turns the login form into a membership
        // oracle: anyone can discover whether an address has an account.
        $customer = Customer::factory()->create(['password' => 'rahasia-yang-panjang']);
        $expected = __('customer.login_failed');

        $this->post('/masuk', ['email' => 'tidak-ada@example.test', 'password' => 'apa-saja'])
            ->assertSessionHasErrors(['email' => $expected]);

        $this->flushSession();

        $this->post('/masuk', ['email' => $customer->email, 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => $expected]);
    }

    public function test_a_deactivated_customer_cannot_sign_in(): void
    {
        $customer = Customer::factory()->deactivated()->create(['password' => 'rahasia-yang-panjang']);

        $this->post('/masuk', [
            'email' => $customer->email,
            'password' => 'rahasia-yang-panjang',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_the_session_id_is_regenerated_on_sign_in(): void
    {
        // Without this an attacker who planted a session id can ride it
        // straight through the login (session fixation).
        $customer = Customer::factory()->create(['password' => 'rahasia-yang-panjang']);

        $this->startSession();
        $before = session()->getId();

        $this->post('/masuk', [
            'email' => $customer->email,
            'password' => 'rahasia-yang-panjang',
        ]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_signing_in_records_the_timestamp(): void
    {
        $customer = Customer::factory()->create([
            'password' => 'rahasia-yang-panjang',
            'last_login_at' => null,
        ]);

        $this->post('/masuk', [
            'email' => $customer->email,
            'password' => 'rahasia-yang-panjang',
        ]);

        $this->assertNotNull($customer->fresh()->last_login_at);
    }

    public function test_an_unverified_customer_is_sent_to_the_verification_notice(): void
    {
        $customer = Customer::factory()->unverified()->create(['password' => 'rahasia-yang-panjang']);

        $this->post('/masuk', [
            'email' => $customer->email,
            'password' => 'rahasia-yang-panjang',
        ]);

        // Signed in, but the verified-only area is closed.
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->get('/akun')->assertRedirect('/verifikasi-email');
    }

    public function test_sign_out_requires_a_post(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        // A GET logout can be triggered by an <img src> on any page, or by a
        // prefetching browser (guide.md 10.4).
        $this->get('/keluar')->assertStatus(405);

        $this->post('/keluar')->assertRedirect('/');
        $this->assertGuest('customer');
    }

    public function test_the_account_page_is_closed_to_guests(): void
    {
        $this->get('/akun')->assertRedirect('/masuk');
    }
}
