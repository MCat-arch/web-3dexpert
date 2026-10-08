<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistration>
 */
class EventRegistrationFactory extends Factory
{
    protected $model = EventRegistration::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'customer_id' => Customer::factory(),
            'attendee_name' => $this->faker->name(),
            'attendee_email' => $this->faker->unique()->safeEmail(),
            'attendee_phone' => '08'.$this->faker->numerify('##########'),
            'status' => EventRegistration::STATUS_CONFIRMED,
            'ticket_code' => EventRegistration::generateTicketCode(),
            'registered_at' => now(),
            'confirmed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => EventRegistration::STATUS_PENDING,
            'confirmed_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => EventRegistration::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function attended(): static
    {
        return $this->state(fn () => [
            'status' => EventRegistration::STATUS_ATTENDED,
            'attended_at' => now(),
        ]);
    }
}
