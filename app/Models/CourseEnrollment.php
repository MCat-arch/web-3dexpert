<?php

namespace App\Models;

use App\Contracts\Payable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model implements Payable
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'course_id',
        'customer_id',
        'student_name',
        'student_email',
        'student_phone',
        'payment_id',
        'status',
        'enrolled_at',
        'activated_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // --- Payable implementation ---

    public function payableAmount(): string
    {
        return (string) $this->course->price;
    }

    public function payableDescription(): string
    {
        return 'Pendaftaran Kursus: '.$this->course->getTranslation('title', config('app.fallback_locale'));
    }

    public function payableExpiryProfile(): string
    {
        return 'course';
    }

    public function fulfilPayment(Payment $payment): void
    {
        if ($this->isActive()) {
            return;
        }

        $this->status = self::STATUS_ACTIVE;
        $this->payment_id = $payment->id;
        $this->activated_at = now();
        $this->save();
    }

    public function revokePayment(Payment $payment): void
    {
        $this->status = self::STATUS_REVOKED;
        $this->save();
    }
}
