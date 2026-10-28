<?php

namespace Tests\Feature\Payments;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The customer picks the payment route; the order follows the choice.
 *
 * Before this, "manual bank transfer" was an env flag that turned the whole
 * site manual. Now the same checkout can produce either a Midtrans QRIS charge
 * or a manual order waiting for a receipt, and the deadline differs because a
 * bank transfer takes longer to validate than a QR scan.
 */
class PaymentMethodChoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A key is present on purpose: choosing bank transfer must not call
        // Midtrans even when the gateway is configured.
        config()->set('midtrans.server_key', 'SB-Mid-server-test');
        config()->set('payments.manual_mode', false);
        config()->set('payments.expiry_minutes', 15);
        config()->set('payments.manual_expiry_hours', 24);

        Notification::fake();
        Http::fake();

        Settings::set('payment_bank_name', 'Bank Contoh');
        Settings::set('payment_bank_account', '1234567890');
        Settings::set('payment_bank_holder', 'PT 3D Expert');
    }

    protected function tearDown(): void
    {
        Settings::flush();

        parent::tearDown();
    }

    private function verifiedCustomer(): Customer
    {
        return Customer::factory()->create(['email_verified_at' => now()]);
    }

    private function paidCourse(): Course
    {
        return Course::factory()->create([
            'is_published' => true,
            'price' => 150000.00,
        ]);
    }

    public function test_choosing_bank_transfer_creates_a_manual_order_without_charging_midtrans(): void
    {
        $customer = $this->verifiedCustomer();
        $course = $this->paidCourse();

        $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => Payment::TYPE_BANK_TRANSFER,
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ])
            ->assertRedirect();

        $payment = Payment::sole();

        $this->assertSame(Payment::TYPE_BANK_TRANSFER, $payment->payment_type);
        $this->assertTrue($payment->isManual());
        $this->assertTrue(
            $payment->expires_at->between(now()->addHours(23)->addMinutes(59), now()->addHours(24)->addMinute()),
            'A manual transfer needs the long validation window, not the 15-minute QR window.',
        );

        // Opening the receipt page must not create a QR the customer never
        // asked for.
        $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id)
            ->assertOk()
            ->assertSee(__('payments.manual_heading'))
            ->assertDontSee(__('payments.qr_heading'));

        Http::assertNothingSent();
    }

    public function test_choosing_qris_keeps_the_short_window(): void
    {
        $customer = $this->verifiedCustomer();
        $course = $this->paidCourse();

        $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => Payment::TYPE_QRIS,
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ]);

        $payment = Payment::sole();

        $this->assertSame(Payment::TYPE_QRIS, $payment->payment_type);
        $this->assertFalse($payment->isManual());
        $this->assertTrue($payment->expires_at->between(now()->addMinutes(14), now()->addMinutes(16)));
    }

    public function test_an_unknown_method_is_rejected_by_validation(): void
    {
        $customer = $this->verifiedCustomer();
        $course = $this->paidCourse();

        $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => 'bitcoin',
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Payment::count());
    }

    public function test_checking_out_again_switches_the_method_on_the_same_pending_order(): void
    {
        $customer = $this->verifiedCustomer();
        $course = $this->paidCourse();

        $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => Payment::TYPE_QRIS,
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ]);

        $first = Payment::sole();
        $this->assertSame(Payment::TYPE_QRIS, $first->payment_type);

        $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => Payment::TYPE_BANK_TRANSFER,
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ]);

        $this->assertSame(1, Payment::count(), 'The customer must not end up with two live orders.');
        $this->assertSame(1, CourseEnrollment::count());

        $second = Payment::sole();

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame($first->order_id, $second->order_id);
        $this->assertSame(Payment::TYPE_BANK_TRANSFER, $second->payment_type);
        $this->assertTrue($second->expires_at->greaterThan(now()->addHours(23)));
    }

    public function test_a_receipt_already_uploaded_pins_the_order_to_bank_transfer(): void
    {
        $customer = $this->verifiedCustomer();
        $course = $this->paidCourse();

        $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => Payment::TYPE_BANK_TRANSFER,
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ]);

        $payment = Payment::sole();
        $payment->forceFill([
            'payment_proof_path' => 'bukti/bukti.pdf',
            'payment_proof_uploaded_at' => now(),
        ])->save();

        $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => Payment::TYPE_QRIS,
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ]);

        // That proof is a real transfer, and staff may already be reviewing
        // it: flipping to QRIS would strand the money.
        $this->assertSame(Payment::TYPE_BANK_TRANSFER, Payment::sole()->payment_type);
    }

    public function test_event_registration_honours_the_chosen_method(): void
    {
        $customer = $this->verifiedCustomer();

        $event = Event::factory()->paid()->create([
            'price' => 250000.00,
            'capacity' => 20,
        ]);

        $this->actingAs($customer, 'customer')
            ->post('/acara/'.$event->slug.'/daftar', [
                'attendee_name' => 'Budi Santoso',
                'attendee_phone' => '081234567890',
                'payment_method' => Payment::TYPE_BANK_TRANSFER,
            ])
            ->assertRedirect();

        $registration = EventRegistration::sole();
        $payment = Payment::sole();

        $this->assertSame($registration->getKey(), $payment->payable_id);
        $this->assertSame(Payment::TYPE_BANK_TRANSFER, $payment->payment_type);
        $this->assertTrue($payment->expires_at->greaterThan(now()->addHours(23)));

        Http::assertNothingSent();
    }

    public function test_the_event_page_offers_both_methods_with_the_bank_details(): void
    {
        $customer = $this->verifiedCustomer();

        $event = Event::factory()->paid()->create(['price' => 250000.00, 'capacity' => 20]);

        $this->actingAs($customer, 'customer')
            ->get('/acara/'.$event->slug)
            ->assertOk()
            ->assertSee('name="payment_method"', false)
            ->assertSee('value="qris"', false)
            ->assertSee('value="bank_transfer"', false)
            ->assertSee('1234567890');
    }
}
