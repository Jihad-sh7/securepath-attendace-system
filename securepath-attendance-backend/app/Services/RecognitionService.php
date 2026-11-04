<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AttendanceActionSource;
use App\Enums\AttendanceSource;
use App\Enums\LivenessResult;
use App\Enums\RecognitionStatus;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;
use App\Events\AlertCreated;
use App\Events\RecognitionPendingVerification;
use App\Exceptions\ApiConflictException;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\Person;
use App\Models\RecognitionEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecognitionService
{
    public function __construct(private readonly AttendanceService $attendance, private readonly SettingsService $settings, private readonly AuditService $audit) {}

    public function submit(array $data): RecognitionEvent
    {
        if ($existing = RecognitionEvent::where('event_uuid', $data['eventUuid'])->first()) return $existing->load(['person', 'attendance', 'verifier']);

        try {
            return DB::transaction(function () use ($data): RecognitionEvent {
                if ($existing = RecognitionEvent::where('event_uuid', $data['eventUuid'])->lockForUpdate()->first()) return $existing;
                $person = isset($data['personId']) ? Person::where('person_code', $data['personId'])->lockForUpdate()->firstOrFail() : null;
                $occurredAt = Carbon::parse($data['occurredAt']);
                $confidence = isset($data['confidence']) ? (float) $data['confidence'] : null;
                $liveness = isset($data['livenessResult']) ? LivenessResult::from($data['livenessResult']) : (isset($data['livenessPassed']) ? ($data['livenessPassed'] ? LivenessResult::Passed : LivenessResult::Failed) : LivenessResult::NotProvided);
                [$recognitionStatus, $verificationStatus] = $this->classify($person, $confidence, $liveness, (bool) ($data['_serverVerifiedImage'] ?? false));

                if ($person && $recognitionStatus === RecognitionStatus::Matched && $this->isDuplicate($person, $occurredAt, $data['deviceIdentifier'])) {
                    $recognitionStatus = RecognitionStatus::Duplicate;
                    $verificationStatus = VerificationStatus::Automatic;
                }

                $event = RecognitionEvent::create([
                    'recognition_code' => 'TMP-'.Str::uuid(), 'event_uuid' => $data['eventUuid'], 'person_id' => $person?->id, 'confidence' => $confidence,
                    'liveness_result' => $liveness, 'recognition_status' => $recognitionStatus, 'verification_status' => $verificationStatus,
                    'source_device_identifier' => $data['deviceIdentifier'], 'location' => $data['location'] ?? null, 'occurred_at' => $occurredAt, 'metadata' => $data['metadata'] ?? null,
                ]);
                $event->update(['recognition_code' => 'REC-'.str_pad((string) $event->id, 6, '0', STR_PAD_LEFT)]);

                if ($person && $recognitionStatus === RecognitionStatus::Matched && $verificationStatus === VerificationStatus::Automatic) {
                    $event->update(['attendance_id' => $this->applyAttendance($person, $event)->id]);
                }

                if ($verificationStatus === VerificationStatus::Pending) {
                    $this->createAlertFor($event);
                    RecognitionPendingVerification::dispatch($event);
                }
                $this->audit->record('recognition.received', $event, newValues: ['status' => $recognitionStatus->value, 'verification' => $verificationStatus->value]);
                return $event->fresh(['person', 'attendance', 'verifier']);
            }, 3);
        } catch (QueryException $exception) {
            if ($existing = RecognitionEvent::where('event_uuid', $data['eventUuid'])->first()) return $existing->load(['person', 'attendance', 'verifier']);
            throw $exception;
        }
    }

    public function verify(RecognitionEvent $event, User $actor, ?string $note = null): RecognitionEvent
    {
        return DB::transaction(function () use ($event, $actor, $note): RecognitionEvent {
            $event = RecognitionEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (! $event->person_id) throw new ApiConflictException('Assign a person before verifying this recognition.', 'PERSON_REQUIRED');
            if ($event->verification_status !== VerificationStatus::Pending) throw new ApiConflictException('This recognition has already been reviewed.', 'RECOGNITION_ALREADY_REVIEWED');
            $event->update(['recognition_status' => RecognitionStatus::Matched, 'verification_status' => VerificationStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now(), 'metadata' => array_merge($event->metadata ?? [], ['reviewNote' => $note])]);
            if (! $event->attendance_id) $event->update(['attendance_id' => $this->applyAttendance($event->person, $event)->id]);
            $event->alerts()->update(['status' => AlertStatus::Resolved, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $this->audit->record('recognition.verified', $event, newValues: ['personId' => $event->person->person_code], userId: $actor->id);
            return $event->fresh(['person', 'attendance', 'verifier']);
        });
    }

    public function assignPerson(RecognitionEvent $event, Person $person, User $actor, ?string $note = null): RecognitionEvent
    {
        return DB::transaction(function () use ($event, $person, $actor, $note): RecognitionEvent {
            $event = RecognitionEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->verification_status !== VerificationStatus::Pending) throw new ApiConflictException('This recognition has already been reviewed.', 'RECOGNITION_ALREADY_REVIEWED');
            $old = ['personId' => $event->person?->person_code];
            $event->update(['person_id' => $person->id, 'metadata' => array_merge($event->metadata ?? [], ['assignmentNote' => $note])]);
            $this->audit->record('recognition.person_assigned', $event, $old, ['personId' => $person->person_code], userId: $actor->id);
            return $event->fresh(['person', 'attendance', 'verifier']);
        });
    }

    public function reject(RecognitionEvent $event, User $actor, ?string $note = null): RecognitionEvent
    {
        return DB::transaction(function () use ($event, $actor, $note): RecognitionEvent {
            $event = RecognitionEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->verification_status !== VerificationStatus::Pending) throw new ApiConflictException('This recognition has already been reviewed.', 'RECOGNITION_ALREADY_REVIEWED');
            $event->update(['recognition_status' => RecognitionStatus::Rejected, 'verification_status' => VerificationStatus::Rejected, 'verified_by' => $actor->id, 'verified_at' => now(), 'metadata' => array_merge($event->metadata ?? [], ['reviewNote' => $note])]);
            $event->alerts()->update(['status' => AlertStatus::Resolved, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $this->audit->record('recognition.rejected', $event, userId: $actor->id);
            return $event->fresh(['person', 'attendance', 'verifier']);
        });
    }

    private function classify(?Person $person, ?float $confidence, LivenessResult $liveness, bool $serverVerifiedImage = false): array
    {
        if ($this->settings->get('livenessDetection') && $liveness !== LivenessResult::Passed && ! $serverVerifiedImage) return [RecognitionStatus::Rejected, VerificationStatus::Pending];
        if (! $person) return [RecognitionStatus::Unknown, VerificationStatus::Pending];
        if (! $person->isBiometricReady()) return [RecognitionStatus::Rejected, VerificationStatus::Pending];
        if ($confidence === null || $confidence < (float) $this->settings->get('manualReviewThreshold')) return [RecognitionStatus::Rejected, VerificationStatus::Pending];
        if ($confidence < (float) $this->settings->get('confidenceThreshold')) return [RecognitionStatus::LowConfidence, VerificationStatus::Pending];
        return [RecognitionStatus::Matched, VerificationStatus::Automatic];
    }

    private function isDuplicate(Person $person, Carbon $occurredAt, string $deviceIdentifier): bool
    {
        $window = (int) $this->settings->get('duplicateRecognitionWindowSeconds');
        return RecognitionEvent::where('person_id', $person->id)->where('source_device_identifier', $deviceIdentifier)->whereBetween('occurred_at', [$occurredAt->copy()->subSeconds($window), $occurredAt])->exists();
    }

    private function applyAttendance(Person $person, RecognitionEvent $event): AttendanceRecord
    {
        if (! $person->isBiometricReady()) throw new ApiConflictException('Biometric attendance requires an active enrolled face profile.', 'BIOMETRIC_NOT_READY');
        $date = $event->occurred_at->copy()->startOfDay();
        $record = AttendanceRecord::withTrashed()->where('person_id', $person->id)->whereDate('attendance_date', $date)->lockForUpdate()->first();
        $operation = $event->metadata['operation'] ?? null;
        if (! $record && $operation === 'check_out') throw new ApiConflictException('Employee is not currently checked in.', 'EMPLOYEE_NOT_CHECKED_IN');
        if ($record && ! $record->check_out_at && $operation === 'check_in') throw new ApiConflictException('Employee is already checked in.', 'EMPLOYEE_ALREADY_CHECKED_IN');
        if ($record && $record->check_out_at) throw new ApiConflictException('Attendance is already complete for this employee today.', 'ATTENDANCE_ALREADY_COMPLETE');

        if (! $record) {
            [$status, $minutesLate, $schedule] = $this->attendance->calculateStatus($person, $date, $event->occurred_at);
            $record = AttendanceRecord::create(['event_code' => 'TMP-'.Str::uuid(), 'person_id' => $person->id, 'attendance_date' => $date, 'scheduled_start' => $schedule?->start_time, 'scheduled_end' => $schedule?->end_time, 'check_in_at' => $event->occurred_at, 'check_in_source' => AttendanceActionSource::FaceRecognition, 'status' => $status, 'minutes_late' => $minutesLate, 'verification_method' => VerificationMethod::FaceRecognition, 'recognition_confidence' => $event->confidence, 'liveness_result' => $event->liveness_result, 'source' => AttendanceSource::Mobile, 'source_identifier' => $event->source_device_identifier, 'location' => $event->location]);
            $record->update(['event_code' => 'EVT-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)]);
            $this->attendance->recordActionEvent($record, 'check_in', $event->occurred_at, AttendanceActionSource::FaceRecognition, metadata: ['recognitionId' => $event->recognition_code, 'deviceIdentifier' => $event->source_device_identifier]);
        } elseif (! $record->check_out_at && $record->check_in_at && ($operation === 'check_out' || $event->occurred_at->diffInSeconds($record->check_in_at) > (int) $this->settings->get('duplicateRecognitionWindowSeconds'))) {
            $record->update(['check_out_at' => $event->occurred_at, 'worked_minutes' => $record->check_in_at->diffInMinutes($event->occurred_at), 'check_out_source' => AttendanceActionSource::FaceRecognition, 'auto_checkout_reason' => null]);
            $this->attendance->recordActionEvent($record, 'check_out', $event->occurred_at, AttendanceActionSource::FaceRecognition, metadata: ['recognitionId' => $event->recognition_code, 'deviceIdentifier' => $event->source_device_identifier]);
        }
        return $record;
    }

    private function createAlertFor(RecognitionEvent $event): Alert
    {
        [$type, $severity, $title] = match (true) {
            $event->recognition_status === RecognitionStatus::Unknown => ['unknown_face', AlertSeverity::Critical, 'Unknown face requires review'],
            $event->recognition_status === RecognitionStatus::LowConfidence => ['low_confidence', AlertSeverity::Warning, 'Low-confidence recognition'],
            $event->liveness_result !== LivenessResult::Passed => ['liveness_failure', AlertSeverity::Critical, 'Liveness verification failed'],
            default => ['low_confidence', AlertSeverity::High, 'Recognition below review threshold'],
        };
        $alert = Alert::create(['alert_code' => 'TMP-'.Str::uuid(), 'type' => $type, 'severity' => $severity, 'title' => $title, 'message' => $event->recognition_code.' requires manual verification.', 'person_id' => $event->person_id, 'recognition_event_id' => $event->id, 'status' => AlertStatus::Open]);
        $alert->update(['alert_code' => 'ALT-'.str_pad((string) $alert->id, 6, '0', STR_PAD_LEFT)]);
        AlertCreated::dispatch($alert);
        return $alert;
    }
}
