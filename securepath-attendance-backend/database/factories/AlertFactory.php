<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AlertFactory extends Factory
{
    public function definition(): array { return ['alert_code' => 'ALT-'.strtoupper(Str::random(10)), 'type' => fake()->randomElement(['unknown_face', 'low_confidence', 'repeated_absence', 'late_arrival']), 'severity' => fake()->randomElement(['warning', 'high', 'critical']), 'title' => fake()->sentence(4), 'message' => fake()->sentence(), 'status' => fake()->randomElement(['open', 'investigating', 'resolved'])]; }
}

