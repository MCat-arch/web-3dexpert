<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['title', 'instructions'];

    protected $fillable = [
        'course_lesson_id',
        'title',
        'instructions',
        'due_at',
        'max_score',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'max_score' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $assignment) {
            // A DB cascade removes rows but does not fire the submission
            // model's deleting hook, so remove private files explicitly.
            $assignment->submissions()->get()->each->delete();
        });
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function isOpen(?CourseEnrollment $enrollment = null): bool
    {
        if (! $this->is_published) {
            return false;
        }

        return $this->due_at === null || $this->due_at->isFuture();
    }

    public function submissionsFor(Customer $customer): HasMany
    {
        return $this->submissions()->where('customer_id', $customer->id);
    }
}
