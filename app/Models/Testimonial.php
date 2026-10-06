<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use App\Concerns\HasYoutubeVideo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory, HasTranslations, HasYoutubeVideo;

    protected array $translatable = ['title', 'client_name'];

    protected $fillable = [
        'youtube_url', 'youtube_id', 'title', 'client_name', 'rating',
        'is_highlighted', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_highlighted' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeHighlighted(Builder $query): Builder
    {
        return $query->where('is_highlighted', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }

    /**
     * Business rule carried over verbatim from edit.php, which refused to save
     * unless at least 2 testimonials were highlighted:
     *
     *     if ($highlightedCount < 2) { alert('Minimal 2 video ...'); }
     *
     * The homepage layout puts highlighted items in a 2-across hero row, so
     * fewer than 2 leaves a visible gap. Enforced in admin validation.
     */
    public const MIN_HIGHLIGHTED = 2;
}
