<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        $question = rtrim($this->faker->sentence(6), '.').'?';

        return [
            // Translatable columns take the per-locale array, not a string.
            'question' => ['id' => $question, 'en' => $question],
            'answer' => ['id' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
