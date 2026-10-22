<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SubmitAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AssignmentSubmissionController extends Controller
{
    public function store(SubmitAssignmentRequest $request, Course $course, Assignment $assignment): RedirectResponse
    {
        if (! $course->is_published) {
            abort(404);
        }

        $assignment->loadMissing('lesson.section');

        if (! $assignment->is_published || (int) $assignment->lesson?->section?->course_id !== (int) $course->getKey()) {
            abort(404);
        }

        $customer = auth('customer')->user();

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->where('customer_id', $customer->getKey())
            ->active()
            ->first();

        abort_unless($enrollment, 403);

        if ($assignment->due_at?->isPast()) {
            return back()->withErrors(['file' => __('courses.assignment_deadline_passed')]);
        }

        $existing = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->getKey())
            ->where('customer_id', $customer->getKey())
            ->first();

        if ($existing?->isGraded()) {
            return back()->withErrors(['file' => __('courses.assignment_locked_graded')]);
        }

        $data = $request->validated();
        $file = $request->file('file');
        $newPath = $file?->store('assignments', 'submissions');
        $oldPath = $existing?->path;

        try {
            $submission = DB::transaction(function () use ($assignment, $customer, $existing, $data, $file, $newPath) {
                return AssignmentSubmission::query()->updateOrCreate(
                    [
                        'assignment_id' => $assignment->getKey(),
                        'customer_id' => $customer->getKey(),
                    ],
                    [
                        'submitted_text' => $data['submitted_text'] ?? null,
                        'disk' => 'submissions',
                        'path' => $newPath ?? $existing?->path,
                        'original_filename' => $file?->getClientOriginalName() ?? $existing?->original_filename,
                        'mime_type' => $file?->getMimeType() ?? $existing?->mime_type,
                        'size' => $file?->getSize() ?? $existing?->size,
                        'status' => AssignmentSubmission::STATUS_SUBMITTED,
                        'score' => null,
                        'feedback' => null,
                        'submitted_at' => now(),
                        'graded_at' => null,
                        'graded_by' => null,
                    ],
                );
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('submissions')->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('submissions')->delete($oldPath);
        }

        app(AuditLogger::class)->log(
            'submission.created',
            $submission,
            [],
            [
                'assignment_id' => $assignment->getKey(),
                'customer_id' => $customer->getKey(),
                'has_text' => filled($data['submitted_text'] ?? null),
                'has_file' => (bool) $file,
                'status' => AssignmentSubmission::STATUS_SUBMITTED,
            ],
        );

        return back()->with('status', __('courses.assignment_saved'));
    }
}
