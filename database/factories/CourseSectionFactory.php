<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseSectionFactory extends Factory
{
    protected $model = CourseSection::class;

    public function definition(): array
    {
        $title = 'Bagian '.$this->faker->words(2, true);

        return [
            'course_id' => Course::factory(),
            'title' => ['id' => $title, 'en' => $title],
            'sort_order' => 1,
        ];
    }
}
