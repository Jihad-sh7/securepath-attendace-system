<?php

namespace Tests\Feature;

use App\Models\MobileDevice;
use App\Models\BiometricProfile;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecognitionApiTest extends TestCase
{
    use RefreshDatabase;

    protected MobileDevice $device;
    protected function setUp(): void { parent::setUp(); \App\Models\Setting::create(['group' => 'biometrics', 'key' => 'confidenceThreshold', 'value' => '0.85', 'type' => 'float']); \App\Models\Setting::create(['group' => 'attendance', 'key' => 'duplicateRecognitionWindowSeconds', 'value' => '60', 'type' => 'integer']); $this->device = MobileDevice::factory()->create(['identifier' => 'DEVICE-1']); Sanctum::actingAs($this->device, ['recognition:submit']); }

    public function test_recognition_creates_attendance_and_same_event_uuid_is_idempotent(): void
    {
        $person = Person::factory()->create(['person_code' => 'EMP-REC', 'face_registered' => true]); BiometricProfile::create(['person_id' => $person->id, 'status' => 'registered', 'model_version' => 'legacy-test']); $uuid = Str::uuid()->toString();
        $payload = ['eventUuid' => $uuid, 'personId' => $person->person_code, 'confidence' => .96, 'livenessPassed' => true, 'occurredAt' => '2026-08-15T08:31:22+03:00', 'deviceIdentifier' => 'spoofed'];
        $this->postJson('/api/v1/mobile/recognition-events', $payload)->assertCreated()->assertJsonPath('data.recognitionStatus', 'matched')->assertJsonPath('data.deviceIdentifier', 'DEVICE-1');
        $this->postJson('/api/v1/mobile/recognition-events', $payload)->assertCreated();
        $this->assertDatabaseCount('recognition_events', 1); $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_unknown_low_confidence_and_liveness_failures_require_review_and_create_alerts(): void
    {
        $person = Person::factory()->create(['person_code' => 'EMP-LOW', 'face_registered' => true]); BiometricProfile::create(['person_id' => $person->id, 'status' => 'registered', 'model_version' => 'legacy-test']);
        foreach ([['personId' => null, 'confidence' => .1, 'livenessPassed' => true], ['personId' => $person->person_code, 'confidence' => .6, 'livenessPassed' => true], ['personId' => $person->person_code, 'confidence' => .99, 'livenessPassed' => false]] as $index => $variant) {
            $this->postJson('/api/v1/mobile/recognition-events', $variant + ['eventUuid' => Str::uuid()->toString(), 'occurredAt' => now()->addMinutes($index)->toIso8601String(), 'deviceIdentifier' => 'DEVICE-1'])->assertCreated()->assertJsonPath('data.verificationStatus', 'pending');
        }
        $this->assertDatabaseCount('alerts', 3);
    }
}
