<?php

namespace Tests\Feature\Courses;

use App\Actions\Courses\GradeAssignmentSubmission;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AssignmentGraded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssignmentGradingTest extends TestCase
{
    use RefreshDatabase;

    public function test_grading_records_score_feedback_audit_and_notifies_the_customer(): void
    {
        Notification::fake();

        $grader = User::factory()->lmsAdmin()->create();
        $this->actingAs($grader);

        $customer = Customer::factory()->create();
        $assignment = Assignment::factory()->create(['max_score' => 100]);
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'customer_id' => $customer->id,
            'submitted_text' => 'Jawaban siswa',
        ]);

        $graded = app(GradeAssignmentSubmission::class)($submission, 85, 'Kerja bagus.');

        $this->assertSame(AssignmentSubmission::STATUS_GRADED, $graded->status);
        $this->assertSame(85, $graded->score);
        $this->assertSame('Kerja bagus.', $graded->feedback);
        $this->assertSame($grader->id, $graded->graded_by);
        $this->assertNotNull($graded->graded_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'submission.graded',
            'subject_id' => $submission->id,
        ]);

        Notification::assertSentTo($customer, AssignmentGraded::class);
    }

    public function test_a_score_above_the_assignment_maximum_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $assignment = Assignment::factory()->create(['max_score' => 100]);
        $submission = AssignmentSubmission::factory()->create(['assignment_id' => $assignment->id]);

        $this->expectException(ValidationException::class);

        app(GradeAssignmentSubmission::class)($submission, 101);
    }

    public function test_a_negative_score_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $submission = AssignmentSubmission::factory()->create();

        $this->expectException(ValidationException::class);

        app(GradeAssignmentSubmission::class)($submission, -1);
    }

    public function test_only_staff_with_the_assignments_area_can_grade(): void
    {
        $submission = AssignmentSubmission::factory()->create();

        $editor = User::factory()->editor()->create();
        $lmsAdmin = User::factory()->lmsAdmin()->create();

        $this->assertFalse($editor->can('grade', $submission));
        $this->assertTrue($lmsAdmin->can('grade', $submission));
    }
}
