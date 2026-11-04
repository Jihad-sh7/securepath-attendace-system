<?php

namespace App\Services;

use App\Contracts\FaceAiServiceInterface;
use App\Enums\AccountStatus;
use App\Enums\BiometricStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\ApiConflictException;
use App\Exceptions\FaceAiUnavailableException;
use App\Models\BiometricEnrollmentSession;
use App\Models\BiometricProfile;
use App\Models\FaceSample;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BiometricEnrollmentService
{
    public function __construct(private readonly FaceAiServiceInterface $ai, private readonly AuditService $audit) {}

    /** @param array<string, UploadedFile> $images */
    public function enroll(Person $person, array $images, User $actor, bool $consentAccepted, bool $requireExisting = false): array
    {
        if ($person->status !== AccountStatus::Active) throw new ApiConflictException('Face enrollment is only available for active employees.', 'EMPLOYEE_INACTIVE');

        $profile = $person->biometricProfile;
        $hasActiveProfile = $person->isBiometricReady();
        if ($requireExisting && ! $hasActiveProfile) throw new ApiConflictException('This employee does not have an active face enrollment to replace.', 'NOT_ENROLLED');
        if (! $requireExisting && $hasActiveProfile) throw new ApiConflictException('This employee is already enrolled. Use re-enrollment instead.', 'ALREADY_ENROLLED');
        if (! $person->biometric_consent && ! $consentAccepted) throw new ApiConflictException('Biometric consent is required before enrollment.', 'CONSENT_REQUIRED');

        $session = DB::transaction(function () use ($person, $actor, $hasActiveProfile, $consentAccepted): BiometricEnrollmentSession {
            if ($consentAccepted && ! $person->biometric_consent) {
                $person->update(['biometric_consent' => true]);
                BiometricProfile::updateOrCreate(['person_id' => $person->id], ['consent_status' => 'granted', 'consent_at' => now()]);
            }

            $session = BiometricEnrollmentSession::create([
                'uuid' => Str::uuid(), 'person_id' => $person->id, 'requested_by' => $actor->id,
                'status' => $this->ai->isConfigured() ? EnrollmentStatus::Pending : EnrollmentStatus::Pending,
                'is_reenrollment' => $hasActiveProfile,
            ]);
            $this->audit->record('biometric.enrollment_started', $person, newValues: ['sessionId' => $session->uuid, 'reenrollment' => $hasActiveProfile], userId: $actor->id);
            return $session;
        });

        $samplePaths = [];
        try {
            foreach (['front', 'left', 'right'] as $pose) {
                $file = $images[$pose];
                $extension = strtolower($file->extension()) === 'webp' ? 'webp' : 'jpg';
                $path = $file->storeAs("employee_{$person->id}/{$session->uuid}", "{$pose}.{$extension}", 'biometrics');
                if (! $path) throw new \RuntimeException("The {$pose} sample could not be stored.");
                $samplePaths[$pose] = $path;
                FaceSample::create(['person_id' => $person->id, 'biometric_enrollment_session_id' => $session->id, 'pose' => $pose, 'image_path' => $path]);
            }
        } catch (Throwable $exception) {
            Storage::disk('biometrics')->deleteDirectory("employee_{$person->id}/{$session->uuid}");
            $session->update(['status' => EnrollmentStatus::Failed, 'failure_reason' => $exception->getMessage()]);
            $this->audit->record('biometric.enrollment_failed', $person, newValues: ['sessionId' => $session->uuid, 'result' => 'storage_failed'], result: 'failed', userId: $actor->id);
            throw $exception;
        }

        if (! $this->ai->isConfigured()) {
            $session->update(['status' => EnrollmentStatus::Failed, 'failure_reason' => 'The face AI service is not configured.']);
            if (! $hasActiveProfile) {
                BiometricProfile::updateOrCreate(['person_id' => $person->id], ['status' => BiometricStatus::Failed, 'consent_status' => 'granted', 'consent_at' => now(), 'last_updated_at' => now()]);
                $person->update(['face_registered' => false]);
            }
            $this->audit->record('biometric.enrollment_failed', $person, newValues: ['sessionId' => $session->uuid, 'result' => 'ai_not_configured'], result: 'failed', userId: $actor->id);
            throw new FaceAiUnavailableException('The face AI service is not configured. The stored photos can be retried when it is available.');
        }

        return $this->processSession($person, $session, $samplePaths, $actor, $hasActiveProfile);
    }

    public function retry(Person $person, User $actor): array
    {
        if ($person->status !== AccountStatus::Active) throw new ApiConflictException('Face enrollment is only available for active employees.', 'EMPLOYEE_INACTIVE');
        if (! $person->biometric_consent) throw new ApiConflictException('Biometric consent is required before enrollment.', 'CONSENT_REQUIRED');
        if (! $this->ai->isConfigured()) throw new FaceAiUnavailableException('The face AI service is not configured.');

        $session = $person->biometricEnrollmentSessions()
            ->with('samples')
            ->whereIn('status', [EnrollmentStatus::Pending, EnrollmentStatus::Failed])
            ->latest()
            ->first();
        if (! $session) throw new ApiConflictException('No pending or failed enrollment is available to retry.', 'NO_RETRYABLE_ENROLLMENT');

        $samples = $session->samples->keyBy('pose');
        $samplePaths = collect(['front', 'left', 'right'])
            ->mapWithKeys(fn (string $pose) => [$pose => $samples->get($pose)?->image_path])
            ->all();
        if (in_array(null, $samplePaths, true)) throw new ApiConflictException('All three stored enrollment samples are required for retry.', 'ENROLLMENT_SAMPLES_MISSING');

        $this->audit->record('biometric.enrollment_retried', $person, newValues: ['sessionId' => $session->uuid], userId: $actor->id);

        return $this->processSession($person, $session, $samplePaths, $actor, $person->isBiometricReady());
    }

    /** @param array<string, string> $samplePaths */
    private function processSession(Person $person, BiometricEnrollmentSession $session, array $samplePaths, User $actor, bool $hasActiveProfile): array
    {

        $session->update(['status' => EnrollmentStatus::Processing]);
        if (! $hasActiveProfile) BiometricProfile::updateOrCreate(['person_id' => $person->id], ['status' => BiometricStatus::Processing, 'consent_status' => 'granted', 'consent_at' => now(), 'last_updated_at' => now()]);

        try {
            $result = $this->ai->enroll($person, $samplePaths);
        } catch (Throwable $exception) {
            $session->update(['status' => EnrollmentStatus::Failed, 'failure_reason' => $exception->getMessage()]);
            if (! $hasActiveProfile) {
                BiometricProfile::where('person_id', $person->id)->update(['status' => BiometricStatus::Failed, 'last_updated_at' => now()]);
                $person->update(['face_registered' => false]);
            }
            $this->audit->record('biometric.enrollment_failed', $person, newValues: ['sessionId' => $session->uuid, 'result' => 'ai_failed'], result: 'failed', userId: $actor->id);
            if ($exception instanceof \App\Exceptions\FaceProcessingException) throw $exception;
            throw new FaceAiUnavailableException('Face enrollment could not be processed. Retake the photos or try again when the AI service is available.');
        }

        return DB::transaction(function () use ($person, $actor, $session, $result, $hasActiveProfile): array {
            $profile = BiometricProfile::where('person_id', $person->id)->lockForUpdate()->first() ?? new BiometricProfile(['person_id' => $person->id]);
            $profile->fill([
                'status' => BiometricStatus::Enrolled, 'embedding' => $result['embedding'], 'embedding_dimension' => $result['embeddingDimension'],
                'model_version' => $result['modelVersion'], 'quality_score' => $result['qualityScore'], 'enrolled_at' => now(), 'revoked_at' => null,
                'last_updated_at' => now(), 'consent_status' => 'granted', 'consent_at' => $profile->consent_at ?? now(), 'secure_reference' => null,
            ])->save();
            $session->update(['status' => EnrollmentStatus::Completed, 'model_version' => $result['modelVersion'], 'quality_score' => $result['qualityScore'], 'completed_at' => now()]);
            $session->samples()->update(['biometric_profile_id' => $profile->id]);
            $person->update(['face_registered' => true, 'biometric_consent' => true]);
            $this->audit->record($hasActiveProfile ? 'biometric.reenrolled' : 'biometric.enrollment_completed', $person, newValues: ['sessionId' => $session->uuid, 'modelVersion' => $result['modelVersion'], 'sampleCount' => 3], userId: $actor->id);
            return $this->safeResult($person->fresh(), $session->fresh('samples'));
        });
    }

    public function status(Person $person): array
    {
        $profile = $person->biometricProfile;
        $latest = $person->biometricEnrollmentSessions()->withCount('samples')->latest()->first();
        $status = $profile?->status->value ?? BiometricStatus::NotRegistered->value;

        return [
            'personId' => $person->person_code,
            'status' => $status,
            'biometricReady' => $person->isBiometricReady(),
            'sampleCount' => $latest?->samples_count ?? 0,
            'modelVersion' => $profile?->model_version,
            'qualityScore' => $profile?->quality_score !== null ? (float) $profile->quality_score : null,
            'enrolledAt' => $profile?->enrolled_at?->toIso8601String(),
            'lastEnrollmentStatus' => $latest?->status->value,
            'lastUpdatedAt' => $profile?->last_updated_at?->toIso8601String(),
        ];
    }

    public function revoke(Person $person, User $actor): void
    {
        DB::transaction(function () use ($person, $actor): void {
            $person->faceSamples()->get()->each(function (FaceSample $sample): void {
                Storage::disk('biometrics')->delete($sample->image_path);
                $sample->delete();
            });
            $person->biometricEnrollmentSessions()->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Processing->value])->update(['status' => EnrollmentStatus::Cancelled]);
            $person->enrollmentRequests()->where('status', EnrollmentStatus::Pending)->update(['status' => EnrollmentStatus::Cancelled]);
            $person->biometricProfile?->update([
                'status' => BiometricStatus::Revoked, 'embedding' => null, 'embedding_dimension' => null, 'quality_score' => null,
                'secure_reference' => null, 'metadata' => null, 'revoked_at' => now(), 'last_updated_at' => now(),
            ]);
            $person->update(['face_registered' => false]);
            Storage::disk('biometrics')->deleteDirectory("employee_{$person->id}");
            $this->audit->record('biometric.revoked', $person, newValues: ['samplesDeleted' => true], userId: $actor->id);
        });
    }

    private function safeResult(Person $person, BiometricEnrollmentSession $session): array
    {
        $status = $session->status === EnrollmentStatus::Completed ? BiometricStatus::Enrolled->value : $session->status->value;
        return ['personId' => $person->person_code, 'status' => $status, 'enrolledAt' => $session->completed_at?->toIso8601String(), 'modelVersion' => $session->model_version, 'sampleCount' => $session->samples->count()];
    }
}
