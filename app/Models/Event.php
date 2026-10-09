<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    public const PUBLIC_LIST_CACHE_KEY = 'events.public-list';

    public const LOCATION_OFFLINE = 'offline';

    public const LOCATION_ONLINE = 'online';

    public const LOCATION_HYBRID = 'hybrid';

    /** @var array<int, string> */
    protected array $translatable = ['title', 'description'];

    protected $fillable = [
        'title', 'slug', 'description', 'cover_path',
        'starts_at', 'ends_at', 'location_type', 'venue', 'online_url',
        'capacity', 'price',
        'registration_opens_at', 'registration_closes_at', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'capacity' => 'integer',
            'seats_taken' => 'integer',
            'price' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (blank($event->slug)) {
                $event->slug = $event->generateSlug();
            }
        });
    }

    public function generateSlug(): string
    {
        $base = Str::slug(
            $this->getTranslation('title', config('app.fallback_locale')) ?? 'acara'
        );

        $slug = $base;
        $suffix = 2;

        // withTrashed: a soft-deleted row still occupies its slug, and the
        // unique index would reject the duplicate.
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public static function listingCacheKey(string $locale): string
    {
        return self::PUBLIC_LIST_CACHE_KEY.'.'.$locale;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('starts_at');
    }

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }

    /** NULL capacity means unlimited, which is why this is not a comparison alone. */
    public function isUnlimited(): bool
    {
        return $this->capacity === null;
    }

    public function hasSeatsLeft(): bool
    {
        return $this->isUnlimited() || $this->seats_taken < $this->capacity;
    }

    public function seatsLeft(): ?int
    {
        return $this->isUnlimited() ? null : max(0, $this->capacity - $this->seats_taken);
    }

    public function getSeatsRemainingAttribute(): ?int
    {
        return $this->seatsLeft();
    }

    public function registrationIsOpen(): bool
    {
        if (! $this->is_published) {
            return false;
        }

        if ($this->starts_at->isPast()) {
            return false;
        }

        if ($this->registration_opens_at && $this->registration_opens_at->isFuture()) {
            return false;
        }

        if ($this->registration_closes_at && $this->registration_closes_at->isPast()) {
            return false;
        }

        return true;
    }
}
