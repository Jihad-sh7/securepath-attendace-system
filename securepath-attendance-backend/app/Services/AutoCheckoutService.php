<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AttendanceActionSource;
use App\Events\AlertCreated;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutoCheckoutService
{
    public function __construct(private readonly WorkScheduleService $schedules, private readonly AuditService $audit, private readonly AttendanceService $attendance) {}

    public function process(Carbon $now): array
    {
        $result = ['checked' => 0, 'closed' => 0, 'skipped' => 0];

        AttendanceRecord::query()->whereNotNull('check_in_at')->whereNull('check_out_at')->with('person.department')->orderBy('id')
            ->chunkById(100, function ($records) use ($now, &$result): void {
                foreach ($records as $record) {
                    $result['checked']++;
                    $person = $record->person;
                    $department = $person?->department;
                    if (! $person || ! $department?->auto_checkout_enabled || $department->max_overtime_minutes === null) {
                        $result['skipped']++;
                        continue;
                    }

                    $bounds = $this->schedules->boundsFor($person, Carbon::parse($record->attendance_date), $record->scheduled_start, $record->scheduled_end);
                    if (! $bounds) {
                        $result['skipped']++;
                        continue;
                    }

                    $deadline = $bounds['end']->copy()->addMinutes((int) $department->max_overtime_minutes);
                    if ($now->lessThan($deadline) || $deadline->lessThanOrEqualTo($record->check_in_at)) continue;
                    if ($this->closeRecord($record->id, $deadline, (int) $department->max_overtime_minutes, $bounds['end'])) $result['closed']++;
                }
            });

        return $result;
    }

    private function closeRecord(int $recordId, Carbon $deadline, int $maxOvertimeMinutes, Carbon $scheduledEnd): bool
    {
        return DB::transaction(function () use ($recordId, $deadline, $maxOvertimeMinutes, $scheduledEnd): bool {
            $record = AttendanceRecord::with('person.department')->lockForUpdate()->findOrFail($recordId);
            if ($record->check_out_at) return false;

            $reason = "Maximum department overtime of {$maxOvertimeMinutes} minutes reached.";
            $record->update(['check_out_at' => $deadline, 'worked_minutes' => $record->check_in_at->diffInMinutes($deadline), 'check_out_source' => AttendanceActionSource::AutoOvertimeLimit, 'auto_checkout_reason' => $reason]);
            $this->attendance->recordActionEvent($record, 'check_out', $deadline, AttendanceActionSource::AutoOvertimeLimit, note: $reason, metadata: ['maxOvertimeMinutes' => $maxOvertimeMinutes]);

            $alert = Alert::firstOrCreate(
                ['type' => 'automatic_checkout', 'attendance_id' => $record->id],
                ['alert_code' => 'TMP-'.Str::uuid(), 'severity' => AlertSeverity::Warning, 'title' => 'Employee automatically checked out', 'message' => $record->person->full_name.' was checked out when the department overtime limit was reached.', 'person_id' => $record->person_id, 'status' => AlertStatus::Open, 'metadata' => ['attendanceId' => $record->event_code, 'department' => $record->person->department?->name, 'scheduledEnd' => $scheduledEnd->toIso8601String(), 'maxOvertimeMinutes' => $maxOvertimeMinutes, 'checkoutAt' => $deadline->toIso8601String(), 'checkoutSource' => AttendanceActionSource::AutoOvertimeLimit->value, 'reason' => $reason]]
            );
            if (str_starts_with($alert->alert_code, 'TMP-')) {
                $alert->update(['alert_code' => 'ALT-'.str_pad((string) $alert->id, 6, '0', STR_PAD_LEFT)]);
                DB::afterCommit(fn () => AlertCreated::dispatch($alert));
            }

            $this->audit->record('attendance.auto_checkout', $record, null, ['checkOutAt' => $deadline->toIso8601String(), 'checkoutSource' => AttendanceActionSource::AutoOvertimeLimit->value, 'reason' => $reason]);
            return true;
        });
    }
}
