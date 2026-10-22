<?php

namespace Tests\Feature\Courses;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function setupCourse(bool $enrolled = true, string $status = CourseEnrollment::STATUS_ACTIVE): array
    {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create(['course_section_id' => $section->id]);
        $assignment = Assignment::factory()->create([
            'course_lesson_id' => $lesson->id,
        ]);

        $customer = Customer::factory()->create();

        if ($enrolled) {
            CourseEnrollment::factory()->create([
                'course_id' => $course->id,
                'customer_id' => $customer->id,
                'status' => $status,
            ]);
        }

        return [$course, $lesson, $assignment, $customer];
    }

    private function submitUrl(Course $course, Assignment $assignment): string
    {
        return route('courses.assignment.submit', [
            'course' => $course->slug,
            'assignment' => $assignment->id,
        ]);
    }

    public function test_a_student_can_submit_text_and_it_is_audited(): void
    {
        [$course, , $assignment, $customer] = $this->setupCourse();

        $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $assignment), [
                'submitted_text' => 'Ini jawaban tugas saya.',
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::query()->sole();

        $this->assertSame(AssignmentSubmission::STATUS_SUBMITTED, $submission->status);
        $this->assertSame('Ini jawaban tugas saya.', $submission->submitted_text);
        $this->assertSame(1, AuditLog::query()->where('event', 'submission.created')->count());
    }

    public function test_a_student_can_upload_a_file_to_the_private_disk(): void
    {
        Storage::fake('submissions');

        [$course, , $assignment, $customer] = $this->setupCourse();

        $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $assignment), [
                'file' => UploadedFile::fake()->create('tugas.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $submission = AssignmentSubmission::query()->sole();

        $this->assertSame('submissions', $submission->disk);
        $this->assertSame('tugas.pdf', $submission->original_filename);
        $this->assertNotNull($submission->path);
        Storage::disk('submissions')->assertExists($submission->path);
    }

    public function test_resubmitting_before_grading_replaces_the_existing_row(): void
    {
        Storage::fake('submissions');

        [$course, , $assignment, $customer] = $this->setupCourse();

        $first = $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $assignment), [
                'submitted_text' => 'Versi pertama',
            ]);
        $first->assertRedirect();

        $submission = AssignmentSubmission::query()->sole();

        $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $assignment), [
                'submitted_text' => 'Versi kedua',
                'file' => UploadedFile::fake()->create('revisi.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertSame(1, AssignmentSubmission::query()->count());
        $this->assertSame('Versi kedua', $submission->fresh()->submitted_text);
        $this->assertSame('revisi.pdf', $submission->fresh()->original_filename);
    }

    public function test_a_graded_submission_cannot_be_changed(): void
    {
        [$course, , $assignment, $customer] = $this->setupCourse();

        $submission = AssignmentSubmission::factory()->graded()->create([
            'assignment_id' => $assignment->id,
            'customer_id' => $customer->id,
            'submitted_text' => 'Sudah dinilai',
        ]);

        $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $assignment), [
                'submitted_text' => 'Percobaan mengubah nilai',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame('Sudah dinilai', $submission->fresh()->submitted_text);
    }

    public function test_a_submission_after_the_deadline_is_rejected(): void
    {
        [$course, , $assignment, $customer] = $this->setupCourse();

        $assignment->forceFill(['due_at' => now()->subMinute()])->save();

        $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $assignment), [
                'submitted_text' => 'Terlambat',
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    public function test_an_assignment_from_another_course_is_rejected(): void
    {
        [$course, , , $customer] = $this->setupCourse();

        [, , $otherAssignment] = $this->setupCourse();

        $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $otherAssignment), [
                'submitted_text' => 'Salah kursus',
            ])
            ->assertNotFound();

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }

    public function test_a_customer_without_an_active_enrollment_is_forbidden(): void
    {
        [$course, , $assignment, $customer] = $this->setupCourse(false);

        $this->actingAs($customer, 'customer')
            ->post($this->submitUrl($course, $assignment), [
                'submitted_text' => 'Tidak terdaftar',
            ])
            ->assertForbidden();

        $this->assertSame(0, AssignmentSubmission::query()->count());
    }
}
