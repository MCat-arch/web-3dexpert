<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    public const CACHE_CATALOG_PREFIX = 'courses.catalog.';

    protected array $translatable = ['title', 'description'];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_media_id',
        'price',
        'level',
        'estimated_minutes',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_published' => 'boolean',
            'estimated_minutes' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $course) {
            if (blank($course->slug)) {
                $course->slug = $course->generateSlug();
            }
        });
    }

    public function generateSlug(): string
    {
        $base = Str::slug($this->getTranslation('title', config('app.fallback_locale')) ?? 'kursus');
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort_order');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(CourseLesson::class, CourseSection::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->coverMedia && $this->coverMedia->storage_path) {
            return route('media.show', ['path' => $this->coverMedia->storage_path]);
        }

        return null;
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public function formattedPrice(): string
    {
        if ($this->isFree()) {
            return __('courses.free');
        }

        return 'Rp '.number_format((float) $this->price, 0, ',', '.');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }
}
