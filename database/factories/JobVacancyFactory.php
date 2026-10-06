<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JobVacancyFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->jobTitle();

        return [
            'title' => ['id' => $title, 'en' => $title],
            'location' => fake()->randomElement(['Surabaya', 'Bandung', 'Jakarta']),
            'department' => fake()->randomElement(['IT', 'Marketing', 'Arsitektur', 'Finance']),
            'employment_type' => fake()->randomElement(['Full Time', 'Part Time', 'Internship', 'Contract']),
            'requirement' => [
                'id' => fake()->sentence(8),
                'en' => fake()->sentence(8),
            ],
            'contact_url' => 'https://wa.me/628'.fake()->numerify('##########'),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function closed(): static
    {
        return $this->state(['closes_at' => now()->subDay()]);
    }
}
