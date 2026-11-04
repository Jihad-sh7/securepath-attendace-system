<?php

namespace App\Services;

use App\Enums\BiometricStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\ApiConflictException;
use App\Models\BiometricEnrollmentRequest;
use App\Models\BiometricProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BiometricService
{
    public function __construct(private readonly AuditService $audit) {}

    public function consent(Person $person, bool $granted, User $actor): BiometricProfile
    {
        return DB::transaction(function () use ($person, $granted, $actor): BiometricProfile {
            $profile = BiometricProfile::firstOrCreate(['person_id' => $person->id], ['status' => BiometricStatus::NotRegistered]);
            $profile->update(['consent_status' => $granted ? 'granted' : 'revoked', 'consent_at' => now()]);
            $person->update(['biometric_consent' => $granted]);
            if (! $granted) $person->enrollmentRequests()->where('status', EnrollmentStatus::Pending)->update(['status' => EnrollmentStatus::Cancelled]);
            $this->audit->record('biometric.consent_updated', $person, newValues: ['granted' => $granted], userId: $actor->id);
            return $profile;
        });
    }

    public function delete(Person $person, User $actor): void
    {
        DB::transaction(function () use ($person, $actor): void {
            $profile = BiometricProfile::where('person_id', $person->id)->lockForUpdate()->first();
            if ($profile?->secure_reference && str_starts_with($profile->secure_reference, 'storage:')) Storage::disk('local')->delete(substr($profile->secure_reference, 8));
            $profile?->update(['status' => BiometricStatus::Revoked, 'secure_reference' => null, 'metadata' => null, 'last_updated_at' => now()]);
            $person->update(['face_registered' => false]);
            $person->enrollmentRequests()->where('status', EnrollmentStatus::Pending)->update(['status' => EnrollmentStatus::Cancelled]);
            $this->audit->record('biometric.deleted', $person, userId: $actor->id);
        });
    }

    public function requestEnrollment(Person $person, User $actor): array
    {
        if (! $person->biometric_consent) throw new ApiConflictException('Biometric consent is required before enrollment.', 'CONSENT_REQUIRED');
        return DB::transaction(function () use ($person, $actor): array {
            $plainToken = Str::random(64);
            $request = BiometricEnrollmentRequest::create(['person_id' => $person->id, 'requested_by' => $actor->id, 'token_hash' => hash('sha256', $plainToken), 'status' => EnrollmentStatus::Pending, 'expires_at' => now()->addMinutes(30)]);
            BiometricProfile::updateOrCreate(['person_id' => $person->id], ['status' => BiometricStatus::Pending, 'consent_status' => 'granted', 'consent_at' => now()]);
            $this->audit->record('biometric.enrollment_requested', $person, newValues: ['expiresAt' => $request->expires_at->toIso8601String()], userId: $actor->id);
            return ['request' => $request, 'token' => $plainToken, 'expiresAt' => $request->expires_at->toIso8601String()];
        });
    }

    public function resolveEnrollment(string $plainToken): BiometricEnrollmentRequest
    {
        $request = BiometricEnrollmentRequest::with('person')->where('token_hash', hash('sha256', $plainToken))->firstOrFail();
        if ($request->status !== EnrollmentStatus::Pending || $request->expires_at->isPast()) throw new ApiConflictException('This enrollment token is invalid or expired.', 'ENROLLMENT_TOKEN_EXPIRED');
        return $request;
    }

    public function complete(string $plainToken, array $data): BiometricEnrollmentRequest
    {
        return DB::transaction(function () use ($plainToken, $data): BiometricEnrollmentRequest {
            $request = BiometricEnrollmentRequest::where('token_hash', hash('sha256', $plainToken))->lockForUpdate()->firstOrFail();
            if ($request->status !== EnrollmentStatus::Pending || $request->expires_at->isPast()) throw new ApiConflictException('This enrollment token is invalid or expired.', 'ENROLLMENT_TOKEN_EXPIRED');
            if (! $request->person->biometric_consent) throw new ApiConflictException('Biometric consent has been revoked.', 'CONSENT_REQUIRED');
            BiometricProfile::updateOrCreate(['person_id' => $request->person_id], ['status' => BiometricStatus::Registered, 'enrolled_at' => now(), 'last_updated_at' => now(), 'model_version' => $data['modelVersion'], 'secure_reference' => $data['secureReference'], 'metadata' => $data['metadata'] ?? null, 'consent_status' => 'granted']);
            $request->update(['status' => EnrollmentStatus::Completed, 'completed_at' => now()]);
            $request->person()->update(['face_registered' => true]);
            $this->audit->record('biometric.enrollment_completed', $request->person, newValues: ['modelVersion' => $data['modelVersion']]);
            return $request->fresh('person');
        });
    }
}
