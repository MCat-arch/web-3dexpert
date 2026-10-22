<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\CourseLesson;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        $title = 'Tugas '.$this->faker->words(3, true);

        return [
            'course_lesson_id' => CourseLesson::factory(),
            'title' => ['id' => $title, 'en' => $title],
            'instructions' => [
                'id' => $this->faker->paragraph(),
                'en' => $this->faker->paragraph(),
            ],
            'due_at' => now()->addWeek(),
            'max_score' => 100,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function closed(): static
    {
        return $this->state(['due_at' => now()->subDay()]);
    }
}
