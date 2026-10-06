<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use App\Concerns\HasYoutubeVideo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Replaces review_design. Kept as its own feature, separate from Testimonial,
 * per your decision (#1).
 */
class DesignReview extends Model
{
    use HasFactory, HasTranslations, HasYoutubeVideo;

    protected array $translatable = ['title', 'description'];

    protected $fillable = [
        'youtube_url', 'youtube_id', 'title', 'description',
        'sort_order', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
