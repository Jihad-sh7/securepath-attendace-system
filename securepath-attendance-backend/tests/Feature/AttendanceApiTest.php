<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Person;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAuthorizedUsers;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    public function test_manual_attendance_calculates_late_status_and_rejects_duplicate_day(): void
    {
        $admin = $this->adminWith(['attendance.create', 'attendance.view']); Sanctum::actingAs($admin);
        $department = Department::factory()->create(); $person = Person::factory()->create(['person_code' => 'EMP-TEST', 'department_id' => $department->id]);
        $schedule = Schedule::create(['name' => 'Office', 'code' => 'SCH-T', 'type' => 'fixed', 'start_time' => '08:30', 'end_time' => '17:00', 'grace_period_minutes' => 10, 'status' => 'active']); $department->schedules()->attach($schedule);
        $payload = ['personId' => 'EMP-TEST', 'date' => '2026-08-15', 'checkIn' => '2026-08-15T09:05:00+03:00', 'reason' => 'Manual check-in'];
        $this->postJson('/api/v1/attendance/manual', $payload)->assertCreated()->assertJsonPath('data.status', 'Late')->assertJsonPath('data.minutesLate', 35);
        $this->postJson('/api/v1/attendance/manual', $payload)->assertConflict()->assertJsonPath('code', 'ATTENDANCE_EXISTS');
    }

    public function test_attendance_edits_create_history_and_approval_applies_values(): void
    {
        $admin = $this->adminWith(['attendance.create', 'attendance.edit', 'attendance.approve']); Sanctum::actingAs($admin);
        $person = Person::factory()->create(['person_code' => 'EMP-HISTORY']);
        $record = app(\App\Services\AttendanceService::class)->createManual(['personId' => $person->person_code, 'date' => '2026-08-14', 'checkIn' => '2026-08-14T08:30:00+03:00', 'reason' => 'Initial'], $admin);
        $this->putJson('/api/v1/attendance/'.$record->event_code, ['checkInAt' => '2026-08-14T08:45:00+03:00', 'status' => 'late', 'reason' => 'Corrected timestamp'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('attendance_corrections', ['attendance_id' => $record->id, 'status' => 'approved']);
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'status' => 'late']);
    }

    public function test_attendance_can_be_filtered_by_location(): void
    {
        $admin = $this->adminWith(['attendance.create', 'attendance.view']); Sanctum::actingAs($admin);
        $person = Person::factory()->create(['person_code' => 'EMP-LOCATION']);
        $attendance = app(\App\Services\AttendanceService::class);
        $attendance->createManual(['personId' => $person->person_code, 'date' => '2026-08-13', 'checkIn' => '2026-08-13T08:30:00+03:00', 'location' => 'Main Entrance'], $admin);
        $attendance->createManual(['personId' => $person->person_code, 'date' => '2026-08-14', 'checkIn' => '2026-08-14T08:30:00+03:00', 'location' => 'South Lobby'], $admin);

        $this->getJson('/api/v1/attendance?location=Main%20Entrance')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.location', 'Main Entrance');
    }
}
