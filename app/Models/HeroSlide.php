<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['alt_text', 'caption'];

    protected $fillable = [
        'image_path', 'alt_text', 'caption', 'link_url',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getUrlAttribute(): ?string
    {
        return filled($this->image_path)
            ? route('media.show', ['path' => $this->image_path])
            : null;
    }
}
