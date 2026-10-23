<?php

namespace Tests\Feature\Payments;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Notifications\Events\RegistrationConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('midtrans.server_key', 'SB-Mid-server-test-key');
        config()->set('midtrans.client_key', 'SB-Mid-client-test-key');
        config()->set('midtrans.is_production', false);
        config()->set('midtrans.api_url', 'https://api.sandbox.midtrans.com/v2');
        config()->set('payments.expiry_minutes', 15);
    }

    /**
     * @return array{0: Event, 1: EventRegistration, 2: Payment, 3: Customer}
     */
    private function pendingEventPayment(array $eventOverrides = [], array $paymentOverrides = []): array
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $event = Event::factory()->paid()->create(array_merge([
            'price' => 250000.00,
            'capacity' => 10,
            'seats_taken' => 1,
        ], $eventOverrides));

        $registration = EventRegistration::factory()->pending()->create([
            'event_id' => $event->getKey(),
            'customer_id' => $customer->getKey(),
        ]);

        $payment = Payment::factory()->create(array_merge([
            'payable_type' => EventRegistration::class,
            'payable_id' => $registration->getKey(),
            'order_id' => 'EVT-'.date('Ymd').'-'.Str::upper(Str::random(8)),
            'gross_amount' => $event->price,
            'status' => Payment::STATUS_PENDING,
            'payment_type' => 'qris',
            'expires_at' => now()->addMinutes(15),
        ], $paymentOverrides));

        return [$event, $registration, $payment, $customer];
    }

    private function signedPayload(Payment $payment, string $status = 'settlement'): array
    {
        $statusCode = '200';
        $grossAmount = (string) $payment->gross_amount;

        return [
            'order_id' => $payment->order_id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'fraud_status' => 'accept',
            'transaction_id' => 'txn-'.$payment->getKey(),
            'payment_type' => 'qris',
            'signature_key' => hash(
                'sha512',
                $payment->order_id.$statusCode.$grossAmount.config('midtrans.server_key'),
            ),
        ];
    }

    public function test_payment_page_creates_one_real_midtrans_qris_charge_and_renders_it(): void
    {
        [$event, $registration, $payment, $customer] = $this->pendingEventPayment();

        $qrUrl = 'https://api.sandbox.midtrans.com/v2/qris/txn-123/qr-code';

        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'status_message' => 'QRIS transaction is created',
                'transaction_id' => 'txn-123',
                'order_id' => $payment->order_id,
                'gross_amount' => '250000.00',
                'payment_type' => 'qris',
                'transaction_status' => 'pending',
                'qr_string' => '00020101021226670016COM.NOBUBANK.WWW0118',
                'actions' => [
                    [
                        'name' => 'generate-qr-code',
                        'method' => 'GET',
                        'url' => $qrUrl,
                    ],
                ],
            ], 201),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id);

        $response->assertOk()
            ->assertSee($payment->order_id)
            ->assertSee($qrUrl)
            ->assertSee(__('payments.countdown_label'))
            ->assertDontSee('NMID')
            ->assertDontSee('api.qrserver.com')
            ->assertDontSee('Simulasi Pembayaran');

        Http::assertSent(function (ClientRequest $request) use ($payment, $customer) {
            return $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
                && $request['transaction_details']['order_id'] === $payment->order_id
                && $request['payment_type'] === 'qris'
                && $request['custom_expiry']['unit'] === 'minute'
                && $request['custom_expiry']['expiry_duration'] >= 14
                && $request['custom_expiry']['expiry_duration'] <= 15
                && $request['customer_details']['email'] === $customer->email;
        });

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame($qrUrl, $payment->qrUrl());
        $this->assertSame('txn-123', $payment->midtrans_transaction_id);

        // A refresh must reuse the stored charge instead of creating another
        // one for the same order id.
        $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id)
            ->assertOk()
            ->assertSee($qrUrl);

        Http::assertSentCount(1);
    }

    public function test_payment_page_without_midtrans_keys_still_records_the_pending_order(): void
    {
        config()->set('midtrans.server_key', null);

        [, , $payment, $customer] = $this->pendingEventPayment();

        Http::fake();

        $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id)
            ->assertOk()
            ->assertSee(__('payments.qr_unavailable_title'))
            ->assertSee(__('payments.contact_admin'))
            ->assertDontSee('Simulasi Pembayaran');

        Http::assertNothingSent();
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_a_signed_webhook_settles_once_and_is_idempotent(): void
    {
        Notification::fake();

        [$event, $registration, $payment, $customer] = $this->pendingEventPayment();

        $payload = $this->signedPayload($payment);

        $this->postJson('/pembayaran/notifikasi', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $payment->refresh();
        $registration->refresh();

        $this->assertSame(Payment::STATUS_SETTLEMENT, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('txn-'.$payment->getKey(), $payment->midtrans_transaction_id);
        $this->assertSame(EventRegistration::STATUS_CONFIRMED, $registration->status);
        $this->assertSame(1, $event->fresh()->seats_taken);
        Notification::assertSentTo($customer, RegistrationConfirmed::class);

        // Midtrans retries notifications. The second one must not grant access
        // again or send a second confirmation.
        $this->postJson('/pembayaran/notifikasi', $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        Notification::assertSentToTimes($customer, RegistrationConfirmed::class, 1);
        $this->assertSame(EventRegistration::STATUS_CONFIRMED, $registration->fresh()->status);
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_webhook_rejects_an_invalid_signature_and_an_amount_mismatch(): void
    {
        [$event, $registration, $payment] = $this->pendingEventPayment();

        $payload = $this->signedPayload($payment);
        $payload['signature_key'] = str_repeat('0', 128);

        $this->postJson('/pembayaran/notifikasi', $payload)->assertForbidden();

        $mismatch = $this->signedPayload($payment);
        $mismatch['gross_amount'] = '1.00';
        $mismatch['signature_key'] = hash(
            'sha512',
            $payment->order_id.'200'.'1.00'.config('midtrans.server_key'),
        );

        $this->postJson('/pembayaran/notifikasi', $mismatch)->assertForbidden();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(EventRegistration::STATUS_PENDING, $registration->fresh()->status);
        $this->assertSame(1, $event->fresh()->seats_taken);
    }

    public function test_expiry_command_releases_the_held_event_seat_once(): void
    {
        [$event, $registration, $payment] = $this->pendingEventPayment([], [
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('payments:expire')
            ->expectsOutput('Expired 1 pending payment(s).')
            ->assertSuccessful();

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame(EventRegistration::STATUS_CANCELLED, $registration->fresh()->status);
        $this->assertNotNull($registration->fresh()->cancelled_at);
        $this->assertSame(0, $event->fresh()->seats_taken);

        // Running the sweep again must not release a second seat.
        $this->artisan('payments:expire')->assertSuccessful();
        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_expired_event_still_shows_the_payment_failure_state(): void
    {
        [$event, $registration, $payment, $customer] = $this->pendingEventPayment([], [
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id)
            ->assertOk()
            ->assertSee(__('payments.expired_title'))
            ->assertSee(__('payments.retry_action'))
            ->assertDontSee(__('payments.qr_heading'));

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame(EventRegistration::STATUS_CANCELLED, $registration->fresh()->status);
        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_late_settlement_after_local_expiry_is_acknowledged_but_grant_nothing(): void
    {
        [$event, $registration, $payment] = $this->pendingEventPayment([], [
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('payments:expire')->assertSuccessful();

        $this->postJson('/pembayaran/notifikasi', $this->signedPayload($payment))
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $payment->refresh();
        $registration->refresh();

        // The money arrived after the seat was released. Keep the gateway
        // payload for staff reconciliation, but do not confirm the registration.
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->status);
        $this->assertSame(EventRegistration::STATUS_CANCELLED, $registration->status);
        $this->assertSame(0, $event->fresh()->seats_taken);
        $this->assertTrue((bool) data_get($payment->raw_response, 'late_settlement'));
    }

    public function test_expiry_command_revokes_an_unpaid_course_enrollment(): void
    {
        $customer = Customer::factory()->create();
        $course = Course::factory()->create(['price' => 350000.00]);

        $enrollment = CourseEnrollment::factory()->pending()->create([
            'course_id' => $course->getKey(),
            'customer_id' => $customer->getKey(),
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => CourseEnrollment::class,
            'payable_id' => $enrollment->getKey(),
            'order_id' => 'CRS-'.date('Ymd').'-'.Str::upper(Str::random(8)),
            'gross_amount' => 350000.00,
            'status' => Payment::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('payments:expire')->assertSuccessful();

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame(CourseEnrollment::STATUS_REVOKED, $enrollment->fresh()->status);
    }

    public function test_a_customer_cannot_open_another_customers_payment_page(): void
    {
        [, , $payment] = $this->pendingEventPayment();
        $other = Customer::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other, 'customer')
            ->get('/pembayaran/'.$payment->order_id)
            ->assertForbidden();
    }
}
