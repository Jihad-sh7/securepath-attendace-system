<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    public function definition(): array
    {
        $first = fake()->firstName(); $last = fake()->lastName();
        return ['person_code' => 'EMP-'.fake()->unique()->numberBetween(10000, 99999), 'first_name' => $first, 'last_name' => $last, 'full_name' => "{$first} {$last}", 'email' => fake()->unique()->safeEmail(), 'phone' => fake()->e164PhoneNumber(), 'department_id' => Department::factory(), 'type' => 'employee', 'job_title' => fake()->jobTitle(), 'status' => 'active', 'face_registered' => fake()->boolean(85), 'biometric_consent' => fake()->boolean(90)];
    }
}

