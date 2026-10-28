<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file shown on the public progress tracker.
 *
 * Two kinds:
 *   progress    -- work in progress (renders, photos, drawings). Visible while
 *                  the project is running, optionally attached to one stage.
 *   deliverable -- the finished files. Only rendered in the "Hasil Akhir"
 *                  section once the project is completed.
 *
 * Files live on the private 'attachments' disk and are streamed through a
 * signed, expiring URL (ProjectAttachmentController); never a public path.
 */
class ProjectAttachment extends Model
{
    use HasFactory;

    public const KIND_PROGRESS = 'progress';

    public const KIND_DELIVERABLE = 'deliverable';

    public const DISK = 'attachments';

    public const MAX_KB = 5120;

    protected $fillable = [
        'project_id', 'project_update_id', 'kind', 'title',
        'path', 'original_name', 'mime_type', 'size_kb', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'size_kb' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Staff upload a file and give it an optional title; mime type and size
         * come from the stored bytes, not from the browser's claim. The
         * original name is captured by the FileUpload field itself
         * (storeFileNamesIn); basename() is only the fallback.
         */
        static::saving(function (self $attachment): void {
            if (blank($attachment->path)) {
                return;
            }

            $disk = Storage::disk(self::DISK);

            if (! $disk->exists($attachment->path)) {
                return;
            }

            $attachment->original_name = $attachment->original_name ?: basename($attachment->path);
            $attachment->mime_type = $disk->mimeType($attachment->path) ?: $attachment->mime_type;
            $attachment->size_kb = (int) ceil($disk->size($attachment->path) / 1024);
        });

        // Deleting the row must not leave an unreferenced customer file on disk.
        static::deleted(function (self $attachment): void {
            if (filled($attachment->path)) {
                Storage::disk(self::DISK)->delete($attachment->path);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Named `stage` because `update()` is taken by Eloquent itself. */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectUpdate::class, 'project_update_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isDeliverable(): bool
    {
        return $this->kind === self::KIND_DELIVERABLE;
    }

    /** Short uppercase type label for the file card ("PDF", "PNG", "ZIP"). */
    public function typeLabel(): string
    {
        $extension = pathinfo((string) $this->original_name, PATHINFO_EXTENSION);

        return $extension !== '' ? strtoupper($extension) : strtoupper((string) $this->kind);
    }

    public function sizeLabel(): string
    {
        if ($this->size_kb >= 1024) {
            return number_format($this->size_kb / 1024, 1, ',', '.').' MB';
        }

        return max(1, $this->size_kb).' KB';
    }

    public function displayName(): string
    {
        return $this->title ?: $this->original_name;
    }

    /** @return array<int, string> */
    public static function kinds(): array
    {
        return [self::KIND_PROGRESS, self::KIND_DELIVERABLE];
    }

    /** Files the public tracker may show for this kind. */
    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }
}
