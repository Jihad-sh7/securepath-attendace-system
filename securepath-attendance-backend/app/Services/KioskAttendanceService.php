<?php

namespace App\Services;

use App\Contracts\FaceAiServiceInterface;
use App\Enums\AccountStatus;
use App\Exceptions\ApiConflictException;
use App\Models\MobileDevice;
use App\Models\Person;
use App\Models\RecognitionEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class KioskAttendanceService
{
    public function __construct(private readonly FaceAiServiceInterface $ai, private readonly RecognitionService $recognition) {}

    public function recognize(UploadedFile $image, array $data, MobileDevice $device): array
    {
        if ($existing = RecognitionEvent::with(['person.department', 'attendance'])->where('event_uuid', $data['eventUuid'])->first()) {
            Log::info('Kiosk attendance reused existing recognition event.', [
                'eventUuid' => $data['eventUuid'],
                'recognitionCode' => $existing->recognition_code,
                'personId' => $existing->person?->person_code,
                'hasAttendance' => (bool) $existing->attendance,
                'confidence' => $existing->confidence,
            ]);
            if (! $existing->person || ! $existing->attendance) {
                throw new ApiConflictException('No enrolled employee matched this face.', 'FACE_NOT_REGISTERED', [
                    'eventUuid' => $data['eventUuid'],
                    'recognitionCode' => $existing->recognition_code,
                    'personId' => $existing->person?->person_code,
                    'confidence' => $existing->confidence ? (float) $existing->confidence : null,
                    'modelVersion' => $existing->metadata['modelVersion'] ?? null,
                    'matchThreshold' => $existing->metadata['matchThreshold'] ?? null,
                    'qualityScore' => $existing->metadata['qualityScore'] ?? null,
                ]);
            }
            return $this->result($existing, $data['operation']);
        }

        $candidates = Person::query()
            ->where('status', AccountStatus::Active)
            ->where('face_registered', true)
            ->whereHas('biometricProfile', fn ($query) => $query->whereIn('status', ['registered', 'enrolled'])->whereNotNull('embedding')->whereNotNull('model_version'))
            ->with(['biometricProfile', 'department'])
            ->get()
            ->map(fn (Person $person) => [
                'personId' => $person->person_code,
                'embedding' => $person->biometricProfile->embedding,
                'modelVersion' => $person->biometricProfile->model_version,
            ])->values()->all();

        Log::info('Kiosk attendance prepared face recognition candidates.', [
            'eventUuid' => $data['eventUuid'],
            'operation' => $data['operation'],
            'deviceIdentifier' => $device->identifier,
            'candidateCount' => count($candidates),
        ]);

        if ($candidates === []) throw new ApiConflictException('No employees have compatible enrolled face templates.', 'NO_ENROLLED_FACES', [
            'eventUuid' => $data['eventUuid'],
            'candidateCount' => 0,
        ]);
        $match = $this->ai->identify($image, $candidates);
        Log::info('Kiosk attendance face AI match result.', [
            'eventUuid' => $data['eventUuid'],
            'matched' => $match['matched'],
            'personId' => $match['personId'],
            'confidence' => $match['confidence'],
            'threshold' => $match['threshold'],
            'modelVersion' => $match['modelVersion'],
            'qualityScore' => $match['qualityScore'],
            'candidateCount' => count($candidates),
        ]);
        $payload = [
            'eventUuid' => $data['eventUuid'],
            'personId' => $match['matched'] ? $match['personId'] : null,
            'confidence' => $match['confidence'],
            'livenessResult' => 'not_provided',
            'occurredAt' => $data['occurredAt'] ?? now()->toIso8601String(),
            'deviceIdentifier' => $device->identifier,
            'location' => $data['location'] ?? null,
            '_serverVerifiedImage' => true,
            'metadata' => [
                'operation' => $data['operation'],
                'modelVersion' => $match['modelVersion'],
                'matchThreshold' => $match['threshold'],
                'qualityScore' => $match['qualityScore'],
                'liveness' => 'not_implemented',
            ],
        ];
        $event = $this->recognition->submit($payload);
        if (! $match['matched'] || ! $event->person || ! $event->attendance) {
            $details = [
                'eventUuid' => $data['eventUuid'],
                'recognitionCode' => $event->recognition_code,
                'matched' => $match['matched'],
                'personId' => $match['personId'],
                'confidence' => $match['confidence'],
                'threshold' => $match['threshold'],
                'modelVersion' => $match['modelVersion'],
                'qualityScore' => $match['qualityScore'],
                'candidateCount' => count($candidates),
            ];
            Log::warning('Kiosk attendance rejected face recognition result.', $details);
            throw new ApiConflictException('No enrolled employee matched this face.', 'FACE_NOT_REGISTERED', $details);
        }

        return $this->result($event, $data['operation']);
    }

    private function result(RecognitionEvent $event, string $operation): array
    {
        $event->loadMissing(['person.department', 'attendance']);
        $attendance = $event->attendance;
        return [
            'requestId' => $event->event_uuid,
            'operation' => $operation,
            'employee' => [
                'id' => $event->person->id,
                'employeeNumber' => $event->person->person_code,
                'fullName' => $event->person->full_name,
                'department' => $event->person->department?->name,
                'jobTitle' => $event->person->job_title,
            ],
            'attendance' => [
                'checkInTime' => $attendance->check_in_at?->toIso8601String(),
                'checkOutTime' => $attendance->check_out_at?->toIso8601String(),
                'workedMinutes' => $attendance->worked_minutes,
                'overtimeMinutes' => 0,
                'earlyDepartureMinutes' => 0,
            ],
            'recognition' => ['confidence' => (float) $event->confidence, 'modelVersion' => $event->metadata['modelVersion'] ?? null],
            'serverTime' => now()->toIso8601String(),
        ];
    }
}
