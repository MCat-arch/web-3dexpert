<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\Customer\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '081234567890',
            'password' => 'rahasia-yang-panjang',
            'password_confirmation' => 'rahasia-yang-panjang',
        ], $overrides);
    }

    public function test_the_register_page_renders(): void
    {
        $this->get('/daftar')->assertOk();
        $this->get('/en/daftar')->assertOk();
    }

    public function test_a_visitor_can_register(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->validPayload())
            ->assertRedirect('/verifikasi-email');

        $customer = Customer::firstWhere('email', 'budi@example.test');

        $this->assertNotNull($customer);
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_registration_lands_in_customers_not_users(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->validPayload());

        // The whole point of D1. If a registration ever creates a User row,
        // it has admin access.
        $this->assertSame(1, Customer::count());
        $this->assertSame(0, User::where('email', 'budi@example.test')->count());
    }

    public function test_a_new_customer_starts_unverified_and_is_sent_a_link(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->validPayload());

        $customer = Customer::firstWhere('email', 'budi@example.test');

        $this->assertNull($customer->email_verified_at);
        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_the_verification_notification_is_queued(): void
    {
        // At launch ~500 people register within minutes. A synchronous send
        // would hold a PHP worker per registration waiting on an external API.
        $this->assertInstanceOf(
            ShouldQueue::class,
            new VerifyEmail,
        );
    }

    public function test_the_password_is_hashed_not_stored_plainly(): void
    {
        Notification::fake();

        $this->post('/daftar', $this->validPayload());

        $customer = Customer::firstWhere('email', 'budi@example.test');

        $this->assertNotSame('rahasia-yang-panjang', $customer->password);
        $this->assertTrue(password_verify('rahasia-yang-panjang', $customer->password));
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        Customer::factory()->create(['email' => 'budi@example.test']);

        $this->post('/daftar', $this->validPayload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Customer::count());
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $this->post('/daftar', $this->validPayload([
            'password_confirmation' => 'sesuatu-yang-lain',
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, Customer::count());
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $this->post('/daftar', $this->validPayload([
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, Customer::count());
    }

    /**
     * One test per input rather than a loop: registration signs the customer
     * in, so a second POST in the same test hits the `guest:customer`
     * middleware and never reaches the controller.
     *
     * @return array<string, array{string, string}>
     */
    public static function phoneNumberFormats(): array
    {
        return [
            'international with punctuation' => ['+62 812-3456-7890', '081234567890'],
            'international without plus' => ['62 812 3456 7890', '081234567890'],
            'national with dashes' => ['0812-3456-7890', '081234567890'],
            'national already clean' => ['081234567890', '081234567890'],
        ];
    }

    #[DataProvider('phoneNumberFormats')]
    public function test_the_phone_number_is_normalised(string $input, string $expected): void
    {
        Notification::fake();

        // +62, 62 and 0 all mean the same national prefix, and punctuation is
        // how the same person ends up with two accounts -- or how P7's
        // progress lookup fails to match a real customer.
        $this->post('/daftar', $this->validPayload(['phone' => $input]))
            ->assertSessionHasNoErrors();

        $this->assertSame($expected, Customer::sole()->phone);
    }

    public function test_an_authenticated_customer_is_kept_off_the_register_page(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/daftar')
            ->assertRedirect();
    }
}
