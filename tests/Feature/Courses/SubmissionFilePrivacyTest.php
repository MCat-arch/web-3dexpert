<?php

namespace Tests\Feature\Courses;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionFilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function setupFileSubmission(): array
    {
        Storage::fake('submissions');

        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create(['course_section_id' => $section->id]);
        $assignment = Assignment::factory()->create(['course_lesson_id' => $lesson->id]);

        $owner = Customer::factory()->create();
        CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'customer_id' => $owner->id,
            'status' => CourseEnrollment::STATUS_ACTIVE,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'customer_id' => $owner->id,
            'disk' => 'submissions',
            'path' => 'assignments/private.pdf',
            'original_filename' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12,
        ]);

        Storage::disk('submissions')->put($submission->path, '%PDF-1.4 test');

        return [$course, $assignment, $submission, $owner];
    }

    public function test_the_private_disk_has_no_public_storage_url(): void
    {
        $this->setupFileSubmission();

        $this->get('/storage/assignments/private.pdf')->assertNotFound();
    }

    public function test_the_owner_can_download_their_own_submission(): void
    {
        [$course, $assignment, , $owner] = $this->setupFileSubmission();

        $this->actingAs($owner, 'customer')
            ->get(route('courses.assignment.download', [
                'course' => $course->slug,
                'assignment' => $assignment->id,
            ]))
            ->assertOk();
    }

    public function test_another_customer_cannot_download_the_submission(): void
    {
        [$course, $assignment] = $this->setupFileSubmission();

        $other = Customer::factory()->create();
        CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'customer_id' => $other->id,
            'status' => CourseEnrollment::STATUS_ACTIVE,
        ]);

        $this->actingAs($other, 'customer')
            ->get(route('courses.assignment.download', [
                'course' => $course->slug,
                'assignment' => $assignment->id,
            ]))
            ->assertForbidden();
    }

    public function test_lms_admin_and_admin_can_download_but_an_editor_cannot(): void
    {
        [, , $submission] = $this->setupFileSubmission();

        $this->actingAs(User::factory()->lmsAdmin()->create())
            ->get(route('admin.assignment-submissions.download', $submission))
            ->assertOk();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.assignment-submissions.download', $submission))
            ->assertOk();

        $this->actingAs(User::factory()->editor()->create())
            ->get(route('admin.assignment-submissions.download', $submission))
            ->assertForbidden();
    }
}
