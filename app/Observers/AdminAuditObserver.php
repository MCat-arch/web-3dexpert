<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Event;
use App\Models\JobVacancy;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Publish-state and staff-account audit hooks.
 *
 * Business actions (refund, enrollment grant, grading, downloads) use
 * AuditLogger directly. These hooks only cover state that can change through
 * several different Filament surfaces.
 */
class AdminAuditObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(Model $model): void
    {
        if ($model instanceof User) {
            $this->audit->log('user.created', $model, [], [
                'name' => $model->name,
                'email' => $model->email,
                'role' => $model->role,
                'is_active' => $model->is_active,
            ]);
        }

        if ($model instanceof Assignment) {
            $this->audit->log('assignment.created', $model, [], [
                'course_lesson_id' => $model->course_lesson_id,
                'max_score' => $model->max_score,
                'is_published' => $model->is_published,
            ]);
        }
    }

    public function updated(Model $model): void
    {
        if ($model instanceof User) {
            if ($model->wasChanged('role')) {
                $this->audit->roleChanged(
                    $model,
                    (string) $model->getOriginal('role'),
                    (string) $model->role,
                );
            }

            if ($model->wasChanged('is_active')) {
                $model->is_active
                    ? $this->audit->reactivated($model)
                    : $this->audit->deactivated($model);
            }

            return;
        }

        if ($model instanceof SiteSetting) {
            $this->auditSetting($model);

            return;
        }

        if (! $model->wasChanged('is_published')) {
            return;
        }

        $published = (bool) $model->is_published;
        $event = match (true) {
            $model instanceof Course => 'course.published',
            $model instanceof Assignment => 'assignment.published',
            $model instanceof Event => 'event.published',
            $model instanceof JobVacancy => 'vacancy.published',
            default => 'content.published',
        };

        if (! $published) {
            $event = str_replace('.published', '.unpublished', $event);
        }

        $this->audit->published($model, $published, $event);
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof MediaAsset) {
            $this->audit->log('media.deleted', $model, [], ['path' => $model->path]);
        }
    }

    private function auditSetting(SiteSetting $setting): void
    {
        if (! $setting->wasChanged(['value', 'value_translations'])) {
            return;
        }

        $sensitive = str_contains(mb_strtolower((string) $setting->key), 'password')
            || str_contains(mb_strtolower((string) $setting->key), 'secret')
            || str_contains(mb_strtolower((string) $setting->key), 'token')
            || str_contains(mb_strtolower((string) $setting->key), 'key');

        $old = $sensitive ? ['value' => '[redacted]'] : ['value' => $setting->getOriginal('value')];
        $new = $sensitive ? ['value' => '[redacted]'] : ['value' => $setting->value];

        $this->audit->log('setting.updated', $setting, $old + ['key' => $setting->key], $new + ['key' => $setting->key]);
    }
}
