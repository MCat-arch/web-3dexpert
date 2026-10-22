<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class JobVacancy extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected array $translatable = ['title', 'requirement', 'description'];

    protected $fillable = [
        'title', 'slug', 'location', 'department', 'employment_type',
        'requirement', 'description', 'contact_url', 'is_published', 'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'closes_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $job) {
            if (blank($job->slug)) {
                $base = Str::slug($job->getTranslation('title', config('app.fallback_locale')) ?? 'lowongan');
                $slug = $base;
                $suffix = 2;
                while (static::withTrashed()->where('slug', $slug)
                    ->when($job->exists, fn ($q) => $q->whereKeyNot($job->getKey()))
                    ->exists()
                ) {
                    $slug = $base.'-'.$suffix++;
                }
                $job->slug = $slug;
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->published()
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->is_published
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
