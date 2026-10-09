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
     * The brief asked for "only the project that not done yet". Completed is
     * obviously in that set. Cancelled is here too (decision K10): it is
     * equally "not in progress", and a cancellation is a conversation to have
     * with a person rather than something a self-service page should announce.
     *
     * Both are hidden by the SAME uniform negative response as a wrong code,
     * so neither can be distinguished from "no such project".
     */
    public const HIDDEN_STATUSES = [self::STATUS_COMPLETED, self::STATUS_CANCELLED];

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
        'title', 'current_stage', 'status',
        'started_at', 'estimated_completion_at', 'completed_at', 'is_visible',
    ];

    protected function casts(): array
    {
        return [
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

    /** Projects the public tracker may disclose. */
    public function scopeTrackable(Builder $query): Builder
    {
        return $query->where('is_visible', true)
            ->whereNotIn('status', self::HIDDEN_STATUSES);
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
