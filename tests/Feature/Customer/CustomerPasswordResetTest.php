<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\Customer\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_request_page_renders(): void
    {
        $this->get('/lupa-sandi')->assertOk();
    }

    public function test_a_reset_link_is_sent(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $this->post('/lupa-sandi', ['email' => $customer->email])
            ->assertRedirect();

        Notification::assertSentTo($customer, ResetPassword::class);
    }

    public function test_an_unknown_email_gets_the_same_response(): void
    {
        Notification::fake();

        // Reporting "no such account" makes this a membership oracle, the
        // same reasoning as the login form and P7's progress lookup.
        $known = Customer::factory()->create();

        $a = $this->post('/lupa-sandi', ['email' => $known->email]);
        $this->flushSession();
        $b = $this->post('/lupa-sandi', ['email' => 'tidak-ada@example.test']);

        $this->assertSame($a->getSession()->get('status'), $b->getSession()->get('status'));
        Notification::assertSentToTimes($known, ResetPassword::class, 1);
    }

    public function test_the_token_goes_to_the_customer_table_not_the_shared_one(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $this->post('/lupa-sandi', ['email' => $customer->email]);

        $this->assertSame(1, DB::table('customer_password_reset_tokens')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_a_customer_can_reset_their_password(): void
    {
        $customer = Customer::factory()->create(['password' => 'sandi-lama-sekali']);
        $token = Password::broker('customers')->createToken($customer);

        $this->post('/atur-sandi', [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'sandi-baru-yang-panjang',
            'password_confirmation' => 'sandi-baru-yang-panjang',
        ])->assertRedirect('/masuk');

        $this->assertTrue(
            auth('customer')->attempt(['email' => $customer->email, 'password' => 'sandi-baru-yang-panjang'])
        );
    }

    public function test_a_staff_token_cannot_reset_a_customer_password(): void
    {
        // The whole reason the broker has its own table: an address present in
        // both tables must not let a token issued for one redeem against the
        // other.
        $email = 'sama@example.test';
        $customer = Customer::factory()->create(['email' => $email, 'password' => 'sandi-lama-sekali']);
        $staff = User::factory()->create(['email' => $email]);

        $staffToken = Password::broker('users')->createToken($staff);

        $this->post('/atur-sandi', [
            'token' => $staffToken,
            'email' => $email,
            'password' => 'sandi-baru-yang-panjang',
            'password_confirmation' => 'sandi-baru-yang-panjang',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(
            auth('customer')->attempt(['email' => $email, 'password' => 'sandi-lama-sekali']),
            'The old customer password should still work.'
        );
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $customer = Customer::factory()->create(['password' => 'sandi-lama-sekali']);

        $this->post('/atur-sandi', [
            'token' => 'token-palsu',
            'email' => $customer->email,
            'password' => 'sandi-baru-yang-panjang',
            'password_confirmation' => 'sandi-baru-yang-panjang',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_weak_new_password_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $token = Password::broker('customers')->createToken($customer);

        $this->post('/atur-sandi', [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');
    }

    public function test_resetting_invalidates_remember_me_cookies(): void
    {
        $customer = Customer::factory()->create(['remember_token' => 'token-lama']);
        $token = Password::broker('customers')->createToken($customer);

        $this->post('/atur-sandi', [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'sandi-baru-yang-panjang',
            'password_confirmation' => 'sandi-baru-yang-panjang',
        ]);

        // A stolen "remember me" cookie must not survive a password change.
        $this->assertNotSame('token-lama', $customer->fresh()->remember_token);
    }
}
