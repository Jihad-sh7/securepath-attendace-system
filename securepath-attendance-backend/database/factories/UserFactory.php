<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'phone' => fake()->e164PhoneNumber(), 'password' => 'Password123!', 'role' => UserRole::User, 'status' => AccountStatus::Active, 'title' => 'Employee', 'email_verified_at' => now()];
    }
    public function admin(): static { return $this->state(fn () => ['role' => UserRole::Admin, 'title' => 'Admin']); }
}

