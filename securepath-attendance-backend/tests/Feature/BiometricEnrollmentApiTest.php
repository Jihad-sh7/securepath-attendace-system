<?php

namespace Tests\Feature;

use App\Enums\BiometricStatus;
use App\Models\BiometricProfile;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesAuthorizedUsers;
use Tests\TestCase;

class BiometricEnrollmentApiTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('biometrics');
    }

    public function test_enrollment_requires_permission_and_all_three_images(): void
    {
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true]);
        $images = $this->images();

        $this->actingAs($this->adminWith(['biometrics.view']), 'sanctum')
            ->postJson("/api/v1/people/{$person->person_code}/biometric-enrollment", $images)->assertForbidden();

        $admin = $this->adminWith(['biometrics.enroll']);
        unset($images['right']);
        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/people/{$person->person_code}/biometric-enrollment", $images, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors('right');
    }

    public function test_samples_are_private_and_enrollment_fails_when_ai_is_not_configured(): void
    {
        config(['services.face_ai.url' => null]);
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true]);

        $this->actingAs($this->adminWith(['biometrics.enroll']), 'sanctum')
            ->post("/api/v1/people/{$person->person_code}/biometric-enrollment", $this->images(), ['Accept' => 'application/json'])
            ->assertServiceUnavailable()->assertJsonPath('code', 'FACE_AI_UNAVAILABLE')
            ->assertJsonMissingPath('data.embedding')->assertJsonMissingPath('data.imagePath');

        $this->assertDatabaseCount('face_samples', 3);
        $person->faceSamples()->get()->each(fn ($sample) => Storage::disk('biometrics')->assertExists($sample->image_path));
        $this->assertSame('failed', $person->fresh()->biometricProfile->status->value);
        $this->assertDatabaseHas('biometric_enrollment_sessions', ['person_id' => $person->id, 'status' => 'failed']);
    }

    public function test_pending_or_failed_enrollment_can_retry_stored_samples(): void
    {
        config(['services.face_ai.url' => null]);
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true]);
        $admin = $this->adminWith(['biometrics.enroll']);
        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/people/{$person->person_code}/biometric-enrollment", $this->images(), ['Accept' => 'application/json'])
            ->assertServiceUnavailable();

        config(['services.face_ai.url' => 'http://face-ai.test']);
        Http::fake(['http://face-ai.test/enroll' => Http::response([
            'success' => true,
            'embedding' => [0.6, 0.8],
            'embeddingDimension' => 2,
            'modelVersion' => 'insightface-buffalo_l',
            'qualityScore' => 0.91,
            'sampleCount' => 3,
        ])]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/people/{$person->person_code}/biometric-enrollment/retry")
            ->assertOk()->assertJsonPath('data.status', 'enrolled')->assertJsonPath('data.modelVersion', 'insightface-buffalo_l');

        $profile = $person->fresh()->biometricProfile;
        $this->assertSame(BiometricStatus::Enrolled, $profile->status);
        $this->assertSame([0.6, 0.8], $profile->embedding);
        $this->assertSame(2, $profile->embedding_dimension);
        $this->assertTrue($person->fresh()->face_registered);
        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric.enrollment_retried', 'resource_id' => $person->person_code]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric.enrollment_completed', 'resource_id' => $person->person_code]);
    }

    public function test_retry_requires_permission_and_ai_errors_remain_failed(): void
    {
        config(['services.face_ai.url' => null]);
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true]);
        $admin = $this->adminWith(['biometrics.enroll']);
        $this->actingAs($admin, 'sanctum')
            ->post("/api/v1/people/{$person->person_code}/biometric-enrollment", $this->images(), ['Accept' => 'application/json'])
            ->assertServiceUnavailable();

        config(['services.face_ai.url' => 'http://face-ai.test']);
        Http::fake(['http://face-ai.test/enroll' => Http::response(['success' => false, 'code' => 'MULTIPLE_FACES', 'message' => 'Exactly one face must be visible.'], 422)]);

        $this->actingAs($this->adminWith(['biometrics.view']), 'sanctum')
            ->postJson("/api/v1/people/{$person->person_code}/biometric-enrollment/retry")
            ->assertForbidden();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/people/{$person->person_code}/biometric-enrollment/retry")
            ->assertUnprocessable()->assertJsonPath('code', 'MULTIPLE_FACES');

        $this->assertSame(BiometricStatus::Failed, $person->fresh()->biometricProfile->status);
        $this->assertFalse($person->fresh()->face_registered);
    }

    public function test_invalid_ai_success_response_does_not_enroll_employee(): void
    {
        config(['services.face_ai.url' => 'http://face-ai.test']);
        Http::fake(['http://face-ai.test/enroll' => Http::response(['success' => true, 'modelVersion' => 'insightface-buffalo_l'])]);
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true]);

        $this->actingAs($this->adminWith(['biometrics.enroll']), 'sanctum')
            ->post("/api/v1/people/{$person->person_code}/biometric-enrollment", $this->images(), ['Accept' => 'application/json'])
            ->assertServiceUnavailable()->assertJsonPath('code', 'FACE_AI_UNAVAILABLE');

        $this->assertSame(BiometricStatus::Failed, $person->fresh()->biometricProfile->status);
        $this->assertFalse($person->fresh()->face_registered);
    }

    public function test_ai_success_creates_an_enrolled_internal_template(): void
    {
        config(['services.face_ai.url' => 'http://face-ai.test']);
        Http::fake(['http://face-ai.test/enroll' => Http::response(['success' => true, 'embedding' => [0.1, 0.2, 0.3], 'embeddingDimension' => 3, 'modelVersion' => 'face-cnn-v1', 'qualityScore' => 0.93])]);
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true]);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->post("/api/v1/people/{$person->person_code}/biometric-enrollment", $this->images(), ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'enrolled')->assertJsonPath('data.modelVersion', 'face-cnn-v1')->assertJsonMissingPath('data.embedding');

        $profile = $person->fresh()->biometricProfile;
        $this->assertSame(BiometricStatus::Enrolled, $profile->status);
        $this->assertSame(3, $profile->embedding_dimension);
        $this->assertSame([0.1, 0.2, 0.3], $profile->embedding);
        $this->assertTrue($person->fresh()->face_registered);
    }

    public function test_inactive_employee_is_rejected_and_failed_reenrollment_keeps_old_template(): void
    {
        $inactive = Person::factory()->create(['status' => 'inactive', 'biometric_consent' => true]);
        $this->actingAs($this->superAdmin(), 'sanctum')
            ->post("/api/v1/people/{$inactive->person_code}/biometric-enrollment", $this->images(), ['Accept' => 'application/json'])
            ->assertConflict()->assertJsonPath('code', 'EMPLOYEE_INACTIVE');

        config(['services.face_ai.url' => 'http://face-ai.test']);
        Http::fake(['http://face-ai.test/enroll' => Http::response(['success' => false, 'code' => 'NO_FACE', 'message' => 'No face detected'], 422)]);
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true, 'face_registered' => true]);
        BiometricProfile::create(['person_id' => $person->id, 'status' => BiometricStatus::Enrolled, 'embedding' => [0.9, 0.8], 'embedding_dimension' => 2, 'model_version' => 'face-cnn-v1', 'enrolled_at' => now()]);

        $this->actingAs($this->adminWith(['biometrics.reenroll']), 'sanctum')
            ->post("/api/v1/people/{$person->person_code}/biometric-reenrollment", $this->images(), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('code', 'NO_FACE');

        $profile = $person->fresh()->biometricProfile;
        $this->assertSame(BiometricStatus::Enrolled, $profile->status);
        $this->assertSame([0.9, 0.8], $profile->embedding);
    }

    public function test_authorized_revocation_removes_samples_and_template_and_is_audited(): void
    {
        config(['services.face_ai.url' => 'http://face-ai.test']);
        Http::fake(['http://face-ai.test/enroll' => Http::response([
            'success' => true,
            'embedding' => [0.6, 0.8],
            'embeddingDimension' => 2,
            'modelVersion' => 'insightface-buffalo_l',
            'qualityScore' => 0.9,
            'sampleCount' => 3,
        ])]);
        $person = Person::factory()->create(['status' => 'active', 'biometric_consent' => true]);
        $this->actingAs($this->superAdmin(), 'sanctum')->post("/api/v1/people/{$person->person_code}/biometric-enrollment", $this->images(), ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($this->adminWith(['biometrics.delete']), 'sanctum')
            ->deleteJson("/api/v1/people/{$person->person_code}/biometric-data")->assertNoContent();

        $this->assertDatabaseCount('face_samples', 0);
        $this->assertSame(BiometricStatus::Revoked, $person->fresh()->biometricProfile->status);
        $this->assertFalse($person->fresh()->face_registered);
        $this->assertDatabaseHas('audit_logs', ['action' => 'biometric.revoked', 'resource_id' => $person->person_code]);
    }

    private function images(): array
    {
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==');
        return ['front' => UploadedFile::fake()->createWithContent('front.jpg', $jpeg), 'left' => UploadedFile::fake()->createWithContent('left.jpg', $jpeg), 'right' => UploadedFile::fake()->createWithContent('right.jpg', $jpeg)];
    }
}
