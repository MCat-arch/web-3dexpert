<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    public function definition(): array
    {
        return [
            'job_vacancy_id' => JobVacancy::factory(),
            'customer_id' => Customer::factory(),
            'applicant_name' => $this->faker->name(),
            'applicant_email' => $this->faker->unique()->safeEmail(),
            'applicant_phone' => '08'.$this->faker->numerify('##########'),
            'cover_letter' => $this->faker->paragraph(),
            'status' => JobApplication::STATUS_SUBMITTED,
            'status_changed_at' => now(),
            'submitted_at' => now(),
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => [
            'status' => JobApplication::STATUS_WITHDRAWN,
            'status_changed_at' => now(),
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'status_changed_at' => now(),
        ]);
    }

    /** An application whose account has been deleted: customer_id is null. */
    public function orphaned(): static
    {
        return $this->state(fn () => ['customer_id' => null]);
    }
}
