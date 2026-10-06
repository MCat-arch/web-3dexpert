<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password-for-testing-only',
            'role' => User::ROLE_EDITOR,
            'is_active' => true,
        ];
    }

    public function superadmin(): static
    {
        return $this->state(['role' => User::ROLE_SUPERADMIN]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
