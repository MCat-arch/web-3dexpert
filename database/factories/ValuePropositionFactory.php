<?php

namespace Database\Factories;

use App\Models\ValueProposition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValueProposition>
 */
class ValuePropositionFactory extends Factory
{
    protected $model = ValueProposition::class;

    public function definition(): array
    {
        $label = $this->faker->sentence(3);

        return [
            'group' => ValueProposition::GROUP_BENEFIT,
            'label' => ['id' => $label, 'en' => $label],
            'icon_path' => 'images/icons/'.$this->faker->unique()->word().'.png',
            'sort_order' => $this->faker->numberBetween(0, 30),
            'is_active' => true,
        ];
    }

    public function benefit(): static
    {
        return $this->state(fn () => ['group' => ValueProposition::GROUP_BENEFIT]);
    }

    public function process(): static
    {
        return $this->state(fn () => ['group' => ValueProposition::GROUP_PROCESS]);
    }

    public function deliverable(): static
    {
        return $this->state(fn () => ['group' => ValueProposition::GROUP_DELIVERABLE]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
