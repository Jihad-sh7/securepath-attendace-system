<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Person;
use App\Models\RecognitionEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardService
{
    public function dashboard(?string $date = null): array
    {
        $day = Carbon::parse($date ?? today())->startOfDay();
        $totalPeople = Person::where('status', 'active')->count();
        $attendance = AttendanceRecord::whereDate('attendance_date', $day);
        $recorded = (clone $attendance)->count();
        $recognitions = RecognitionEvent::whereDate('occurred_at', $day);
        $totalRecognitions = (clone $recognitions)->whereNot('recognition_status', 'duplicate')->count();
        $matchedRecognitions = (clone $recognitions)->where('recognition_status', 'matched')->count();

        return [
            'stats' => [
                'totalUsers' => $totalPeople,
                'totalPeople' => $totalPeople,
                'presentToday' => (clone $attendance)->where('status', 'present')->count(),
                'absentToday' => max(0, $totalPeople - $recorded) + (clone $attendance)->where('status', 'absent')->count(),
                'lateToday' => (clone $attendance)->where('status', 'late')->count(),
                'onLeaveToday' => (clone $attendance)->where('status', 'on_leave')->count(),
                'attendanceRate' => $totalPeople ? round($recorded / $totalPeople * 100, 1) : 0,
                'recognitionSuccessRate' => $totalRecognitions ? round($matchedRecognitions / $totalRecognitions * 100, 1) : 0,
                'pendingVerification' => (clone $recognitions)->where('verification_status', 'pending')->count(),
                'activeAdmins' => User::whereIn('role', ['admin', 'super_admin'])->where('status', 'active')->count(),
            ],
            'attendanceTrend' => $this->attendanceTrend($day),
            'statusBreakdown' => collect(['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On Leave'])->map(fn (string $label, string $status) => ['name' => $label, 'value' => (clone $attendance)->where('status', $status)->count()])->values(),
            'recognitionAccuracy' => $this->recognitionTrend($day),
            'departments' => $this->departmentStats($day),
            'recentActivity' => RecognitionEvent::with('person')->latest('occurred_at')->limit(10)->get()->map(fn (RecognitionEvent $event) => ['id' => $event->recognition_code, 'personId' => $event->person?->person_code, 'name' => $event->person?->full_name ?? 'Unknown Face', 'confidence' => round((float) $event->confidence * 100, 1), 'status' => $event->verification_status->value, 'occurredAt' => $event->occurred_at?->toIso8601String(), 'deviceIdentifier' => $event->source_device_identifier]),
        ];
    }

    public function attendanceTrend(Carbon $end): array
    {
        $start = $end->copy()->subDays(6);
        $rows = AttendanceRecord::whereBetween('attendance_date', [$start, $end])->get(['attendance_date', 'status'])->groupBy(fn ($record) => $record->attendance_date->toDateString());
        return collect(CarbonPeriod::create($start, $end))->map(function (Carbon $date) use ($rows): array {
            $day = $rows->get($date->toDateString(), collect());
            return ['day' => $date->format('D'), 'date' => $date->toDateString(), 'present' => $day->where('status.value', 'present')->count(), 'late' => $day->where('status.value', 'late')->count(), 'absent' => $day->where('status.value', 'absent')->count()];
        })->values()->all();
    }

    public function recognitionTrend(Carbon $end): array
    {
        $start = $end->copy()->subDays(6);
        $rows = RecognitionEvent::whereBetween('occurred_at', [$start, $end->copy()->endOfDay()])->get()->groupBy(fn ($event) => $event->occurred_at->toDateString());
        return collect(CarbonPeriod::create($start, $end))->map(function (Carbon $date) use ($rows): array {
            $day = $rows->get($date->toDateString(), collect());
            return ['day' => $date->format('D'), 'date' => $date->toDateString(), 'confidence' => round((float) $day->avg('confidence') * 100, 1), 'events' => $day->count()];
        })->values()->all();
    }

    public function departmentStats(Carbon $day): array
    {
        return Department::withCount('people')->with(['manager', 'schedules'])->get()->map(function (Department $department) use ($day): array {
            $records = AttendanceRecord::whereDate('attendance_date', $day)->whereHas('person', fn ($query) => $query->where('department_id', $department->id))->get();
            $members = max(1, $department->people_count);
            return ['id' => $department->code, 'name' => $department->name, 'manager' => $department->manager?->name, 'memberCount' => $department->people_count, 'assignedSchedule' => $department->schedules->first()?->name, 'attendanceRate' => round($records->count() / $members * 100, 1), 'lateRate' => round($records->where('status.value', 'late')->count() / $members * 100, 1), 'absenceRate' => round($records->where('status.value', 'absent')->count() / $members * 100, 1)];
        })->values()->all();
    }
}

