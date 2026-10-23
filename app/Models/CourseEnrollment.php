<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Notifications\Courses\CourseEnrollmentActivated;
use App\Support\CourseProgress;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    /**
     * @return array{completed: int, total: int, percent: int, last_completed_at: ?Carbon}
     */
    public function progress(): array
    {
        return CourseProgress::for($this);
    }

    /**
     * Set completed_at exactly once when every lesson has been completed.
     */
    public function markCompletedIfFinished(): void
    {
        if ($this->completed_at !== null) {
            return;
        }

        $progress = $this->progress();

        if ($progress['total'] > 0 && $progress['completed'] >= $progress['total']) {
            $this->forceFill(['completed_at' => now()])->saveQuietly();
        }
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
        $this->loadMissing('course');

        return (string) $this->course->price;
    }

    public function payableDescription(): string
    {
        $this->loadMissing('course');

        return 'Pendaftaran Kursus: '.$this->course->getTranslation('title', config('app.fallback_locale'));
    }

    public function payableExpiryProfile(): string
    {
        return 'course';
    }

    public function payableCustomer(): ?Customer
    {
        $this->loadMissing('customer');

        return $this->customer;
    }

    public function payableTitle(): string
    {
        $this->loadMissing('course');

        return $this->course->getTranslation('title', config('app.fallback_locale'));
    }

    public function payableSuccessUrl(): string
    {
        return Locale::route('customer.courses');
    }

    public function payableRetryUrl(): string
    {
        $this->loadMissing('course');

        return Locale::route('courses.show', ['course' => $this->course->slug]);
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

        $this->loadMissing('customer');

        DB::afterCommit(fn () => $this->customer?->notify(
            new CourseEnrollmentActivated($this->fresh(['course', 'payment']))
        ));
    }

    public function revokePayment(Payment $payment): void
    {
        $this->status = self::STATUS_REVOKED;
        $this->save();
    }

    public function expirePayment(Payment $payment): void
    {
        // Only an unpaid enrollment is released. A concurrent staff grant may
        // already have activated it; do not revoke access they just gave.
        if (! $this->isPending()) {
            return;
        }

        $this->status = self::STATUS_REVOKED;
        $this->save();
    }
}
