<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_type', 'actor_id',
        'event', 'subject_type', 'subject_id',
        'description', 'old_values', 'new_values',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function subjectLabel(): string
    {
        if (! $this->subject) {
            return '-';
        }

        $label = class_basename($this->subject_type).' #'.$this->subject_id;

        foreach (['name', 'title', 'order_id', 'email'] as $attribute) {
            $value = $this->subject->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $label.' — '.$value;
            }
        }

        return $label;
    }
}
