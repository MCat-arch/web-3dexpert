<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssignmentSubmission extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_GRADED = 'graded';

    protected $fillable = [
        'assignment_id',
        'customer_id',
        'submitted_text',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'status',
        'score',
        'feedback',
        'submitted_at',
        'graded_at',
        'graded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'score' => 'integer',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $submission) {
            if (filled($submission->path)) {
                Storage::disk($submission->disk)->delete($submission->path);
            }
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function isGraded(): bool
    {
        return $this->status === self::STATUS_GRADED;
    }

    public function hasFile(): bool
    {
        return filled($this->path) && Storage::disk($this->disk)->exists($this->path);
    }

    public function downloadName(): string
    {
        return $this->original_filename ?: 'tugas-'.$this->getKey();
    }
}
