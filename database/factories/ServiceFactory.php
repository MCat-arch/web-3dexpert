<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);
        $original = fake()->numberBetween(20, 200) * 1000;

        return [
            'title' => ['id' => $title, 'en' => $title],
            'price' => (int) round($original * 0.825),
            'original_price' => $original,
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function needsReview(): static
    {
        return $this->state(['needs_review' => true, 'is_published' => false]);
    }
}
