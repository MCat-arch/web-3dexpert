<?php

namespace App\Models;

use App\Actions\Events\CancelEventRegistration;
use App\Contracts\Payable;
use App\Notifications\Events\RegistrationConfirmed;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventRegistration extends Model implements Payable
{
    use HasFactory;

    /** Awaiting payment. Only reachable on the paid path (P5b). */
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ATTENDED = 'attended';

    protected $fillable = [
        'event_id', 'customer_id',
        'attendee_name', 'attendee_email', 'attendee_phone',
        'status', 'ticket_code',
        'registered_at', 'confirmed_at', 'cancelled_at', 'attended_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The most recent payment attempt for this registration. */
    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable')->latestOfMany();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_ATTENDED], true);
    }

    /**
     * Statuses that occupy a seat.
     *
     * Pending counts: on the paid path a seat is held while payment is
     * outstanding, otherwise two people could pay for the same last seat.
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_ATTENDED]);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_ATTENDED]);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function occupiesSeat(): bool
    {
        return ! $this->isCancelled();
    }

    /**
     * Random and non-sequential: the code is shown to the attendee, and a
     * derived one would leak how many people have registered. Same discipline
     * as P7's project codes -- ambiguous characters omitted because people
     * read these aloud and type them by hand.
     */
    public static function generateTicketCode(): string
    {
        do {
            $code = 'EVT-'.Str::upper(Str::random(8));
            $code = str_replace(['0', 'O', '1', 'I', 'L'], ['2', '3', '4', '5', '6'], $code);
        } while (static::where('ticket_code', $code)->exists());

        return $code;
    }

    // --- Payable implementation ---

    public function payableAmount(): string
    {
        $this->loadMissing('event');

        return (string) $this->event->price;
    }

    public function payableDescription(): string
    {
        $this->loadMissing('event');

        return 'Pendaftaran Acara: '.$this->event->getTranslation('title', config('app.fallback_locale'));
    }

    public function payableExpiryProfile(): string
    {
        return 'event';
    }

    public function payableCustomer(): ?Customer
    {
        $this->loadMissing('customer');

        return $this->customer;
    }

    public function payableTitle(): string
    {
        $this->loadMissing('event');

        return $this->event->getTranslation('title', config('app.fallback_locale'));
    }

    public function payableSuccessUrl(): string
    {
        return Locale::route('customer.events');
    }

    public function payableRetryUrl(): string
    {
        $this->loadMissing('event');

        return Locale::route('events.show', ['event' => $this->event->slug]);
    }

    public function fulfilPayment(Payment $payment): void
    {
        // A late settlement must not resurrect a registration whose seat was
        // released. Staff can refund it from the payment detail page.
        if ($this->isCancelled()) {
            return;
        }

        if ($this->isConfirmed()) {
            return;
        }

        $this->forceFill([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ])->save();

        $this->loadMissing('customer');

        DB::afterCommit(fn () => $this->customer?->notify(
            new RegistrationConfirmed($this->fresh(['event']))
        ));
    }

    public function revokePayment(Payment $payment): void
    {
        if ($this->isCancelled()) {
            return;
        }

        app(CancelEventRegistration::class)($this);
    }

    public function expirePayment(Payment $payment): void
    {
        if (! $this->isPending()) {
            return;
        }

        // CancelEventRegistration owns the seat decrement; doing it here would
        // risk a second release if a refund/cancel runs at the same time.
        app(CancelEventRegistration::class)($this);
    }
}
