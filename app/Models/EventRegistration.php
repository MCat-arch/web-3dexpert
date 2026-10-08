<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventRegistration extends Model
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
}
