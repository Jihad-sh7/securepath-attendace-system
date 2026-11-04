<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MobileDeviceFactory extends Factory
{
    public function definition(): array { return ['name' => fake()->company().' Kiosk', 'identifier' => 'DEVICE-'.strtoupper(Str::random(10)), 'secret' => 'DeviceSecret123!', 'status' => 'active', 'metadata' => ['platform' => 'test']]; }
}

