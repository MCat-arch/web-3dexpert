<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'customer_id' => Customer::factory(),
            'submitted_text' => $this->faker->paragraph(),
            'disk' => 'submissions',
            'status' => AssignmentSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ];
    }

    public function graded(): static
    {
        return $this->state([
            'status' => AssignmentSubmission::STATUS_GRADED,
            'score' => 85,
            'feedback' => 'Kerja bagus.',
            'graded_at' => now(),
        ]);
    }
}
