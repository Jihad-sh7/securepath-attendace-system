<?php

namespace Tests\Feature;

use App\Contracts\FaceAiServiceInterface;
use App\Models\BiometricProfile;
use App\Models\MobileDevice;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class KioskFaceAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_image_is_matched_server_side_and_records_requested_action(): void
    {
        $device = MobileDevice::factory()->create(['identifier' => 'KIOSK-TEST']);
        Sanctum::actingAs($device, ['recognition:submit']);
        $person = Person::factory()->create(['person_code' => 'EMP-FACE', 'face_registered' => true]);
        BiometricProfile::create([
            'person_id' => $person->id,
            'status' => 'enrolled',
            'embedding' => [0.1, 0.2, 0.3],
            'embedding_dimension' => 3,
            'model_version' => 'insightface-buffalo_l',
        ]);

        $ai = Mockery::mock(FaceAiServiceInterface::class);
        $ai->shouldReceive('identify')->once()->andReturn([
            'matched' => true,
            'personId' => 'EMP-FACE',
            'confidence' => 0.91,
            'threshold' => 0.45,
            'modelVersion' => 'insightface-buffalo_l',
            'qualityScore' => 0.87,
        ]);
        $this->app->instance(FaceAiServiceInterface::class, $ai);

        $this->post('/api/v1/mobile/attendance/recognize', [
            'image' => $this->image(),
            'operation' => 'check_in',
            'eventUuid' => Str::uuid()->toString(),
            'occurredAt' => '2026-08-18T08:30:00+03:00',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.employee.employeeNumber', 'EMP-FACE')
            ->assertJsonPath('data.operation', 'check_in');

        $this->assertDatabaseHas('attendance_records', [
            'person_id' => $person->id,
            'source_identifier' => 'KIOSK-TEST',
            'verification_method' => 'face_recognition',
        ]);
    }

    public function test_kiosk_cannot_submit_an_employee_identity_and_requires_device_token(): void
    {
        $this->post('/api/v1/mobile/attendance/recognize', [
            'image' => $this->image(),
            'operation' => 'check_in',
            'eventUuid' => Str::uuid()->toString(),
            'personId' => 'EMP-SPOOFED',
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_unknown_or_low_confidence_face_creates_no_attendance(): void
    {
        $device = MobileDevice::factory()->create(['identifier' => 'KIOSK-TEST']);
        Sanctum::actingAs($device, ['recognition:submit']);
        $person = Person::factory()->create(['person_code' => 'EMP-FACE', 'face_registered' => true]);
        BiometricProfile::create([
            'person_id' => $person->id,
            'status' => 'enrolled',
            'embedding' => [0.1, 0.2, 0.3],
            'embedding_dimension' => 3,
            'model_version' => 'insightface-buffalo_l',
        ]);

        $ai = Mockery::mock(FaceAiServiceInterface::class);
        $ai->shouldReceive('identify')->once()->andReturn([
            'matched' => false,
            'personId' => null,
            'confidence' => 0.31,
            'threshold' => 0.45,
            'modelVersion' => 'insightface-buffalo_l',
            'qualityScore' => 0.82,
        ]);
        $this->app->instance(FaceAiServiceInterface::class, $ai);

        $this->post('/api/v1/mobile/attendance/recognize', [
            'image' => $this->image(),
            'operation' => 'check_in',
            'eventUuid' => Str::uuid()->toString(),
        ], ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJsonPath('recognized', false)
            ->assertJsonPath('code', 'FACE_NOT_REGISTERED')
            ->assertJsonPath('details.matched', false)
            ->assertJsonPath('details.confidence', 0.31)
            ->assertJsonPath('details.threshold', 0.45)
            ->assertJsonPath('details.qualityScore', 0.82)
            ->assertJsonPath('details.candidateCount', 1);

        $this->assertDatabaseCount('attendance_records', 0);
    }

    private function image(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        return UploadedFile::fake()->createWithContent('face.png', $png);
    }
}
