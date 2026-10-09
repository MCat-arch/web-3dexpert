<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Notifications\Customer\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(Customer $customer, ?string $email = null): string
    {
        return URL::temporarySignedRoute('customer.verification.verify', now()->addHour(), [
            'id' => $customer->getKey(),
            'hash' => sha1($email ?? $customer->getEmailForVerification()),
        ]);
    }

    public function test_an_unverified_customer_sees_the_notice(): void
    {
        $this->actingAs(Customer::factory()->unverified()->create(), 'customer')
            ->get('/verifikasi-email')
            ->assertOk();
    }

    public function test_a_verified_customer_is_redirected_away_from_the_notice(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/verifikasi-email')
            ->assertRedirect('/akun');
    }

    public function test_a_valid_link_verifies_the_email(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get($this->verificationUrl($customer))
            ->assertRedirect('/akun');

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_a_guest_clicking_a_valid_signed_link_is_verified_and_logged_in(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->get($this->verificationUrl($customer))
            ->assertRedirect('/akun');

        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_an_unsigned_link_is_rejected(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get('/verifikasi-email/'.$customer->getKey().'/'.sha1($customer->email))
            ->assertForbidden();

        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_a_tampered_hash_is_rejected(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get($this->verificationUrl($customer, 'orang-lain@example.test'))
            ->assertForbidden();

        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_a_link_for_one_account_cannot_verify_another(): void
    {
        // The signature only proves the link came from us. Without the id
        // check, a signed link could be replayed by whoever is signed in.
        $target = Customer::factory()->unverified()->create();
        $attacker = Customer::factory()->unverified()->create();

        $this->actingAs($attacker, 'customer')
            ->get($this->verificationUrl($target))
            ->assertForbidden();

        $this->assertNull($attacker->fresh()->email_verified_at);
        $this->assertNull($target->fresh()->email_verified_at);
    }

    public function test_the_link_can_be_resent(): void
    {
        Notification::fake();

        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->post('/verifikasi-email/kirim-ulang')
            ->assertRedirect();

        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_the_verified_area_is_closed_until_verification(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')->get('/akun')
            ->assertRedirect('/verifikasi-email');

        $customer->markEmailAsVerified();

        $this->actingAs($customer->fresh(), 'customer')->get('/akun')->assertOk();
    }

    public function test_guests_cannot_reach_the_verification_routes(): void
    {
        $this->get('/verifikasi-email')->assertRedirect('/masuk');
        $this->post('/verifikasi-email/kirim-ulang')->assertRedirect('/masuk');
    }
}
