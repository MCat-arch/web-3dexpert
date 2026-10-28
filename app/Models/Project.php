<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Statuses the public tracker will NOT disclose.
     *
     * Cancelled only (K10): a cancellation is a conversation to have with a
     * person rather than something a self-service page should announce.
     *
     * Completed projects ARE disclosed now: the brief asks for the finished
     * files and the closing explanation to be visible to the owner. That does
     * not weaken the lookup -- it still requires the code AND the matching
     * contact, and a wrong contact gets the same uniform negative as an
     * unknown code.
     */
    public const HIDDEN_STATUSES = [self::STATUS_CANCELLED];

    /**
     * Code alphabet.
     *
     * 0/O, 1/I/L and 5/S are omitted: customers read these off a WhatsApp
     * message and type them by hand, and a code that cannot be transcribed
     * reliably produces support load indistinguishable from a real failure.
     */
    private const CODE_ALPHABET = '234678ABCDEFGHJKMNPQRTUVWXYZ';

    private const CODE_LENGTH = 8;

    protected $fillable = [
        'project_code', 'customer_name', 'customer_phone', 'customer_email',
        'title', 'current_stage', 'progress_percent', 'summary', 'status',
        'started_at', 'estimated_completion_at', 'completed_at', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'started_at' => 'date',
            'estimated_completion_at' => 'date',
            'completed_at' => 'date',
            'is_visible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $project) {
            if (blank($project->project_code)) {
                $project->project_code = static::generateCode();
            }
        });

        // Stored normalised so the lookup can compare directly rather than
        // fuzzily -- see App\Support\PhoneNumber.
        static::saving(function (self $project) {
            $project->customer_phone = PhoneNumber::normalise($project->customer_phone);

            if (filled($project->customer_email)) {
                $project->customer_email = Str::lower(trim($project->customer_email));
            }

            /*
             * A finished project is 100% by definition. Deriving it here keeps
             * the tracker consistent even when staff forget the slider, and
             * stamps the completion date so the "Hasil Akhir" section always
             * has a date to show.
             */
            if ($project->status === self::STATUS_COMPLETED) {
                $project->progress_percent = 100;
                $project->completed_at ??= now();
            }
        });
    }

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    public static function generateCode(): string
    {
        do {
            $code = '3DX-';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::withTrashed()->where('project_code', $code)->exists());

        return $code;
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->orderBy('sort_order')->orderBy('occurred_at');
    }

    /** Every file on the tracker: progress renders and final deliverables. */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Project-level progress files (not tied to one stage). */
    public function progressAttachments(): HasMany
    {
        return $this->attachments()
            ->where('kind', ProjectAttachment::KIND_PROGRESS)
            ->whereNull('project_update_id');
    }

    /** The finished files, only rendered when the project is completed. */
    public function deliverables(): HasMany
    {
        return $this->attachments()->where('kind', ProjectAttachment::KIND_DELIVERABLE);
    }

    /** Progress for the bar. A finished project is always 100%. */
    public function progressPercent(): int
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 100;
        }

        return max(0, min(100, (int) $this->progress_percent));
    }

    /** Projects the public tracker may disclose. */
    public function scopeTrackable(Builder $query): Builder
    {
        return $query->where('is_visible', true)
            ->whereNotIn('status', self::HIDDEN_STATUSES);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isTrackable(): bool
    {
        return $this->is_visible && ! in_array($this->status, self::HIDDEN_STATUSES, true);
    }

    /**
     * Does the supplied value match this project's contact details?
     *
     * Accepts either the phone or the email -- the customer should not have to
     * remember which one we hold.
     */
    public function verificationMatches(string $value): bool
    {
        $value = trim($value);

        if (PhoneNumber::matches($value, $this->customer_phone)) {
            return true;
        }

        return filled($this->customer_email)
            && hash_equals($this->customer_email, Str::lower($value));
    }
}
