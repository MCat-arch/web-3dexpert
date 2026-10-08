<?php

namespace Database\Factories;

use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobApplicationDocument>
 */
class JobApplicationDocumentFactory extends Factory
{
    protected $model = JobApplicationDocument::class;

    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'type' => JobApplicationDocument::TYPE_CV,
            'disk' => 'applications',
            // Matches the generated shape used by SubmitJobApplication: a uuid,
            // never the uploaded filename.
            'path' => 'applications/1/'.Str::uuid()->toString().'.pdf',
            'original_filename' => 'cv-'.$this->faker->lastName().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(50_000, 2_000_000),
            'checksum' => hash('sha256', Str::random(32)),
        ];
    }
}
