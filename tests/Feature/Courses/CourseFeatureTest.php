<?php

namespace Tests\Feature\Courses;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_renders_in_both_locales(): void
    {
        $course = Course::factory()->create([
            'title' => ['id' => 'Kursus Blender', 'en' => 'Blender Course'],
            'is_published' => true,
        ]);

        $responseId = $this->get('/kursus');
        $responseId->assertStatus(200);
        $responseId->assertSee('Kursus Blender');

        $responseEn = $this->get('/en/kursus');
        $responseEn->assertStatus(200);
        $responseEn->assertSee('Blender Course');
    }

    public function test_catalog_hides_unpublished_courses(): void
    {
        Course::factory()->unpublished()->create([
            'title' => ['id' => 'Draft Rahasia', 'en' => 'Secret Draft'],
        ]);

        $response = $this->get('/kursus');
        $response->assertStatus(200);
        $response->assertDontSee('Draft Rahasia');
    }

    public function test_course_detail_renders_with_syllabus(): void
    {
        $course = Course::factory()->create([
            'title' => ['id' => 'Kursus Arsitektur 3D', 'en' => '3D Architecture Course'],
            'is_published' => true,
        ]);

        $section = CourseSection::factory()->create([
            'course_id' => $course->id,
            'title' => ['id' => 'Pengenalan Modul', 'en' => 'Module Intro'],
        ]);

        CourseLesson::factory()->create([
            'course_section_id' => $section->id,
            'title' => ['id' => 'Dasar Modeling', 'en' => 'Modeling Basics'],
        ]);

        $response = $this->get('/kursus/'.$course->slug);
        $response->assertStatus(200);
        $response->assertSee('Kursus Arsitektur 3D');
        $response->assertSee('Pengenalan Modul');
        $response->assertSee('Dasar Modeling');
    }

    public function test_locked_lesson_does_not_leak_youtube_id(): void
    {
        $course = Course::factory()->create([
            'is_published' => true,
            'price' => 200000.00,
        ]);

        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $lockedLesson = CourseLesson::factory()->create([
            'course_section_id' => $section->id,
            'title' => ['id' => 'Pelajaran Rahasia Berbayar', 'en' => 'Paid Secret Lesson'],
            'youtube_id' => 'SECRET_YT_99',
            'is_free_preview' => false,
        ]);

        // 1. Check Detail Page outline does not contain SECRET_YT_99
        $response = $this->get('/kursus/'.$course->slug);
        $response->assertStatus(200);
        $response->assertDontSee('SECRET_YT_99');

        // 2. Direct player access for locked lesson without enrollment is blocked
        $playerResponse = $this->get('/kursus/'.$course->slug.'/belajar/'.$lockedLesson->id);
        $playerResponse->assertRedirect('/kursus/'.$course->slug.'/checkout');
    }

    public function test_free_preview_lesson_is_accessible_to_guest(): void
    {
        $course = Course::factory()->create([
            'is_published' => true,
            'price' => 200000.00,
        ]);

        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $previewLesson = CourseLesson::factory()->preview()->create([
            'course_section_id' => $section->id,
            'title' => ['id' => 'Materi Pembuka Gratis', 'en' => 'Free Intro Lesson'],
            'youtube_url' => 'https://youtu.be/PREVIEW_YT1',
        ]);

        $response = $this->get('/kursus/'.$course->slug.'/belajar/'.$previewLesson->id);
        $response->assertStatus(200);
        $response->assertSee('PREVIEW_YT1');
    }

    public function test_verified_customer_can_checkout_and_simulate_payment(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $course = Course::factory()->create([
            'is_published' => true,
            'price' => 150000.00,
        ]);

        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create([
            'course_section_id' => $section->id,
            'youtube_url' => 'https://youtu.be/COURSE_PAID',
            'is_free_preview' => false,
        ]);

        // Checkout store
        $checkoutResponse = $this->actingAs($customer, 'customer')
            ->post('/kursus/'.$course->slug.'/checkout', [
                'payment_method' => 'qris',
                'student_name' => 'Budi Tester',
                'student_phone' => '08123456789',
            ]);

        $this->assertDatabaseHas('course_enrollments', [
            'course_id' => $course->id,
            'customer_id' => $customer->id,
            'status' => CourseEnrollment::STATUS_PENDING,
        ]);

        $payment = Payment::where('status', Payment::STATUS_PENDING)->first();
        $this->assertNotNull($payment);

        $checkoutResponse->assertRedirect('/pembayaran/'.$payment->order_id);

        // Payment Instruction page renders
        $paymentPage = $this->actingAs($customer, 'customer')
            ->get('/pembayaran/'.$payment->order_id);
        $paymentPage->assertStatus(200);
        $paymentPage->assertSee($payment->order_id);

        // Simulate successful payment
        $simulateResponse = $this->actingAs($customer, 'customer')
            ->post('/pembayaran/'.$payment->order_id.'/simulasi');

        $simulateResponse->assertRedirect('/kursus/'.$course->slug.'/belajar');

        // Enrollment is now active
        $this->assertDatabaseHas('course_enrollments', [
            'course_id' => $course->id,
            'customer_id' => $customer->id,
            'status' => CourseEnrollment::STATUS_ACTIVE,
        ]);

        // Student can now watch the paid lesson
        $playerResponse = $this->actingAs($customer, 'customer')
            ->get('/kursus/'.$course->slug.'/belajar/'.$lesson->id);
        $playerResponse->assertStatus(200);
        $playerResponse->assertSee('COURSE_PAID');
    }
}
