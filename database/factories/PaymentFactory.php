<?php

namespace Database\Factories;

use App\Models\CourseEnrollment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payable_type' => CourseEnrollment::class,
            'payable_id' => CourseEnrollment::factory(),
            'order_id' => 'CRS-'.date('Ymd').'-'.strtoupper(Str::random(6)),
            'gross_amount' => 350000.00,
            'status' => Payment::STATUS_PENDING,
            'payment_type' => 'qris',
            'expires_at' => now()->addMinutes((int) config('payments.expiry_minutes', 15)),
        ];
    }

    public function settled(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_SETTLEMENT,
            'paid_at' => now(),
        ]);
    }
}
