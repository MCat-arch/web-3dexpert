<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectUpdate extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    protected $fillable = [
        'project_id', 'stage_name', 'status', 'note', 'occurred_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'date'];
    }

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_DONE];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Files shown inside this timeline step. */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class)->orderBy('sort_order')->orderBy('id');
    }
}
