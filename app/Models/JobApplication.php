<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobApplication extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_SCREENING = 'screening';

    public const STATUS_INTERVIEW = 'interview';

    public const STATUS_OFFERED = 'offered';

    public const STATUS_REJECTED = 'rejected';

    /** Withdrawn by the applicant. Frees the unique index for a reapply. */
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'job_vacancy_id', 'customer_id',
        'applicant_name', 'applicant_email', 'applicant_phone',
        'cover_letter', 'portfolio_url', 'status', 'status_changed_at',
        'reviewed_by', 'internal_notes', 'submitted_at',
    ];

    /**
     * internal_notes is staff-only and must never reach an applicant-facing
     * response. Hiding it here means forgetting to exclude it in a view or a
     * JSON payload cannot leak it.
     */
    protected $hidden = ['internal_notes'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_SCREENING,
            self::STATUS_INTERVIEW,
            self::STATUS_OFFERED,
            self::STATUS_REJECTED,
            self::STATUS_WITHDRAWN,
        ];
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(JobApplicationDocument::class);
    }

    /**
     * Statuses that occupy the applicant's single slot for this vacancy.
     * Withdrawn does not, which is what allows a reapply.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNot('status', self::STATUS_WITHDRAWN);
    }

    public function isActive(): bool
    {
        return $this->status !== self::STATUS_WITHDRAWN;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    /**
     * True once the account behind this application is gone.
     *
     * The application stays fully readable because identity was snapshotted --
     * this just tells staff why there is no live customer to contact through.
     */
    public function applicantAccountDeleted(): bool
    {
        return $this->customer_id === null;
    }
}
