<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    use HasFactory;

    protected $fillable = ['payroll_period_id', 'person_id', 'department_id', 'role_id', 'person_code', 'person_name', 'department_code', 'department_name', 'role_code', 'role_name', 'regular_minutes', 'overtime_minutes', 'total_worked_minutes', 'hourly_rate', 'currency', 'regular_pay', 'overtime_pay', 'total_pay', 'status', 'issues', 'attendance_breakdown'];

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:4', 'regular_pay' => 'decimal:2', 'overtime_pay' => 'decimal:2', 'total_pay' => 'decimal:2', 'issues' => 'array', 'attendance_breakdown' => 'array'];
    }

    public function period(): BelongsTo { return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id'); }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
}
