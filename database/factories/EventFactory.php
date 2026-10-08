<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = 'Workshop '.$this->faker->unique()->words(3, true);

        return [
            'title' => ['id' => $title, 'en' => $title],
            'slug' => null,
            'description' => ['id' => $this->faker->paragraph(), 'en' => $this->faker->paragraph()],
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(2)->addHours(3),
            'location_type' => Event::LOCATION_OFFLINE,
            'venue' => $this->faker->city(),
            'capacity' => 50,
            'price' => 0,
            'is_published' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['price' => 0]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['price' => 250000]);
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['capacity' => null]);
    }

    public function capacity(int $seats): static
    {
        return $this->state(fn () => ['capacity' => $seats]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'seats_taken' => $attributes['capacity'] ?? 50,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHours(3),
        ]);
    }

    public function registrationClosed(): static
    {
        return $this->state(fn () => ['registration_closes_at' => now()->subDay()]);
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'location_type' => Event::LOCATION_ONLINE,
            'venue' => null,
            'online_url' => 'https://meet.example.test/'.$this->faker->slug(),
        ]);
    }
}
