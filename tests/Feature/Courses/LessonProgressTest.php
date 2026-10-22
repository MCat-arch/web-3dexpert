<?php

namespace Tests\Feature\Courses;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\Customer;
use App\Models\LessonCompletion;
use App\Support\CourseProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithLessons(int $count = 2): array
    {
        $course = Course::factory()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $lessons = CourseLesson::factory()->count($count)->create([
            'course_section_id' => $section->id,
        ]);

        return [$course, $section, $lessons];
    }

    private function enrolledCustomer(Course $course, string $status = CourseEnrollment::STATUS_ACTIVE): array
    {
        $customer = Customer::factory()->create();
        $enrollment = CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'customer_id' => $customer->id,
            'status' => $status,
        ]);

        return [$customer, $enrollment];
    }

    public function test_lesson_completion_is_idempotent_and_audited_once(): void
    {
        [$course, , $lessons] = $this->courseWithLessons();
        [$customer] = $this->enrolledCustomer($course);

        $url = route('courses.lesson.complete', [
            'course' => $course->slug,
            'lesson' => $lessons->first()->id,
        ]);

        $this->actingAs($customer, 'customer')->post($url)->assertRedirect();
        $this->actingAs($customer, 'customer')->post($url)->assertRedirect();

        $this->assertSame(1, LessonCompletion::query()->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'lesson.completed')->count());
    }

    public function test_progress_math_and_completion_only_change_when_the_last_lesson_is_finished(): void
    {
        [$course, , $lessons] = $this->courseWithLessons(2);
        [$customer, $enrollment] = $this->enrolledCustomer($course);

        $enrollment->setRelation('course', $course->loadCount('lessons'));
        $enrollment->loadCount('completions');

        $this->assertSame(0, CourseProgress::for($enrollment)['percent']);

        $this->actingAs($customer, 'customer')->post(route('courses.lesson.complete', [
            'course' => $course->slug,
            'lesson' => $lessons[0]->id,
        ]))->assertRedirect();

        $enrollment = $enrollment->fresh();
        $enrollment->setRelation('course', $course->loadCount('lessons'));
        $enrollment->loadCount('completions');

        $progress = CourseProgress::for($enrollment);
        $this->assertSame(1, $progress['completed']);
        $this->assertSame(2, $progress['total']);
        $this->assertSame(50, $progress['percent']);
        $this->assertNull($enrollment->completed_at);

        $this->actingAs($customer, 'customer')->post(route('courses.lesson.complete', [
            'course' => $course->slug,
            'lesson' => $lessons[1]->id,
        ]))->assertRedirect();

        $enrollment = $enrollment->fresh();
        $enrollment->setRelation('course', $course->loadCount('lessons'));
        $enrollment->loadCount('completions');

        $this->assertSame(100, CourseProgress::for($enrollment)['percent']);
        $this->assertNotNull($enrollment->completed_at);
    }

    public function test_a_lesson_from_another_course_is_rejected(): void
    {
        [$courseA] = $this->courseWithLessons(1);
        [$courseB, , $lessonsB] = $this->courseWithLessons(1);
        [$customer] = $this->enrolledCustomer($courseA);

        $this->actingAs($customer, 'customer')
            ->post(route('courses.lesson.complete', [
                'course' => $courseA->slug,
                'lesson' => $lessonsB->first()->id,
            ]))
            ->assertNotFound();

        $this->assertSame(0, LessonCompletion::query()->count());
    }

    public function test_a_revoked_enrollment_cannot_complete_a_lesson(): void
    {
        [$course, , $lessons] = $this->courseWithLessons(1);
        [$customer] = $this->enrolledCustomer($course, CourseEnrollment::STATUS_REVOKED);

        $this->actingAs($customer, 'customer')
            ->post(route('courses.lesson.complete', [
                'course' => $course->slug,
                'lesson' => $lessons->first()->id,
            ]))
            ->assertForbidden();

        $this->assertSame(0, LessonCompletion::query()->count());
    }

    public function test_a_loaded_progress_row_does_not_run_queries(): void
    {
        [$course] = $this->courseWithLessons(1);
        [, $enrollment] = $this->enrolledCustomer($course);

        $course->loadCount('lessons');

        $enrollment = CourseEnrollment::query()
            ->whereKey($enrollment->id)
            ->withCount('completions')
            ->withMax('completions', 'completed_at')
            ->firstOrFail();
        $enrollment->setRelation('course', $course);

        DB::flushQueryLog();
        DB::enableQueryLog();

        CourseProgress::for($enrollment);

        $this->assertCount(0, DB::getQueryLog());
    }
}
