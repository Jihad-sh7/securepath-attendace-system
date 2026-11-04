<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RecognitionEventFactory extends Factory
{
    public function definition(): array { return ['recognition_code' => 'REC-'.strtoupper(Str::random(10)), 'event_uuid' => fake()->uuid(), 'person_id' => Person::factory(), 'confidence' => fake()->randomFloat(4, 0.82, 0.99), 'liveness_result' => 'passed', 'recognition_status' => 'matched', 'verification_status' => 'automatic', 'source_device_identifier' => 'MOBILE-KIOSK-01', 'location' => 'Main Entrance', 'occurred_at' => fake()->dateTimeBetween('-30 days')]; }
}

