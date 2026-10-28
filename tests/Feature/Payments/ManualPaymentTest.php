<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\RejectPaymentProof;
use App\Actions\Payments\ValidateManualPayment;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('midtrans.server_key', null);
        config()->set('payments.manual_expiry_hours', 24);

        Notification::fake();
        Storage::fake(Payment::PROOF_DISK);

        Settings::set('payment_bank_name', 'Bank Contoh');
        Settings::set('payment_bank_account', '1234567890');
        Settings::set('payment_bank_holder', 'PT 3D Expert');
    }

    protected function tearDown(): void
    {
        // Drop the per-request memo so the next test re-reads its own rows.
        Settings::flush();

        parent::tearDown();
    }

    /**
     * @return array{0: CourseEnrollment, 1: Payment, 2: Customer}
     */
    private function pendingCoursePayment(array $paymentOverrides = []): array
    {
        $customer = Customer::factory()->create(['email_verified_at' => now()]);
        $course = Course::factory()->create(['price' => 350000.00]);

        $enrollment = CourseEnrollment::factory()->pending()->create([
            'course_id' => $course->getKey(),
            'customer_id' => $customer->getKey(),
        ]);

        $payment = Payment::factory()->create(array_merge([
            'payable_type' => CourseEnrollment::class,
            'payable_id' => $enrollment->getKey(),
            'order_id' => 'CRS-'.date('Ymd').'-'.Str::upper(Str::random(8)),
            'gross_amount' => 350000.00,
            'status' => Payment::STATUS_PENDING,
            'payment_type' => Payment::TYPE_BANK_TRANSFER,
            'expires_at' => now()->addHours(24),
        ], $paymentOverrides));

        return [$enrollment, $payment, $customer];
    }

    public function test_manual_payment_page_shows_bank_details_and_upload_form(): void
    {
        [, $payment, $customer] = $this->pendingCoursePayment();

        $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id)
            ->assertOk()
            ->assertSee('Bank Contoh')
            ->assertSee('1234567890')
            ->assertSee('PT 3D Expert')
            ->assertSee(__('payments.proof_label'))
            ->assertSee(__('payments.proof_submit'))
            ->assertSee(__('payments.method_bank_transfer'))
            ->assertDontSee(__('payments.qr_heading'));
    }

    public function test_manual_mode_switch_forces_bank_transfer_even_with_a_midtrans_key(): void
    {
        config()->set('midtrans.server_key', 'SB-Mid-server-stale-key');
        config()->set('payments.manual_mode', true);

        [, $payment, $customer] = $this->pendingCoursePayment();

        Http::fake();

        $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id)
            ->assertOk()
            ->assertSee(__('payments.manual_heading'))
            ->assertDontSee(__('payments.qr_heading'));

        Http::assertNothingSent();
        $this->assertTrue($payment->fresh()->isManual());
    }

    public function test_customer_upload_is_stored_privately_and_extends_the_window(): void
    {
        [, $payment, $customer] = $this->pendingCoursePayment();

        $this->actingAs($customer, 'customer')
            ->post('/pembayaran/'.$payment->order_id.'/bukti', [
                'proof' => UploadedFile::fake()->create('bukti-transfer.pdf', 200, 'application/pdf'),
                'note' => 'Transfer dari rekening BCA',
            ])
            ->assertRedirect('/pembayaran/'.$payment->order_id)
            ->assertSessionHas('status', __('payments.proof_uploaded_flash'));

        $payment->refresh();

        $this->assertTrue($payment->hasProof());
        $this->assertSame('bukti-transfer.pdf', $payment->payment_proof_original_name);
        $this->assertSame('Transfer dari rekening BCA', $payment->payment_proof_note);
        $this->assertNotNull($payment->payment_proof_uploaded_at);
        $this->assertTrue($payment->expires_at->greaterThan(now()->addHours(23)));
        Storage::disk(Payment::PROOF_DISK)->assertExists($payment->payment_proof_path);
    }

    public function test_another_customer_cannot_upload_or_read_the_proof(): void
    {
        [, $payment] = $this->pendingCoursePayment();

        $other = Customer::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other, 'customer')
            ->post('/pembayaran/'.$payment->order_id.'/bukti', [
                'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->assertFalse($payment->fresh()->hasProof());
    }

    public function test_admin_validation_settles_the_payment_and_grants_course_access(): void
    {
        [$enrollment, $payment] = $this->pendingCoursePayment([
            'payment_proof_path' => 'bukti/bukti.pdf',
            'payment_proof_original_name' => 'bukti.pdf',
            'payment_proof_uploaded_at' => now(),
        ]);

        $admin = User::factory()->admin()->create();

        $settled = app(ValidateManualPayment::class)($payment, $admin);

        $this->assertSame(Payment::STATUS_SETTLEMENT, $settled->status);
        $this->assertSame($admin->getKey(), $settled->validated_by);
        $this->assertNotNull($settled->validated_at);
        $this->assertNotNull($settled->paid_at);
        $this->assertSame(CourseEnrollment::STATUS_ACTIVE, $enrollment->fresh()->status);
    }

    public function test_rejecting_a_proof_releases_the_held_event_seat(): void
    {
        $customer = Customer::factory()->create();
        $event = Event::factory()->paid()->create([
            'price' => 250000.00,
            'capacity' => 10,
            'seats_taken' => 1,
        ]);

        $registration = EventRegistration::factory()->pending()->create([
            'event_id' => $event->getKey(),
            'customer_id' => $customer->getKey(),
        ]);

        $payment = Payment::factory()->create([
            'payable_type' => EventRegistration::class,
            'payable_id' => $registration->getKey(),
            'order_id' => 'EVT-'.date('Ymd').'-'.Str::upper(Str::random(8)),
            'gross_amount' => 250000.00,
            'status' => Payment::STATUS_PENDING,
            'payment_type' => Payment::TYPE_BANK_TRANSFER,
            'payment_proof_path' => 'bukti/bukti.pdf',
            'payment_proof_uploaded_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $admin = User::factory()->admin()->create();

        $rejected = app(RejectPaymentProof::class)($payment, 'Nominal tidak sesuai', $admin);

        $this->assertSame(Payment::STATUS_CANCELLED, $rejected->status);
        $this->assertStringContainsString('Nominal tidak sesuai', (string) $rejected->payment_proof_note);
        $this->assertSame(EventRegistration::STATUS_CANCELLED, $registration->fresh()->status);
        $this->assertSame(0, $event->fresh()->seats_taken);
    }

    public function test_proof_file_is_only_readable_by_staff_with_the_payments_area(): void
    {
        [, $payment, $customer] = $this->pendingCoursePayment([
            'payment_proof_path' => 'bukti/bukti.pdf',
            'payment_proof_original_name' => 'bukti.pdf',
            'payment_proof_uploaded_at' => now(),
        ]);

        Storage::disk(Payment::PROOF_DISK)->put('bukti/bukti.pdf', 'dummy receipt');

        $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id.'/bukti')
            ->assertOk();

        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get(route('admin.payments.proof', ['payment' => $payment]))
            ->assertOk();

        $this->actingAs(User::factory()->editor()->create(), 'web')
            ->get(route('admin.payments.proof', ['payment' => $payment]))
            ->assertForbidden();
    }
}
