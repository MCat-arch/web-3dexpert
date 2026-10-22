<?php

namespace App\Actions\Courses;

use App\Models\AssignmentSubmission;
use App\Notifications\AssignmentGraded;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grade one student submission.
 *
 * Kept outside the Filament action so the score bounds, audit entry and
 * notification are testable without driving a Livewire modal.
 */
class GradeAssignmentSubmission
{
    public function __invoke(AssignmentSubmission $submission, int $score, ?string $feedback = null): AssignmentSubmission
    {
        $submission->loadMissing('assignment');

        $maxScore = $submission->assignment?->max_score ?? 100;

        if ($score < 0 || $score > $maxScore) {
            throw ValidationException::withMessages([
                'score' => "Nilai harus berada di antara 0 dan {$maxScore}.",
            ]);
        }

        if (mb_strlen((string) $feedback) > 3000) {
            throw ValidationException::withMessages([
                'feedback' => 'Umpan balik maksimal 3000 karakter.',
            ]);
        }

        $old = [
            'status' => $submission->status,
            'score' => $submission->score,
        ];

        DB::transaction(function () use ($submission, $score, $feedback, $old): void {
            $submission->forceFill([
                'status' => AssignmentSubmission::STATUS_GRADED,
                'score' => $score,
                'feedback' => $feedback,
                'graded_at' => now(),
                'graded_by' => auth()->id(),
            ])->save();

            app(AuditLogger::class)->log(
                'submission.graded',
                $submission,
                $old,
                [
                    'status' => AssignmentSubmission::STATUS_GRADED,
                    'score' => $score,
                    'has_feedback' => filled($feedback),
                ],
            );
        });

        $submission = $submission->fresh(['assignment', 'customer']);

        if ($submission?->customer) {
            $submission->customer->notify(new AssignmentGraded($submission));
        }

        return $submission;
    }
}
