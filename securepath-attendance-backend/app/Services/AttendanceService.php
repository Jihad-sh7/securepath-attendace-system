<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AttendanceActionSource;
use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Enums\LivenessResult;
use App\Enums\LeaveStatus;
use App\Enums\VerificationMethod;
use App\Exceptions\ApiConflictException;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Person;
use App\Models\User;
use App\Notifications\SystemActionNotification;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private readonly AuditService $audit, private readonly SettingsService $settings, private readonly WorkScheduleService $schedules) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sorts = ['date' => 'attendance_date', 'checkIn' => 'check_in_at', 'checkOut' => 'check_out_at', 'status' => 'status', 'confidence' => 'recognition_confidence', 'createdAt' => 'created_at'];
        return AttendanceRecord::query()->with(['person.department'])
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $inner) => $inner->where('event_code', 'like', "%{$search}%")->orWhere('source_identifier', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")->orWhereHas('person', fn (Builder $person) => $person->where('full_name', 'like', "%{$search}%"))))
            ->when($filters['date'] ?? null, fn (Builder $q, string $date) => $q->whereDate('attendance_date', $date))
            ->when($filters['dateFrom'] ?? null, fn (Builder $q, string $date) => $q->whereDate('attendance_date', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn (Builder $q, string $date) => $q->whereDate('attendance_date', '<=', $date))
            ->when($filters['personId'] ?? null, fn (Builder $q, string $code) => $q->whereHas('person', fn (Builder $p) => $p->where('person_code', $code)))
            ->when($filters['departmentId'] ?? null, fn (Builder $q, string $code) => $q->whereHas('person.department', fn (Builder $d) => $d->where('code', $code)))
            ->when($filters['location'] ?? null, fn (Builder $q, string $location) => $q->where('location', $location))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', strtolower(str_replace(' ', '_', $status))))
            ->when($filters['verificationMethod'] ?? null, fn (Builder $q, string $method) => $q->where('verification_method', $method))
            ->when($filters['minConfidence'] ?? null, fn (Builder $q, mixed $confidence) => $q->where('recognition_confidence', '>=', (float) $confidence))
            ->orderBy($sorts[$filters['sortBy'] ?? 'date'] ?? 'attendance_date', $filters['sortOrder'] ?? 'desc')
            ->paginate($filters['limit'] ?? 20);
    }

    public function manualCheckIn(array $data, User $actor): AttendanceRecord
    {
        return DB::transaction(function () use ($data, $actor): AttendanceRecord {
            $person = $this->personForAction($data['personId']);
            if ($person->status !== AccountStatus::Active) {
                throw new ApiConflictException('Only active employees can be checked in.', 'PERSON_INACTIVE');
            }

            $checkIn = isset($data['checkInAt']) ? Carbon::parse($data['checkInAt']) : now();
            $date = $checkIn->copy()->startOfDay();
            $open = AttendanceRecord::withTrashed()->where('person_id', $person->id)->whereNotNull('check_in_at')->whereNull('check_out_at')->lockForUpdate()->first();
            if ($open) throw new ApiConflictException('Employee is already checked in.', 'EMPLOYEE_ALREADY_CHECKED_IN');

            $existing = AttendanceRecord::withTrashed()->where('person_id', $person->id)->whereDate('attendance_date', $date)->lockForUpdate()->first();
            if ($existing) throw new ApiConflictException('Attendance already exists for this employee and date.', 'ATTENDANCE_EXISTS');

            $bounds = $this->schedules->boundsFor($person, $date);
            if (! $bounds) throw new ApiConflictException('No working schedule was found for this employee at the selected time.', 'NO_SCHEDULE');
            [$status, $minutesLate] = $this->calculateStatus($person, $date, $checkIn);

            $record = AttendanceRecord::create([
                'event_code' => 'TMP-'.Str::uuid(),
                'person_id' => $person->id,
                'attendance_date' => $date,
                'scheduled_start' => $bounds['start']->format('H:i:s'),
                'scheduled_end' => $bounds['end']->format('H:i:s'),
                'check_in_at' => $checkIn,
                'check_in_source' => AttendanceActionSource::AdminManual,
                'checked_in_by_user_id' => $actor->id,
                'status' => $status,
                'minutes_late' => $minutesLate,
                'verification_method' => VerificationMethod::Manual,
                'source' => AttendanceSource::Dashboard,
                'liveness_result' => LivenessResult::NotProvided,
                'notes' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);
            $record->update(['event_code' => 'EVT-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)]);
            $this->recordActionEvent($record, 'check_in', $checkIn, AttendanceActionSource::AdminManual, $actor, $data['note'] ?? null, ['channel' => 'web_dashboard']);
            $this->audit->record('attendance.manual_checkin', $record, null, [
                'attendanceId' => $record->event_code,
                'personId' => $person->person_code,
                'checkInAt' => $checkIn->toIso8601String(),
                'checkInSource' => AttendanceActionSource::AdminManual->value,
                'note' => $data['note'] ?? null,
            ], userId: $actor->id);

            return $record->load(['person.department', 'events.performer', 'checkedInBy', 'checkedOutBy']);
        });
    }

    public function manualCheckOut(array $data, User $actor): AttendanceRecord
    {
        return DB::transaction(function () use ($data, $actor): AttendanceRecord {
            $person = $this->personForAction($data['personId']);
            $record = AttendanceRecord::where('person_id', $person->id)->whereNotNull('check_in_at')->whereNull('check_out_at')->latest('check_in_at')->lockForUpdate()->first();
            if (! $record) {
                $latest = AttendanceRecord::where('person_id', $person->id)->whereNotNull('check_out_at')->latest('check_out_at')->first();
                if ($latest?->check_out_source === AttendanceActionSource::AutoOvertimeLimit) {
                    throw new ApiConflictException('Employee was already checked out automatically at '.$latest->check_out_at->format('Y-m-d H:i').'.', 'EMPLOYEE_AUTO_CHECKED_OUT');
                }
                throw new ApiConflictException('Employee is not currently checked in.', 'EMPLOYEE_NOT_CHECKED_IN');
            }

            $checkOut = isset($data['checkOutAt']) ? Carbon::parse($data['checkOutAt']) : now();
            if ($checkOut->lessThanOrEqualTo($record->check_in_at)) {
                throw ValidationException::withMessages(['checkOutAt' => ['Check-out time must be after the employee check-in time.']]);
            }

            $bounds = $this->boundsForRecord($person, $record);
            if (! $bounds) throw new ApiConflictException('No working schedule was found for this attendance record.', 'NO_SCHEDULE');
            $maximum = $this->maximumCheckoutAt($person, $bounds);
            if ($maximum && $checkOut->greaterThan($maximum)) {
                throw new ApiConflictException('Checkout time exceeds the department overtime limit. Maximum allowed checkout time: '.$maximum->format('Y-m-d H:i').'.', 'OVERTIME_LIMIT_EXCEEDED');
            }

            $old = [
                'checkOutAt' => null,
                'checkOutSource' => null,
                'workedMinutes' => $record->worked_minutes,
                'status' => $record->status->value,
            ];
            $newStatus = $checkOut->lessThan($bounds['end']) ? AttendanceStatus::EarlyDeparture : $record->status;
            $record->update([
                'check_out_at' => $checkOut,
                'check_out_source' => AttendanceActionSource::AdminManual,
                'checked_out_by_user_id' => $actor->id,
                'worked_minutes' => $record->check_in_at->diffInMinutes($checkOut),
                'status' => $newStatus,
                'auto_checkout_reason' => null,
                'updated_by' => $actor->id,
            ]);
            $this->recordActionEvent($record, 'check_out', $checkOut, AttendanceActionSource::AdminManual, $actor, $data['note'] ?? null, ['channel' => 'web_dashboard']);
            $new = [
                'attendanceId' => $record->event_code,
                'personId' => $person->person_code,
                'checkOutAt' => $checkOut->toIso8601String(),
                'checkOutSource' => AttendanceActionSource::AdminManual->value,
                'workedMinutes' => $record->worked_minutes,
                'status' => $record->status->value,
                'note' => $data['note'] ?? null,
            ];
            $this->audit->record('attendance.manual_checkout', $record, $old, $new, userId: $actor->id);

            return $record->load(['person.department', 'events.performer', 'checkedInBy', 'checkedOutBy']);
        });
    }

    public function currentStatus(Person $person, ?Carbon $at = null): array
    {
        $at ??= now();
        $person->loadMissing(['department', 'role']);
        $record = AttendanceRecord::where('person_id', $person->id)->whereNotNull('check_in_at')->whereNull('check_out_at')->latest('check_in_at')->first();
        $record ??= AttendanceRecord::where('person_id', $person->id)->whereDate('attendance_date', $at)->latest('check_in_at')->first();
        $bounds = $record ? $this->boundsForRecord($person, $record) : $this->schedules->boundsFor($person, $at->copy()->startOfDay());
        $maximum = $bounds ? $this->maximumCheckoutAt($person, $bounds) : null;
        $worked = $record?->check_in_at ? $record->check_in_at->diffInMinutes($record->check_out_at ?? $at) : 0;
        [$regular, $overtime] = $record ? $this->workMinuteBreakdown($record, $record->check_out_at ?? $at, $bounds) : [0, 0];

        return [
            'personId' => $person->person_code,
            'personName' => $person->full_name,
            'department' => $person->department?->name ?? 'Unassigned',
            'role' => $person->role?->name ?? $person->job_title,
            'status' => $record?->check_in_at && ! $record->check_out_at ? 'checked_in' : ($record?->check_out_at ? 'checked_out' : 'not_checked_in'),
            'attendanceId' => $record?->event_code,
            'checkInAt' => $record?->check_in_at?->toIso8601String(),
            'checkOutAt' => $record?->check_out_at?->toIso8601String(),
            'checkInSource' => $record?->check_in_source?->value,
            'checkOutSource' => $record?->check_out_source?->value,
            'workedMinutes' => $worked,
            'regularMinutes' => $regular,
            'overtimeMinutes' => $overtime,
            'schedule' => $bounds ? ['name' => $bounds['schedule']->name, 'startAt' => $bounds['start']->toIso8601String(), 'endAt' => $bounds['end']->toIso8601String()] : null,
            'scheduledEnd' => isset($bounds['end']) ? $bounds['end']->toIso8601String() : null,
            'maximumCheckoutAt' => $maximum?->toIso8601String(),
            'maxOvertimeMinutes' => $person->department?->max_overtime_minutes,
        ];
    }

    public function recordActionEvent(AttendanceRecord $record, string $type, Carbon $occurredAt, AttendanceActionSource $source, ?User $actor = null, ?string $note = null, ?array $metadata = null): AttendanceEvent
    {
        return $record->events()->create([
            'person_id' => $record->person_id,
            'type' => $type,
            'occurred_at' => $occurredAt,
            'source' => $source,
            'performed_by_user_id' => $actor?->id,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }

    public function createManual(array $data, User $actor): AttendanceRecord
    {
        return DB::transaction(function () use ($data, $actor): AttendanceRecord {
            $person = Person::where('person_code', $data['personId'])->lockForUpdate()->firstOrFail();
            $date = Carbon::parse($data['date'])->startOfDay();
            if (AttendanceRecord::where('person_id', $person->id)->whereDate('attendance_date', $date)->lockForUpdate()->exists()) {
                throw new ApiConflictException('Attendance already exists for this person and date.', 'ATTENDANCE_EXISTS');
            }
            $checkIn = $this->timestamp($data['checkIn'] ?? null, $date);
            $checkOut = $this->timestamp($data['checkOut'] ?? null, $date);
            if ($checkIn && $checkOut && $checkOut->lessThan($checkIn)) $checkOut->addDay();
            [$status, $minutesLate] = $this->calculateStatus($person, $date, $checkIn);
            $bounds = $this->schedules->boundsFor($person, $date);
            $record = AttendanceRecord::create([
                'event_code' => 'TMP-'.Str::uuid(), 'person_id' => $person->id, 'attendance_date' => $date, 'scheduled_start' => $bounds ? $bounds['start']->format('H:i:s') : null, 'scheduled_end' => $bounds ? $bounds['end']->format('H:i:s') : null,
                'check_in_at' => $checkIn, 'check_in_source' => $checkIn ? AttendanceActionSource::AdminManual : null, 'checked_in_by_user_id' => $checkIn ? $actor->id : null,
                'check_out_at' => $checkOut, 'check_out_source' => $checkOut ? AttendanceActionSource::AdminManual : null, 'checked_out_by_user_id' => $checkOut ? $actor->id : null, 'status' => $data['status'] ?? $status->value, 'minutes_late' => $minutesLate,
                'worked_minutes' => $checkIn && $checkOut ? $checkIn->diffInMinutes($checkOut) : null, 'verification_method' => VerificationMethod::Manual,
                'source' => AttendanceSource::Dashboard, 'liveness_result' => LivenessResult::NotProvided, 'location' => $data['location'] ?? null, 'notes' => $data['notes'] ?? $data['reason'] ?? null, 'created_by' => $actor->id,
            ]);
            $record->update(['event_code' => 'EVT-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)]);
            if ($checkIn) $this->recordActionEvent($record, 'check_in', $checkIn, AttendanceActionSource::AdminManual, $actor, $data['notes'] ?? $data['reason'] ?? null);
            if ($checkOut) $this->recordActionEvent($record, 'check_out', $checkOut, AttendanceActionSource::AdminManual, $actor, $data['notes'] ?? $data['reason'] ?? null);
            $this->audit->record('attendance.manual_created', $record, newValues: $record->toArray(), userId: $actor->id);
            return $record->load('person.department');
        });
    }

    public function calculateStatus(Person $person, Carbon $date, ?Carbon $checkIn): array
    {
        $bounds = $this->schedules->boundsFor($person, $date);
        if ($this->hasApprovedLeave($person, $date)) return [AttendanceStatus::OnLeave, 0, $bounds['schedule'] ?? null];
        if ($this->isHoliday($person, $date)) return [AttendanceStatus::Excused, 0, $bounds['schedule'] ?? null];
        if (! $checkIn) return [AttendanceStatus::Absent, 0, $bounds['schedule'] ?? null];
        if (! $bounds) return [AttendanceStatus::Present, 0, null];
        $grace = $bounds['schedule']->grace_period_minutes ?? (int) $this->settings->get('gracePeriod');
        $minutesLate = max(0, (int) $bounds['start']->diffInMinutes($checkIn, false));
        return [$minutesLate > $grace ? AttendanceStatus::Late : AttendanceStatus::Present, $minutesLate, $bounds['schedule']];
    }

    public function requestCorrection(AttendanceRecord $attendance, array $data, User $actor): AttendanceCorrection
    {
        $allowed = ['checkInAt', 'checkOutAt', 'status', 'notes'];
        $requested = Arr::only($data['requestedValues'], $allowed);
        $previous = ['checkInAt' => $attendance->check_in_at?->toIso8601String(), 'checkOutAt' => $attendance->check_out_at?->toIso8601String(), 'status' => $attendance->status->value, 'notes' => $attendance->notes];
        $correction = $attendance->corrections()->create(['requested_by' => $actor->id, 'previous_values' => $previous, 'requested_values' => $requested, 'reason' => $data['reason'], 'status' => CorrectionStatus::Pending]);
        $this->audit->record('attendance.correction_requested', $attendance, $previous, $requested, userId: $actor->id);
        $this->notifyApprovers('Attendance correction requested', $attendance->event_code.' requires review.', ['attendanceId' => $attendance->event_code, 'correctionId' => $correction->id]);
        return $correction->load(['attendance', 'requester', 'reviewer']);
    }

    public function reviewCorrection(AttendanceCorrection $correction, User $reviewer, bool $approve, ?string $note = null): AttendanceCorrection
    {
        return DB::transaction(function () use ($correction, $reviewer, $approve, $note): AttendanceCorrection {
            $correction = AttendanceCorrection::whereKey($correction->id)->lockForUpdate()->firstOrFail();
            if ($correction->status !== CorrectionStatus::Pending) throw new ApiConflictException('This correction has already been reviewed.', 'CORRECTION_ALREADY_REVIEWED');
            if ($approve) {
                $record = AttendanceRecord::whereKey($correction->attendance_id)->lockForUpdate()->firstOrFail();
                $mapped = Arr::whereNotNull(['check_in_at' => $correction->requested_values['checkInAt'] ?? null, 'check_out_at' => $correction->requested_values['checkOutAt'] ?? null, 'status' => $correction->requested_values['status'] ?? null, 'notes' => $correction->requested_values['notes'] ?? null]);
                if (isset($mapped['check_out_at'])) { $mapped['check_out_source'] = AttendanceActionSource::AdminCorrection; $mapped['checked_out_by_user_id'] = $reviewer->id; $mapped['auto_checkout_reason'] = null; }
                $effectiveCheckIn = isset($mapped['check_in_at']) ? Carbon::parse($mapped['check_in_at']) : $record->check_in_at;
                $effectiveCheckOut = isset($mapped['check_out_at']) ? Carbon::parse($mapped['check_out_at']) : $record->check_out_at;
                if (($effectiveCheckIn && $effectiveCheckOut) && (isset($mapped['check_in_at']) || isset($mapped['check_out_at']))) $mapped['worked_minutes'] = $effectiveCheckIn->diffInMinutes($effectiveCheckOut);
                $mapped['verification_method'] = VerificationMethod::AdminCorrection;
                $mapped['updated_by'] = $reviewer->id;
                $record->update($mapped);
                if (isset($mapped['check_out_at'])) $this->recordActionEvent($record, 'manual_correction', Carbon::parse($mapped['check_out_at']), AttendanceActionSource::AdminCorrection, $reviewer, $note, ['correctionId' => $correction->id]);
            }
            $correction->update(['status' => $approve ? CorrectionStatus::Approved : CorrectionStatus::Rejected, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
            $this->audit->record($approve ? 'attendance.correction_approved' : 'attendance.correction_rejected', $correction->attendance, $correction->previous_values, $correction->requested_values, userId: $reviewer->id);
            $correction->requester?->notify(new SystemActionNotification('Attendance correction '.($approve ? 'approved' : 'rejected'), $correction->attendance?->event_code.' was reviewed.', $approve ? 'success' : 'warning'));
            return $correction->fresh(['attendance', 'requester', 'reviewer']);
        });
    }

    public function delete(AttendanceRecord $attendance, User $actor): void
    {
        $this->audit->record('attendance.deleted', $attendance, $attendance->toArray(), userId: $actor->id);
        $attendance->delete();
    }

    public function createLeave(array $data): LeaveRequest
    {
        $person = Person::where('person_code', $data['personId'])->firstOrFail();
        $leave = LeaveRequest::create(['request_code' => 'TMP-'.Str::uuid(), 'person_id' => $person->id, 'start_date' => $data['startDate'], 'end_date' => $data['endDate'], 'type' => $data['type'], 'reason' => $data['reason'], 'status' => LeaveStatus::Pending, 'requested_at' => now()]);
        $leave->update(['request_code' => 'LR-'.str_pad((string) $leave->id, 6, '0', STR_PAD_LEFT)]);
        $this->notifyApprovers('Leave request submitted', $person->full_name.' submitted a leave request.', ['leaveRequestId' => $leave->request_code]);
        return $leave->load('person');
    }

    public function reviewLeave(LeaveRequest $leave, User $reviewer, bool $approve, ?string $note = null): LeaveRequest
    {
        return DB::transaction(function () use ($leave, $reviewer, $approve, $note): LeaveRequest {
            $leave = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            if ($leave->status !== LeaveStatus::Pending) throw new ApiConflictException('This leave request has already been reviewed.', 'LEAVE_ALREADY_REVIEWED');
            $leave->update(['status' => $approve ? LeaveStatus::Approved : LeaveStatus::Rejected, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
            if ($approve) {
                foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $date) {
                    $record = AttendanceRecord::firstOrCreate(['person_id' => $leave->person_id, 'attendance_date' => $date->toDateString()], ['event_code' => 'TMP-'.Str::uuid(), 'status' => AttendanceStatus::OnLeave, 'verification_method' => VerificationMethod::LeaveApproval, 'source' => AttendanceSource::System, 'liveness_result' => LivenessResult::NotProvided, 'notes' => 'Approved leave '.$leave->request_code, 'created_by' => $reviewer->id]);
                    if (str_starts_with($record->event_code, 'TMP-')) $record->update(['event_code' => 'EVT-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT)]);
                }
            }
            $this->audit->record($approve ? 'leave.approved' : 'leave.rejected', $leave, newValues: ['status' => $leave->status->value], userId: $reviewer->id);
            return $leave->fresh(['person', 'reviewer']);
        });
    }

    private function hasApprovedLeave(Person $person, Carbon $date): bool { return $person->leaveRequests()->where('status', LeaveStatus::Approved)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists(); }
    private function isHoliday(Person $person, Carbon $date): bool { return Holiday::whereDate('date', $date)->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $person->department_id))->exists(); }

    private function timestamp(?string $value, Carbon $date): ?Carbon
    {
        if (! $value) return null;
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? Carbon::parse($date->toDateString().' '.$value) : Carbon::parse($value);
    }

    private function personForAction(string|int $identifier): Person
    {
        return Person::query()->with(['department', 'role'])->where(function (Builder $query) use ($identifier): void {
            $query->where('person_code', (string) $identifier);
            if (is_numeric($identifier)) $query->orWhereKey((int) $identifier);
        })->lockForUpdate()->firstOrFail();
    }

    private function boundsForRecord(Person $person, AttendanceRecord $record): ?array
    {
        return $this->schedules->boundsFor($person, Carbon::parse($record->attendance_date), $record->scheduled_start, $record->scheduled_end);
    }

    private function maximumCheckoutAt(Person $person, array $bounds): ?Carbon
    {
        if ($person->department?->max_overtime_minutes === null) return null;
        return $bounds['end']->copy()->addMinutes((int) $person->department->max_overtime_minutes);
    }

    private function workMinuteBreakdown(AttendanceRecord $record, Carbon $until, ?array $bounds): array
    {
        if (! $record->check_in_at || ! $bounds || $until->lessThanOrEqualTo($record->check_in_at)) return [0, 0];
        $total = $record->check_in_at->diffInMinutes($until);
        $overlapStart = $record->check_in_at->greaterThan($bounds['start']) ? $record->check_in_at : $bounds['start'];
        $overlapEnd = $until->lessThan($bounds['end']) ? $until : $bounds['end'];
        $regular = $overlapEnd->greaterThan($overlapStart) ? $overlapStart->diffInMinutes($overlapEnd) : 0;
        return [$regular, max(0, $total - $regular)];
    }

    private function notifyApprovers(string $title, string $message, array $context): void
    {
        User::where('role', 'super_admin')->orWhereHas('permissions', fn ($q) => $q->where('name', 'attendance.approve'))->each(fn (User $user) => $user->notify(new SystemActionNotification($title, $message, 'warning', $context)));
    }
}
