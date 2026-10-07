<?php

namespace Database\Factories;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    protected $model = HeroSlide::class;

    public function definition(): array
    {
        $alt = $this->faker->sentence(4);

        return [
            // A media path, not a URL: images are served through
            // MediaController, which resolves this against media_assets.
            'image_path' => 'images/hero_'.$this->faker->unique()->numberBetween(1, 9999).'.jpg',
            'alt_text' => ['id' => $alt, 'en' => $alt],
            'caption' => ['id' => $this->faker->sentence(), 'en' => $this->faker->sentence()],
            'link_url' => null,
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
