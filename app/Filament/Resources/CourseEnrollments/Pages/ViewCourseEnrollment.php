<?php

namespace App\Filament\Resources\CourseEnrollments\Pages;

use App\Filament\Resources\CourseEnrollments\CourseEnrollmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseEnrollment extends ViewRecord
{
    protected static string $resource = CourseEnrollmentResource::class;

    public function getTitle(): string
    {
        return 'Detail Siswa';
    }
}
