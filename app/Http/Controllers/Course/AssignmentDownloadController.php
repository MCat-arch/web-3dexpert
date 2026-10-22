<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentDownloadController extends Controller
{
    public function download(Course $course, Assignment $assignment): StreamedResponse
    {
        $assignment->loadMissing('lesson.section');

        if (! $course->is_published || (int) $assignment->lesson?->section?->course_id !== (int) $course->getKey()) {
            abort(404);
        }

        $customer = auth('customer')->user();

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->getKey())
            ->firstOrFail();

        // Authorise after loading so another customer gets a 403 rather than
        // a 404 that would make submissions look nonexistent.
        Gate::forUser($customer)->authorize('download', $submission);

        return $this->stream($submission);
    }

    public function downloadStaff(AssignmentSubmission $submission): StreamedResponse
    {
        Gate::authorize('download', $submission);

        return $this->stream($submission);
    }

    private function stream(AssignmentSubmission $submission): StreamedResponse
    {
        if (! $submission->hasFile()) {
            abort(404);
        }

        app(AuditLogger::class)->log(
            'submission.downloaded',
            $submission,
            [],
            ['assignment_id' => $submission->assignment_id],
        );

        return Storage::disk($submission->disk)->download(
            $submission->path,
            $submission->downloadName(),
        );
    }
}
