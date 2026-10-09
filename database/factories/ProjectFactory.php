<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            // Left null so the model's creating hook generates a real one --
            // tests must exercise the actual code generator.
            'project_code' => null,
            'customer_name' => $this->faker->name(),
            'customer_phone' => '08'.$this->faker->numerify('##########'),
            'customer_email' => $this->faker->unique()->safeEmail(),
            'title' => 'Desain '.$this->faker->words(2, true),
            'current_stage' => 'Desain 3D',
            'status' => Project::STATUS_IN_PROGRESS,
            'started_at' => now()->subWeeks(3),
            'estimated_completion_at' => now()->addWeeks(4),
            'is_visible' => true,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Project::STATUS_COMPLETED,
            'completed_at' => now()->subDays(3),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Project::STATUS_CANCELLED]);
    }

    public function onHold(): static
    {
        return $this->state(fn () => ['status' => Project::STATUS_ON_HOLD]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_visible' => false]);
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['customer_email' => null]);
    }

    public function withoutPhone(): static
    {
        return $this->state(fn () => ['customer_phone' => null]);
    }
}
