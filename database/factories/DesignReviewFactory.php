<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DesignReviewFactory extends Factory
{
    public function definition(): array
    {
        $id = Str::random(11);

        return [
            'youtube_url' => 'https://www.youtube.com/watch?v='.$id,
            'title' => ['id' => fake()->sentence(3), 'en' => fake()->sentence(3)],
            'is_published' => true,
        ];
    }
}
