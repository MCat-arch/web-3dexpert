<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TestimonialFactory extends Factory
{
    public function definition(): array
    {
        // A valid 11-character YouTube id, so youtube_id resolves and the model
        // does not get flagged as broken by app:verify-migration.
        $id = Str::random(11);

        return [
            'youtube_url' => 'https://www.youtube.com/watch?v='.$id,
            'title' => ['id' => fake()->sentence(3), 'en' => fake()->sentence(3)],
            'rating' => fake()->numberBetween(4, 5),
            'is_highlighted' => false,
            'is_published' => true,
        ];
    }

    public function highlighted(): static
    {
        return $this->state(['is_highlighted' => true]);
    }
}
