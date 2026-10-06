<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Portfolio extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected array $translatable = ['title', 'description'];

    protected $fillable = [
        'title', 'slug', 'description', 'price', 'location', 'style',
        'floors', 'bedrooms', 'bathrooms', 'land_size', 'building_size',
        'is_featured', 'is_published', 'sort_order',
        'legacy_source', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $portfolio) {
            if (blank($portfolio->slug)) {
                $portfolio->slug = $portfolio->generateSlug();
            }
        });
    }

    public function generateSlug(): string
    {
        $base = Str::slug($this->getTranslation('title', config('app.fallback_locale')) ?? 'portofolio');
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

    public function images(): HasMany
    {
        return $this->hasMany(PortfolioImage::class)->orderBy('sort_order');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(PortfolioSpec::class)->orderBy('sort_order');
    }

    public function coverImage(): HasMany
    {
        return $this->images()->where('is_cover', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * Legacy detail.php ran "UPDATE properties SET likes = likes + 1" inline in
     * the page render, which made a GET request non-idempotent and let every
     * bot and refresh inflate the homepage's featured pick.
     *
     * This is still called from a GET, but it is (a) an atomic increment that
     * touches one column and skips model events, (b) never used to choose what
     * the homepage shows -- that is is_featured, set by a human.
     *
     * If view counts ever start to matter, move this into a queued job or a
     * daily aggregate from the access log.
     */
    public function recordView(): void
    {
        static::withoutTimestamps(
            fn () => $this->newQuery()->whereKey($this->getKey())->increment('view_count')
        );
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
