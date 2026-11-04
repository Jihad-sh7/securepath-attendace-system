<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricConsentRequest;
use App\Http\Requests\SupervisedBiometricEnrollmentRequest;
use App\Models\Person;
use App\Services\BiometricEnrollmentService;
use App\Services\BiometricService;
use App\Support\ApiResponse;

class BiometricController extends Controller
{
    public function __construct(private readonly BiometricService $biometrics, private readonly BiometricEnrollmentService $enrollments) {}
    public function status(Person $person) { return ApiResponse::success($this->enrollments->status($person)); }
    public function consentStatus(Person $person) { $profile = $person->biometricProfile; return ApiResponse::success(['personId' => $person->person_code, 'granted' => $person->biometric_consent, 'status' => $profile?->consent_status ?? ($person->biometric_consent ? 'granted' : 'pending'), 'consentAt' => $profile?->consent_at?->toIso8601String()]); }
    public function consent(BiometricConsentRequest $request, Person $person) { $profile = $this->biometrics->consent($person, $request->boolean('granted'), $request->user()); return ApiResponse::success(['personId' => $person->person_code, 'granted' => $person->fresh()->biometric_consent, 'status' => $profile->consent_status]); }
    public function enroll(SupervisedBiometricEnrollmentRequest $request, Person $person) { return ApiResponse::success($this->enrollments->enroll($person, ['front' => $request->file('front'), 'left' => $request->file('left'), 'right' => $request->file('right')], $request->user(), $request->boolean('consentAccepted')), 'Face enrollment completed.', 201); }
    public function reenroll(SupervisedBiometricEnrollmentRequest $request, Person $person) { return ApiResponse::success($this->enrollments->enroll($person, ['front' => $request->file('front'), 'left' => $request->file('left'), 'right' => $request->file('right')], $request->user(), $request->boolean('consentAccepted'), true), 'Face re-enrollment completed.', 201); }
    public function retry(Person $person, \Illuminate\Http\Request $request) { return ApiResponse::success($this->enrollments->retry($person, $request->user()), 'Face enrollment completed.'); }
    public function destroy(Person $person, \Illuminate\Http\Request $request) { $this->enrollments->revoke($person, $request->user()); return response()->noContent(); }
    public function requestEnrollment(Person $person, \Illuminate\Http\Request $request) { return ApiResponse::success($this->biometrics->requestEnrollment($person, $request->user()), 'Enrollment request created.', 201); }
    public function enrollmentStatus(Person $person) { $enrollment = $person->enrollmentRequests()->latest()->first(); return ApiResponse::success($enrollment ? ['status' => $enrollment->status->value, 'expiresAt' => $enrollment->expires_at?->toIso8601String(), 'completedAt' => $enrollment->completed_at?->toIso8601String()] : ['status' => 'not_requested']); }
}
