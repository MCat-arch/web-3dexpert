<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = 'Kursus '.$this->faker->unique()->words(3, true);

        return [
            'title' => ['id' => $title, 'en' => $title],
            'slug' => null,
            'description' => ['id' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
            'price' => 350000.00,
            'level' => 'beginner',
            'estimated_minutes' => 120,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => 0]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
