<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DepartmentFactory extends Factory
{
    public function definition(): array { $name = fake()->unique()->jobTitle(); return ['name' => $name, 'code' => 'DEP-'.strtoupper(Str::substr(Str::slug($name, ''), 0, 6)), 'description' => fake()->sentence(), 'status' => 'active']; }
}

