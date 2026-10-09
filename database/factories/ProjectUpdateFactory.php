<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectUpdate>
 */
class ProjectUpdateFactory extends Factory
{
    protected $model = ProjectUpdate::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'stage_name' => $this->faker->randomElement(['Survey Lokasi', 'Desain 3D', 'Revisi', 'Gambar Kerja']),
            'status' => ProjectUpdate::STATUS_DONE,
            'note' => $this->faker->sentence(),
            'occurred_at' => now()->subDays($this->faker->numberBetween(1, 20)),
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
