<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use App\Concerns\HasYoutubeVideo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLesson extends Model
{
    use HasFactory, HasTranslations, HasYoutubeVideo;

    protected array $translatable = ['title', 'body'];

    protected $fillable = [
        'course_section_id',
        'title',
        'content_type',
        'youtube_url',
        'youtube_id',
        'body',
        'attachment_media_id',
        'duration_seconds',
        'is_free_preview',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_free_preview' => 'boolean',
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function attachmentMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'attachment_media_id');
    }

    public function attachmentUrl(): ?string
    {
        if ($this->attachmentMedia && $this->attachmentMedia->storage_path) {
            return route('media.show', ['path' => $this->attachmentMedia->storage_path]);
        }

        return null;
    }

    public function durationFormatted(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
