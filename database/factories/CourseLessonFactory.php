<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseLessonFactory extends Factory
{
    protected $model = CourseLesson::class;

    public function definition(): array
    {
        $title = 'Materi '.$this->faker->words(3, true);

        return [
            'course_section_id' => CourseSection::factory(),
            'title' => ['id' => $title, 'en' => $title],
            'content_type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'youtube_id' => 'dQw4w9WgXcQ',
            'duration_seconds' => 600,
            'is_free_preview' => false,
            'sort_order' => 1,
            'body' => ['id' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
        ];
    }

    public function preview(): static
    {
        return $this->state(fn () => ['is_free_preview' => true]);
    }
}
