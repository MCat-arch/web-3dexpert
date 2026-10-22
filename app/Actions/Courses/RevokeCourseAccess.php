<?php

namespace App\Actions\Courses;

use App\Models\CourseEnrollment;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Revokes entitlement only. Payments are intentionally untouched; a refund is
 * a separate admin-only action.
 */
class RevokeCourseAccess
{
    public function __invoke(CourseEnrollment $enrollment, ?string $reason = null): CourseEnrollment
    {
        if ($enrollment->status === CourseEnrollment::STATUS_REVOKED) {
            return $enrollment;
        }

        return DB::transaction(function () use ($enrollment, $reason) {
            $old = $enrollment->status;

            $enrollment->forceFill([
                'status' => CourseEnrollment::STATUS_REVOKED,
            ])->save();

            app(AuditLogger::class)->log(
                'enrollment.revoked',
                $enrollment,
                ['status' => $old],
                ['status' => CourseEnrollment::STATUS_REVOKED],
                null,
                filled($reason) ? 'Akses dicabut: '.$reason : 'Akses dicabut.',
            );

            return $enrollment;
        });
    }
}
