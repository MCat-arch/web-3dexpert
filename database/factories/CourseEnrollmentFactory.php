<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseEnrollmentFactory extends Factory
{
    protected $model = CourseEnrollment::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'customer_id' => Customer::factory(),
            'student_name' => $this->faker->name(),
            'student_email' => $this->faker->safeEmail(),
            'student_phone' => '08123456789',
            'status' => CourseEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
            'activated_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => CourseEnrollment::STATUS_PENDING,
            'activated_at' => null,
        ]);
    }
}
