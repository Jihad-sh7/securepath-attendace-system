<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentCompleteRequest;
use App\Services\BiometricService;
use App\Support\ApiResponse;

class EnrollmentController extends Controller
{
    public function __construct(private readonly BiometricService $biometrics) {}
    public function show(string $token) { $request = $this->biometrics->resolveEnrollment($token); return ApiResponse::success(['personId' => $request->person->person_code, 'personName' => $request->person->full_name, 'status' => $request->status->value, 'expiresAt' => $request->expires_at->toIso8601String()]); }
    public function complete(EnrollmentCompleteRequest $request, string $token) { $enrollment = $this->biometrics->complete($token, $request->validated()); return ApiResponse::success(['personId' => $enrollment->person->person_code, 'status' => $enrollment->status->value, 'completedAt' => $enrollment->completed_at?->toIso8601String()], 'Enrollment completed.'); }
}

