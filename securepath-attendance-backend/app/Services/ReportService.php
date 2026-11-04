<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\RecognitionEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService
{
    public const TYPES = ['attendance', 'absences', 'late-arrivals', 'working-hours', 'departments', 'recognition-accuracy'];

    public function generate(string $type, array $filters): array
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
        return match ($type) {
            'departments' => $this->departments($filters),
            'recognition-accuracy' => $this->recognitionAccuracy($filters),
            default => ['type' => $type, 'filters' => $filters, 'summary' => $this->attendanceSummary($this->attendanceQuery($type, $filters)), 'rows' => $this->attendanceRows($this->attendanceQuery($type, $filters)->limit(1000)->get())],
        };
    }

    public function exportRows(string $type, array $filters): iterable
    {
        if ($type === 'recognition-accuracy') {
            foreach (RecognitionEvent::with('person')->when($filters['dateFrom'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '>=', $date))->when($filters['dateTo'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '<=', $date))->cursor() as $event) yield [$event->recognition_code, $event->person?->person_code, $event->person?->full_name, $event->confidence, $event->liveness_result->value, $event->recognition_status->value, $event->verification_status->value, $event->occurred_at?->toIso8601String()];
            return;
        }
        foreach ($this->attendanceQuery($type, $filters)->with('person.department')->cursor() as $record) yield [$record->event_code, $record->person?->person_code, $record->person?->full_name, $record->person?->department?->name, $record->attendance_date?->toDateString(), $record->check_in_at?->toIso8601String(), $record->check_out_at?->toIso8601String(), $record->status->value, $record->worked_minutes, $record->verification_method->value, $record->recognition_confidence];
    }

    private function attendanceQuery(string $type, array $filters): Builder
    {
        return AttendanceRecord::query()->with('person.department')
            ->when($type === 'absences', fn ($q) => $q->where('status', 'absent'))
            ->when($type === 'late-arrivals', fn ($q) => $q->where('status', 'late'))
            ->when($filters['dateFrom'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))
            ->when($filters['personId'] ?? null, fn ($q, $code) => $q->whereHas('person', fn ($person) => $person->where('person_code', $code)))
            ->when($filters['departmentId'] ?? null, fn ($q, $code) => $q->whereHas('person.department', fn ($department) => $department->where('code', $code)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('attendance_date');
    }

    private function attendanceSummary(Builder $query): array
    {
        $rows = (clone $query)->get(['status', 'worked_minutes', 'minutes_late']);
        return ['records' => $rows->count(), 'present' => $rows->where('status.value', 'present')->count(), 'late' => $rows->where('status.value', 'late')->count(), 'absent' => $rows->where('status.value', 'absent')->count(), 'averageWorkedMinutes' => round((float) $rows->avg('worked_minutes'), 1), 'averageMinutesLate' => round((float) $rows->avg('minutes_late'), 1)];
    }

    private function attendanceRows(Collection $rows): array
    {
        return $rows->map(fn ($record) => ['id' => $record->event_code, 'personId' => $record->person?->person_code, 'personName' => $record->person?->full_name, 'department' => $record->person?->department?->name, 'date' => $record->attendance_date?->toDateString(), 'checkIn' => $record->check_in_at?->format('H:i'), 'checkOut' => $record->check_out_at?->format('H:i'), 'status' => $record->status->value, 'workedMinutes' => $record->worked_minutes, 'minutesLate' => $record->minutes_late, 'verificationMethod' => $record->verification_method->value, 'confidence' => $record->recognition_confidence])->values()->all();
    }

    private function departments(array $filters): array
    {
        return ['type' => 'departments', 'rows' => Department::withCount('people')->get()->map(fn ($department) => ['id' => $department->code, 'name' => $department->name, 'memberCount' => $department->people_count])->all()];
    }

    private function recognitionAccuracy(array $filters): array
    {
        $query = RecognitionEvent::query()->when($filters['dateFrom'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '>=', $date))->when($filters['dateTo'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '<=', $date));
        $rows = $query->with('person')->limit(1000)->get();
        return ['type' => 'recognition-accuracy', 'summary' => ['events' => $rows->count(), 'matched' => $rows->where('recognition_status.value', 'matched')->count(), 'pending' => $rows->where('verification_status.value', 'pending')->count(), 'averageConfidence' => round((float) $rows->avg('confidence') * 100, 1)], 'rows' => $rows->map(fn ($event) => ['id' => $event->recognition_code, 'personId' => $event->person?->person_code, 'name' => $event->person?->full_name ?? 'Unknown Face', 'confidence' => round((float) $event->confidence * 100, 1), 'liveness' => $event->liveness_result->value, 'status' => $event->recognition_status->value, 'verificationStatus' => $event->verification_status->value, 'occurredAt' => $event->occurred_at?->toIso8601String()])->all()];
    }
}

