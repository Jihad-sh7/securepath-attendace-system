<?php

namespace Tests\Feature;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\LivenessResult;
use App\Enums\VerificationMethod;
use App\Models\Alert;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\DepartmentRoleRate;
use App\Models\Person;
use App\Models\Role;
use App\Models\Schedule;
use App\Services\AutoCheckoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAuthorizedUsers;
use Tests\TestCase;

class PayrollAndAutoCheckoutTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    public function test_payroll_uses_historical_rate_and_splits_regular_and_overtime_minutes(): void
    {
        $admin = $this->adminWith(['payroll.view', 'payroll.manage']);
        Sanctum::actingAs($admin);
        [$department, $role, $person] = $this->employeeWithSchedule('08:30', '17:00');
        DepartmentRoleRate::create(['department_id' => $department->id, 'role_id' => $role->id, 'hourly_rate' => '20.0000', 'currency' => 'USD', 'effective_from' => '2026-07-01']);
        $this->attendance($person, '2026-07-10', '08:00', '18:00');

        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 7])->assertCreated();
        $periodId = $this->getJson('/api/v1/payroll?year=2026&month=7')->assertOk()->json('data.period.id');
        $this->getJson("/api/v1/payroll/periods/{$periodId}/employees")
            ->assertOk()->assertJsonPath('data.0.regularMinutes', 510)->assertJsonPath('data.0.overtimeMinutes', 90)
            ->assertJsonPath('data.0.totalWorkedMinutes', 600)->assertJsonPath('data.0.totalPay', '200.00');
    }

    public function test_payroll_marks_missing_role_or_rate_for_attention(): void
    {
        $admin = $this->adminWith(['payroll.view', 'payroll.manage']); Sanctum::actingAs($admin);
        $department = Department::factory()->create();
        $person = Person::factory()->create(['department_id' => $department->id, 'role_id' => null]);
        $this->attendance($person, '2026-07-10', '08:30', '17:00');
        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 7])->assertCreated();
        $periodId = $this->getJson('/api/v1/payroll?year=2026&month=7')->json('data.period.id');
        $this->getJson("/api/v1/payroll/periods/{$periodId}/employees")->assertJsonPath('data.0.status', 'Attention')->assertJsonPath('data.0.totalPay', null);
    }

    public function test_payroll_includes_active_employee_without_attendance_as_zero_pay(): void
    {
        $admin = $this->adminWith(['payroll.view', 'payroll.manage']);
        Sanctum::actingAs($admin);
        [$department, $role] = $this->employeeWithSchedule('08:30', '17:00');
        DepartmentRoleRate::create(['department_id' => $department->id, 'role_id' => $role->id, 'hourly_rate' => '20.0000', 'currency' => 'USD', 'effective_from' => '2026-07-01']);

        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 7])->assertCreated();
        $periodId = $this->getJson('/api/v1/payroll?year=2026&month=7')->json('data.period.id');
        $this->getJson("/api/v1/payroll/periods/{$periodId}/employees")
            ->assertOk()
            ->assertJsonPath('data.0.regularMinutes', 0)
            ->assertJsonPath('data.0.overtimeMinutes', 0)
            ->assertJsonPath('data.0.hourlyRate', '20.0000')
            ->assertJsonPath('data.0.totalPay', '0.00')
            ->assertJsonPath('data.0.status', 'Calculated');
    }

    public function test_payroll_applies_each_rate_when_hourly_rate_changes_mid_period(): void
    {
        $admin = $this->adminWith(['payroll.view', 'payroll.manage']); Sanctum::actingAs($admin);
        [$department, $role, $person] = $this->employeeWithSchedule('08:30', '17:00');
        DepartmentRoleRate::create(['department_id' => $department->id, 'role_id' => $role->id, 'hourly_rate' => '20.0000', 'currency' => 'USD', 'effective_from' => '2026-07-01', 'effective_until' => '2026-07-15']);
        DepartmentRoleRate::create(['department_id' => $department->id, 'role_id' => $role->id, 'hourly_rate' => '30.0000', 'currency' => 'USD', 'effective_from' => '2026-07-16']);
        $this->attendance($person, '2026-07-10', '08:30', '17:00');
        $this->attendance($person, '2026-07-20', '08:30', '17:00');

        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 7])->assertCreated();
        $periodId = $this->getJson('/api/v1/payroll?year=2026&month=7')->json('data.period.id');
        $this->getJson("/api/v1/payroll/periods/{$periodId}/employees")->assertOk()->assertJsonPath('data.0.totalPay', '425.00')->assertJsonPath('data.0.hourlyRate', null);
    }

    public function test_closed_payroll_period_can_be_recalculated_from_latest_attendance(): void
    {
        $admin = $this->adminWith(['payroll.view', 'payroll.manage']);
        Sanctum::actingAs($admin);
        [$department, $role, $person] = $this->employeeWithSchedule('08:30', '17:00');
        DepartmentRoleRate::create(['department_id' => $department->id, 'role_id' => $role->id, 'hourly_rate' => '20.0000', 'currency' => 'USD', 'effective_from' => '2026-07-01']);
        $this->attendance($person, '2026-07-10', '08:30', '17:00');

        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 7])->assertCreated();
        $periodId = $this->getJson('/api/v1/payroll?year=2026&month=7')->json('data.period.id');
        $this->postJson("/api/v1/payroll/periods/{$periodId}/approve")->assertOk();
        $this->postJson("/api/v1/payroll/periods/{$periodId}/close")->assertOk();

        $second = Person::factory()->create(['department_id' => $department->id, 'role_id' => $role->id, 'job_title' => $role->name]);
        $this->attendance($second, '2026-07-11', '08:30', '17:00');

        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 7])
            ->assertCreated()
            ->assertJsonPath('data.status', 'Calculated')
            ->assertJsonPath('data.entryCount', 2);
        $this->getJson("/api/v1/payroll/periods/{$periodId}/employees")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_payroll_endpoints_enforce_permissions(): void
    {
        Sanctum::actingAs($this->adminWith(['attendance.view']));
        $this->getJson('/api/v1/payroll')->assertForbidden();
        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 7])->assertForbidden();
    }

    public function test_auto_checkout_uses_exact_deadline_and_is_idempotent(): void
    {
        [$department, , $person] = $this->employeeWithSchedule('08:00', '17:00');
        $department->update(['auto_checkout_enabled' => true, 'max_overtime_minutes' => 120]);
        $record = $this->attendance($person, '2026-08-14', '08:00', null);
        $service = app(AutoCheckoutService::class);

        $service->process(Carbon::parse('2026-08-14 20:00:00'));
        $service->process(Carbon::parse('2026-08-14 20:05:00'));

        $record->refresh();
        $this->assertSame('2026-08-14 19:00:00', $record->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('auto_overtime_limit', $record->check_out_source->value);
        $this->assertSame(660, $record->worked_minutes);
        $this->assertSame(1, Alert::where('type', 'automatic_checkout')->where('attendance_id', $record->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.auto_checkout', 'resource_id' => $record->event_code]);
    }

    public function test_auto_checkout_handles_overnight_shifts_and_skips_missing_schedules(): void
    {
        [$department, , $person] = $this->employeeWithSchedule('22:00', '06:00');
        $department->update(['auto_checkout_enabled' => true, 'max_overtime_minutes' => 60]);
        $overnight = $this->attendance($person, '2026-08-14', '22:00', null, '22:00', '06:00');

        $noScheduleDepartment = Department::factory()->create(['auto_checkout_enabled' => true, 'max_overtime_minutes' => 60]);
        $noSchedulePerson = Person::factory()->create(['department_id' => $noScheduleDepartment->id]);
        $missing = $this->attendance($noSchedulePerson, '2026-08-14', '08:00', null, null, null);

        app(AutoCheckoutService::class)->process(Carbon::parse('2026-08-15 08:00:00'));

        $this->assertSame('2026-08-15 07:00:00', $overnight->fresh()->check_out_at->format('Y-m-d H:i:s'));
        $this->assertNull($missing->fresh()->check_out_at);
    }

    private function employeeWithSchedule(string $start, string $end): array
    {
        $department = Department::factory()->create();
        $role = Role::create(['name' => 'Developer', 'code' => 'DEVELOPER', 'status' => 'active']);
        $person = Person::factory()->create(['department_id' => $department->id, 'role_id' => $role->id, 'job_title' => $role->name]);
        $schedule = Schedule::create(['name' => 'Office', 'code' => 'SCH-'.uniqid(), 'type' => 'fixed', 'start_time' => $start, 'end_time' => $end, 'grace_period_minutes' => 10, 'status' => 'active']);
        $department->schedules()->attach($schedule);
        return [$department, $role, $person];
    }

    private function attendance(Person $person, string $date, string $checkIn, ?string $checkOut, ?string $scheduledStart = '08:30', ?string $scheduledEnd = '17:00'): AttendanceRecord
    {
        $in = Carbon::parse("{$date} {$checkIn}");
        $out = $checkOut ? Carbon::parse("{$date} {$checkOut}") : null;
        if ($out && $out->lessThanOrEqualTo($in)) $out->addDay();
        return AttendanceRecord::create(['event_code' => 'EVT-'.uniqid(), 'person_id' => $person->id, 'attendance_date' => $date, 'scheduled_start' => $scheduledStart, 'scheduled_end' => $scheduledEnd, 'check_in_at' => $in, 'check_in_source' => 'admin_manual', 'check_out_at' => $out, 'check_out_source' => $out ? 'admin_manual' : null, 'status' => AttendanceStatus::Present, 'worked_minutes' => $out ? $in->diffInMinutes($out) : null, 'verification_method' => VerificationMethod::Manual, 'liveness_result' => LivenessResult::NotProvided, 'source' => AttendanceSource::Dashboard]);
    }
}
