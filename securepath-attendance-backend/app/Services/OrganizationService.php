<?php

namespace App\Services;

use App\Exceptions\ApiConflictException;
use App\Models\Department;
use App\Models\Person;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function __construct(private readonly AuditService $audit) {}

    public function saveDepartment(?Department $department, array $data): Department
    {
        $department ??= new Department;
        $old = $department->exists ? $department->toArray() : null;
        $department->fill(['name' => $data['name'] ?? $department->name, 'code' => strtoupper($data['code'] ?? $department->code), 'status' => $data['status'] ?? $department->status ?? 'active']);
        if (array_key_exists('description', $data)) $department->description = $data['description'];
        if (array_key_exists('managerId', $data)) $department->manager_id = $data['managerId'];
        if (array_key_exists('maxOvertimeMinutes', $data)) $department->max_overtime_minutes = $data['maxOvertimeMinutes'];
        if (array_key_exists('autoCheckoutEnabled', $data)) $department->auto_checkout_enabled = $data['autoCheckoutEnabled'];
        $department->save();
        $this->audit->record($old ? 'department.updated' : 'department.created', $department, $old, $department->toArray());
        return $department->load(['manager', 'schedules'])->loadCount('people');
    }

    public function saveSchedule(?Schedule $schedule, array $data): Schedule
    {
        return DB::transaction(function () use ($schedule, $data): Schedule {
            $schedule ??= new Schedule;
            $old = $schedule->exists ? $schedule->toArray() : null;
            $mapped = [];
            foreach (['name' => 'name', 'type' => 'type', 'startTime' => 'start_time', 'endTime' => 'end_time', 'gracePeriodMinutes' => 'grace_period_minutes', 'breakDurationMinutes' => 'break_duration_minutes', 'breakWindow' => 'break_window', 'effectiveFrom' => 'effective_from', 'effectiveUntil' => 'effective_until', 'status' => 'status'] as $source => $target) {
                if (array_key_exists($source, $data)) $mapped[$target] = $data[$source];
            }
            if (array_key_exists('code', $data)) $mapped['code'] = strtoupper($data['code']);
            $schedule->fill($mapped)->save();
            if (isset($data['days'])) {
                $schedule->days()->delete();
                $schedule->days()->createMany(array_map(fn ($day) => ['day_of_week' => $day['dayOfWeek'], 'is_working_day' => $day['isWorkingDay'], 'start_time' => $day['startTime'] ?? null, 'end_time' => $day['endTime'] ?? null], $data['days']));
            }
            if (isset($data['personIds'])) $schedule->people()->sync(Person::whereIn('person_code', $data['personIds'])->pluck('id'));
            if (isset($data['departmentIds'])) $schedule->departments()->sync(Department::whereIn('code', $data['departmentIds'])->pluck('id'));
            $this->assertNoAssignmentConflict($schedule);
            $this->audit->record($old ? 'schedule.updated' : 'schedule.created', $schedule, $old, $schedule->toArray());
            return $schedule->load(['days', 'people', 'departments']);
        });
    }

    private function assertNoAssignmentConflict(Schedule $schedule): void
    {
        $personIds = $schedule->people()->pluck('people.id');
        $departmentIds = $schedule->departments()->pluck('departments.id');
        $conflict = Schedule::whereKeyNot($schedule->id)->where('status', 'active')->where(fn ($query) => $query
            ->whereHas('people', fn ($q) => $q->whereIn('people.id', $personIds))
            ->orWhereHas('departments', fn ($q) => $q->whereIn('departments.id', $departmentIds)))->exists();
        if ($conflict) throw new ApiConflictException('A person or department already has an overlapping active schedule.', 'SCHEDULE_CONFLICT');
    }
}
