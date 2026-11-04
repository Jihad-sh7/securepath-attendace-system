<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->code,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'managerId' => $this->manager_id,
            'manager' => $this->manager?->name,
            'status' => ucfirst($this->status),
            'maxOvertimeMinutes' => $this->max_overtime_minutes,
            'maxOvertimeHours' => $this->max_overtime_minutes === null ? null : round($this->max_overtime_minutes / 60, 2),
            'autoCheckoutEnabled' => $this->auto_checkout_enabled,
            'memberCount' => $this->people_count ?? $this->people?->count() ?? 0,
            'assignedSchedule' => $this->schedules?->first()?->name,
            'attendanceRate' => (float) ($this->attendance_rate ?? 0),
            'lateRate' => (float) ($this->late_rate ?? 0),
            'absenceRate' => (float) ($this->absence_rate ?? 0),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
