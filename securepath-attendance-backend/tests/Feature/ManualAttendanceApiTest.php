<?php

namespace Tests\Feature;

use App\Enums\AttendanceActionSource;
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

class ManualAttendanceApiTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_authorized_admin_can_manually_check_in_an_active_employee(): void
    {
        $admin = $this->adminWith(['attendance.manual_checkin', 'attendance.view', 'people.view']);
        Sanctum::actingAs($admin);
        [, , $person] = $this->employeeWithSchedule();

        $this->postJson('/api/v1/attendance/manual-check-in', [
            'personId' => $person->person_code,
            'checkInAt' => '2026-08-15T09:22:00+03:00',
            'note' => 'Camera was unavailable.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'Late')
            ->assertJsonPath('data.checkInSource', 'admin_manual')
            ->assertJsonPath('data.checkedInBy', $admin->name);

        $this->assertDatabaseHas('attendance_records', [
            'person_id' => $person->id,
            'check_in_source' => 'admin_manual',
            'checked_in_by_user_id' => $admin->id,
            'minutes_late' => 22,
        ]);
        $this->assertDatabaseHas('attendance_events', ['person_id' => $person->id, 'type' => 'check_in', 'source' => 'admin_manual', 'performed_by_user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.manual_checkin', 'user_id' => $admin->id]);
        $this->getJson('/api/v1/people/'.$person->person_code.'/attendance-status')->assertOk()->assertJsonPath('data.status', 'checked_in');
    }

    public function test_duplicate_manual_check_in_is_rejected_without_creating_another_record(): void
    {
        $admin = $this->adminWith(['attendance.manual_checkin']);
        Sanctum::actingAs($admin);
        [, , $person] = $this->employeeWithSchedule();
        $payload = ['personId' => $person->person_code, 'checkInAt' => '2026-08-15T09:05:00+03:00'];

        $this->postJson('/api/v1/attendance/manual-check-in', $payload)->assertCreated();
        $this->postJson('/api/v1/attendance/manual-check-in', $payload)->assertConflict()->assertJsonPath('code', 'EMPLOYEE_ALREADY_CHECKED_IN');
        $this->assertSame(1, $person->attendanceRecords()->count());
    }

    public function test_manual_checkout_calculates_minutes_and_contributes_to_payroll(): void
    {
        $admin = $this->adminWith(['attendance.manual_checkin', 'attendance.manual_checkout', 'payroll.manage', 'payroll.view']);
        Sanctum::actingAs($admin);
        [$department, $role, $person] = $this->employeeWithSchedule();
        DepartmentRoleRate::create(['department_id' => $department->id, 'role_id' => $role->id, 'hourly_rate' => '20.0000', 'currency' => 'USD', 'effective_from' => '2026-08-01']);

        $this->postJson('/api/v1/attendance/manual-check-in', ['personId' => $person->person_code, 'checkInAt' => '2026-08-15T09:05:00+03:00'])->assertCreated();
        $this->postJson('/api/v1/attendance/manual-check-out', ['personId' => $person->person_code, 'checkOutAt' => '2026-08-15T18:10:00+03:00', 'note' => 'Supervisor confirmed checkout.'])
            ->assertOk()
            ->assertJsonPath('data.checkOutSource', 'admin_manual')
            ->assertJsonPath('data.workedMinutes', 545)
            ->assertJsonPath('data.regularMinutes', 475)
            ->assertJsonPath('data.overtimeMinutes', 70);

        $this->assertDatabaseHas('attendance_records', ['person_id' => $person->id, 'check_out_source' => 'admin_manual', 'checked_out_by_user_id' => $admin->id, 'worked_minutes' => 545]);
        $this->assertDatabaseHas('attendance_events', ['person_id' => $person->id, 'type' => 'check_out', 'source' => 'admin_manual', 'performed_by_user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.manual_checkout', 'user_id' => $admin->id]);

        $this->postJson('/api/v1/payroll/generate', ['year' => 2026, 'month' => 8])->assertCreated();
        $periodId = $this->getJson('/api/v1/payroll?year=2026&month=8')->json('data.period.id');
        $this->getJson("/api/v1/payroll/periods/{$periodId}/employees")
            ->assertOk()->assertJsonPath('data.0.regularMinutes', 475)->assertJsonPath('data.0.overtimeMinutes', 70)->assertJsonPath('data.0.totalWorkedMinutes', 545);
    }

    public function test_checkout_without_check_in_and_invalid_checkout_time_are_rejected(): void
    {
        $admin = $this->adminWith(['attendance.manual_checkin', 'attendance.manual_checkout']);
        Sanctum::actingAs($admin);
        [, , $person] = $this->employeeWithSchedule();

        $this->postJson('/api/v1/attendance/manual-check-out', ['personId' => $person->person_code])->assertConflict()->assertJsonPath('code', 'EMPLOYEE_NOT_CHECKED_IN');
        $this->postJson('/api/v1/attendance/manual-check-in', ['personId' => $person->person_code, 'checkInAt' => '2026-08-15T10:00:00+03:00'])->assertCreated();
        $this->postJson('/api/v1/attendance/manual-check-out', ['personId' => $person->person_code, 'checkOutAt' => '2026-08-15T09:00:00+03:00'])
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertNull($person->attendanceRecords()->first()->check_out_at);
    }

    public function test_checkout_beyond_department_overtime_limit_is_rejected(): void
    {
        $admin = $this->adminWith(['attendance.manual_checkin', 'attendance.manual_checkout']);
        Sanctum::actingAs($admin);
        [, , $person] = $this->employeeWithSchedule(maxOvertimeMinutes: 120);

        $this->postJson('/api/v1/attendance/manual-check-in', ['personId' => $person->person_code, 'checkInAt' => '2026-08-15T09:00:00+03:00'])->assertCreated();
        $this->postJson('/api/v1/attendance/manual-check-out', ['personId' => $person->person_code, 'checkOutAt' => '2026-08-15T21:00:00+03:00'])
            ->assertConflict()->assertJsonPath('code', 'OVERTIME_LIMIT_EXCEEDED');
        $this->assertNull($person->attendanceRecords()->first()->check_out_at);
    }

    public function test_manual_check_in_can_be_closed_by_auto_checkout_without_duplicate_checkout(): void
    {
        $admin = $this->adminWith(['attendance.manual_checkin', 'attendance.manual_checkout']);
        Sanctum::actingAs($admin);
        [, , $person] = $this->employeeWithSchedule(maxOvertimeMinutes: 120, autoCheckout: true);

        $this->postJson('/api/v1/attendance/manual-check-in', ['personId' => $person->person_code, 'checkInAt' => '2026-08-15T09:00:00+03:00'])->assertCreated();
        app(AutoCheckoutService::class)->process(Carbon::parse('2026-08-15T19:00:00+03:00'));

        $record = $person->attendanceRecords()->first();
        $this->assertSame(AttendanceActionSource::AdminManual, $record->check_in_source);
        $this->assertSame(AttendanceActionSource::AutoOvertimeLimit, $record->check_out_source);
        $this->postJson('/api/v1/attendance/manual-check-out', ['personId' => $person->person_code])
            ->assertConflict()->assertJsonPath('code', 'EMPLOYEE_AUTO_CHECKED_OUT');
        $this->assertSame(1, $person->attendanceRecords()->count());
    }

    public function test_dedicated_permissions_are_enforced_and_super_admin_bypasses_them(): void
    {
        [, , $person] = $this->employeeWithSchedule();
        Sanctum::actingAs($this->adminWith(['attendance.view']));
        $this->postJson('/api/v1/attendance/manual-check-in', ['personId' => $person->person_code])->assertForbidden();

        Sanctum::actingAs($this->superAdmin());
        Carbon::setTestNow('2026-08-15T09:00:00+03:00');
        $this->postJson('/api/v1/attendance/manual-check-in', ['personId' => $person->person_code])->assertCreated();
    }

    public function test_inactive_employee_cannot_be_manually_checked_in(): void
    {
        $admin = $this->adminWith(['attendance.manual_checkin']);
        Sanctum::actingAs($admin);
        [, , $person] = $this->employeeWithSchedule();
        $person->update(['status' => 'inactive']);

        $this->postJson('/api/v1/attendance/manual-check-in', ['personId' => $person->person_code])
            ->assertConflict()->assertJsonPath('code', 'PERSON_INACTIVE');
    }

    private function employeeWithSchedule(?int $maxOvertimeMinutes = 120, bool $autoCheckout = false): array
    {
        $department = Department::factory()->create(['max_overtime_minutes' => $maxOvertimeMinutes, 'auto_checkout_enabled' => $autoCheckout]);
        $role = Role::create(['name' => 'Developer', 'code' => 'DEVELOPER-'.uniqid(), 'status' => 'active']);
        $person = Person::factory()->create(['department_id' => $department->id, 'role_id' => $role->id, 'job_title' => $role->name, 'status' => 'active']);
        $schedule = Schedule::create(['name' => 'Office', 'code' => 'SCH-'.uniqid(), 'type' => 'fixed', 'start_time' => '09:00', 'end_time' => '17:00', 'grace_period_minutes' => 15, 'status' => 'active']);
        $department->schedules()->attach($schedule);
        return [$department, $role, $person];
    }
}

