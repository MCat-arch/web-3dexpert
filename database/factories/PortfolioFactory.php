<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PortfolioFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => ['id' => $title, 'en' => $title],
            'description' => [
                'id' => fake()->paragraphs(2, true),
                'en' => fake()->paragraphs(2, true),
            ],
            'price' => fake()->numberBetween(300, 5000) * 1_000_000,
            'location' => fake()->randomElement([
                'Sidoarjo, Jawa Timur', 'Surabaya, Jawa Timur',
                'Bandung, Jawa Barat', 'Jakarta Selatan',
            ]),
            'style' => fake()->randomElement(['Modern', 'Classic-Tropis', 'Industrial', 'Minimalis']),
            'floors' => fake()->numberBetween(1, 3),
            'bedrooms' => fake()->numberBetween(2, 6),
            'bathrooms' => fake()->numberBetween(1, 5),
            'land_size' => fake()->numberBetween(80, 600),
            'building_size' => fake()->numberBetween(60, 500),
            'is_published' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
