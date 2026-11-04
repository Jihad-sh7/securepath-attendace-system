<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AttendanceRecordFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days'); $checkIn = fake()->dateTimeBetween($date->format('Y-m-d').' 07:30', $date->format('Y-m-d').' 09:30');
        return ['event_code' => 'EVT-'.strtoupper(Str::random(10)), 'person_id' => Person::factory(), 'attendance_date' => $date->format('Y-m-d'), 'scheduled_start' => '08:30', 'scheduled_end' => '17:00', 'check_in_at' => $checkIn, 'check_out_at' => null, 'status' => $checkIn->format('H:i') > '08:40' ? 'late' : 'present', 'minutes_late' => 0, 'verification_method' => 'face_recognition', 'recognition_confidence' => fake()->randomFloat(4, 0.82, 0.99), 'liveness_result' => 'passed', 'source' => 'mobile', 'source_identifier' => 'MOBILE-KIOSK-01'];
    }
}

