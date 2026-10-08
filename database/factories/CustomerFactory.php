<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            // Verified by default: most tests are about what a legitimate
            // customer can do. Use unverified() for the gate itself.
            'email_verified_at' => now(),
            'password' => 'password',
            'phone' => '08'.$this->faker->numerify('##########'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
