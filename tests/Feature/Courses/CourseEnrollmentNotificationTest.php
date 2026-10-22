<?php

namespace Tests\Feature\Courses;

use App\Actions\Courses\GrantCourseAccess;
use App\Actions\Courses\SimulatePaymentSettlement;
use App\Actions\Courses\StartCourseRegistration;
use App\Models\Course;
use App\Models\Customer;
use App\Notifications\Courses\CourseEnrollmentActivated;
use App\Notifications\Courses\CourseRegistrationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CourseEnrollmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_free_course_sends_an_activation_email(): void
    {
        $customer = Customer::factory()->create();
        $course = Course::factory()->create([
            'is_published' => true,
            'price' => 0,
        ]);

        app(StartCourseRegistration::class)($course, $customer);

        Notification::assertSentTo($customer, CourseEnrollmentActivated::class);
    }

    public function test_a_paid_course_sends_registration_and_activation_emails(): void
    {
        $customer = Customer::factory()->create();
        $course = Course::factory()->create([
            'is_published' => true,
            'price' => 150000,
        ]);

        $result = app(StartCourseRegistration::class)($course, $customer);

        Notification::assertSentTo($customer, CourseRegistrationReceived::class);
        Notification::assertNotSentTo($customer, CourseEnrollmentActivated::class);

        app(SimulatePaymentSettlement::class)($result['payment']);

        Notification::assertSentTo($customer, CourseEnrollmentActivated::class);
    }

    public function test_staff_grant_sends_one_activation_email(): void
    {
        $customer = Customer::factory()->create();
        $course = Course::factory()->create([
            'is_published' => true,
            'price' => 150000,
        ]);

        app(GrantCourseAccess::class)($course, $customer);
        app(GrantCourseAccess::class)($course, $customer);

        Notification::assertSentToTimes($customer, CourseEnrollmentActivated::class, 1);
    }
}
