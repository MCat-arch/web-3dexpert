<?php

namespace Tests\Feature\Admin;

use App\Actions\Payments\RefundPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_resource_is_read_only(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $payment = Payment::factory()->settled()->create();

        $this->assertTrue(PaymentResource::canViewAny());
        $this->assertFalse(PaymentResource::canCreate());
        $this->assertFalse(PaymentResource::canEdit($payment));
        $this->assertFalse(PaymentResource::canDelete($payment));
        $this->assertFalse(PaymentResource::canDeleteAny());
    }

    public function test_only_settled_payments_can_be_refunded(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $enrollment = CourseEnrollment::factory()->create();
        $payment = Payment::factory()->create([
            'payable_type' => CourseEnrollment::class,
            'payable_id' => $enrollment->id,
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->expectException(ValidationException::class);

        app(RefundPayment::class)($payment);
    }

    public function test_editor_cannot_open_the_payment_area(): void
    {
        $this->actingAs(User::factory()->editor()->create());

        $this->assertFalse(PaymentResource::canViewAny());
    }
}
