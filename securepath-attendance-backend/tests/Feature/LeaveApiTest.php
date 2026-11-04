<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAuthorizedUsers;
use Tests\TestCase;

class LeaveApiTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    public function test_approved_leave_creates_on_leave_attendance_records(): void
    {
        $admin = $this->adminWith(['attendance.create', 'attendance.view', 'attendance.approve']); Sanctum::actingAs($admin); $person = Person::factory()->create(['person_code' => 'EMP-LEAVE']);
        $id = $this->postJson('/api/v1/leave-requests', ['personId' => $person->person_code, 'startDate' => '2026-08-17', 'endDate' => '2026-08-18', 'type' => 'medical', 'reason' => 'Medical appointment'])->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/leave-requests/'.$id.'/approve', ['reviewNote' => 'Approved'])->assertOk()->assertJsonPath('data.status', 'Approved');
        $this->assertDatabaseCount('attendance_records', 2); $this->assertDatabaseHas('attendance_records', ['person_id' => $person->id, 'status' => 'on_leave']);
    }
}

