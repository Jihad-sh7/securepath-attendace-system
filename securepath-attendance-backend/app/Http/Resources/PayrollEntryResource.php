<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'periodId' => $this->payroll_period_id, 'personId' => $this->person_code, 'personName' => $this->person_name, 'departmentId' => $this->department_code, 'department' => $this->department_name ?? 'Unassigned', 'roleId' => $this->role_code, 'role' => $this->role_name ?? 'Unassigned', 'regularMinutes' => $this->regular_minutes, 'overtimeMinutes' => $this->overtime_minutes, 'totalWorkedMinutes' => $this->total_worked_minutes, 'regularHours' => round($this->regular_minutes / 60, 2), 'overtimeHours' => round($this->overtime_minutes / 60, 2), 'totalWorkedHours' => round($this->total_worked_minutes / 60, 2), 'hourlyRate' => $this->hourly_rate, 'currency' => $this->currency, 'regularPay' => $this->regular_pay, 'overtimePay' => $this->overtime_pay, 'totalPay' => $this->total_pay, 'status' => ucfirst($this->status), 'issues' => $this->issues ?? [], 'attendanceBreakdown' => $this->attendance_breakdown ?? []];
    }
}
