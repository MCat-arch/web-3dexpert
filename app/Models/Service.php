<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['title', 'description', 'discount_text'];

    protected $fillable = [
        'title', 'slug', 'description', 'price', 'original_price',
        'discount_text', 'brochure_path', 'icon_path', 'sort_order',
        'is_published', 'needs_review',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'is_published' => 'boolean',
            'needs_review' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $service) {
            if (blank($service->slug)) {
                $base = Str::slug($service->getTranslation('title', config('app.fallback_locale')) ?? 'layanan');
                $slug = $base;
                $suffix = 2;
                while (static::where('slug', $slug)
                    ->when($service->exists, fn ($q) => $q->whereKeyNot($service->getKey()))
                    ->exists()
                ) {
                    $slug = $base.'-'.$suffix++;
                }
                $service->slug = $slug;
            }
        });
    }

    public function features(): HasMany
    {
        return $this->hasMany(ServiceFeature::class)->orderBy('sort_order');
    }

    public function contactSubmissions(): HasMany
    {
        return $this->hasMany(ContactSubmission::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (blank($this->original_price) || blank($this->price)
            || (float) $this->original_price <= 0) {
            return null;
        }

        $percent = (1 - ((float) $this->price / (float) $this->original_price)) * 100;

        return $percent > 0 ? (int) round($percent) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
