<?php

namespace Database\Factories;

use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\LessonCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonCompletionFactory extends Factory
{
    protected $model = LessonCompletion::class;

    public function definition(): array
    {
        return [
            'course_enrollment_id' => CourseEnrollment::factory(),
            'course_lesson_id' => CourseLesson::factory(),
            'completed_at' => now(),
        ];
    }
}
